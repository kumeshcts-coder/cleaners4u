<?php
declare(strict_types=1);

namespace Cleaners4u;

/** JSON responses for the form endpoints. Never cached (Varnish, browsers, proxies). */
final class Response
{
    public static function json(int $status, array $data): never
    {
        self::send($status, $data);
        exit;
    }

    /**
     * Sends the response and, under PHP-FPM, closes the connection so the visitor
     * moves on while slow work (sending emails) continues in the background.
     */
    public static function sendAndContinue(int $status, array $data): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        ignore_user_abort(true);
        set_time_limit(120);
        self::send($status, $data);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
    }

    private static function send(int $status, array $data): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate, private, max-age=0');
            header('Pragma: no-cache');
            header('X-Content-Type-Options: nosniff');
            header('X-Robots-Tag: noindex');
            header('Referrer-Policy: same-origin');
        }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
