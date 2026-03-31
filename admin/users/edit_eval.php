<?php
declare(strict_types=1);

/**
 * POST handler — saves first_name, last_name, phone, and is_admin
 * for a specific user (admin only).
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

$editUrl = '/admin/users/edit.php?id=' . $targetId;

$targetUser = UserManagement::findUserById($targetId);
if (!$targetUser) {
    Flash::set('error', 'User not found.');
    redirect('/admin/users/index.php');
}

$ctx = UserContext::getLoggedInUserContext();

try {
    // Update profile fields
    UserManagement::updateProfile($ctx, [
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name'  => trim($_POST['last_name']  ?? ''),
        'phone'      => trim($_POST['phone']      ?? ''),
    ], $targetId);

    // Update is_admin flag separately (not handled by updateProfile)
    $newIsAdmin = !empty($_POST['is_admin']) ? 1 : 0;
    if ((int)$targetUser['is_admin'] !== $newIsAdmin) {
        $st = pdo()->prepare('UPDATE users SET is_admin = :v WHERE id = :id');
        $st->bindValue(':v',  $newIsAdmin, \PDO::PARAM_INT);
        $st->bindValue(':id', $targetId,   \PDO::PARAM_INT);
        $st->execute();
        ActivityLog::log($ctx, 'admin_set_user_admin', [
            'target_user_id' => $targetId,
            'is_admin'       => $newIsAdmin,
        ]);
    }
} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect($editUrl);
}

Flash::set('success', 'User profile updated.');
redirect($editUrl);
