<?php
declare(strict_types=1);

/**
 * POST handler for admin/settings.php.
 *
 * Validates CSRF, saves each submitted setting via Settings::set(), then
 * redirects back to the settings page with a success or error flash message.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Settings.php';

Application::init();
Auth::requireAdmin();

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/settings.php');
}

try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/admin/settings.php');
}

// Keys that may be updated via this form
$allowedKeys = [
    'site_title',
    'announcement',
    'timezone',
    'student_email_domains',
    'adult_email_domains',
];

$ctx = UserContext::getLoggedInUserContext();
if ($ctx === null) {
    // Shouldn't happen after requireAdmin(), but guard anyway
    Flash::set('error', 'Could not determine the current user context.');
    redirect('/admin/settings.php');
}

try {
    $submitted = $_POST['s'] ?? [];
    foreach ($allowedKeys as $key) {
        $value = isset($submitted[$key]) ? trim((string)$submitted[$key]) : '';
        Settings::set($ctx, $key, $value !== '' ? $value : null);
    }
    Flash::set('success', 'Settings saved successfully.');
} catch (\RuntimeException $e) {
    Flash::set('error', 'Failed to save settings: ' . $e->getMessage());
}

redirect('/admin/settings.php');
