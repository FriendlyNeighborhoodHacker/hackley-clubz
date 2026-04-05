<?php
declare(strict_types=1);
/**
 * POST handler — create a new custom conversation.
 * POST: club_id, name, is_secret, members[], _csrf_token
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
$clubId = (int)($_POST['club_id'] ?? 0);

if ($clubId <= 0) {
    Flash::set('error', 'Invalid club.');
    redirect('/clubs/browse.php');
}

$isClubAdmin = ClubManagement::isUserClubAdmin($ctx->id, $clubId) || $ctx->admin;
if (!$isClubAdmin) {
    Flash::set('error', 'Only club leaders can create conversations.');
    redirect('/clubs/view.php?id=' . $clubId);
}

$name      = trim($_POST['name'] ?? '');
$isSecret  = !empty($_POST['is_secret']);
$memberIds = array_map('intval', (array)($_POST['members'] ?? []));

if ($name === '') {
    Flash::set('error', 'Conversation name is required.');
    redirect('/clubs/conversations/create.php?club_id=' . $clubId);
}

try {
    $convId = ConversationManagement::createConversation($ctx, $clubId, $name, $isSecret, $memberIds);
    Flash::set('success', '"' . $name . '" chat created!');
    redirect('/clubs/conversations/view.php?id=' . $convId);
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/clubs/conversations/create.php?club_id=' . $clubId);
}
