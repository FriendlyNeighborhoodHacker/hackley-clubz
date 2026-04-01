<?php
declare(strict_types=1);

/**
 * Account creation wizard — Step 6 evaluation.
 * Saves phone number (country code + local number) to the user profile.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/UserManagement.php';
require_once __DIR__ . '/../../lib/Settings.php';
require_once __DIR__ . '/../../auth.php';

Application::init();
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/users/create_flow/step_6.php');
}

try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/users/create_flow/step_6.php');
}

$countryCode = trim($_POST['country_code'] ?? '');
$phoneLocal  = trim($_POST['phone_local']  ?? '');

// Strip everything except digits and formatting from the local part
$phoneLocalDigits = preg_replace('/\D/', '', $phoneLocal);

if ($phoneLocalDigits === '') {
    Flash::set('error', 'Please enter your phone number or click "Do this later".');
    redirect('/users/create_flow/step_6.php');
}

// Combine into a single stored value, e.g. "+1 (555) 555-5555"
$phone = trim($countryCode . ' ' . $phoneLocal);

$ctx = \UserContext::getLoggedInUserContext();

try {
    UserManagement::updateProfile($ctx, ['phone' => $phone]);
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/users/create_flow/step_6.php');
}

Flash::set('success', 'Welcome to ' . Settings::siteTitle() . '!');
redirect('/index.php');
