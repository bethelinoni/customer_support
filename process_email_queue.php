<?php
/**
 * Asynchronous Background Email Queue Worker
 * 
 * Invoked asynchronously by web pages via fetch() so that user requests
 * and redirects execute instantaneously without waiting for SMTP handshakes.
 */

// Allow processing to continue even if the client closes the HTTP connection
ignore_user_abort(true);
set_time_limit(30);

require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/mailer.php';

// Set JSON response headers
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Optional: fastcgi_finish_request allows closing the client connection immediately
if (function_exists('fastcgi_finish_request')) {
    echo json_encode(['status' => 'dispatched']);
    fastcgi_finish_request();
}

// Process up to 10 pending emails per execution batch
$processed = processEmailQueue(10);

echo json_encode([
    'status' => 'complete',
    'processed' => $processed,
    'timestamp' => date('Y-m-d H:i:s')
]);
