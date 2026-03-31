<?php
declare(strict_types=1);

/**
 * Account creation wizard — Step 4 evaluation.
 * Saves first and last name, redirects to step 5 (profile photo).
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/UserManagement.php';
require_once __DIR__ . '/../../auth.php';

Application::init();
Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/users/create_flow/step_4.php');
}

try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/users/create_flow/step_4.php');
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name']  ?? '');

$ctx = \UserContext::getLoggedInUserContext();

try {
    UserManagement::completeName($ctx, $firstName, $lastName);
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/users/create_flow/step_4.php');
}

redirect('/users/create_flow/step_5.php');
