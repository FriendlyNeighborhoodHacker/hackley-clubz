<?php
declare(strict_types=1);
/**
 * AJAX — toggle a heart reaction on a message.
 * POST: message_id, reaction ('heart'), _csrf_token
 * Returns JSON: { success, reacted, count } | { success: false, error }
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
$reaction  = trim($_POST['reaction'] ?? 'heart');

// Only allow known reaction types
if (!in_array($reaction, ['heart'], true)) {
    $reaction = 'heart';
}

try {
    $result = ConversationManagement::toggleReaction($ctx, $messageId, $reaction);
    echo json_encode(['success' => true, 'reacted' => $result['reacted'], 'count' => $result['count']]);
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
