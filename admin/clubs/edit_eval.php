<?php
declare(strict_types=1);

/**
 * POST handler for updating an existing club.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/UserContext.php';
require_once __DIR__ . '/../../lib/Files.php';
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

// Verify the club exists before attempting the update
$club = ClubManagement::getClubById($clubId);
if (!$club) {
    Flash::set('error', 'Club not found.');
    redirect('/admin/clubs/index.php');
}

$name            = trim($_POST['name']             ?? '');
$description     = trim($_POST['description']      ?? '');
$meetingDays     = implode(',', array_filter(array_map('intval', (array)($_POST['meeting_days'] ?? []))));
$meetingLocation = trim($_POST['meeting_location'] ?? '');
$isSecret        = !empty($_POST['is_secret']);
$clearPhoto  = !empty($_POST['clear_photo']);
$clearHero   = !empty($_POST['clear_hero']);

if ($name === '') {
    Flash::set('error', 'Club name is required.');
    redirect('/admin/clubs/edit.php?id=' . $clubId);
}

try {
    // ── Profile photo (base64 from canvas crop) ─────────────────────────────
    $photoFileId = ClubManagement::savePhotoFromDataUrl(
        $_POST['photo_data'] ?? '',
        'club_photo.jpg',
        $ctx->id
    );
    // If a new photo was provided, clear_photo is superseded
    if ($photoFileId !== null) $clearPhoto = false;

    // ── Hero image (base64 from canvas crop) ────────────────────────────────
    $heroFileId = ClubManagement::savePhotoFromDataUrl(
        $_POST['hero_data'] ?? '',
        'club_hero.jpg',
        $ctx->id
    );
    // If a new hero was provided, clear_hero is superseded
    if ($heroFileId !== null) $clearHero = false;

    ClubManagement::updateClub(
        $ctx,
        $clubId,
        $name,
        $description,
        $meetingDays,
        $meetingLocation,
        $photoFileId,
        $heroFileId,
        $isSecret,
        $clearPhoto,
        $clearHero
    );

    Flash::set('success', 'Club updated successfully.');
    redirect('/admin/clubs/edit.php?id=' . $clubId);

} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/admin/clubs/edit.php?id=' . $clubId);
}
