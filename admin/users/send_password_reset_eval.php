<?php
declare(strict_types=1);

/**
 * POST handler — sends a password reset email to a specific user (admin only).
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/UserManagement.php';
require_once __DIR__ . '/../../lib/Mailer.php';
require_once __DIR__ . '/../../lib/Settings.php';

Application::init();
Auth::requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/admin/users/index.php');
}

try {
    csrf_verify();
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/admin/users/index.php');
}

$targetId = (int)($_POST['user_id'] ?? 0);
if ($targetId <= 0) {
    Flash::set('error', 'Invalid user ID.');
    redirect('/admin/users/index.php');
}

$editUrl = '/admin/users/edit.php?id=' . $targetId;

$targetUser = UserManagement::findUserById($targetId);
if (!$targetUser) {
    Flash::set('error', 'User not found.');
    redirect('/admin/users/index.php');
}

if ($targetUser['email_verified_at'] === null) {
    Flash::set('error', 'Cannot send a password reset to an unverified account.');
    redirect($editUrl);
}

$email      = (string)$targetUser['email'];
$plainToken = UserManagement::initiatePasswordReset($email);

if ($plainToken === null) {
    Flash::set('error', 'Could not generate a reset token. The account may not be verified.');
    redirect($editUrl);
}

$appUrl    = defined('APP_URL') ? rtrim(APP_URL, '/') : '';
$resetLink = $appUrl . '/users/reset_password.php?token=' . urlencode($plainToken);
$siteName  = Settings::siteTitle();

$subject = "Reset your password — $siteName";
$html    = '<!DOCTYPE html><html><body style="font-family:sans-serif;max-width:520px;margin:40px auto;padding:0 20px;">'
         . '<h2 style="color:#2D1B69;">Password Reset</h2>'
         . '<p>An administrator has requested a password reset for your <strong>' . htmlspecialchars($siteName, ENT_QUOTES) . '</strong> account.</p>'
         . '<p>Click the button below to choose a new password. This link expires in 1 hour.</p>'
         . '<p style="margin:32px 0;">'
         . '<a href="' . htmlspecialchars($resetLink, ENT_QUOTES) . '" '
         . 'style="background:#038BFF;color:#fff;padding:14px 28px;border-radius:8px;text-decoration:none;font-size:15px;">'
         . 'Reset Password</a></p>'
         . '<p style="color:#666;font-size:13px;">Or copy this link:<br>'
         . '<a href="' . htmlspecialchars($resetLink, ENT_QUOTES) . '">' . htmlspecialchars($resetLink, ENT_QUOTES) . '</a></p>'
         . '<p style="color:#999;font-size:12px;margin-top:32px;">If you did not expect this email, please contact your administrator.</p>'
         . '</body></html>';

send_email($email, $subject, $html);

Flash::set('success', 'Password reset email sent to ' . $email . '.');
redirect($editUrl);
