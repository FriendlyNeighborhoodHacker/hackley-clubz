<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/Settings.php';

Application::init();
Auth::requireAdmin();

$errorMsg = Flash::get('error');

$pageTitle     = 'Add Club';
$activeSidebar = 'admin';

ob_start();
?>
<style>
  /* Circular crop widget (reused from profile edit) */
  .crop-circle-wrap {
    position:relative; width:200px; height:200px; margin:0 auto 16px;
    border-radius:50%; overflow:hidden;
    background:var(--border); border:2px solid var(--border);
  }
  .crop-canvas { position:absolute; top:0; left:0; cursor:grab; touch-action:none; }
  .crop-canvas:active { cursor:grabbing; }
  .zoom-row {
    display:flex; align-items:center; gap:10px;
    max-width:200px; margin:0 auto 16px;
  }
  .zoom-row label { font-size:12px; color:var(--text-muted); white-space:nowrap; }
  .zoom-row input[type=range] {
    flex:1; padding:0; border:none; box-shadow:none; height:4px; accent-color:var(--accent-blue);
  }
  /* Hero crop (rectangular) */
  .hero-crop-wrap {
    position:relative; width:100%; margin:0 auto 16px;
    border-radius:var(--radius-sm); overflow:hidden;
    background:var(--border); border:2px solid var(--border);
    aspect-ratio: 3 / 1;
  }
  .hero-crop-wrap canvas { display:block; width:100%; height:100%; cursor:grab; touch-action:none; }
  .hero-crop-wrap canvas:active { cursor:grabbing; }
</style>

<div style="max-width:640px; margin:0 auto;">

  <div style="display:flex; align-items:center; gap:12px; margin-bottom:28px;">
    <a href="/admin/clubs/index.php" style="color:var(--text-secondary); font-size:14px;">← Clubs</a>
    <h1 style="font-family:var(--font-title); font-weight:200; font-size:1.5rem;">Add Club</h1>
  </div>

  <?php if ($errorMsg): ?>
    <div class="flash flash--error"><?= e($errorMsg) ?></div>
  <?php endif; ?>

  <form method="POST" action="/admin/clubs/add_eval.php" novalidate>
    <?= csrf_input() ?>

    <!-- ── Basic info ── -->
    <div class="card" style="background:var(--surface); border:1px solid var(--border);
                              border-radius:var(--radius); padding:24px; margin-bottom:20px;">
      <h2 style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:20px; font-weight:400;">
        Club details
      </h2>

      <div class="form-group">
        <label for="name">Club Name <span style="color:var(--error);">*</span></label>
        <input type="text" id="name" name="name" placeholder="e.g. Chess Club"
               value="<?= e($_POST['name'] ?? '') ?>" required autofocus>
      </div>

      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="4"
                  placeholder="What is this club about?"><?= e($_POST['description'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label for="meets">Meeting time &amp; place</label>
        <input type="text" id="meets" name="meets"
               placeholder="e.g. Tuesdays 3:30pm, Room 214"
               value="<?= e($_POST['meets'] ?? '') ?>">
        <small style="color:var(--text-muted); font-size:12px; margin-top:4px; display:block;">
          Free-form text shown to members in the club panel.
        </small>
      </div>

      <div class="form-group" style="display:flex; align-items:center; gap:10px;">
        <input type="checkbox" id="is_secret" name="is_secret" value="1"
               style="width:auto; padding:0;"
               <?= !empty($_POST['is_secret']) ? 'checked' : '' ?>>
        <label for="is_secret" style="margin:0; cursor:pointer;">
          Secret club <small style="color:var(--text-muted);">(hidden from the public club browser)</small>
        </label>
      </div>
    </div>

    <!-- ── Profile photo ── -->
    <div class="card" style="background:var(--surface); border:1px solid var(--border);
                              border-radius:var(--radius); padding:24px; margin-bottom:20px;">
      <h2 style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:4px; font-weight:400;">
        Club profile photo
      </h2>
      <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:16px;">
        Shown as a circle in the sidebar and member lists.
      </p>

      <div id="photoUploadArea" class="photo-upload-area" style="text-align:center; cursor:pointer; margin-bottom:12px;"
           onclick="document.getElementById('photoFileInput').click()">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
             style="margin:0 auto 8px; color:var(--text-muted);">
          <rect x="3" y="3" width="18" height="18" rx="2"/>
          <circle cx="8.5" cy="8.5" r="1.5"/>
          <polyline points="21 15 16 10 5 21"/>
        </svg>
        <p style="font-size:0.85rem; color:var(--text-secondary);">Click to choose a photo</p>
        <p style="font-size:11px; color:var(--text-muted); margin-top:4px;">JPEG, PNG, WebP</p>
      </div>

      <div id="photoCropSection" style="display:none; text-align:center;">
        <div class="crop-circle-wrap">
          <canvas id="photoCropCanvas" class="crop-canvas"></canvas>
        </div>
        <div class="zoom-row">
          <label for="photoZoom">Zoom</label>
          <input type="range" id="photoZoom" min="0.5" max="3" step="0.01" value="1">
        </div>
        <button type="button" class="btn btn-secondary" style="font-size:13px; margin-bottom:8px;"
                onclick="document.getElementById('photoFileInput').click()">
          Choose different photo
        </button>
      </div>

      <input type="file" id="photoFileInput" accept="image/jpeg,image/png,image/webp"
             style="display:none;" onchange="initPhotoCrop(this)">
      <input type="hidden" name="photo_data" id="photoDataHidden">
    </div>

    <!-- ── Hero image ── -->
    <div class="card" style="background:var(--surface); border:1px solid var(--border);
                              border-radius:var(--radius); padding:24px; margin-bottom:28px;">
      <h2 style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:4px; font-weight:400;">
        Hero image
      </h2>
      <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:16px;">
        Wide banner (3:1) shown at the top of the club info page. Drag to reposition, zoom to adjust.
      </p>

      <div id="heroUploadArea" class="photo-upload-area"
           style="text-align:center; cursor:pointer; margin-bottom:12px;"
           onclick="document.getElementById('heroFileInput').click()">
        <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
             style="margin:0 auto 8px; color:var(--text-muted);">
          <rect x="3" y="3" width="18" height="18" rx="2"/>
          <circle cx="8.5" cy="8.5" r="1.5"/>
          <polyline points="21 15 16 10 5 21"/>
        </svg>
        <p style="font-size:0.85rem; color:var(--text-secondary);">Click to choose a hero image</p>
        <p style="font-size:11px; color:var(--text-muted); margin-top:4px;">JPEG, PNG, WebP — displayed at 3:1 ratio</p>
      </div>

      <div id="heroCropSection" style="display:none; text-align:center;">
        <div class="hero-crop-wrap">
          <canvas id="heroCropCanvas"></canvas>
        </div>
        <div class="zoom-row" style="max-width:100%;">
          <label for="heroZoom">Zoom</label>
          <input type="range" id="heroZoom" min="0.5" max="3" step="0.01" value="1">
        </div>
        <button type="button" class="btn btn-secondary" style="font-size:13px; margin-bottom:8px;"
                onclick="document.getElementById('heroFileInput').click()">
          Choose different image
        </button>
      </div>

      <input type="file" id="heroFileInput" accept="image/jpeg,image/png,image/webp"
             style="display:none;" onchange="initHeroCrop(this)">
      <input type="hidden" name="hero_data" id="heroDataHidden">
    </div>

    <div style="display:flex; gap:12px; justify-content:flex-end;">
      <a href="/admin/clubs/index.php" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Create Club</button>
    </div>
  </form>

</div>

<script>
// ── Profile photo circular crop ────────────────────────────────────────────
let pImg = null, pScale = 1, pOffX = 0, pOffY = 0, pDrag = false, pLX = 0, pLY = 0;
const PC = 200;
const pCanvas = document.getElementById('photoCropCanvas');
const pCtx    = pCanvas.getContext('2d');
const pZoom   = document.getElementById('photoZoom');
pCanvas.width = pCanvas.height = PC;

function initPhotoCrop(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    pImg = new Image();
    pImg.onload = () => {
      pScale = Math.max(PC / pImg.width, PC / pImg.height);
      pOffX  = (PC - pImg.width  * pScale) / 2;
      pOffY  = (PC - pImg.height * pScale) / 2;
      pZoom.value = pScale;
      pZoom.min   = Math.max(0.1, pScale * 0.5);
      pZoom.max   = pScale * 4;
      document.getElementById('photoUploadArea').style.display  = 'none';
      document.getElementById('photoCropSection').style.display = 'block';
      pDraw();
    };
    pImg.src = ev.target.result;
  };
  reader.readAsDataURL(file);
}

function pDraw() {
  pCtx.clearRect(0, 0, PC, PC);
  if (!pImg) return;
  pCtx.drawImage(pImg, pOffX, pOffY, pImg.width * pScale, pImg.height * pScale);
}

pZoom.addEventListener('input', () => {
  const ns = parseFloat(pZoom.value);
  pOffX = PC/2 - (PC/2 - pOffX) * (ns / pScale);
  pOffY = PC/2 - (PC/2 - pOffY) * (ns / pScale);
  pScale = ns; pClamp(); pDraw();
});

pCanvas.addEventListener('mousedown',  e => { pDrag=true; pLX=e.clientX; pLY=e.clientY; });
pCanvas.addEventListener('mousemove',  e => {
  if (!pDrag) return;
  pOffX += e.clientX-pLX; pOffY += e.clientY-pLY;
  pLX=e.clientX; pLY=e.clientY; pClamp(); pDraw();
});
pCanvas.addEventListener('mouseup',    () => pDrag=false);
pCanvas.addEventListener('mouseleave', () => pDrag=false);

function pClamp() {
  if (!pImg) return;
  pOffX = Math.min(0, Math.max(pOffX, PC - pImg.width  * pScale));
  pOffY = Math.min(0, Math.max(pOffY, PC - pImg.height * pScale));
}

// ── Hero image rectangular crop ───────────────────────────────────────────
let hImg = null, hScale = 1, hOffX = 0, hOffY = 0, hDrag = false, hLX = 0, hLY = 0;
const HW = 600, HH = 200;
const hCanvas = document.getElementById('heroCropCanvas');
const hCtx    = hCanvas.getContext('2d');
const hZoom   = document.getElementById('heroZoom');
hCanvas.width  = HW;
hCanvas.height = HH;

function initHeroCrop(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    hImg = new Image();
    hImg.onload = () => {
      hScale = Math.max(HW / hImg.width, HH / hImg.height);
      hOffX  = (HW - hImg.width  * hScale) / 2;
      hOffY  = (HH - hImg.height * hScale) / 2;
      hZoom.value = hScale;
      hZoom.min   = Math.max(0.1, hScale * 0.5);
      hZoom.max   = hScale * 4;
      document.getElementById('heroUploadArea').style.display  = 'none';
      document.getElementById('heroCropSection').style.display = 'block';
      hDraw();
    };
    hImg.src = ev.target.result;
  };
  reader.readAsDataURL(file);
}

function hDraw() {
  hCtx.clearRect(0, 0, HW, HH);
  if (!hImg) return;
  hCtx.drawImage(hImg, hOffX, hOffY, hImg.width * hScale, hImg.height * hScale);
}

hZoom.addEventListener('input', () => {
  const ns = parseFloat(hZoom.value);
  hOffX = HW/2 - (HW/2 - hOffX) * (ns / hScale);
  hOffY = HH/2 - (HH/2 - hOffY) * (ns / hScale);
  hScale = ns; hClamp(); hDraw();
});

hCanvas.addEventListener('mousedown', e => {
  hDrag = true; hLX = e.clientX; hLY = e.clientY;
});
hCanvas.addEventListener('mousemove', e => {
  if (!hDrag) return;
  const rect   = hCanvas.getBoundingClientRect();
  const scaleX = HW / rect.width;
  const scaleY = HH / rect.height;
  hOffX += (e.clientX - hLX) * scaleX;
  hOffY += (e.clientY - hLY) * scaleY;
  hLX = e.clientX; hLY = e.clientY;
  hClamp(); hDraw();
});
hCanvas.addEventListener('mouseup',    () => hDrag = false);
hCanvas.addEventListener('mouseleave', () => hDrag = false);

function hClamp() {
  if (!hImg) return;
  hOffX = Math.min(0, Math.max(hOffX, HW - hImg.width  * hScale));
  hOffY = Math.min(0, Math.max(hOffY, HH - hImg.height * hScale));
}

// Capture both canvases before submit
document.querySelector('form').addEventListener('submit', () => {
  if (pImg) document.getElementById('photoDataHidden').value = pCanvas.toDataURL('image/jpeg', 0.9);
  if (hImg) document.getElementById('heroDataHidden').value  = hCanvas.toDataURL('image/jpeg', 0.9);
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/layout.php';
