<?php
declare(strict_types=1);

/**
 * Account creation wizard — Step 5 evaluation.
 * Receives the base64-encoded cropped photo, stores it, redirects to homepage.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/UserManagement.php';
require_once __DIR__ . '/../../lib/ImageManager.php';
require_once __DIR__ . '/../../lib/Settings.php';
require_once __DIR__ . '/../../auth.php';

Application::init();
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/users/create_flow/step_5.php');
}

try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/users/create_flow/step_5.php');
}

$photoData = trim($_POST['photo_data'] ?? '');

if ($photoData === '') {
    Flash::set('error', 'No photo data received. Please try again.');
    redirect('/users/create_flow/step_5.php');
}

$ctx = \UserContext::getLoggedInUserContext();

try {
    $fileId = ImageManager::storeBase64Image($ctx, $photoData, 'profile_photo.jpg');
    UserManagement::setProfilePhoto($ctx, $fileId);
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/users/create_flow/step_5.php');
}

Flash::set('success', 'Welcome to ' . \Settings::siteTitle() . '!');
redirect('/index.php');
