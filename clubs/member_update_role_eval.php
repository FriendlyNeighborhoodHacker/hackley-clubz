<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/ClubManagement.php';
require_once __DIR__ . '/../lib/UserContext.php';

Application::init();
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/clubs/browse.php');
}

$clubId       = 0;
$targetUserId = 0;

try {
    csrf_verify();

    $clubId       = (int)($_POST['club_id'] ?? 0);
    $targetUserId = (int)($_POST['user_id'] ?? 0);
    $role         = trim((string)($_POST['role'] ?? ''));

    if ($clubId <= 0 || $targetUserId <= 0) {
        throw new \RuntimeException('Invalid request.');
    }

    $ctx = UserContext::getLoggedInUserContext();
    ClubManagement::updateMemberRole($ctx, $clubId, $targetUserId, $role);

    Flash::set('success', 'Role updated.');
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
}

redirect('/clubs/membership_edit.php?club_id=' . $clubId . '&user_id=' . $targetUserId);
