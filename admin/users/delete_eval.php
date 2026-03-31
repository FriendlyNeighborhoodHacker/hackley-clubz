<?php
declare(strict_types=1);

/**
 * POST handler — permanently deletes a user account (admin only).
 *
 * Expects POST fields:
 *   user_id   int   The ID of the user to delete
 *   csrf_token       Standard CSRF token
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/UserManagement.php';

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

$ctx = UserContext::getLoggedInUserContext();

try {
    UserManagement::deleteUser($ctx, $targetId);
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/admin/users/edit.php?id=' . $targetId);
}

Flash::set('success', 'User account has been permanently deleted.');
redirect('/admin/users/index.php');
