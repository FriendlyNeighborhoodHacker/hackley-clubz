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

$clubId       = (int)($_POST['club_id'] ?? 0);
$targetUserId = (int)($_POST['user_id'] ?? 0);

try {
    csrf_verify();

    $makeAdmin = !empty($_POST['make_admin']); // true = grant, false = revoke

    if ($clubId <= 0 || $targetUserId <= 0) {
        throw new \RuntimeException('Invalid request.');
    }

    $ctx = UserContext::getLoggedInUserContext();
    ClubManagement::setMemberAdminStatus($ctx, $clubId, $targetUserId, $makeAdmin);

    $label = $makeAdmin ? 'Club Leader status granted.' : 'Club Leader status removed.';
    Flash::set('success', $label);
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
}

$returnTo = '/clubs/membership_edit.php?club_id=' . $clubId . '&user_id=' . $targetUserId;
redirect($returnTo);
