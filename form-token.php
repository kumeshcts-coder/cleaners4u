<?php
/**
 * Gives the form JavaScript a CSRF token tied to the visitor's secure session.
 * The HTML pages are static (and cached), so the token is fetched separately and never cached.
 */
declare(strict_types=1);

define('C4U_APP', true);

/** @var Cleaners4u\App $app */
$app = require __DIR__ . '/app/bootstrap.php';

use Cleaners4u\Response;
use Cleaners4u\Security;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    header('Allow: GET');
    Response::json(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

$security = new Security($app);
if (!$security->isAjax()) {
    Response::json(403, ['ok' => false, 'message' => 'Forbidden.']);
}

$security->startSession();
Response::json(200, ['ok' => true, 'token' => $security->csrfToken()]);
