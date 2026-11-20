<?php
/**
 * Loads Composer, config.php and the App for the form endpoints.
 * Usage (from a public endpoint):  define('C4U_APP', true); $app = require __DIR__ . '/app/bootstrap.php';
 */
declare(strict_types=1);

defined('C4U_APP') || exit;

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$root = dirname(__DIR__);

$unavailable = static function (string $reason): never {
    error_log('[cleaners4u-forms] ' . $reason);
    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
    }
    echo json_encode([
        'ok'      => false,
        'message' => 'Our online form is temporarily unavailable. Please call us on 03 7042 9778.',
    ]);
    exit;
};

if (!is_file($root . '/vendor/autoload.php')) {
    $unavailable('vendor/autoload.php is missing: run "composer install --no-dev" in ' . $root);
}
require $root . '/vendor/autoload.php';

if (!is_file($root . '/config.php')) {
    $unavailable('config.php is missing: copy config.sample.php to config.php and fill it in');
}
$config = require $root . '/config.php';
if (!is_array($config)) {
    $unavailable('config.php must return an array');
}

$app = new Cleaners4u\App($config);
date_default_timezone_set($app->tz()->getName());

set_exception_handler(static function (Throwable $e) use ($app): void {
    $app->logger()->error('Unhandled ' . get_class($e) . ': ' . $e->getMessage(), [
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    Cleaners4u\Response::json(500, ['ok' => false, 'message' => $app->genericError()]);
});

return $app;
