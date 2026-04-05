<?php
declare(strict_types=1);
/**
 * AJAX endpoint — post a new message to a conversation.
 *
 * POST params: conversation_id, body, _csrf_token
 * Returns JSON: { success, html, last_id } | { success: false, error }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/UserContext.php';
require_once __DIR__ . '/../../lib/ConversationManagement.php';
require_once __DIR__ . '/../../lib/ConversationUI.php';
require_once __DIR__ . '/../../lib/ClubManagement.php';

Application::init();

if (empty($_SESSION['uid'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}
try {
    csrf_verify();
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

$ctx    = UserContext::getLoggedInUserContext();
$convId = (int)($_POST['conversation_id'] ?? 0);
$body   = trim($_POST['body'] ?? '');

try {
    $msgId = ConversationManagement::postMessage($ctx, $convId, $body);

    // Fetch the new message row with all joined data for rendering
    $msgs = ConversationManagement::listMessages($convId, $msgId - 1, 1);
    $msg  = $msgs[0] ?? null;
    if (!$msg || (int)$msg['id'] !== $msgId) {
        echo json_encode(['success' => false, 'error' => 'Message posted but could not render.']);
        exit;
    }

    $conv        = ConversationManagement::getConversationById($convId);
    $isClubAdmin = $conv
        ? ClubManagement::isUserClubAdmin($ctx->id, (int)$conv['club_id']) || $ctx->admin
        : false;
    $html = ConversationUI::renderMessage($msg, $ctx->id, $isClubAdmin, false);

    echo json_encode(['success' => true, 'html' => $html, 'last_id' => $msgId]);
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
exit;
