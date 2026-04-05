<?php
declare(strict_types=1);
/**
 * AJAX — toggle pin on a message (club admins only).
 * POST: message_id, _csrf_token
 * Returns JSON: { success, pinned } | { success: false, error }
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/UserContext.php';
require_once __DIR__ . '/../../lib/ConversationManagement.php';
require_once __DIR__ . '/../../lib/ClubManagement.php';

Application::init();
if (empty($_SESSION['uid'])) { echo json_encode(['success'=>false,'error'=>'Not authenticated.']); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'error'=>'Method not allowed.']); exit; }
try { csrf_verify(); } catch (\RuntimeException $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); exit; }

$ctx       = UserContext::getLoggedInUserContext();
$messageId = (int)($_POST['message_id'] ?? 0);

try {
    $msg = ConversationManagement::getMessageById($messageId);
    if (!$msg) { throw new \RuntimeException('Message not found.'); }

    $conv = ConversationManagement::getConversationById((int)$msg['conversation_id']);
    $isClubAdmin = $conv
        ? ClubManagement::isUserClubAdmin($ctx->id, (int)$conv['club_id']) || $ctx->admin
        : false;

    if (!$isClubAdmin) { throw new \RuntimeException('Only club leaders can pin messages.'); }

    $newPinned = ConversationManagement::togglePinMessage($ctx, $messageId);
    echo json_encode(['success' => true, 'pinned' => $newPinned]);
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
