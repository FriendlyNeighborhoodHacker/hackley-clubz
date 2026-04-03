<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/ClubManagement.php';
require_once __DIR__ . '/../lib/UserContext.php';

Application::init();
Auth::requireLogin();
csrf_verify();

$clubId = (int)($_POST['club_id'] ?? 0);
if ($clubId <= 0) {
    Flash::set('error', 'Invalid club.');
    redirect('/clubs/browse.php');
}

$club = ClubManagement::getClubById($clubId);
if (!$club) {
    Flash::set('error', 'Club not found.');
    redirect('/clubs/browse.php');
}

$ctx         = UserContext::getLoggedInUserContext();
$isClubAdmin = ClubManagement::isUserClubAdmin($ctx->id, $clubId);
$canManage   = $isClubAdmin || $ctx->admin;

if (!$canManage) {
    Flash::set('error', 'You do not have permission to manage this club.');
    redirect('/clubs/view.php?id=' . $clubId);
}

$settingsUrl = '/clubs/settings.php?id=' . $clubId;

try {
    // ── Text fields ────────────────────────────────────────────────────────
    $name            = trim((string)($_POST['name']             ?? ''));
    $description     = trim((string)($_POST['description']     ?? ''));
    $meetingLocation = trim((string)($_POST['meeting_location'] ?? ''));
    $isSecret        = !empty($_POST['is_secret']);

    // Build meeting_days comma string from checkboxes
    $rawDays = array_filter(array_map('intval', (array)($_POST['meeting_days'] ?? [])),
                            fn($d) => $d >= 1 && $d <= 8);
    sort($rawDays, SORT_NUMERIC);
    $meetingDays = implode(',', $rawDays);

    if ($name === '') {
        throw new \RuntimeException('Club name is required.');
    }

    // ── Profile photo ──────────────────────────────────────────────────────
    $photoFileId = null;
    $clearPhoto  = !empty($_POST['clear_photo']);

    $photoData = trim((string)($_POST['photo_data'] ?? ''));
    if ($photoData !== '') {
        $photoFileId = ClubManagement::savePhotoFromDataUrl(
            $photoData,
            'club_photo_' . $clubId . '.jpg',
            $ctx->id
        );
    }

    // ── Hero image ─────────────────────────────────────────────────────────
    $heroFileId = null;
    $clearHero  = !empty($_POST['clear_hero']);

    $heroData = trim((string)($_POST['hero_data'] ?? ''));
    if ($heroData !== '') {
        $heroFileId = ClubManagement::savePhotoFromDataUrl(
            $heroData,
            'club_hero_' . $clubId . '.jpg',
            $ctx->id
        );
    }

    // ── Save ───────────────────────────────────────────────────────────────
    ClubManagement::updateClubSettings(
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

    Flash::set('success', 'Club settings saved.');
    redirect($settingsUrl);

} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect($settingsUrl);
}
