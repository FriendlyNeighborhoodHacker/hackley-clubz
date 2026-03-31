<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/UserManagement.php';
require_once __DIR__ . '/../../lib/Files.php';

Application::init();
Auth::requireAdmin();

// ── Pagination ─────────────────────────────────────────────────────────────
$limitOptions = [25, 50, 100];
$qLimit = (int)($_GET['limit'] ?? 25);
if (!in_array($qLimit, $limitOptions, true)) $qLimit = 25;
$qPage  = max(1, (int)($_GET['page'] ?? 1));

$total      = UserManagement::countAllUsers();
$totalPages = max(1, (int)ceil($total / $qLimit));
if ($qPage > $totalPages) $qPage = $totalPages;
$offset     = ($qPage - 1) * $qLimit;

$users          = UserManagement::listAllUsers($qLimit, $offset);
$loggedInUserId = (UserContext::getLoggedInUserContext())->id;

function buildUsersUrl(array $overrides): string {
    $base = [
        'limit' => $_GET['limit'] ?? '',
        'page'  => $_GET['page']  ?? '',
    ];
    foreach ($overrides as $k => $v) $base[$k] = $v;
    $base = array_filter($base, fn($v) => $v !== '' && $v !== null);
    $qs = http_build_query($base);
    return '/admin/users/index.php' . ($qs ? '?' . $qs : '');
}

$pageTitle     = 'User Management';
$activeSidebar = 'admin';

ob_start();
?>
<div class="admin-page">

  <div class="admin-subnav">
    <a href="/admin/settings.php">Settings</a>
    <a href="/admin/activity_log.php">Activity Log</a>
    <a href="/admin/email_log.php">Email Log</a>
    <a href="/admin/users/index.php" class="active">Users</a>
  </div>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <h2>Users</h2>
    <div style="color:var(--text-secondary);font-size:0.875rem;">
      <?= number_format($total) ?> total &middot; Page <?= $qPage ?> of <?= $totalPages ?>
    </div>
  </div>

  <div class="table-wrap">
    <table class="log-table">
      <thead>
        <tr>
          <th style="width:44px;"></th>
          <th>Name</th>
          <th>Email</th>
          <th>Type</th>
          <th>Role</th>
          <th>Verified</th>
          <th>Joined</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <?php
            $photoUrl = Files::profilePhotoUrl($u['photo_public_file_id'] ?? null);
            $initials = strtoupper(
              substr($u['first_name'] ?? '', 0, 1) .
              substr($u['last_name']  ?? '', 0, 1)
            );
            if ($initials === '') $initials = strtoupper(substr($u['email'] ?? '', 0, 1));
            $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
            if ($fullName === '') $fullName = '(no name)';
          ?>
          <tr>
            <td style="padding:8px 10px;">
              <?php if ($photoUrl !== ''): ?>
                <img src="<?= e($photoUrl) ?>" class="avatar avatar-sm" alt="">
              <?php else: ?>
                <div class="avatar-placeholder avatar-sm" style="font-size:11px;"><?= e($initials) ?></div>
              <?php endif; ?>
            </td>
            <td>
              <a href="/admin/users/edit.php?id=<?= (int)$u['id'] ?>" style="color:var(--text-primary);font-weight:500;">
                <?= e($fullName) ?>
              </a>
            </td>
            <td class="log-ts"><?= e($u['email'] ?? '') ?></td>
            <td>
              <span class="action-badge" style="<?= ($u['user_type'] ?? '') === 'student' ? 'color:var(--accent-blue)' : '' ?>">
                <?= e($u['user_type'] ?? 'adult') ?>
              </span>
            </td>
            <td>
              <?php if (!empty($u['is_admin'])): ?>
                <span class="action-badge" style="color:var(--purple-mid);">App Admin</span>
              <?php else: ?>
                <span style="color:var(--text-muted);font-size:0.8rem;">—</span>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($u['email_verified_at'] !== null): ?>
                <span class="status-success" style="font-size:0.8rem;">✓ Verified</span>
              <?php else: ?>
                <span class="status-failed" style="font-size:0.8rem;">Unverified</span>
              <?php endif; ?>
            </td>
            <td class="log-ts"><?= e(substr((string)($u['created_at'] ?? ''), 0, 10)) ?></td>
            <td style="white-space:nowrap;display:flex;gap:6px;align-items:center;">
              <a href="/admin/users/edit.php?id=<?= (int)$u['id'] ?>" class="btn btn-secondary" style="font-size:12px;padding:5px 12px;">Edit</a>
              <?php if ((int)$u['id'] !== $loggedInUserId): ?>
                <form method="POST" action="/admin/users/delete_eval.php" style="margin:0;"
                      onsubmit="return confirm('Permanently delete <?= e(addslashes(trim(($u['first_name'] ?? '').' '.($u['last_name'] ?? '')) ?: ($u['email'] ?? 'this user'))) ?>?\n\nThis cannot be undone.')">
                  <?= csrf_input() ?>
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <button type="submit" class="btn btn-danger" style="font-size:12px;padding:5px 12px;">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
          <tr><td colspan="8" style="padding:24px;text-align:center;color:var(--text-muted);">No users found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Pagination -->
  <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;">
    <?php if ($qPage > 1): ?>
      <a href="<?= e(buildUsersUrl(['page' => $qPage - 1])) ?>" class="btn btn-secondary">← Prev</a>
    <?php else: ?>
      <span class="btn btn-secondary" style="opacity:.4;cursor:default;">← Prev</span>
    <?php endif; ?>
    <?php if ($qPage < $totalPages): ?>
      <a href="<?= e(buildUsersUrl(['page' => $qPage + 1])) ?>" class="btn btn-secondary">Next →</a>
    <?php else: ?>
      <span class="btn btn-secondary" style="opacity:.4;cursor:default;">Next →</span>
    <?php endif; ?>
  </div>

</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/layout.php';
