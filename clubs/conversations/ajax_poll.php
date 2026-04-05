<?php
declare(strict_types=1);
/**
 * AJAX polling — return new messages since after_id.
 * GET: conversation_id, after_id
 * Returns JSON: { success, html, last_id } | { success: false, error }
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/UserContext.php';
require_once __DIR__ . '/../../lib/ConversationManagement.php';
require_once __DIR__ . '/../../lib/ConversationUI.php';
require_once __DIR__ . '/../../lib/ClubManagement.php';

Application::init();

if (empty($_SESSION['uid'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

$ctx    = UserContext::getLoggedInUserContext();
$convId = (int)($_GET['conversation_id'] ?? 0);
$afterId= (int)($_GET['after_id'] ?? 0);

if ($convId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid conversation.']);
    exit;
}

if (!ConversationManagement::isUserMemberOfConversation($ctx->id, $convId)) {
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

$conv        = ConversationManagement::getConversationById($convId);
$isClubAdmin = $conv
    ? ClubManagement::isUserClubAdmin($ctx->id, (int)$conv['club_id']) || $ctx->admin
    : false;

$messages = ConversationManagement::listMessages($convId, $afterId);

if (empty($messages)) {
    echo json_encode(['success' => true, 'html' => '', 'last_id' => $afterId]);
    exit;
}

$html    = '';
$lastId  = $afterId;
foreach ($messages as $msg) {
    $hasReacted = ConversationManagement::hasReacted($ctx->id, (int)$msg['id']);
    $html      .= ConversationUI::renderMessage($msg, $ctx->id, $isClubAdmin, $hasReacted);
    if ((int)$msg['id'] > $lastId) $lastId = (int)$msg['id'];
}

echo json_encode(['success' => true, 'html' => $html, 'last_id' => $lastId]);
exit;
