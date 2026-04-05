<?php
declare(strict_types=1);
/**
 * POST handler — delete an entire custom conversation (admin only).
 * POST: conversation_id, club_id, _csrf_token
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/UserContext.php';
require_once __DIR__ . '/../../lib/ClubManagement.php';
require_once __DIR__ . '/../../lib/ConversationManagement.php';

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

$isClubAdmin = ClubManagement::isUserClubAdmin($ctx->id, $clubId) || $ctx->admin;
if (!$isClubAdmin) {
    Flash::set('error', 'Only club leaders can delete conversations.');
    redirect('/clubs/view.php?id=' . $clubId);
}

try {
    ConversationManagement::deleteConversation($ctx, $convId);
    Flash::set('success', 'Conversation deleted.');
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
}

redirect('/clubs/view.php?id=' . $clubId);
