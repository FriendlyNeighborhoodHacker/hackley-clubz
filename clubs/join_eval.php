<?php
declare(strict_types=1);

/**
 * POST handler — join a club.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/UserContext.php';
require_once __DIR__ . '/../lib/ClubManagement.php';

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
$returnTo = $_POST['return_to'] ?? '/clubs/browse.php';

// Sanitise the return URL — only allow relative paths on this host
if (!preg_match('/^\/[a-zA-Z0-9\/\-_\.?=&%]+$/', $returnTo)) {
    $returnTo = '/clubs/browse.php';
}

if ($clubId <= 0) {
    Flash::set('error', 'Invalid club.');
    redirect($returnTo);
}

$club = ClubManagement::getClubById($clubId);
if (!$club || $club['is_secret']) {
    Flash::set('error', 'Club not found.');
    redirect($returnTo);
}

try {
    ClubManagement::joinClub($ctx, $clubId);
    Flash::set('success', 'You have joined ' . $club['name'] . '!');
    // Signal the next page to fire confetti via session so it works
    // regardless of URL structure or sanitisation rules.
    $_SESSION['_confetti'] = true;
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
}

redirect($returnTo);
