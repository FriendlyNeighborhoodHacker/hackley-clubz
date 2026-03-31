<?php
declare(strict_types=1);

/**
 * My Profile page — view profile details and access account actions.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Files.php';
require_once __DIR__ . '/../auth.php';

Application::init();
Auth::requireLogin();

$user       = Auth::currentUser();
$siteTitle  = Settings::siteTitle();
$photoUrl   = Files::profilePhotoUrl($user['photo_public_file_id'] ?? null);

// Initials for placeholder
$initials = strtoupper(
    substr($user['first_name'] ?? '', 0, 1) .
    substr($user['last_name']  ?? '', 0, 1)
);
if ($initials === '') $initials = strtoupper(substr($user['email'] ?? '', 0, 1));

$fullName   = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$userTypeLabel = ($user['user_type'] ?? '') === 'adult' ? 'Faculty' : 'Student';

$pageTitle = 'My Profile';

ob_start();
?>
<div style="max-width: 600px; margin: 0 auto;">

  <!-- Flash messages are rendered by layout.php -->

  <div style="display:flex; align-items:center; gap:24px; margin-bottom:32px;">
    <?php if ($photoUrl !== ''): ?>
      <img src="<?= e($photoUrl) ?>" alt="Profile photo" class="avatar avatar-xl">
    <?php else: ?>
      <div class="avatar-placeholder avatar-xl"><?= e($initials) ?></div>
    <?php endif; ?>

    <div>
      <h1 style="font-family:var(--font-title); font-weight:200; font-size:1.75rem; margin-bottom:4px;">
        <?= e($fullName ?: $user['email']) ?>
      </h1>
      <p style="color:var(--text-secondary); font-size:0.9rem;">
        <?= e($userTypeLabel) ?>
        <?php if (!empty($user['is_admin'])): ?>
          &nbsp;·&nbsp; <span style="color:var(--coral); font-size:0.85rem;">App Admin</span>
        <?php endif; ?>
      </p>
      <p style="color:var(--text-muted); font-size:0.875rem; margin-top:4px;"><?= e($user['email'] ?? '') ?></p>
    </div>
  </div>

  <!-- Profile Details Card -->
  <div style="background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:24px; margin-bottom:20px;">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
      <h2 style="font-size:1rem; font-weight:400; color:var(--text-secondary);">Account details</h2>
      <a href="/profile/edit.php" class="btn btn-secondary" style="padding:8px 16px; font-size:14px;">Edit Profile</a>
    </div>

    <dl style="display:grid; grid-template-columns:140px 1fr; gap:12px 16px; font-size:0.9rem;">
      <dt style="color:var(--text-muted);">First name</dt>
      <dd><?= e($user['first_name'] ?? '—') ?></dd>

      <dt style="color:var(--text-muted);">Last name</dt>
      <dd><?= e($user['last_name'] ?? '—') ?></dd>

      <dt style="color:var(--text-muted);">Email</dt>
      <dd><?= e($user['email'] ?? '') ?></dd>

      <?php if (!empty($user['phone'])): ?>
      <dt style="color:var(--text-muted);">Phone</dt>
      <dd><?= e($user['phone']) ?></dd>
      <?php endif; ?>

      <dt style="color:var(--text-muted);">Member since</dt>
      <dd><?= e(date('F j, Y', strtotime($user['created_at'] ?? 'now'))) ?></dd>
    </dl>
  </div>

  <!-- Actions -->
  <div style="display:flex; flex-direction:column; gap:12px;">
    <a href="/profile/edit.php" class="btn btn-secondary">Edit Profile &amp; Photo</a>
    <a href="/logout.php" class="btn btn-danger">Log Out</a>
  </div>

</div>
<?php
$content = ob_get_clean();
$activeSidebar = 'profile';
include __DIR__ . '/../templates/layout.php';
