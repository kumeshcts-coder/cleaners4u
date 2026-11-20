<?php
declare(strict_types=1);

namespace Cleaners4u;

use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Throwable;

/** Sends email over SMTP with PHPMailer. Returns null on success or the error text. */
final class Mailer
{
    public function __construct(private readonly array $smtp, private readonly Logger $logger)
    {
    }

    /**
     * @param list<string>|string $to
     * @param array{0: string, 1?: string}|null $replyTo [email, name]
     */
    public function send(array|string $to, string $subject, string $html, string $text, ?array $replyTo = null): ?string
    {
        $recipients = array_values(array_filter((array) $to, static fn ($a) => is_string($a) && filter_var($a, FILTER_VALIDATE_EMAIL)));
        if (!$recipients) {
            return 'No valid recipient address.';
        }
        if (empty($this->smtp['host']) || empty($this->smtp['from_email'])) {
            $this->logger->error('SMTP is not configured (smtp.host / smtp.from_email in config.php).');
            return 'SMTP is not configured.';
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) $this->smtp['host'];
            $mail->Port = (int) ($this->smtp['port'] ?? 587);
            $mail->Timeout = (int) ($this->smtp['timeout'] ?? 15);
            $mail->SMTPAuth = ($this->smtp['username'] ?? '') !== '';
            $mail->Username = (string) ($this->smtp['username'] ?? '');
            $mail->Password = (string) ($this->smtp['password'] ?? '');

            switch (strtolower((string) ($this->smtp['encryption'] ?? 'tls'))) {
                case 'ssl':
                case 'smtps':
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                    break;
                case 'none':
                case '':
                    $mail->SMTPSecure = '';
                    $mail->SMTPAutoTLS = false;
                    break;
                default:
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->SMTPDebug = SMTP::DEBUG_OFF;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
            $mail->XMailer = ' ';

            $mail->setFrom((string) $this->smtp['from_email'], (string) ($this->smtp['from_name'] ?? ''), false);
            foreach ($recipients as $address) {
                $mail->addAddress($address);
            }
            if ($replyTo && filter_var($replyTo[0], FILTER_VALIDATE_EMAIL)) {
                $mail->addReplyTo($replyTo[0], $replyTo[1] ?? '');
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html;
            $mail->AltBody = $text;

            $mail->send();
            return null;
        } catch (MailException $e) {
            $error = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $this->logger->error('Email failed: ' . $error, ['subject' => $subject]);
        return $error;
    }
}
