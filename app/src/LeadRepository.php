<?php
declare(strict_types=1);

namespace Cleaners4u;

use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

/** All database access for leads. Every query is a PDO prepared statement. */
final class LeadRepository
{
    /** Columns step 1 may write. */
    private const STEP1_COLUMNS = [
        'session_hash', 'name', 'email', 'phone', 'suburb_hint', 'form_id', 'terms_accepted',
        'gclid', 'gbraid', 'gad_source', 'gad_campaignid', 'device_id', 'current_url', 'referrer',
        'ip_address', 'user_agent', 'created_at',
    ];

    /** Columns step 2 may write. */
    private const STEP2_COLUMNS = [
        'clean_type', 'clean_date', 'clean_date_unsure', 'suburb', 'property_type', 'bedrooms', 'bathrooms',
        'addons', 'carpet_bedrooms', 'carpet_living', 'windows_count', 'walls_count', 'notes',
        'additional_services', 'combined_booking', 'move_date', 'move_other_suburb',
        'utilities', 'utility_consent', 'utility_consent_text', 'utility_consent_at',
        'step2_ip_address', 'completed_at',
    ];

    private const MAIL_COLUMNS = [
        'step1_admin_mailed_at', 'step1_customer_mailed_at', 'step2_admin_mailed_at', 'step2_customer_mailed_at',
    ];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * Inserts a new partial lead with a unique reference such as C4U-261120-7K3QX.
     * @return array{id: int, lead_ref: string}
     */
    public function create(array $fields, DateTimeImmutable $now): array
    {
        $fields = array_intersect_key($fields, array_flip(self::STEP1_COLUMNS));
        $columns = array_keys($fields);
        $sql = sprintf(
            'INSERT INTO `leads` (`lead_ref`, `status`, `%s`) VALUES (:lead_ref, \'partial\', :%s)',
            implode('`, `', $columns),
            implode(', :', $columns)
        );
        $stmt = $this->db->prepare($sql);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $ref = self::newReference($now);
            $params = [':lead_ref' => $ref];
            foreach ($fields as $column => $value) {
                $params[':' . $column] = $value;
            }
            try {
                $stmt->execute($params);
                return ['id' => (int) $this->db->lastInsertId(), 'lead_ref' => $ref];
            } catch (PDOException $e) {
                // 1062 duplicate key on the reference: try another one.
                if (($e->errorInfo[1] ?? null) === 1062 && str_contains($e->getMessage(), 'uq_lead_ref')) {
                    continue;
                }
                throw $e;
            }
        }
        throw new RuntimeException('Could not generate a unique lead reference.');
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM `leads` WHERE `id` = ? LIMIT 1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    /** The lead only if it belongs to this browser session. */
    public function findForSession(int $id, string $sessionHash, bool $lock = false): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM `leads` WHERE `id` = ? AND `session_hash` = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : '')
        );
        $stmt->execute([$id, $sessionHash]);
        return $stmt->fetch() ?: null;
    }

    /** A recent unfinished lead with the same email, phone and IP (a repeat of step 1). */
    public function findRecentPartial(string $email, string $phone, string $ip, string $since): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM `leads`
              WHERE `email` = ? AND `phone` = ? AND `ip_address` = ? AND `status` = \'partial\' AND `created_at` >= ?
              ORDER BY `id` DESC LIMIT 1'
        );
        $stmt->execute([$email, $phone, $ip, $since]);
        return $stmt->fetch() ?: null;
    }

    public function countFromIpSince(string $ip, string $since): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM `leads` WHERE `ip_address` = ? AND `created_at` >= ?');
        $stmt->execute([$ip, $since]);
        return (int) $stmt->fetchColumn();
    }

    public function bindSession(int $id, string $sessionHash): void
    {
        $stmt = $this->db->prepare('UPDATE `leads` SET `session_hash` = ? WHERE `id` = ?');
        $stmt->execute([$sessionHash, $id]);
    }

    /** Adds the step 2 details to the same lead and marks it complete. */
    public function completeStep2(int $id, array $fields): void
    {
        $fields = array_intersect_key($fields, array_flip(self::STEP2_COLUMNS));
        $sets = [];
        $params = [];
        foreach ($fields as $column => $value) {
            $sets[] = sprintf('`%s` = :%s', $column, $column);
            $params[':' . $column] = $value;
        }
        $params[':id'] = $id;
        $stmt = $this->db->prepare(
            'UPDATE `leads` SET ' . implode(', ', $sets) . ', `status` = \'complete\' WHERE `id` = :id'
        );
        $stmt->execute($params);
    }

    /** Records the outcome of one email: the send time, or the SMTP error. */
    public function recordMail(int $id, string $column, ?string $error, DateTimeImmutable $now): void
    {
        if (!in_array($column, self::MAIL_COLUMNS, true)) {
            throw new RuntimeException('Unknown mail column ' . $column);
        }
        if ($error === null) {
            $stmt = $this->db->prepare("UPDATE `leads` SET `$column` = ? WHERE `id` = ?");
            $stmt->execute([$now->format('Y-m-d H:i:s'), $id]);
        } else {
            $stmt = $this->db->prepare('UPDATE `leads` SET `last_mail_error` = ? WHERE `id` = ?');
            $stmt->execute([mb_substr($column . ': ' . $error, 0, 500), $id]);
        }
    }

    private static function newReference(DateTimeImmutable $now): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O or 1/I, easy to read out on the phone
        $suffix = '';
        for ($i = 0; $i < 5; $i++) {
            $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        return 'C4U-' . $now->format('ymd') . '-' . $suffix;
    }
}
