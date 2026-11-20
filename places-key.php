<?php
/**
 * Gives the step 2 page the Google Maps browser key (from config.php) for the address suggestions.
 * The key is a public browser key: restrict it to https://cleaners4u.com.au/* in Google Cloud.
 */
declare(strict_types=1);

define('C4U_APP', true);

/** @var Cleaners4u\App $app */
$app = require __DIR__ . '/app/bootstrap.php';

use Cleaners4u\Response;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    Response::json(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

$key = trim((string) $app->cfg('google.maps_api_key', ''));
Response::json(200, ['ok' => $key !== '', 'key' => $key]);
