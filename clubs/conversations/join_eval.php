<?php
declare(strict_types=1);
/**
 * POST handler — join a public conversation.
 * POST: conversation_id, club_id, _csrf_token
 * Redirects to the conversation on success.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/UserContext.php';
require_once __DIR__ . '/../../lib/ConversationManagement.php';
require_once __DIR__ . '/../../lib/ClubManagement.php';

Application::init();
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/clubs/browse.php');
}
try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/clubs/browse.php');
}

$ctx    = UserContext::getLoggedInUserContext();
$convId = (int)($_POST['conversation_id'] ?? 0);
$clubId = (int)($_POST['club_id'] ?? 0);

if ($convId <= 0 || $clubId <= 0) {
    Flash::set('error', 'Invalid request.');
    redirect('/clubs/browse.php');
}

// Must be a club member to join any conversation in that club
if (!ClubManagement::isUserMember($ctx->id, $clubId)) {
    Flash::set('error', 'You must be a club member to join this conversation.');
    redirect('/clubs/view.php?id=' . $clubId);
}

try {
    ConversationManagement::joinConversation($ctx, $convId);
    Flash::set('success', 'You have joined the conversation!');
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
}

redirect('/clubs/conversations/view.php?id=' . $convId);
