<?php
declare(strict_types=1);

/**
 * Account creation wizard — Step 2 evaluation.
 * Validates passwords, creates pending user, sends verification email.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/UserManagement.php';
require_once __DIR__ . '/../../lib/Mailer.php';
require_once __DIR__ . '/../../lib/Settings.php';
require_once __DIR__ . '/../../auth.php';

Application::init();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/users/create_flow/step_2.php');
}

if (empty($_SESSION['create_email'])) {
    redirect('/users/create_flow/step_1.php');
}

try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/users/create_flow/step_2.php');
}

$email    = $_SESSION['create_email'];
$password = $_POST['password']         ?? '';
$confirm  = $_POST['password_confirm'] ?? '';

// Validate passwords match
if ($password !== $confirm) {
    Flash::set('error', 'Passwords do not match. Please try again.');
    redirect('/users/create_flow/step_2.php');
}

// Validate password strength
$pwError = UserManagement::validatePassword($password, $email);
if ($pwError !== null) {
    Flash::set('error', $pwError);
    redirect('/users/create_flow/step_2.php');
}

$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    // Check if a verified account already exists — try to log them in
    $existing = UserManagement::findUserByEmail($email);
    if ($existing && $existing['email_verified_at'] !== null) {
        // Account exists and is verified — attempt login with the submitted password
        $loggedIn = UserManagement::attemptLogin($email, $password);
        if ($loggedIn) {
            Auth::loginUser($loggedIn);
            Flash::set('info', 'You have been logged in.');
            // Clean up wizard session state
            unset($_SESSION['create_email']);
            redirect('/index.php');
        } else {
            Flash::set('error', 'An account with this email already exists. Please log in instead.');
            redirect('/login.php');
        }
    }

    $userId = UserManagement::createPendingUser($email, $passwordHash);
    $token  = UserManagement::getEmailVerifyToken($userId);

    // Send verification email
    $appUrl  = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    $verifyLink = $appUrl . '/users/create_flow/verify_email.php?token=' . urlencode($token ?? '');
    $siteTitle  = Settings::siteTitle();

    $subject = "Verify your email — $siteTitle";
    $html    = '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:520px;margin:40px auto;padding:0 20px;">'
             . '<h2 style="color:#2D1B69;">Almost there!</h2>'
             . '<p>Thanks for signing up for <strong>' . htmlspecialchars($siteTitle, ENT_QUOTES) . '</strong>.</p>'
             . '<p>Click the button below to verify your email address and activate your account:</p>'
             . '<p style="margin:32px 0;">'
             . '<a href="' . htmlspecialchars($verifyLink, ENT_QUOTES) . '" '
             . 'style="background:#038BFF;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-size:15px;">'
             . 'Verify Email Address</a></p>'
             . '<p style="color:#666;font-size:13px;">Or copy this link into your browser:<br>'
             . '<a href="' . htmlspecialchars($verifyLink, ENT_QUOTES) . '">' . htmlspecialchars($verifyLink, ENT_QUOTES) . '</a></p>'
             . '<p style="color:#999;font-size:12px;margin-top:32px;">If you did not create an account, you can ignore this email.</p>'
             . '</body></html>';

    send_email($email, $subject, $html);

} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/users/create_flow/step_2.php');
}

// Show "check your email" page
redirect('/users/create_flow/step_3.php');
