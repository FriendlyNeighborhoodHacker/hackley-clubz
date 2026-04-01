<?php
declare(strict_types=1);

/**
 * POST handler for creating a new club.
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
    redirect('/admin/clubs/add.php');
}

$ctx = UserContext::getLoggedInUserContext();

$name        = trim($_POST['name']        ?? '');
$description = trim($_POST['description'] ?? '');
$meets       = trim($_POST['meets']       ?? '');
$isSecret    = !empty($_POST['is_secret']);

// Validate
if ($name === '') {
    Flash::set('error', 'Club name is required.');
    redirect('/admin/clubs/add.php');
}

try {
    // ── Profile photo (base64 from canvas crop) ─────────────────────────────
    $photoFileId = ClubManagement::savePhotoFromDataUrl(
        $_POST['photo_data'] ?? '',
        'club_photo.jpg',
        $ctx->id
    );

    // ── Hero image (base64 from canvas crop) ────────────────────────────────
    $heroFileId = ClubManagement::savePhotoFromDataUrl(
        $_POST['hero_data'] ?? '',
        'club_hero.jpg',
        $ctx->id
    );

    $clubId = ClubManagement::createClub(
        $ctx,
        $name,
        $description,
        $meets,
        $photoFileId,
        $heroFileId,
        $isSecret
    );

    Flash::set('success', 'Club "' . $name . '" created successfully.');
    redirect('/admin/clubs/edit.php?id=' . $clubId);

} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/admin/clubs/add.php');
}
