<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/UserManagement.php';
require_once __DIR__ . '/../../lib/Files.php';
require_once __DIR__ . '/../../lib/AdminUI.php';

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

  <?= AdminUI::adminSubnav('users') ?>

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
    <h2>Users</h2>
    <div style="color:var(--text-secondary);font-size:0.875rem;">
      <?= number_format($total) ?> total &middot; Page <?= $qPage ?> of <?= $totalPages ?>
    </div>
  </div>

  <div class="table-wrap">
    <table class="log-table">
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
            <!-- Square avatar -->
            <td style="padding:8px 10px; width:76px; min-width:76px; vertical-align:middle;">
              <?php if ($photoUrl !== ''): ?>
                <img src="<?= e($photoUrl) ?>" class="avatar"
                     alt="" style="width:60px; height:60px;">
              <?php else: ?>
                <div class="avatar-placeholder"
                     style="width:60px; height:60px; font-size:20px;"><?= e($initials) ?></div>
              <?php endif; ?>
            </td>

            <!-- Stacked info: name, badges, email -->
            <td style="vertical-align:middle;">
              <div>
                <a href="/admin/users/edit.php?id=<?= (int)$u['id'] ?>"
                   style="color:var(--text-primary); font-weight:600; font-size:0.95rem;">
                  <?= e($fullName) ?>
                </a>
                <?php if (!empty($u['is_admin'])): ?>
                  <span class="action-badge" style="color:var(--purple-mid); margin-left:6px;">App Admin</span>
                <?php endif; ?>
                <?php if (($u['user_type'] ?? '') === 'adult'): ?>
                  <span class="action-badge" style="color:var(--coral); margin-left:6px;">Adult</span>
                <?php endif; ?>
              </div>
              <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
                <?= e($u['email'] ?? '') ?>
              </div>
            </td>

            <!-- Edit button only -->
            <td style="white-space:nowrap; vertical-align:middle; text-align:right;">
              <a href="/admin/users/edit.php?id=<?= (int)$u['id'] ?>"
                 class="btn btn-secondary" style="font-size:12px; padding:5px 12px;">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($users)): ?>
          <tr><td colspan="3" style="padding:32px;text-align:center;color:var(--text-muted);">No users found.</td></tr>
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
