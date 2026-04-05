<?php
declare(strict_types=1);
/**
 * AJAX — return HTML fragment listing joinable conversations for a club.
 * GET: club_id
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

if (empty($_SESSION['uid'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

$ctx    = UserContext::getLoggedInUserContext();
$clubId = (int)($_GET['club_id'] ?? 0);

if ($clubId <= 0 || !ClubManagement::isUserMember($ctx->id, $clubId)) {
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
}

$joinable  = ConversationManagement::listJoinableConversations($clubId, $ctx->id);
$csrfToken = csrf_token();
$html      = ConversationUI::renderJoinableList($joinable, $clubId, $csrfToken);

echo json_encode(['success' => true, 'html' => $html]);
exit;
