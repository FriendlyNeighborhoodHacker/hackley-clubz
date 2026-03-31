<?php
declare(strict_types=1);

/**
 * Password reset evaluation — validates token and new password, applies change.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/UserManagement.php';
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

$token    = trim($_POST['token']            ?? '');
$password = $_POST['password']              ?? '';
$confirm  = $_POST['password_confirm']      ?? '';

if ($token === '') {
    Flash::set('error', 'Invalid reset token. Please request a new link.');
    redirect('/users/forgot_password.php');
}

// Validate the token is still valid before we do password checks
$user = UserManagement::findUserByResetToken($token);
if (!$user) {
    Flash::set('error', 'This password reset link is invalid or has expired.');
    redirect('/users/forgot_password.php');
}

$resetUrl = '/users/reset_password.php?token=' . urlencode($token);

if ($password !== $confirm) {
    Flash::set('error', 'Passwords do not match.');
    redirect($resetUrl);
}

$pwError = UserManagement::validatePassword($password, (string)$user['email']);
if ($pwError !== null) {
    Flash::set('error', $pwError);
    redirect($resetUrl);
}

$newHash = password_hash($password, PASSWORD_DEFAULT);

try {
    UserManagement::resetPassword($token, $newHash);
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/users/forgot_password.php');
}

// Log the user in after a successful reset
Auth::loginUser($user);
Flash::set('success', 'Your password has been updated. Welcome back!');
redirect('/index.php');
