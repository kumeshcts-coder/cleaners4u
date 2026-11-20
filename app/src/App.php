<?php
declare(strict_types=1);

namespace Cleaners4u;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use Throwable;

/** Holds the configuration and lazily creates the shared services. */
final class App
{
    private ?PDO $pdo = null;
    private ?Logger $logger = null;
    private ?Mailer $mailer = null;
    private ?EmailTemplates $templates = null;
    private ?DateTimeZone $tz = null;

    public function __construct(private readonly array $config)
    {
    }

    /** Reads a config value by dot path, e.g. cfg('smtp.host'). */
    public function cfg(string $path, mixed $default = null): mixed
    {
        $value = $this->config;
        foreach (explode('.', $path) as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $default;
            }
            $value = $value[$key];
        }
        return $value;
    }

    public function tz(): DateTimeZone
    {
        if ($this->tz === null) {
            try {
                $this->tz = new DateTimeZone((string) $this->cfg('site.timezone', 'Australia/Melbourne'));
            } catch (Throwable) {
                $this->tz = new DateTimeZone('Australia/Melbourne');
            }
        }
        return $this->tz;
    }

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', $this->tz());
    }

    public function logger(): Logger
    {
        return $this->logger ??= new Logger((string) $this->cfg('log_dir', ''));
    }

    /** Opens the database connection on first use. Throws PDOException on failure. */
    public function db(): PDO
    {
        if ($this->pdo === null) {
            $db = (array) $this->cfg('db', []);
            $charset = preg_replace('/[^a-z0-9_]/i', '', (string) ($db['charset'] ?? 'utf8mb4')) ?: 'utf8mb4';
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'] ?? '127.0.0.1',
                (int) ($db['port'] ?? 3306),
                $db['name'] ?? '',
                $charset
            );
            $this->pdo = new PDO($dsn, (string) ($db['user'] ?? ''), (string) ($db['pass'] ?? ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::ATTR_TIMEOUT            => 5,
            ]);
        }
        return $this->pdo;
    }

    public function mailer(): Mailer
    {
        return $this->mailer ??= new Mailer((array) $this->cfg('smtp', []), $this->logger());
    }

    public function templates(): EmailTemplates
    {
        return $this->templates ??= new EmailTemplates((array) $this->cfg('site', []));
    }

    /** @return list<string> */
    public function adminEmails(): array
    {
        $raw = $this->cfg('admin_email', '');
        $list = is_array($raw) ? $raw : explode(',', (string) $raw);
        $out = [];
        foreach ($list as $address) {
            $address = trim((string) $address);
            if ($address !== '' && filter_var($address, FILTER_VALIDATE_EMAIL)) {
                $out[] = $address;
            }
        }
        return array_values(array_unique($out));
    }

    public function phone(): string
    {
        return (string) $this->cfg('site.phone', '03 7042 9778');
    }

    public function genericError(): string
    {
        return 'Sorry, we couldn’t send your details. Please try again or call us on ' . $this->phone() . '.';
    }
}
