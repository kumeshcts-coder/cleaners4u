<?php
declare(strict_types=1);

namespace Cleaners4u;

use Throwable;

/**
 * The two form steps.
 *
 * Step 1 validates the contact details, saves a new lead (or reuses a duplicate), ties it to this
 * browser's session, answers the browser with the lead reference and then emails the team and the customer.
 * Step 2 finds that lead through the session, adds the cleaning details to the same row and emails again.
 */
final class LeadController
{
    private const SESSION_KEY = 'c4u_lead';

    public function __construct(private readonly App $app, private readonly Security $security)
    {
    }

    public function step1(array $post): never
    {
        $log = $this->app->logger();
        $ip = $this->security->clientIp();

        // Honeypot: people never see this field, bots fill it in.
        if (Validator::clean($post['extra_field'] ?? '') !== '') {
            $log->warning('Step 1 rejected: honeypot filled', ['ip' => $ip]);
            Response::json(422, ['ok' => false, 'message' => $this->app->genericError()]);
        }

        // Submitted faster than a person can type: treat as a bot.
        $started = (int) Validator::clean($post['form_timestamp'] ?? '');
        $minMs = (int) $this->app->cfg('security.min_fill_seconds', 2) * 1000;
        $nowMs = (int) floor(microtime(true) * 1000);
        if ($started > 0 && $started <= $nowMs && $nowMs - $started < $minMs) {
            $log->warning('Step 1 rejected: submitted too fast', ['ip' => $ip]);
            Response::json(422, ['ok' => false, 'message' => 'Please take a moment to check your details, then try again.']);
        }

        $validator = new Validator($this->app->tz(), $this->security->allowedHosts());
        [$data, $errors] = $validator->step1($post);
        if ($errors) {
            Response::json(422, ['ok' => false, 'message' => implode(' ', $errors), 'errors' => $errors]);
        }

        $now = $this->app->now();
        $repo = new LeadRepository($this->app->db());
        $signature = hash('sha256', strtolower($data['email']) . '|' . $data['phone']);
        $windowMinutes = max(1, (int) $this->app->cfg('security.duplicate_window_minutes', 30));

        // Duplicate: this browser already sent the same details (double click, back button, retry).
        $existing = $_SESSION[self::SESSION_KEY] ?? null;
        if (is_array($existing)
            && hash_equals((string) ($existing['sig'] ?? ''), $signature)
            && time() - (int) ($existing['at'] ?? 0) < $windowMinutes * 60
        ) {
            $lead = $repo->findForSession((int) $existing['id'], $this->security->sessionHash());
            if ($lead && $lead['status'] === 'partial') {
                $log->info('Step 1 duplicate in session, lead reused', ['lead' => $lead['lead_ref']]);
                $this->respondStep1($lead['lead_ref'], $data['name']);
            }
        }

        // Duplicate: same email + phone from the same IP recently, but a new session (cookies cleared, new tab).
        $since = $now->modify('-' . $windowMinutes . ' minutes')->format('Y-m-d H:i:s');
        $dupe = $repo->findRecentPartial($data['email'], $data['phone'], $ip, $since);
        if ($dupe) {
            $this->security->regenerateSession();
            $repo->bindSession((int) $dupe['id'], $this->security->sessionHash());
            $this->rememberLead((int) $dupe['id'], $dupe['lead_ref'], $signature);
            $log->info('Step 1 duplicate from same IP, lead reused', ['lead' => $dupe['lead_ref']]);
            $this->respondStep1($dupe['lead_ref'], $data['name']);
        }

        // Rate limit per IP.
        $max = max(1, (int) $this->app->cfg('security.max_leads_per_ip_per_hour', 10));
        if ($repo->countFromIpSince($ip, $now->modify('-1 hour')->format('Y-m-d H:i:s')) >= $max) {
            $log->warning('Step 1 rejected: rate limit', ['ip' => $ip]);
            Response::json(429, [
                'ok'      => false,
                'message' => 'We’ve received several enquiries from your connection. Please call us on ' . $this->app->phone() . ' and we’ll help straight away.',
            ]);
        }

        // New lead, owned by a fresh session id.
        $this->security->regenerateSession();
        $created = $repo->create($data + [
            'session_hash' => $this->security->sessionHash(),
            'referrer'     => self::header('HTTP_REFERER', 500),
            'ip_address'   => $ip,
            'user_agent'   => self::header('HTTP_USER_AGENT', 255),
            'created_at'   => $now->format('Y-m-d H:i:s'),
        ], $now);
        $this->rememberLead($created['id'], $created['lead_ref'], $signature);
        $log->info('Step 1 saved', ['lead' => $created['lead_ref']]);

        // Answer the browser first so the visitor moves straight on to step 2, then send the emails.
        Response::sendAndContinue(200, $this->step1Payload($created['lead_ref'], $data['name']));

        try {
            $lead = $repo->find($created['id']);
            if ($lead) {
                $this->sendEmails($repo, $lead, 1);
            }
        } catch (Throwable $e) {
            $log->error('Step 1 emails failed: ' . $e->getMessage(), ['lead' => $created['lead_ref']]);
        }
        exit;
    }

    public function step2(array $post): never
    {
        $log = $this->app->logger();
        $session = $_SESSION[self::SESSION_KEY] ?? null;
        if (!is_array($session) || empty($session['id'])) {
            Response::json(409, [
                'ok'      => false,
                'code'    => 'no_lead',
                'message' => 'We couldn’t find your details from step 1, so your session may have expired. Please start again or call us on ' . $this->app->phone() . '.',
            ]);
        }

        $validator = new Validator($this->app->tz(), $this->security->allowedHosts());
        [$data, $errors] = $validator->step2($post);
        if ($errors) {
            Response::json(422, ['ok' => false, 'message' => 'Please check the highlighted answers.', 'errors' => $errors]);
        }

        $now = $this->app->now();
        $db = $this->app->db();
        $repo = new LeadRepository($db);

        $db->beginTransaction();
        try {
            $lead = $repo->findForSession((int) $session['id'], $this->security->sessionHash(), true);
            if (!$lead) {
                $db->rollBack();
                $log->warning('Step 2: lead not found for session', ['lead_id' => (int) $session['id']]);
                Response::json(409, [
                    'ok'      => false,
                    'code'    => 'no_lead',
                    'message' => 'We couldn’t find your details from step 1. Please start again or call us on ' . $this->app->phone() . '.',
                ]);
            }

            // Duplicate: step 2 already done for this lead (double click or resubmit). Don't update or email again.
            if ($lead['status'] === 'complete') {
                $db->commit();
                $log->info('Step 2 duplicate ignored', ['lead' => $lead['lead_ref']]);
                Response::json(200, ['ok' => true, 'duplicate' => true, 'lead_ref' => $lead['lead_ref'], 'next' => $this->thankYouUrl($lead['lead_ref'])]);
            }

            $repo->completeStep2((int) $lead['id'], $data + [
                'step2_ip_address' => $this->security->clientIp(),
                'completed_at'     => $now->format('Y-m-d H:i:s'),
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $log->info('Step 2 saved', ['lead' => $lead['lead_ref']]);
        Response::sendAndContinue(200, ['ok' => true, 'lead_ref' => $lead['lead_ref'], 'next' => $this->thankYouUrl($lead['lead_ref'])]);

        try {
            $fresh = $repo->find((int) $lead['id']);
            if ($fresh) {
                $this->sendEmails($repo, $fresh, 2);
            }
        } catch (Throwable $e) {
            $log->error('Step 2 emails failed: ' . $e->getMessage(), ['lead' => $lead['lead_ref']]);
        }
        exit;
    }

    /** Admin notification and customer confirmation for a step. Each result is recorded on the lead. */
    private function sendEmails(LeadRepository $repo, array $lead, int $step): void
    {
        $templates = $this->app->templates();
        $mailer = $this->app->mailer();
        $id = (int) $lead['id'];
        $siteName = (string) $this->app->cfg('site.name', 'Cleaners4u');

        $admins = $this->app->adminEmails();
        if ($admins) {
            $mail = $step === 1 ? $templates->adminStep1($lead) : $templates->adminStep2($lead);
            $error = $mailer->send($admins, $mail['subject'], $mail['html'], $mail['text'], [$lead['email'], $lead['name']]);
            $repo->recordMail($id, "step{$step}_admin_mailed_at", $error, $this->app->now());
        } else {
            $this->app->logger()->error('No valid admin_email in config.php; admin email not sent', ['lead' => $lead['lead_ref']]);
        }

        $mail = $step === 1 ? $templates->customerStep1($lead) : $templates->customerStep2($lead);
        $replyTo = $admins ? [$admins[0], $siteName] : null;
        $error = $mailer->send($lead['email'], $mail['subject'], $mail['html'], $mail['text'], $replyTo);
        $repo->recordMail($id, "step{$step}_customer_mailed_at", $error, $this->app->now());
    }

    private function respondStep1(string $ref, string $name): never
    {
        Response::json(200, $this->step1Payload($ref, $name) + ['duplicate' => true]);
    }

    private function step1Payload(string $ref, string $name): array
    {
        return [
            'ok'       => true,
            'lead_ref' => $ref,
            'first'    => EmailTemplates::firstName($name),
            'next'     => 'additional-details.html?id=' . rawurlencode($ref),
        ];
    }

    private function thankYouUrl(string $ref): string
    {
        return 'thank-you.html?id=' . rawurlencode($ref);
    }

    private function rememberLead(int $id, string $ref, string $signature): void
    {
        $_SESSION[self::SESSION_KEY] = ['id' => $id, 'ref' => $ref, 'sig' => $signature, 'at' => time()];
    }

    private static function header(string $key, int $max): ?string
    {
        $value = Validator::clean($_SERVER[$key] ?? '');
        return $value !== '' ? mb_substr($value, 0, $max) : null;
    }
}
