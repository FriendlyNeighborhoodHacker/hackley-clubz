<?php
declare(strict_types=1);

/**
 * POST handler — saves a cropped profile photo for a target user (admin only).
 * Mirrors profile/edit_photo_eval.php but accepts a user_id field.
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/UserManagement.php';
require_once __DIR__ . '/../../lib/ImageManager.php';

Application::init();
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/users/index.php');
}

try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/admin/users/index.php');
}

$targetId = (int)($_POST['user_id'] ?? 0);
if ($targetId <= 0) {
    Flash::set('error', 'Invalid user ID.');
    redirect('/admin/users/index.php');
}

$editUrl   = '/admin/users/edit.php?id=' . $targetId;
$photoData = trim($_POST['photo_data'] ?? '');

if ($photoData === '') {
    Flash::set('error', 'No photo data received. Please try again.');
    redirect($editUrl);
}

$ctx = UserContext::getLoggedInUserContext();

try {
    $fileId = ImageManager::storeBase64Image($ctx, $photoData, 'profile_photo.jpg');
    UserManagement::setProfilePhoto($ctx, $fileId, $targetId);
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect($editUrl);
}

Flash::set('success', 'Profile photo updated.');
redirect($editUrl);
