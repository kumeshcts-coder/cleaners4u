<?php
declare(strict_types=1);

namespace Cleaners4u;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/**
 * Server-side validation for both form steps. Mirrors the browser checks and
 * the exact field names, values and dropdown/radio options in the HTML forms.
 * Returns cleaned data; values are stored as text and escaped when output.
 */
final class Validator
{
    public const CLEAN_TYPES = ['End-of-lease clean', 'Move-out clean', 'Move-in clean', 'General clean'];
    public const PROPERTY_TYPES = ['Apartment or unit', 'Single-storey home', 'Double-storey home', 'Triple-storey home'];
    public const BEDROOMS = ['1', '2', '3', '4', '5', '6+'];
    public const BATHROOMS = ['1', '2', '3', '4+'];
    public const ADDONS = ['Carpet steam cleaning', 'Balcony', 'Outside windows', 'Wall deep clean'];
    public const SERVICE_MOVE = 'Removalists (10% off move)';
    public const SERVICE_UTILITIES = 'Gas & Electricity';
    public const UTILITIES = ['Electricity', 'Gas', 'Internet'];
    public const UTILITY_CONSENT_TEXT = 'By selecting this, you agree to be contacted by our connection partner about connecting your services.';

    public function __construct(private readonly DateTimeZone $tz, private readonly array $allowedHosts)
    {
    }

    /**
     * Step 1: contact details and tracking fields.
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function step1(array $in): array
    {
        $errors = [];

        $name = self::clean($in['name'] ?? '');
        if ($name === '') {
            $errors['name'] = 'Please enter your name.';
        } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 50) {
            $errors['name'] = 'Please enter your name (2 to 50 characters).';
        } elseif (!preg_match("/^\\p{L}[\\p{L}\\p{M} .'’\\-]*$/u", $name)) {
            $errors['name'] = 'Please use letters only in your name.';
        }

        $phoneRaw = self::clean($in['phone'] ?? '');
        $phone = self::normalisePhone($phoneRaw);
        if ($phoneRaw === '') {
            $errors['phone'] = 'Please enter your phone number.';
        } elseif ($phone === null) {
            $errors['phone'] = 'Please enter a valid Australian phone number, e.g. 0412 345 678.';
        }

        $email = self::clean($in['email'] ?? '');
        if ($email === '') {
            $errors['email'] = 'Please enter your e-mail address.';
        } elseif (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid e-mail address, e.g. name@example.com.';
        } else {
            [$local, $domain] = explode('@', $email, 2);
            $email = $local . '@' . strtolower($domain);
        }

        if ((string) ($in['terms_status'] ?? '') !== '1') {
            $errors['terms_status'] = 'Please agree to our Privacy Policy to continue.';
        }

        $data = [
            'name'           => $name,
            'phone'          => $phone ?? '',
            'email'          => $email,
            'suburb_hint'    => self::limit(self::clean($in['suburb'] ?? ''), 80) ?: null,
            'form_id'        => self::token($in['form_id'] ?? '', 50),
            'terms_accepted' => 1,
            'gclid'          => self::token($in['gclid'] ?? '', 255),
            'gbraid'         => self::token($in['gbraid'] ?? '', 255),
            'gad_source'     => self::token($in['gad_source'] ?? '', 50),
            'gad_campaignid' => self::token($in['gad_campaignid'] ?? '', 50),
            'device_id'      => self::token($in['device_id'] ?? '', 64),
            'current_url'    => $this->siteUrl($in['current_url'] ?? '') ?? $this->siteUrl($_SERVER['HTTP_REFERER'] ?? ''),
        ];

        return [$data, $errors];
    }

    /**
     * Step 2: the clean, the property, extras, the move and utilities.
     * @return array{0: array<string, mixed>, 1: array<string, string>}
     */
    public function step2(array $in): array
    {
        $errors = [];
        $today = new DateTimeImmutable('today', $this->tz);
        // The browser's "today" can be a day behind Melbourne's for visitors in other time zones.
        $earliest = $today->modify('-1 day');
        $latest = $today->modify('+2 years');

        $cleanType = self::choice($in['clean_type'] ?? '', self::CLEAN_TYPES);
        if ($cleanType === null) {
            $errors['clean_type'] = 'Choose the clean you need.';
        }

        $unsure = (string) ($in['clean_date_unsure'] ?? '') === '1';
        $cleanDate = null;
        if (!$unsure) {
            $cleanDate = self::date($in['clean_date'] ?? '', $this->tz);
            if ($cleanDate === null) {
                $errors['clean_date'] = 'Pick a date, or choose "Not sure yet".';
            } elseif ($cleanDate < $earliest || $cleanDate > $latest) {
                $errors['clean_date'] = 'Pick a cleaning date from today onwards, or choose "Not sure yet".';
            }
        }

        $suburb = self::clean($in['suburb'] ?? '');
        if ($suburb === '') {
            $errors['suburb'] = 'Enter the suburb of the property.';
        } elseif (mb_strlen($suburb) > 80 || !preg_match("/^[\\p{L}\\p{M}0-9 ,.'’()\\-\\/]+$/u", $suburb)) {
            $errors['suburb'] = 'Enter the suburb of the property (letters only, e.g. Richmond).';
        }

        $propertyType = self::choice($in['property_type'] ?? '', self::PROPERTY_TYPES);
        if ($propertyType === null) {
            $errors['property_type'] = 'Choose the property type.';
        }
        $bedrooms = self::choice($in['bedrooms'] ?? '', self::BEDROOMS);
        if ($bedrooms === null) {
            $errors['bedrooms'] = 'Choose the number of bedrooms.';
        }
        $bathrooms = self::choice($in['bathrooms'] ?? '', self::BATHROOMS);
        if ($bathrooms === null) {
            $errors['bathrooms'] = 'Choose the number of bathrooms.';
        }

        $addons = self::choices($in['addons'] ?? [], self::ADDONS);
        $carpetBedrooms = $carpetLiving = $windows = $walls = null;
        if (in_array('Carpet steam cleaning', $addons, true)) {
            $carpetBedrooms = self::int($in['carpet_bedrooms'] ?? '', 0, 5);
            if ($carpetBedrooms === null) {
                $errors['carpet_bedrooms'] = 'Choose how many carpeted bedrooms.';
            }
            $carpetLiving = self::int($in['carpet_living'] ?? '', 0, 4);
            if ($carpetLiving === null) {
                $errors['carpet_living'] = 'Choose how many carpeted living rooms.';
            }
        }
        if (in_array('Outside windows', $addons, true) && self::clean($in['windows_count'] ?? '') !== '') {
            $windows = self::int($in['windows_count'], 1, 99);
            if ($windows === null) {
                $errors['windows_count'] = 'Enter the number of outside windows as a number from 1 to 99.';
            }
        }
        if (in_array('Wall deep clean', $addons, true) && self::clean($in['walls_count'] ?? '') !== '') {
            $walls = self::int($in['walls_count'], 1, 99);
            if ($walls === null) {
                $errors['walls_count'] = 'Enter the number of walls as a number from 1 to 99.';
            }
        }

        $notes = self::clean($in['notes'] ?? '', true);
        if (mb_strlen($notes) > 1000) {
            $errors['notes'] = 'Please keep your notes under 1,000 characters.';
        }

        // The move (10% off) and free utility connection.
        $services = self::choices($in['additional-services'] ?? [], [self::SERVICE_MOVE, self::SERVICE_UTILITIES]);
        $combined = (string) ($in['combined_booking'] ?? '') === '1' || in_array(self::SERVICE_MOVE, $services, true);
        $moveDate = null;
        $moveOther = null;
        if ($combined) {
            if (!in_array(self::SERVICE_MOVE, $services, true)) {
                $services[] = self::SERVICE_MOVE;
            }
            if (self::clean($in['move_date'] ?? '') !== '') {
                $moveDate = self::date($in['move_date'], $this->tz);
                if ($moveDate === null || $moveDate < $earliest || $moveDate > $latest) {
                    $errors['move_date'] = 'Pick a moving date from today onwards, or leave it empty.';
                }
            }
            $moveOther = self::clean($in['move_other_suburb'] ?? '');
            if (mb_strlen($moveOther) > 80) {
                $errors['move_other_suburb'] = 'Enter the other suburb for your move (up to 80 characters).';
            }
            $moveOther = $moveOther !== '' ? $moveOther : null;
        }

        $utilities = [];
        $consent = (string) ($in['utility_consent'] ?? '') === '1';
        if ($consent) {
            $utilities = self::choices($in['utilities'] ?? [], self::UTILITIES);
            if (!$utilities) {
                $consent = false; // every utility unticked means no opt-in
            }
        }
        $services = array_values(array_filter($services, static fn ($s) => $s !== self::SERVICE_UTILITIES));
        if ($consent) {
            $services[] = self::SERVICE_UTILITIES;
        }

        $data = [
            'clean_type'           => $cleanType,
            'clean_date'           => $cleanDate?->format('Y-m-d'),
            'clean_date_unsure'    => $unsure ? 1 : 0,
            'suburb'               => $suburb,
            'property_type'        => $propertyType,
            'bedrooms'             => $bedrooms,
            'bathrooms'            => $bathrooms,
            'addons'               => $addons ? implode(', ', $addons) : null,
            'carpet_bedrooms'      => $carpetBedrooms,
            'carpet_living'        => $carpetLiving,
            'windows_count'        => $windows,
            'walls_count'          => $walls,
            'notes'                => $notes !== '' ? $notes : null,
            'additional_services'  => $services ? implode(', ', $services) : null,
            'combined_booking'     => $combined ? 1 : 0,
            'move_date'            => $moveDate?->format('Y-m-d'),
            'move_other_suburb'    => $moveOther,
            'utilities'            => $consent ? implode(', ', $utilities) : null,
            'utility_consent'      => $consent ? 1 : 0,
            'utility_consent_text' => $consent ? self::UTILITY_CONSENT_TEXT : null,
            'utility_consent_at'   => $consent ? $this->consentTime($in['utility_consent_at'] ?? '') : null,
        ];

        return [$data, $errors];
    }

    /** Australian numbers only, same rules as the browser check. Returns digits, e.g. 0412345678. */
    public static function normalisePhone(string $value): ?string
    {
        $d = preg_replace('/[\s()\-.]/', '', $value) ?? '';
        if (str_starts_with($d, '+61')) {
            $d = '0' . substr($d, 3);
        } elseif (preg_match('/^61\d{9}$/', $d)) {
            $d = '0' . substr($d, 2);
        }
        if (preg_match('/^0[2-478]\d{8}$/', $d) || preg_match('/^1[38]00\d{6}$/', $d) || preg_match('/^13\d{4}$/', $d)) {
            return $d;
        }
        return null;
    }

    /** Trims, removes control characters and invalid UTF-8; single line unless $multiline. */
    public static function clean(mixed $value, bool $multiline = false): string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            return '';
        }
        $value = str_replace(["\r\n", "\r"], "\n", $value);
        if ($multiline) {
            $value = preg_replace('/[^\P{C}\n]+/u', '', $value) ?? '';
            $value = preg_replace("/[ \t]+\n/", "\n", $value) ?? '';
            $value = preg_replace("/\n{3,}/", "\n\n", $value) ?? '';
        } else {
            $value = preg_replace('/\p{C}+/u', ' ', $value) ?? '';
            $value = preg_replace('/\s+/u', ' ', $value) ?? '';
        }
        return trim($value);
    }

    private static function limit(string $value, int $max): string
    {
        return mb_substr($value, 0, $max);
    }

    /** Tracking ids: letters, digits and - _ . ~ only, otherwise dropped. */
    private static function token(mixed $value, int $max): ?string
    {
        $value = self::clean($value);
        if ($value === '' || strlen($value) > $max || !preg_match('/^[A-Za-z0-9_\-.~]+$/', $value)) {
            return null;
        }
        return $value;
    }

    private static function choice(mixed $value, array $allowed): ?string
    {
        $value = self::clean($value);
        return in_array($value, $allowed, true) ? $value : null;
    }

    /** @return list<string> allowed values in the form's order, duplicates removed */
    private static function choices(mixed $values, array $allowed): array
    {
        if (!is_array($values)) {
            return [];
        }
        $given = array_map(static fn ($v) => self::clean($v), $values);
        return array_values(array_filter($allowed, static fn ($a) => in_array($a, $given, true)));
    }

    private static function int(mixed $value, int $min, int $max): ?int
    {
        $value = self::clean($value);
        if (!preg_match('/^\d{1,3}$/', $value)) {
            return null;
        }
        $n = (int) $value;
        return $n >= $min && $n <= $max ? $n : null;
    }

    private static function date(mixed $value, DateTimeZone $tz): ?DateTimeImmutable
    {
        $value = self::clean($value);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $value, $tz);
        return $d && $d->format('Y-m-d') === $value ? $d : null;
    }

    /** Page URL the form was sent from: only our own site, without fragments, max 500 chars. */
    private function siteUrl(mixed $value): ?string
    {
        $value = self::clean($value);
        if ($value === '' || strlen($value) > 500 || !filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }
        $parts = parse_url($value);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (!in_array($scheme, ['http', 'https'], true) || !in_array($host, $this->allowedHosts, true)) {
            return null;
        }
        return strtok($value, '#') ?: null;
    }

    /** The time the visitor ticked the utilities opt-in, if plausible; otherwise now. */
    private function consentTime(mixed $value): string
    {
        $now = new DateTimeImmutable('now', $this->tz);
        $value = self::clean($value);
        if ($value !== '' && strlen($value) <= 40) {
            try {
                $t = (new DateTimeImmutable($value))->setTimezone($this->tz);
                if (abs($t->getTimestamp() - $now->getTimestamp()) <= 86400) {
                    return $t->format('Y-m-d H:i:s');
                }
            } catch (Throwable) {
                // fall through to now
            }
        }
        return $now->format('Y-m-d H:i:s');
    }
}
