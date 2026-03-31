<?php
declare(strict_types=1);

/**
 * Login form evaluation.
 * POST target for /login.php.
 * On success: populates session and redirects to the destination page.
 * On failure: redirects back to login.php with an error flash message.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Application.php';
require_once __DIR__ . '/lib/UserManagement.php';
require_once __DIR__ . '/auth.php';

Application::init();

// Only handle POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/login.php');
}

// Preserve the deep-link redirect value through error redirects
$redirectTarget = Auth::postLoginRedirectUrl();
$redirectParam  = trim($_POST['redirect'] ?? '');
$loginUrl       = '/login.php' . ($redirectParam !== '' ? '?redirect=' . urlencode($redirectParam) : '');

try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect($loginUrl);
}

$email    = trim($_POST['email']    ?? '');
$password = trim($_POST['password'] ?? '');

if ($email === '' || $password === '') {
    Flash::set('error', 'Please enter your email and password.');
    redirect($loginUrl);
}

$user = UserManagement::attemptLogin($email, $password);

if (!$user) {
    // Check whether the account exists but is unverified (give a more helpful message)
    $existing = UserManagement::findUserByEmail($email);
    if ($existing && $existing['email_verified_at'] === null) {
        Flash::set('error', 'Your email address has not been verified yet. Please check your inbox for a verification link.');
    } else {
        Flash::set('error', 'Invalid email address or password.');
    }
    redirect($loginUrl);
}

// Success — populate session and redirect
Auth::loginUser($user);
redirect($redirectTarget);
