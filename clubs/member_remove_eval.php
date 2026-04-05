<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/ClubManagement.php';
require_once __DIR__ . '/../lib/ConversationManagement.php';
require_once __DIR__ . '/../lib/UserContext.php';

Application::init();
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/clubs/browse.php');
}

$clubId = 0;

try {
    csrf_verify();

    $clubId       = (int)($_POST['club_id'] ?? 0);
    $targetUserId = (int)($_POST['user_id'] ?? 0);

    if ($clubId <= 0 || $targetUserId <= 0) {
        throw new \RuntimeException('Invalid request.');
    }

    $ctx = UserContext::getLoggedInUserContext();
    ClubManagement::removeMember($ctx, $clubId, $targetUserId);
    ConversationManagement::onUserLeftClub($ctx, $clubId, $targetUserId);

    Flash::set('success', 'Member removed from club.');
    redirect('/clubs/members.php?id=' . $clubId);
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/clubs/members.php?id=' . $clubId);
}
