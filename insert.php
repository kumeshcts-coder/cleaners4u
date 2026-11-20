<?php
/**
 * Quote form endpoint for both steps (the forms' action="/insert.php").
 *   Step 1 (index.html):              posts with a "request" field
 *   Step 2 (additional-details.html): posts with a "detail" field
 * Answers with JSON. Requests must come from the site's own JavaScript with a valid CSRF token.
 */
declare(strict_types=1);

define('C4U_APP', true);

/** @var Cleaners4u\App $app */
$app = require __DIR__ . '/app/bootstrap.php';

use Cleaners4u\LeadController;
use Cleaners4u\Response;
use Cleaners4u\Security;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    Response::json(405, ['ok' => false, 'message' => 'Method not allowed.']);
}

$security = new Security($app);

if (!$security->isAjax() || !$security->sameOrigin()) {
    $app->logger()->warning('Rejected cross-site or non-AJAX post', ['ip' => $security->clientIp()]);
    Response::json(403, ['ok' => false, 'message' => 'Please use the form on our website.']);
}

// Anything far bigger than the form can produce is not a real submission.
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 64 * 1024) {
    Response::json(413, ['ok' => false, 'message' => 'That submission is too large.']);
}

$security->startSession();

if (!$security->validCsrf($_POST['csrf_token'] ?? null)) {
    Response::json(419, [
        'ok'      => false,
        'code'    => 'csrf',
        'message' => 'Your session has expired. Please refresh the page and try again.',
    ]);
}

$controller = new LeadController($app, $security);

if (array_key_exists('detail', $_POST)) {
    $controller->step2($_POST);
}
if (array_key_exists('request', $_POST)) {
    $controller->step1($_POST);
}

Response::json(400, ['ok' => false, 'message' => 'Unknown form.']);
