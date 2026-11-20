<?php
declare(strict_types=1);

namespace Cleaners4u;

/** Secure sessions, CSRF tokens, same-origin checks and the visitor's real IP. */
final class Security
{
    private const SESSION_NAME = 'C4USESS';

    public function __construct(private readonly App $app)
    {
    }

    /** Starts a hardened PHP session (cookie only, strict mode, HttpOnly, SameSite, Secure on HTTPS). */
    public function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.sid_bits_per_character', '6');
        $idleMinutes = max(10, (int) $this->app->cfg('security.session_idle_minutes', 120));
        ini_set('session.gc_maxlifetime', (string) ($idleMinutes * 60));

        session_name(self::SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $this->isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Expire idle sessions ourselves; PHP's garbage collector is not prompt.
        $now = time();
        if (isset($_SESSION['_last']) && $now - (int) $_SESSION['_last'] > $idleMinutes * 60) {
            $_SESSION = [];
            session_regenerate_id(true);
        }
        $_SESSION['_last'] = $now;
    }

    /** New session id after a privilege-like change (a lead now belongs to this browser). */
    public function regenerateSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /** Hash of the current session id, stored on the lead so step 2 can only update this browser's lead. */
    public function sessionHash(): string
    {
        return hash('sha256', (string) session_id());
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf'];
    }

    public function validCsrf(mixed $token): bool
    {
        return is_string($token)
            && isset($_SESSION['_csrf'])
            && is_string($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    /** The form JS sends this header; plain cross-site form posts cannot. */
    public function isAjax(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    /** Rejects requests whose Origin (or Referer) is another website. */
    public function sameOrigin(): bool
    {
        $source = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
        if ($source === '' || $source === 'null') {
            return $source === ''; // no header at all: rely on the CSRF token
        }
        $host = strtolower((string) parse_url($source, PHP_URL_HOST));
        if ($host === '') {
            return false;
        }
        return in_array($host, $this->allowedHosts(), true);
    }

    /** @return list<string> */
    public function allowedHosts(): array
    {
        $hosts = array_map('strtolower', (array) $this->app->cfg('security.allowed_hosts', []));
        $siteHost = parse_url((string) $this->app->cfg('site.url', ''), PHP_URL_HOST);
        if (is_string($siteHost) && $siteHost !== '') {
            $hosts[] = strtolower($siteHost);
        }
        return array_values(array_unique($hosts));
    }

    /** The visitor's IP, read from X-Forwarded-For / X-Real-IP only when the request came through a trusted proxy. */
    public function clientIp(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $trusted = (array) $this->app->cfg('security.trusted_proxies', ['127.0.0.1', '::1']);

        if (in_array($remote, $trusted, true)) {
            $forwarded = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
            if ($forwarded !== '') {
                // Right to left: the first address that isn't one of our proxies is the client.
                foreach (array_reverse(array_map('trim', explode(',', $forwarded))) as $candidate) {
                    if (filter_var($candidate, FILTER_VALIDATE_IP) && !in_array($candidate, $trusted, true)) {
                        return $candidate;
                    }
                }
            }
            $real = trim((string) ($_SERVER['HTTP_X_REAL_IP'] ?? ''));
            if ($real !== '' && filter_var($real, FILTER_VALIDATE_IP)) {
                return $real;
            }
        }
        return filter_var($remote, FILTER_VALIDATE_IP) ? $remote : '0.0.0.0';
    }

    public function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
            return true;
        }
        return str_starts_with(strtolower((string) $this->app->cfg('site.url', '')), 'https://');
    }
}
