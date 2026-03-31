<?php
declare(strict_types=1);

/**
 * Profile edit evaluation — saves first_name and last_name.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/UserManagement.php';
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

$ctx = \UserContext::getLoggedInUserContext();

try {
    UserManagement::updateProfile($ctx, [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name']  ?? ''),
    ]);
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/profile/edit.php');
}

Flash::set('success', 'Profile updated.');
redirect('/profile/edit.php');
