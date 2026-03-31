<?php
declare(strict_types=1);

/**
 * Edit profile page — update name and profile photo.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Files.php';
require_once __DIR__ . '/../auth.php';

Application::init();
Auth::requireLogin();

$user       = Auth::currentUser();
$photoUrl   = Files::profilePhotoUrl($user['photo_public_file_id'] ?? null);

$initials = strtoupper(
    substr($user['first_name'] ?? '', 0, 1) .
    substr($user['last_name']  ?? '', 0, 1)
);
if ($initials === '') $initials = strtoupper(substr($user['email'] ?? '', 0, 1));

$errorMsg   = Flash::get('error');
$successMsg = Flash::get('success');
$pageTitle  = 'Edit Profile';

ob_start();
?>
<div style="max-width:540px; margin:0 auto;">

  <div style="display:flex; align-items:center; gap:12px; margin-bottom:28px;">
    <a href="/profile/index.php" style="color:var(--text-secondary); font-size:14px;">← My Profile</a>
    <h1 style="font-family:var(--font-title); font-weight:200; font-size:1.5rem;">Edit Profile</h1>
  </div>

  <?php if ($errorMsg): ?><div class="flash flash--error"><?= e($errorMsg) ?></div><?php endif; ?>
  <?php if ($successMsg): ?><div class="flash flash--success"><?= e($successMsg) ?></div><?php endif; ?>

  <!-- Photo section -->
  <div style="background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:24px; margin-bottom:20px;">
    <h2 style="font-size:0.95rem; color:var(--text-secondary); margin-bottom:20px; font-weight:400;">Profile photo</h2>

    <div style="display:flex; align-items:center; gap:20px; margin-bottom:20px;">
      <?php if ($photoUrl !== ''): ?>
        <img src="<?= e($photoUrl) ?>" alt="Current profile photo" class="avatar avatar-lg">
      <?php else: ?>
        <div class="avatar-placeholder avatar-lg"><?= e($initials) ?></div>
      <?php endif; ?>
      <div>
        <p style="font-size:0.875rem; color:var(--text-secondary); margin-bottom:8px;">
          <?= $photoUrl !== '' ? 'Your current photo' : 'No photo set yet.' ?>
        </p>
        <button type="button" class="btn btn-secondary" style="font-size:13px; padding:8px 16px;"
                onclick="document.getElementById('photoFileInput').click()">
          <?= $photoUrl !== '' ? 'Change photo' : 'Upload photo' ?>
        </button>
      </div>
    </div>

    <!-- Photo crop UI (same pattern as wizard step 5) -->
    <div id="photoCropArea" style="display:none; text-align:center;">
      <div style="position:relative; width:200px; height:200px; margin:0 auto 16px;
                  border-radius:50%; overflow:hidden; background:var(--border); border:2px solid var(--border);">
        <canvas id="editCropCanvas" style="position:absolute; top:0; left:0; cursor:grab; touch-action:none;"></canvas>
      </div>
      <div style="display:flex; align-items:center; gap:10px; max-width:200px; margin:0 auto 16px;">
        <label style="font-size:12px; color:var(--text-muted); white-space:nowrap;">Zoom</label>
        <input type="range" id="editZoom" min="0.5" max="3" step="0.01" value="1"
               style="flex:1; padding:0; border:none; box-shadow:none; height:4px; accent-color:var(--accent-blue);">
      </div>
      <button type="button" class="btn btn-secondary" style="font-size:13px; margin-bottom:12px;"
              onclick="document.getElementById('photoFileInput').click()">Choose different photo</button>
    </div>

    <input type="file" id="photoFileInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;"
           onchange="handleEditPhoto(this)">

    <form method="POST" action="/profile/edit_photo_eval.php" id="editPhotoForm" style="display:none;">
      <?= csrf_input() ?>
      <input type="hidden" name="photo_data" id="editPhotoData">
      <button type="submit" class="btn btn-primary" style="font-size:14px;">Save Photo</button>
    </form>
  </div>

  <!-- Profile fields -->
  <div style="background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:24px;">
    <h2 style="font-size:0.95rem; color:var(--text-secondary); margin-bottom:20px; font-weight:400;">Personal information</h2>

    <form method="POST" action="/profile/edit_eval.php" novalidate>
      <?= csrf_input() ?>

      <div class="form-group">
        <label for="first_name">First name</label>
        <input type="text" id="first_name" name="first_name"
               value="<?= e($user['first_name'] ?? '') ?>"
               placeholder="First name" autocomplete="given-name" required>
      </div>

      <div class="form-group">
        <label for="last_name">Last name</label>
        <input type="text" id="last_name" name="last_name"
               value="<?= e($user['last_name'] ?? '') ?>"
               placeholder="Last name" autocomplete="family-name" required>
      </div>

      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" value="<?= e($user['email'] ?? '') ?>"
               disabled style="background:var(--bg); color:var(--text-muted); cursor:not-allowed;">
        <small style="color:var(--text-muted); font-size:12px;">Email address cannot be changed.</small>
      </div>

      <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
  </div>

</div>

<script>
// ─── Photo crop (same logic as step 5, 200px canvas) ─────────────────────
let eImg = null, eScale = 1, eOffX = 0, eOffY = 0, eDrag = false, eLX = 0, eLY = 0;
const EC = 200;
const eCanvas = document.getElementById('editCropCanvas');
const eCtx    = eCanvas.getContext('2d');
const eZoom   = document.getElementById('editZoom');
eCanvas.width = eCanvas.height = EC;

function handleEditPhoto(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = (ev) => {
    eImg = new Image();
    eImg.onload = () => {
      eScale = Math.max(EC / eImg.width, EC / eImg.height);
      eOffX = (EC - eImg.width  * eScale) / 2;
      eOffY = (EC - eImg.height * eScale) / 2;
      eZoom.value = eScale;
      eZoom.min   = Math.max(0.1, eScale * 0.5);
      eZoom.max   = eScale * 4;
      document.getElementById('photoCropArea').style.display = 'block';
      document.getElementById('editPhotoForm').style.display = 'block';
      drawEdit();
    };
    eImg.src = ev.target.result;
  };
  reader.readAsDataURL(file);
}

function drawEdit() {
  eCtx.clearRect(0, 0, EC, EC);
  if (!eImg) return;
  eCtx.drawImage(eImg, eOffX, eOffY, eImg.width * eScale, eImg.height * eScale);
}

eZoom.addEventListener('input', () => {
  const ns = parseFloat(eZoom.value);
  eOffX = EC/2 - (EC/2 - eOffX) * (ns / eScale);
  eOffY = EC/2 - (EC/2 - eOffY) * (ns / eScale);
  eScale = ns; clampE(); drawEdit();
});

eCanvas.addEventListener('mousedown',  (e) => { eDrag=true; eLX=e.clientX; eLY=e.clientY; });
eCanvas.addEventListener('mousemove',  (e) => {
  if (!eDrag) return;
  eOffX += e.clientX - eLX; eOffY += e.clientY - eLY;
  eLX = e.clientX; eLY = e.clientY; clampE(); drawEdit();
});
eCanvas.addEventListener('mouseup',    () => eDrag=false);
eCanvas.addEventListener('mouseleave', () => eDrag=false);

function clampE() {
  if (!eImg) return;
  eOffX = Math.min(0, Math.max(eOffX, EC - eImg.width  * eScale));
  eOffY = Math.min(0, Math.max(eOffY, EC - eImg.height * eScale));
}

document.getElementById('editPhotoForm').addEventListener('submit', (ev) => {
  if (!eImg) { ev.preventDefault(); return; }
  document.getElementById('editPhotoData').value = eCanvas.toDataURL('image/jpeg', 0.9);
});
</script>
<?php
$content = ob_get_clean();
$activeSidebar = 'profile';
include __DIR__ . '/../templates/layout.php';
