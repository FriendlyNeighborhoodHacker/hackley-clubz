<?php
declare(strict_types=1);

/**
 * Profile photo update evaluation — receives base64 cropped image and saves it.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/UserManagement.php';
require_once __DIR__ . '/../lib/ImageManager.php';
require_once __DIR__ . '/../auth.php';

Application::init();
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/profile/edit.php');
}

try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/profile/edit.php');
}

$photoData = trim($_POST['photo_data'] ?? '');

if ($photoData === '') {
    Flash::set('error', 'No photo data received. Please try again.');
    redirect('/profile/edit.php');
}

$ctx = \UserContext::getLoggedInUserContext();

try {
    $fileId = ImageManager::storeBase64Image($ctx, $photoData, 'profile_photo.jpg');
    UserManagement::setProfilePhoto($ctx, $fileId);
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/profile/edit.php');
}

Flash::set('success', 'Profile photo updated.');
redirect('/profile/edit.php');
