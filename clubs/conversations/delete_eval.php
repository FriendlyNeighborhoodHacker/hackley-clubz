<?php
declare(strict_types=1);
/**
 * AJAX — soft-delete a message.
 * POST: message_id, _csrf_token
 * Returns JSON: { success, html } | { success: false, error }
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/UserContext.php';
require_once __DIR__ . '/../../lib/ConversationManagement.php';
require_once __DIR__ . '/../../lib/ConversationUI.php';
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

    $conv        = ConversationManagement::getConversationById((int)$msg['conversation_id']);
    $isClubAdmin = $conv
        ? ClubManagement::isUserClubAdmin($ctx->id, (int)$conv['club_id']) || $ctx->admin
        : false;

    ConversationManagement::deleteMessage($ctx, $messageId, $isClubAdmin);

    // Fetch the now-deleted row for rendering
    $deleted = ConversationManagement::getMessageById($messageId);
    if (!$deleted) { throw new \RuntimeException('Could not reload deleted message.'); }

    // Add minimal joined fields for the renderer
    $deleted['first_name']           = null;
    $deleted['last_name']            = null;
    $deleted['user_email']           = null;
    $deleted['photo_public_file_id'] = null;
    $deleted['heart_count']          = 0;

    $html = ConversationUI::renderMessage($deleted, $ctx->id, $isClubAdmin, false);
    echo json_encode(['success' => true, 'html' => $html]);
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
