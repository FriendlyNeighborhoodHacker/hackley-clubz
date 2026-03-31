<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/UserManagement.php';
require_once __DIR__ . '/../../lib/Files.php';

Application::init();
Auth::requireAdmin();

$targetId = (int)($_GET['id'] ?? 0);
if ($targetId <= 0) {
    Flash::set('error', 'Invalid user ID.');
    redirect('/admin/users/index.php');
}

$targetUser = UserManagement::findUserById($targetId);
if (!$targetUser) {
    Flash::set('error', 'User not found.');
    redirect('/admin/users/index.php');
}

$photoUrl = Files::profilePhotoUrl($targetUser['photo_public_file_id'] ?? null);
$initials = strtoupper(
    substr($targetUser['first_name'] ?? '', 0, 1) .
    substr($targetUser['last_name']  ?? '', 0, 1)
);
if ($initials === '') $initials = strtoupper(substr($targetUser['email'] ?? '', 0, 1));

$fullName = trim(($targetUser['first_name'] ?? '') . ' ' . ($targetUser['last_name'] ?? ''));
if ($fullName === '') $fullName = $targetUser['email'];

$pageTitle     = 'Edit User — ' . $fullName;
$activeSidebar = 'admin';

ob_start();
?>
<div class="admin-page" style="max-width:640px;">

  <div class="admin-subnav">
    <a href="/admin/settings.php">Settings</a>
    <a href="/admin/activity_log.php">Activity Log</a>
    <a href="/admin/email_log.php">Email Log</a>
    <a href="/admin/users/index.php" class="active">Users</a>
  </div>

  <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
    <a href="/admin/users/index.php" style="color:var(--text-secondary);font-size:14px;">← Users</a>
    <h2 style="font-size:1.3rem;"><?= e($fullName) ?></h2>
    <?php if (!empty($targetUser['is_admin'])): ?>
      <span class="action-badge" style="color:var(--purple-mid);">App Admin</span>
    <?php endif; ?>
  </div>

  <!-- ── Profile Photo ──────────────────────────────────────────────────── -->
  <div class="card" style="padding:24px;margin-bottom:20px;">
    <h3 style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:16px;font-weight:400;">Profile Photo</h3>

    <div style="display:flex;align-items:center;gap:20px;margin-bottom:16px;">
      <?php if ($photoUrl !== ''): ?>
        <img src="<?= e($photoUrl) ?>" class="avatar avatar-lg" alt="Current photo">
      <?php else: ?>
        <div class="avatar-placeholder avatar-lg"><?= e($initials) ?></div>
      <?php endif; ?>
      <div>
        <p style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:8px;">
          <?= $photoUrl !== '' ? 'Current profile photo' : 'No photo set.' ?>
        </p>
        <button type="button" class="btn btn-secondary" style="font-size:13px;padding:8px 16px;"
                onclick="document.getElementById('adminPhotoFileInput').click()">
          <?= $photoUrl !== '' ? 'Change photo' : 'Upload photo' ?>
        </button>
      </div>
    </div>

    <!-- Crop UI (same pattern as profile/edit.php) -->
    <div id="adminPhotoCropArea" style="display:none;text-align:center;">
      <div style="position:relative;width:200px;height:200px;margin:0 auto 16px;
                  border-radius:50%;overflow:hidden;background:var(--border);border:2px solid var(--border);">
        <canvas id="adminCropCanvas" style="position:absolute;top:0;left:0;cursor:grab;touch-action:none;"></canvas>
      </div>
      <div style="display:flex;align-items:center;gap:10px;max-width:200px;margin:0 auto 16px;">
        <label style="font-size:12px;color:var(--text-muted);white-space:nowrap;">Zoom</label>
        <input type="range" id="adminZoom" min="0.5" max="3" step="0.01" value="1"
               style="flex:1;padding:0;border:none;box-shadow:none;height:4px;accent-color:var(--accent-blue);">
      </div>
      <button type="button" class="btn btn-secondary" style="font-size:13px;margin-bottom:12px;"
              onclick="document.getElementById('adminPhotoFileInput').click()">Choose different photo</button>
    </div>

    <input type="file" id="adminPhotoFileInput" accept="image/jpeg,image/png,image/webp,image/gif"
           style="display:none;" onchange="handleAdminPhoto(this)">

    <form method="POST" action="/admin/users/edit_photo_eval.php" id="adminPhotoForm" style="display:none;">
      <?= csrf_input() ?>
      <input type="hidden" name="user_id" value="<?= $targetId ?>">
      <input type="hidden" name="photo_data" id="adminPhotoData">
      <button type="submit" class="btn btn-primary" style="font-size:14px;">Save Photo</button>
    </form>
  </div>

  <!-- ── Profile Fields ─────────────────────────────────────────────────── -->
  <div class="card" style="padding:24px;margin-bottom:20px;">
    <h3 style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:16px;font-weight:400;">Personal Information</h3>

    <form method="POST" action="/admin/users/edit_eval.php" novalidate>
      <?= csrf_input() ?>
      <input type="hidden" name="user_id" value="<?= $targetId ?>">

      <div class="form-group">
        <label for="first_name">First name</label>
        <input type="text" id="first_name" name="first_name"
               value="<?= e($targetUser['first_name'] ?? '') ?>"
               placeholder="First name" autocomplete="off">
      </div>

      <div class="form-group">
        <label for="last_name">Last name</label>
        <input type="text" id="last_name" name="last_name"
               value="<?= e($targetUser['last_name'] ?? '') ?>"
               placeholder="Last name" autocomplete="off">
      </div>

      <div class="form-group">
        <label for="phone">Phone</label>
        <input type="tel" id="phone" name="phone"
               value="<?= e($targetUser['phone'] ?? '') ?>"
               placeholder="(555) 555-5555">
      </div>

      <div class="form-group">
        <label for="email_display">Email</label>
        <input type="email" id="email_display" value="<?= e($targetUser['email'] ?? '') ?>"
               disabled style="background:var(--bg);color:var(--text-muted);cursor:not-allowed;">
        <small style="color:var(--text-muted);font-size:12px;">Email cannot be changed here.</small>
      </div>

      <div class="form-group">
        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
          <input type="checkbox" name="is_admin" value="1"
                 <?= !empty($targetUser['is_admin']) ? 'checked' : '' ?>
                 style="width:auto;accent-color:var(--accent-blue);">
          <span>App Admin</span>
        </label>
        <small style="color:var(--text-muted);font-size:12px;display:block;margin-top:4px;">
          Grants access to admin pages (settings, logs, user management).
        </small>
      </div>

      <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
  </div>

  <!-- ── Actions ────────────────────────────────────────────────────────── -->
  <div class="card" style="padding:24px;">
    <h3 style="font-size:0.9rem;color:var(--text-secondary);margin-bottom:16px;font-weight:400;">Actions</h3>

    <div style="display:flex;flex-direction:column;gap:12px;">

      <!-- Send password reset email -->
      <?php if ($targetUser['email_verified_at'] !== null): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;
                    border:1px solid var(--border);border-radius:var(--radius-sm);">
          <div>
            <strong style="font-size:0.875rem;">Send Password Reset Email</strong>
            <p style="font-size:0.8rem;color:var(--text-muted);margin-top:2px;">
              Sends a reset link to <?= e($targetUser['email'] ?? '') ?>
            </p>
          </div>
          <form method="POST" action="/admin/users/send_password_reset_eval.php"
                onsubmit="return confirm('Send a password reset email to <?= e(addslashes($targetUser['email'] ?? '')) ?>?')">
            <?= csrf_input() ?>
            <input type="hidden" name="user_id" value="<?= $targetId ?>">
            <button type="submit" class="btn btn-secondary" style="font-size:13px;padding:8px 16px;">
              Send Email
            </button>
          </form>
        </div>
      <?php else: ?>
        <div style="padding:14px 16px;border:1px solid var(--border);border-radius:var(--radius-sm);
                    color:var(--text-muted);font-size:0.875rem;">
          Password reset is unavailable — this user has not verified their email address.
        </div>
      <?php endif; ?>

      <!-- Account status -->
      <div style="padding:14px 16px;border:1px solid var(--border);border-radius:var(--radius-sm);">
        <strong style="font-size:0.875rem;">Account Status</strong>
        <div style="margin-top:6px;font-size:0.8rem;color:var(--text-secondary);display:flex;gap:16px;flex-wrap:wrap;">
          <span>User type: <strong><?= e($targetUser['user_type'] ?? 'adult') ?></strong></span>
          <span>
            Email:
            <?php if ($targetUser['email_verified_at'] !== null): ?>
              <strong class="status-success">Verified</strong>
              <span style="color:var(--text-muted);"> <?= e(substr((string)$targetUser['email_verified_at'], 0, 10)) ?></span>
            <?php else: ?>
              <strong class="status-failed">Unverified</strong>
            <?php endif; ?>
          </span>
          <span>Joined: <?= e(substr((string)($targetUser['created_at'] ?? ''), 0, 10)) ?></span>
        </div>
      </div>

      <!-- Delete user -->
      <?php
        $loggedInCtx = UserContext::getLoggedInUserContext();
        $isSelf      = ($loggedInCtx && $loggedInCtx->id === $targetId);
      ?>
      <?php if (!$isSelf): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;
                    border:1px solid var(--error);border-radius:var(--radius-sm);background:var(--error-bg);">
          <div>
            <strong style="font-size:0.875rem;color:var(--error);">Delete Account</strong>
            <p style="font-size:0.8rem;color:var(--text-muted);margin-top:2px;">
              Permanently removes this user and all their data. This cannot be undone.
            </p>
          </div>
          <form method="POST" action="/admin/users/delete_eval.php"
                onsubmit="return confirm('Permanently delete the account for <?= e(addslashes($targetUser['email'] ?? '')) ?>?\n\nThis cannot be undone.')">
            <?= csrf_input() ?>
            <input type="hidden" name="user_id" value="<?= $targetId ?>">
            <button type="submit" class="btn btn-danger" style="font-size:13px;padding:8px 16px;white-space:nowrap;">
              Delete User
            </button>
          </form>
        </div>
      <?php else: ?>
        <div style="padding:14px 16px;border:1px solid var(--border);border-radius:var(--radius-sm);
                    color:var(--text-muted);font-size:0.875rem;">
          You cannot delete your own account.
        </div>
      <?php endif; ?>

    </div>
  </div>

</div><!-- .admin-page -->

<script>
// ── Photo crop (same logic as profile/edit.php) ────────────────────────────
let aImg = null, aScale = 1, aOffX = 0, aOffY = 0, aDrag = false, aLX = 0, aLY = 0;
const AC = 200;
const aCanvas = document.getElementById('adminCropCanvas');
const aCtx    = aCanvas.getContext('2d');
const aZoom   = document.getElementById('adminZoom');
aCanvas.width = aCanvas.height = AC;

function handleAdminPhoto(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = (ev) => {
    aImg = new Image();
    aImg.onload = () => {
      aScale = Math.max(AC / aImg.width, AC / aImg.height);
      aOffX  = (AC - aImg.width  * aScale) / 2;
      aOffY  = (AC - aImg.height * aScale) / 2;
      aZoom.value = aScale;
      aZoom.min   = Math.max(0.1, aScale * 0.5);
      aZoom.max   = aScale * 4;
      document.getElementById('adminPhotoCropArea').style.display = 'block';
      document.getElementById('adminPhotoForm').style.display     = 'block';
      drawAdmin();
    };
    aImg.src = ev.target.result;
  };
  reader.readAsDataURL(file);
}

function drawAdmin() {
  aCtx.clearRect(0, 0, AC, AC);
  if (!aImg) return;
  aCtx.drawImage(aImg, aOffX, aOffY, aImg.width * aScale, aImg.height * aScale);
}

aZoom.addEventListener('input', () => {
  const ns = parseFloat(aZoom.value);
  aOffX = AC/2 - (AC/2 - aOffX) * (ns / aScale);
  aOffY = AC/2 - (AC/2 - aOffY) * (ns / aScale);
  aScale = ns; clampA(); drawAdmin();
});

aCanvas.addEventListener('mousedown',  e => { aDrag=true;  aLX=e.clientX; aLY=e.clientY; });
aCanvas.addEventListener('mousemove',  e => {
  if (!aDrag) return;
  aOffX += e.clientX - aLX; aOffY += e.clientY - aLY;
  aLX = e.clientX; aLY = e.clientY; clampA(); drawAdmin();
});
aCanvas.addEventListener('mouseup',    () => aDrag=false);
aCanvas.addEventListener('mouseleave', () => aDrag=false);

function clampA() {
  if (!aImg) return;
  aOffX = Math.min(0, Math.max(aOffX, AC - aImg.width  * aScale));
  aOffY = Math.min(0, Math.max(aOffY, AC - aImg.height * aScale));
}

document.getElementById('adminPhotoForm').addEventListener('submit', ev => {
  if (!aImg) { ev.preventDefault(); return; }
  document.getElementById('adminPhotoData').value = aCanvas.toDataURL('image/jpeg', 0.9);
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/layout.php';
