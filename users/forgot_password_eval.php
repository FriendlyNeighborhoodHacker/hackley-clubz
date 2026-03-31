<?php
declare(strict_types=1);

/**
 * Forgot password evaluation — generates reset token and sends email.
 * Always redirects back with a neutral message (to avoid revealing whether
 * the email is registered).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/UserManagement.php';
require_once __DIR__ . '/../lib/Mailer.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../auth.php';

Application::init();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/users/forgot_password.php');
}

try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/users/forgot_password.php');
}

$email = strtolower(trim($_POST['email'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Flash::set('error', 'Please enter a valid email address.');
    redirect('/users/forgot_password.php');
}

// Always show a success message — don't reveal if the email exists
$neutralMessage = 'If an account exists for that email, you will receive a password reset link shortly.';

$plainToken = UserManagement::initiatePasswordReset($email);

if ($plainToken !== null) {
    $appUrl    = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
    $resetLink = $appUrl . '/users/reset_password.php?token=' . urlencode($plainToken);
    $siteName  = Settings::siteTitle();

    $subject = "Reset your password — $siteName";
    $html    = '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:520px;margin:40px auto;padding:0 20px;">'
             . '<h2 style="color:#2D1B69;">Password Reset</h2>'
             . '<p>We received a request to reset the password for your <strong>' . htmlspecialchars($siteName, ENT_QUOTES) . '</strong> account.</p>'
             . '<p>Click the button below to choose a new password. This link expires in 1 hour.</p>'
             . '<p style="margin:32px 0;">'
             . '<a href="' . htmlspecialchars($resetLink, ENT_QUOTES) . '" '
             . 'style="background:#038BFF;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-size:15px;">'
             . 'Reset Password</a></p>'
             . '<p style="color:#666;font-size:13px;">Or copy this link:<br>'
             . '<a href="' . htmlspecialchars($resetLink, ENT_QUOTES) . '">' . htmlspecialchars($resetLink, ENT_QUOTES) . '</a></p>'
             . '<p style="color:#999;font-size:12px;margin-top:32px;">If you did not request this, you can ignore this email. Your password will not change.</p>'
             . '</body></html>';

    send_email($email, $subject, $html);
}

Flash::set('success', $neutralMessage);
redirect('/users/forgot_password.php');
