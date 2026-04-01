<?php
declare(strict_types=1);

/**
 * POST handler for deleting a club.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/UserContext.php';
require_once __DIR__ . '/../../lib/ClubManagement.php';

Application::init();
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/clubs/index.php');
}

try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/admin/clubs/index.php');
}

$ctx    = UserContext::getLoggedInUserContext();
$clubId = (int)($_POST['club_id'] ?? 0);

if ($clubId <= 0) {
    Flash::set('error', 'Invalid club ID.');
    redirect('/admin/clubs/index.php');
}

try {
    $club = ClubManagement::getClubById($clubId);
    $name = $club['name'] ?? 'Club';

    ClubManagement::deleteClub($ctx, $clubId);

    Flash::set('success', '"' . $name . '" was deleted.');
    redirect('/admin/clubs/index.php');

} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/admin/clubs/index.php');
}
