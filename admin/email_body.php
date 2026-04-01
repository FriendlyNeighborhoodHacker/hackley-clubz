<?php
declare(strict_types=1);

/**
 * Raw email body renderer — outputs the stored HTML body for a given email ID.
 * Loaded inside an <iframe> by admin/email_view.php.
 * Access is restricted to app admins.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/EmailLog.php';

Application::init();
Auth::requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo '<!DOCTYPE html><html><body>Invalid ID.</body></html>';
    exit;
}

$email = EmailLog::findById($id);
if (!$email || empty($email['body_html'])) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:20px;color:#666;">No email body stored.</body></html>';
    exit;
}

// Output the stored HTML exactly as it was sent
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
echo $email['body_html'];
exit;
