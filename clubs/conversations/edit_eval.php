<?php
declare(strict_types=1);
/**
 * AJAX — edit a message body.
 * POST: message_id, body, _csrf_token
 * Returns JSON: { success, body_html } | { success: false, error }
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/UserContext.php';
require_once __DIR__ . '/../../lib/ConversationManagement.php';

Application::init();
if (empty($_SESSION['uid'])) { echo json_encode(['success'=>false,'error'=>'Not authenticated.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'Method not allowed.']); exit; }
try { csrf_verify(); } catch (\RuntimeException $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); exit; }

$ctx       = UserContext::getLoggedInUserContext();
$messageId = (int)($_POST['message_id'] ?? 0);
$body      = trim($_POST['body'] ?? '');

try {
    ConversationManagement::editMessage($ctx, $messageId, $body);
    $bodyHtml = '<span class="msg-body-text">' . nl2br(e($body)) . '</span>'
              . ' <span class="msg-edited-label">(edited)</span>';
    echo json_encode(['success' => true, 'body_html' => $bodyHtml]);
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
