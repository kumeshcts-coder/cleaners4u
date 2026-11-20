<?php
declare(strict_types=1);

namespace Cleaners4u;

use DateTimeImmutable;

/**
 * Responsive HTML emails in the Cleaners4u style (navy #223869, mint #86CEBB, Barlow),
 * with a plain-text version of each. Table layout and inline styles for Gmail, Outlook and Apple Mail.
 * Every customer value is escaped with e().
 */
final class EmailTemplates
{
    private const FONT = "Barlow, 'Helvetica Neue', Helvetica, Arial, sans-serif";
    private const NAVY = '#223869';
    private const INK = '#14233F';
    private const MINT = '#86CEBB';
    private const MINT_BTN = '#40A288';
    private const MINT_SOFT = '#E9F6F2';
    private const TEAL = '#2A7564';
    private const TEXT = '#3C4656';
    private const MUTED = '#667085';
    private const LINE = '#E6EAF0';
    private const SOFT = '#F7F9FB';

    public function __construct(private readonly array $site)
    {
    }

    /* ------------------------------------------------------------------ */
    /* Step 1                                                              */
    /* ------------------------------------------------------------------ */

    public function adminStep1(array $lead): array
    {
        $phoneHref = 'tel:' . $lead['phone'];
        $subject = sprintf('New cleaning lead: %s, %s [%s]', $lead['name'], self::formatPhone($lead['phone']), $lead['lead_ref']);

        $body = $this->callout(
            '<strong>Call now.</strong> This customer has just sent their contact details and was promised a call back within 2 minutes. '
            . 'Step 2 (cleaning details) is not finished yet; you’ll get a second email if they complete it.',
            'amber'
        );
        $body .= $this->button($phoneHref, 'Call ' . self::formatPhone($lead['phone']));
        $body .= $this->heading('Contact details');
        $body .= $this->table([
            'Name'          => $this->e($lead['name']),
            'Phone'         => '<a href="' . $this->e($phoneHref) . '" style="color:' . self::TEAL . ';font-weight:600">' . $this->e(self::formatPhone($lead['phone'])) . '</a>',
            'E-mail'        => '<a href="mailto:' . $this->e($lead['email']) . '" style="color:' . self::TEAL . ';font-weight:600">' . $this->e($lead['email']) . '</a>',
            'Suburb'        => $this->e($lead['suburb_hint'] ?? ''),
            'Lead reference' => $this->e($lead['lead_ref']),
            'Submitted'     => $this->e(self::formatDateTime($lead['created_at'])),
        ]);
        $body .= $this->trackingTable($lead);

        $text = $this->textBlock('NEW CLEANING LEAD (step 1 of 2): call now', [
            'Name'           => $lead['name'],
            'Phone'          => self::formatPhone($lead['phone']),
            'E-mail'         => $lead['email'],
            'Suburb'         => $lead['suburb_hint'] ?? '',
            'Lead reference' => $lead['lead_ref'],
            'Submitted'      => self::formatDateTime($lead['created_at']),
        ]) . $this->trackingText($lead);

        return [
            'subject' => $subject,
            'html'    => $this->layout($subject, 'New lead: call ' . $lead['name'] . ' now', 'New lead · Step 1 of 2', 'New cleaning enquiry from ' . $lead['name'], $body, false),
            'text'    => $text,
        ];
    }

    public function customerStep1(array $lead): array
    {
        $first = self::firstName($lead['name']);
        $subject = sprintf('Thanks %s, we’ve received your cleaning enquiry', $first);

        $body = $this->p('Hi ' . $this->e($first) . ',');
        $body .= $this->p('Thanks for getting in touch with ' . $this->e($this->siteName()) . '. We’ve received your details and someone from our Melbourne team will call you shortly on <strong style="color:' . self::INK . '">' . $this->e(self::formatPhone($lead['phone'])) . '</strong> to talk through your clean.');
        $body .= $this->refBox($lead['lead_ref']);
        $body .= $this->heading('What happens next');
        $body .= $this->steps([
            ['We call you', 'A friendly member of our team rings to confirm what you need and answer any questions.'],
            ['You get one clear quote', 'An upfront price based on your property and the extras you choose, with no hidden fees.'],
            ['We book your clean', 'Your vetted, police-checked cleaning team is booked around your handover or preferred date.'],
        ]);
        $body .= $this->p('If you didn’t get a chance to finish the second step of the form, no problem. We can take the rest of your details over the phone.');
        $body .= $this->button('tel:' . $this->phoneHref(), 'Call us on ' . $this->phone());
        $body .= $this->p('Speak soon,<br><strong style="color:' . self::INK . '">The ' . $this->e($this->siteName()) . ' team</strong>');

        $text = "Hi {$first},\n\n"
            . 'Thanks for getting in touch with ' . $this->siteName() . ". We've received your details and someone from our Melbourne team will call you shortly on " . self::formatPhone($lead['phone']) . " to talk through your clean.\n\n"
            . 'Your reference: ' . $lead['lead_ref'] . "\n\n"
            . "WHAT HAPPENS NEXT\n"
            . "1. We call you to confirm what you need and answer any questions.\n"
            . "2. You get one clear, upfront quote with no hidden fees.\n"
            . "3. We book your vetted, police-checked cleaning team around your handover or preferred date.\n\n"
            . "If you didn't finish the second step of the form, we can take the rest of your details over the phone.\n\n"
            . 'Call us on ' . $this->phone() . "\n\nSpeak soon,\nThe " . $this->siteName() . " team\n" . $this->siteUrl() . "\n";

        return [
            'subject' => $subject,
            'html'    => $this->layout($subject, 'We’ll call you shortly to talk through your clean. Your reference: ' . $lead['lead_ref'], 'Enquiry received', 'Thanks ' . $first . ', we’ll be in touch shortly', $body, true),
            'text'    => $text,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Step 2                                                              */
    /* ------------------------------------------------------------------ */

    public function adminStep2(array $lead): array
    {
        $combined = (int) $lead['combined_booking'] === 1;
        $subject = sprintf(
            '%sCleaning enquiry complete: %s in %s [%s]',
            $combined ? 'COMBINED BOOKING · ' : '',
            $lead['clean_type'],
            $lead['suburb'],
            $lead['lead_ref']
        );

        $body = '';
        if ($combined) {
            $body .= $this->callout('<strong>Combined booking: move added (10% off the move).</strong> Pass this lead to the moving sales team as well as quoting the clean.', 'amber');
        }
        $body .= $this->p('<strong style="color:' . self::INK . '">' . $this->e($lead['name']) . '</strong> has finished both steps of the quote form. Here is everything they told us.');
        $body .= $this->button('tel:' . $lead['phone'], 'Call ' . self::formatPhone($lead['phone']));

        $body .= $this->heading('Customer');
        $body .= $this->table([
            'Name'           => $this->e($lead['name']),
            'Phone'          => '<a href="tel:' . $this->e($lead['phone']) . '" style="color:' . self::TEAL . ';font-weight:600">' . $this->e(self::formatPhone($lead['phone'])) . '</a>',
            'E-mail'         => '<a href="mailto:' . $this->e($lead['email']) . '" style="color:' . self::TEAL . ';font-weight:600">' . $this->e($lead['email']) . '</a>',
            'Lead reference' => $this->e($lead['lead_ref']),
        ]);

        $body .= $this->heading('The clean');
        $body .= $this->table($this->escapeRows($this->cleanRows($lead, true)));

        if ($combined || (int) $lead['utility_consent'] === 1) {
            $body .= $this->heading('Moving and utilities');
            $body .= $this->table($this->escapeRows($this->moveRows($lead, true)));
        }

        $body .= $this->trackingTable($lead, true);

        $text = $this->textBlock(($combined ? 'COMBINED BOOKING (move added, 10% off). ' : '') . 'CLEANING ENQUIRY COMPLETE', [
            'Name'           => $lead['name'],
            'Phone'          => self::formatPhone($lead['phone']),
            'E-mail'         => $lead['email'],
            'Lead reference' => $lead['lead_ref'],
        ]) . $this->textBlock('THE CLEAN', $this->cleanRows($lead, true))
            . (($combined || (int) $lead['utility_consent'] === 1) ? $this->textBlock('MOVING AND UTILITIES', $this->moveRows($lead, true)) : '')
            . $this->trackingText($lead, true);

        return [
            'subject' => $subject,
            'html'    => $this->layout($subject, $lead['clean_type'] . ' in ' . $lead['suburb'] . ' for ' . $lead['name'], 'Enquiry complete · Step 2 of 2', $lead['clean_type'] . ' in ' . $lead['suburb'], $body, false),
            'text'    => $text,
        ];
    }

    public function customerStep2(array $lead): array
    {
        $first = self::firstName($lead['name']);
        $combined = (int) $lead['combined_booking'] === 1;
        $utilities = (int) $lead['utility_consent'] === 1;
        $subject = sprintf('Your cleaning enquiry is complete (ref %s)', $lead['lead_ref']);

        $body = $this->p('Hi ' . $this->e($first) . ',');
        $body .= $this->p('Thanks for telling us about your clean. That’s everything we need to prepare an accurate quote. Our team will call you on <strong style="color:' . self::INK . '">' . $this->e(self::formatPhone($lead['phone'])) . '</strong> to confirm your price and lock in a date.');
        $body .= $this->refBox($lead['lead_ref']);
        $body .= $this->heading('Your clean at a glance');
        $body .= $this->table($this->escapeRows($this->cleanRows($lead, false)));

        if ($combined) {
            $move = 'Our removals team will contact you to plan your move alongside your clean, with 10% off your move.';
            $body .= $this->callout('<strong>You’re saving 10% on your move.</strong> ' . $this->e($move), 'mint');
        }
        if ($utilities) {
            $body .= $this->callout('<strong>Free utility connection added.</strong> Our connection partner will be in touch about connecting your ' . $this->e(self::lowerList((string) $lead['utilities'])) . ', at no cost to you.', 'mint');
        }

        $body .= $this->heading('Before your clean');
        $body .= $this->steps([
            ['Check your quote', 'We’ll confirm the price on the phone and send it through in writing.'],
            ['Plan access', 'Let us know how the team gets in: keys, lockbox or someone on site.'],
            ['Relax', 'Your clean is covered by our bond-back guarantee, so you’re protected at the final inspection.'],
        ]);
        $body .= $this->p('Need to change anything? Just reply to this email or call us on <a href="tel:' . $this->e($this->phoneHref()) . '" style="color:' . self::TEAL . ';font-weight:600">' . $this->e($this->phone()) . '</a>.');
        $body .= $this->button($this->siteUrl() . '/bond-back-guarantee/', 'Read our bond-back guarantee');
        $body .= $this->p('Thanks for choosing us,<br><strong style="color:' . self::INK . '">The ' . $this->e($this->siteName()) . ' team</strong>');

        $text = "Hi {$first},\n\n"
            . "Thanks for telling us about your clean. That's everything we need to prepare an accurate quote. Our team will call you on " . self::formatPhone($lead['phone']) . " to confirm your price and lock in a date.\n\n"
            . 'Your reference: ' . $lead['lead_ref'] . "\n\n"
            . $this->textBlock('YOUR CLEAN AT A GLANCE', $this->cleanRows($lead, false))
            . ($combined ? "You're saving 10% on your move. Our removals team will contact you to plan your move alongside your clean.\n\n" : '')
            . ($utilities ? 'Free utility connection added. Our connection partner will be in touch about connecting your ' . self::lowerList((string) $lead['utilities']) . ", at no cost to you.\n\n" : '')
            . "BEFORE YOUR CLEAN\n"
            . "1. Check your quote: we'll confirm the price on the phone and send it in writing.\n"
            . "2. Plan access: keys, lockbox or someone on site.\n"
            . "3. Relax: your clean is covered by our bond-back guarantee.\n\n"
            . 'Need to change anything? Reply to this email or call ' . $this->phone() . ".\n"
            . 'Bond-back guarantee: ' . $this->siteUrl() . "/bond-back-guarantee/\n\n"
            . "Thanks for choosing us,\nThe " . $this->siteName() . " team\n" . $this->siteUrl() . "\n";

        return [
            'subject' => $subject,
            'html'    => $this->layout($subject, 'We have everything we need to quote your ' . strtolower((string) $lead['clean_type']) . '.', 'Enquiry complete', 'You’re all set, ' . $first, $body, true),
            'text'    => $text,
        ];
    }

    /* ------------------------------------------------------------------ */
    /* Shared rows                                                         */
    /* ------------------------------------------------------------------ */

    /** @return array<string, string> plain-text rows (escape before using in HTML) */
    private function cleanRows(array $lead, bool $forAdmin): array
    {
        $date = (int) $lead['clean_date_unsure'] === 1 || empty($lead['clean_date'])
            ? 'Not sure yet'
            : self::formatDate((string) $lead['clean_date']);

        $extras = [];
        foreach (array_filter(array_map('trim', explode(',', (string) ($lead['addons'] ?? '')))) as $addon) {
            if ($addon === 'Carpet steam cleaning' && $lead['carpet_bedrooms'] !== null) {
                $addon .= sprintf(' (%d bedroom%s, %d living room%s)', $lead['carpet_bedrooms'], (int) $lead['carpet_bedrooms'] === 1 ? '' : 's', $lead['carpet_living'], (int) $lead['carpet_living'] === 1 ? '' : 's');
            } elseif ($addon === 'Outside windows' && $lead['windows_count'] !== null) {
                $addon .= ' (about ' . (int) $lead['windows_count'] . ')';
            } elseif ($addon === 'Wall deep clean' && $lead['walls_count'] !== null) {
                $addon .= ' (about ' . (int) $lead['walls_count'] . ' walls)';
            }
            $extras[] = $addon;
        }

        $rows = [
            'Clean'         => (string) $lead['clean_type'],
            'Cleaning date' => $date,
            'Suburb'        => (string) $lead['suburb'],
            'Property'      => (string) $lead['property_type'],
            'Bedrooms'      => (string) $lead['bedrooms'],
            'Bathrooms'     => (string) $lead['bathrooms'],
            'Extras'        => $extras ? implode("\n", $extras) : ($forAdmin ? 'None' : ''),
            'Notes'         => (string) ($lead['notes'] ?? ''),
        ];
        if (!$forAdmin) {
            if ((int) $lead['combined_booking'] === 1) {
                $rows['Your move'] = 'Added, 10% off' . $this->moveSummary($lead);
            }
            if ((int) $lead['utility_consent'] === 1) {
                $rows['Utilities'] = (string) $lead['utilities'];
            }
        }
        return $rows;
    }

    /** @return array<string, string> */
    private function moveRows(array $lead, bool $forAdmin): array
    {
        $rows = [];
        if ((int) $lead['combined_booking'] === 1) {
            $rows['Move'] = 'Yes, removalists added (10% off the move)';
            $rows['Moving date'] = !empty($lead['move_date']) ? self::formatDate((string) $lead['move_date']) : 'To be confirmed';
            if (!empty($lead['move_other_suburb'])) {
                $rows[$lead['clean_type'] === 'Move-in clean' ? 'Moving from' : 'Moving to'] = (string) $lead['move_other_suburb'];
            }
        }
        if ((int) $lead['utility_consent'] === 1) {
            $rows['Utility connection'] = (string) $lead['utilities'];
            if ($forAdmin) {
                $rows['Consent given'] = self::formatDateTime((string) $lead['utility_consent_at']);
                $rows['Consent wording'] = (string) $lead['utility_consent_text'];
            }
        }
        return $rows;
    }

    private function moveSummary(array $lead): string
    {
        $parts = [];
        if (!empty($lead['move_date'])) {
            $parts[] = self::formatDate((string) $lead['move_date']);
        }
        if (!empty($lead['move_other_suburb'])) {
            $parts[] = ($lead['clean_type'] === 'Move-in clean' ? 'from ' : 'to ') . $lead['move_other_suburb'];
        }
        return $parts ? ' · ' . implode(', ', $parts) : '';
    }

    private function trackingTable(array $lead, bool $withStep2 = false): string
    {
        return $this->heading('Source and tracking', true) . $this->table($this->escapeRows($this->trackingRows($lead, $withStep2)), true);
    }

    private function trackingText(array $lead, bool $withStep2 = false): string
    {
        return $this->textBlock('SOURCE AND TRACKING', $this->trackingRows($lead, $withStep2));
    }

    /** @return array<string, string> */
    private function trackingRows(array $lead, bool $withStep2): array
    {
        return [
            'Step 1 sent'    => self::formatDateTime((string) $lead['created_at']),
            'Step 2 sent'    => $withStep2 ? self::formatDateTime((string) ($lead['completed_at'] ?? '')) : '',
            'IP address'     => (string) $lead['ip_address'],
            'Page URL'       => (string) ($lead['current_url'] ?? ''),
            'GCLID'          => (string) ($lead['gclid'] ?? ''),
            'GBRAID'         => (string) ($lead['gbraid'] ?? ''),
            'Ad source'      => (string) ($lead['gad_source'] ?? ''),
            'Ad campaign ID' => (string) ($lead['gad_campaignid'] ?? ''),
            'Form'           => (string) ($lead['form_id'] ?? ''),
            'Device ID'      => (string) ($lead['device_id'] ?? ''),
            'Browser'        => (string) ($lead['user_agent'] ?? ''),
        ];
    }

    /* ------------------------------------------------------------------ */
    /* HTML building blocks                                                */
    /* ------------------------------------------------------------------ */

    private function layout(string $title, string $preheader, string $eyebrow, string $heading, string $content, bool $forCustomer): string
    {
        $font = self::FONT;
        $site = $this->e($this->siteUrl());
        $logo = $this->e((string) ($this->site['logo_url'] ?? $this->siteUrl() . '/assets/email/logo.png'));
        $name = $this->e($this->siteName());
        $phone = $this->e($this->phone());
        $phoneHref = $this->e($this->phoneHref());
        $year = date('Y');

        $trust = $forCustomer ? $this->trustStrip() : '';
        $footerNote = $forCustomer
            ? 'You’re receiving this email because you asked for a cleaning quote at ' . $this->e(preg_replace('#^https?://#', '', $this->siteUrl())) . '.'
            : 'Internal notification from the ' . $name . ' quote form. Reply to this email to contact the customer.';

        return <<<HTML
<!doctype html>
<html lang="en-AU" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="x-apple-disable-message-reformatting">
<meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<title>{$this->e($title)}</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700&amp;display=swap" rel="stylesheet">
<style>
  body{margin:0!important;padding:0!important;width:100%!important;background:#F2F5F9}
  table,td{border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0}
  img{border:0;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
  a{color:#2A7564}
  @media only screen and (max-width:620px){
    .c4u-container{width:100%!important;border-radius:0!important}
    .c4u-px{padding-left:22px!important;padding-right:22px!important}
    .c4u-h1{font-size:24px!important;line-height:30px!important}
    .c4u-stack{display:block!important;width:100%!important;padding:6px 0!important}
    .c4u-btn,.c4u-btn a{display:block!important;width:100%!important;box-sizing:border-box}
    .c4u-lbl{width:40%!important}
  }
</style>
<!--[if mso]><style>body,table,td,a,p,h1,h2{font-family:Arial,Helvetica,sans-serif!important}</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:#F2F5F9;">
<div style="display:none;font-size:1px;color:#F2F5F9;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">{$this->e($preheader)}&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;&#8199;&#65279;&#847;</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#F2F5F9;">
  <tr>
    <td align="center" style="padding:24px 10px;">
      <table role="presentation" class="c4u-container" width="600" cellpadding="0" cellspacing="0" border="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:10px;overflow:hidden;">
        <tr><td style="height:5px;line-height:5px;font-size:0;background:#86CEBB;">&nbsp;</td></tr>
        <tr>
          <td class="c4u-px" style="padding:24px 40px 22px;">
            <a href="{$site}" target="_blank" style="text-decoration:none;"><img src="{$logo}" width="170" height="60" alt="{$name}" style="display:block;width:170px;max-width:170px;height:auto;"></a>
          </td>
        </tr>
        <tr>
          <td class="c4u-px" style="background:#223869;padding:30px 40px;">
            <p style="margin:0 0 8px;font-family:{$font};font-size:13px;line-height:18px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:#86CEBB;">{$this->e($eyebrow)}</p>
            <h1 class="c4u-h1" style="margin:0;font-family:{$font};font-size:28px;line-height:35px;font-weight:600;color:#ffffff;">{$this->e($heading)}</h1>
          </td>
        </tr>
        <tr>
          <td class="c4u-px" style="padding:32px 40px 12px;font-family:{$font};font-size:16px;line-height:26px;color:#3C4656;">
            {$content}
          </td>
        </tr>
        {$trust}
        <tr>
          <td class="c4u-px" style="background:#14233F;padding:26px 40px;font-family:{$font};font-size:14px;line-height:22px;color:#C9D3E6;">
            <p style="margin:0 0 6px;"><a href="tel:{$phoneHref}" style="color:#86CEBB;font-size:18px;font-weight:700;text-decoration:none;">{$phone}</a></p>
            <p style="margin:0 0 12px;">Open 7 days · <a href="{$site}" style="color:#ffffff;text-decoration:underline;">{$this->e(preg_replace('#^https?://#', '', $this->siteUrl()))}</a> · <a href="{$site}/bond-back-guarantee/" style="color:#ffffff;text-decoration:underline;">Bond-back guarantee</a></p>
            <p style="margin:0;font-size:12px;line-height:18px;color:#8FA0BF;">{$footerNote}<br>© {$year} {$name}. Melbourne end-of-lease, move-out, move-in and general cleaning.</p>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>
</body>
</html>
HTML;
    }

    private function trustStrip(): string
    {
        $font = self::FONT;
        $cell = static fn (string $label): string =>
            '<td class="c4u-stack" width="33%" valign="top" style="padding:0 6px;font-family:' . $font . ';font-size:14px;line-height:20px;font-weight:600;color:' . self::INK . ';">'
            . '<span style="display:inline-block;width:22px;height:22px;line-height:22px;border-radius:11px;background:' . self::MINT_SOFT . ';color:' . self::TEAL . ';text-align:center;font-size:13px;margin-right:6px;">&#10003;</span>'
            . $label . '</td>';

        return '<tr><td class="c4u-px" style="padding:6px 40px 30px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . self::SOFT . ';border-radius:8px;">'
            . '<tr><td style="padding:16px 12px;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            . $cell('Bond-back guarantee') . $cell('ID &amp; police-checked cleaners') . $cell('Rated 4.8&#9733; on Google')
            . '</tr></table></td></tr></table></td></tr>';
    }

    private function p(string $html): string
    {
        return '<p style="margin:0 0 18px;font-family:' . self::FONT . ';font-size:16px;line-height:26px;color:' . self::TEXT . ';">' . $html . '</p>';
    }

    private function heading(string $text, bool $small = false): string
    {
        return '<h2 style="margin:' . ($small ? '28px' : '30px') . ' 0 12px;font-family:' . self::FONT . ';font-size:' . ($small ? '15px' : '19px') . ';line-height:24px;font-weight:600;color:' . ($small ? self::MUTED : self::INK) . ';' . ($small ? 'text-transform:uppercase;letter-spacing:.5px;' : '') . '">' . $this->e($text) . '</h2>';
    }

    /** @param array<string, string> $rows label => value HTML (empty values are skipped) */
    private function table(array $rows, bool $compact = false): string
    {
        $html = '';
        $rows = array_filter($rows, static fn ($v) => trim(strip_tags((string) $v)) !== '');
        $last = array_key_last($rows);
        foreach ($rows as $label => $value) {
            $border = $label === $last ? '' : 'border-bottom:1px solid ' . self::LINE . ';';
            $pad = $compact ? '8px 14px' : '11px 14px';
            $html .= '<tr>'
                . '<td class="c4u-lbl" width="36%" valign="top" style="padding:' . $pad . ';background:' . self::SOFT . ';' . $border . 'font-family:' . self::FONT . ';font-size:' . ($compact ? '13px' : '14px') . ';line-height:20px;color:' . self::MUTED . ';">' . $this->e((string) $label) . '</td>'
                . '<td valign="top" style="padding:' . $pad . ';' . $border . 'font-family:' . self::FONT . ';font-size:' . ($compact ? '13px' : '15px') . ';line-height:21px;color:' . self::INK . ';font-weight:' . ($compact ? '400' : '600') . ';word-break:break-word;">' . $value . '</td>'
                . '</tr>';
        }
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid ' . self::LINE . ';border-radius:8px;border-collapse:separate;overflow:hidden;margin:0 0 18px;">' . $html . '</table>';
    }

    /** @param array<string, string> $rows */
    private function escapeRows(array $rows): array
    {
        return array_map(fn ($v) => nl2br($this->e((string) $v), false), $rows);
    }

    private function button(string $href, string $label): string
    {
        return '<table role="presentation" class="c4u-btn" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 24px;"><tr>'
            . '<td align="center" bgcolor="' . self::MINT_BTN . '" style="border-radius:6px;background:' . self::MINT_BTN . ';">'
            . '<a href="' . $this->e($href) . '" target="_blank" style="display:inline-block;padding:15px 28px;font-family:' . self::FONT . ';font-size:17px;line-height:20px;font-weight:600;color:' . self::INK . ';text-decoration:none;border-radius:6px;">' . $this->e($label) . '</a>'
            . '</td></tr></table>';
    }

    /** @param 'mint'|'amber' $tone */
    private function callout(string $html, string $tone): string
    {
        [$bg, $bar, $color] = $tone === 'amber' ? ['#FFF6E5', '#F2A11F', '#7A4A00'] : [self::MINT_SOFT, self::MINT_BTN, self::INK];
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 20px;"><tr>'
            . '<td style="background:' . $bg . ';border-left:4px solid ' . $bar . ';border-radius:6px;padding:14px 16px;font-family:' . self::FONT . ';font-size:15px;line-height:23px;color:' . $color . ';">' . $html . '</td>'
            . '</tr></table>';
    }

    private function refBox(string $ref): string
    {
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;"><tr>'
            . '<td style="background:' . self::MINT_SOFT . ';border:1px solid ' . self::MINT . ';border-radius:6px;padding:10px 16px;font-family:' . self::FONT . ';font-size:15px;line-height:20px;color:' . self::INK . ';">'
            . 'Your reference: <strong style="letter-spacing:.5px;">' . $this->e($ref) . '</strong></td></tr></table>';
    }

    /** @param list<array{0: string, 1: string}> $items [title, text] */
    private function steps(array $items): string
    {
        $html = '';
        foreach ($items as $i => [$title, $text]) {
            $html .= '<tr>'
                . '<td width="44" valign="top" style="padding:0 0 16px;"><span style="display:inline-block;width:30px;height:30px;line-height:30px;border-radius:15px;background:' . self::NAVY . ';color:#ffffff;text-align:center;font-family:' . self::FONT . ';font-size:15px;font-weight:700;">' . ($i + 1) . '</span></td>'
                . '<td valign="top" style="padding:3px 0 16px;font-family:' . self::FONT . ';font-size:15px;line-height:23px;color:' . self::TEXT . ';"><strong style="color:' . self::INK . ';">' . $this->e($title) . '.</strong> ' . $this->e($text) . '</td>'
                . '</tr>';
        }
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 10px;">' . $html . '</table>';
    }

    /* ------------------------------------------------------------------ */
    /* Text helpers                                                        */
    /* ------------------------------------------------------------------ */

    /** @param array<string, string> $rows */
    private function textBlock(string $title, array $rows): string
    {
        $out = $title . "\n";
        foreach ($rows as $label => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $out .= $label . ': ' . str_replace("\n", '; ', $value) . "\n";
            }
        }
        return $out . "\n";
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }

    private function siteName(): string
    {
        return (string) ($this->site['name'] ?? 'Cleaners4u');
    }

    private function siteUrl(): string
    {
        return rtrim((string) ($this->site['url'] ?? 'https://cleaners4u.com.au'), '/');
    }

    private function phone(): string
    {
        return (string) ($this->site['phone'] ?? '03 7042 9778');
    }

    private function phoneHref(): string
    {
        return preg_replace('/[^0-9+]/', '', (string) ($this->site['phone_href'] ?? $this->phone())) ?? '';
    }

    public static function firstName(string $name): string
    {
        $first = explode(' ', trim($name))[0] ?? '';
        return mb_strtoupper(mb_substr($first, 0, 1)) . mb_substr($first, 1);
    }

    /** 0412345678 → 0412 345 678, 0398765432 → 03 9876 5432, 1300123456 → 1300 123 456. */
    public static function formatPhone(string $digits): string
    {
        if (preg_match('/^(04\d{2})(\d{3})(\d{3})$/', $digits, $m)) {
            return "$m[1] $m[2] $m[3]";
        }
        if (preg_match('/^(0[2378])(\d{4})(\d{4})$/', $digits, $m)) {
            return "$m[1] $m[2] $m[3]";
        }
        if (preg_match('/^(1[38]00)(\d{3})(\d{3})$/', $digits, $m)) {
            return "$m[1] $m[2] $m[3]";
        }
        if (preg_match('/^(13)(\d{2})(\d{2})$/', $digits, $m)) {
            return "$m[1] $m[2] $m[3]";
        }
        return $digits;
    }

    public static function formatDate(string $ymd): string
    {
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
        return $d ? $d->format('l j F Y') : $ymd;
    }

    public static function formatDateTime(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $d = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value);
        return $d ? $d->format('D j M Y, g:i a') . ' (Melbourne time)' : $value;
    }

    private static function lowerList(string $list): string
    {
        $items = array_map(static fn ($s) => strtolower(trim($s)), explode(',', $list));
        if (count($items) > 1) {
            $last = array_pop($items);
            return implode(', ', $items) . ' and ' . $last;
        }
        return $items[0] ?? '';
    }
}
