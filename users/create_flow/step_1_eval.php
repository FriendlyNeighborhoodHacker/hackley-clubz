<?php
declare(strict_types=1);

/**
 * Account creation wizard — Step 1 evaluation.
 * Validates the email domain, then sends to step 2.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/UserManagement.php';
require_once __DIR__ . '/../../auth.php';

Application::init();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/users/create_flow/step_1.php');
}

try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/users/create_flow/step_1.php');
}

$email = strtolower(trim($_POST['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Flash::set('error', 'Please enter a valid email address.');
    redirect('/users/create_flow/step_1.php');
}

if (!UserManagement::isEmailDomainAllowed($email)) {
    // Determine the domain for the error message
    $domain = substr($email, strrpos($email, '@') + 1);
    Flash::set('error', "The email domain \"$domain\" is not allowed. Please use your school email address.");
    $_SESSION['create_email'] = $email;
    redirect('/users/create_flow/step_1.php');
}

// Store in session and advance to step 2
$_SESSION['create_email'] = $email;
redirect('/users/create_flow/step_2.php');
