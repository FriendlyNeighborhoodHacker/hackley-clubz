<?php
declare(strict_types=1);

/**
 * Account creation wizard — Step 5: Add a profile photo.
 * Uses an in-browser crop/zoom tool (pure JS + Canvas, no external libraries).
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Settings.php';
require_once __DIR__ . '/../../lib/Files.php';
require_once __DIR__ . '/../../auth.php';

Application::init();
Auth::requireLogin();

$siteTitle  = Settings::siteTitle();
$logoFileId = Settings::siteLogoFileId();
$logoUrl    = $logoFileId ? Files::publicFileUrl($logoFileId) : '';

$errorMsg   = Flash::get('error');
$user       = Auth::currentUser();
$firstName  = $user['first_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add a Profile Photo — <?= e($siteTitle) ?></title>
  <link rel="stylesheet" href="/public/css/app.css">
  <style>
    .cropper-container {
      position: relative;
      width: 280px;
      height: 280px;
      margin: 0 auto 20px;
      border-radius: 50%;
      overflow: hidden;
      background: var(--border);
      border: 3px solid var(--border);
    }
    .cropper-canvas {
      position: absolute;
      top: 0; left: 0;
      cursor: grab;
      touch-action: none;
    }
    .cropper-canvas:active { cursor: grabbing; }
    .crop-controls {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 0 auto 20px;
      max-width: 280px;
    }
    .crop-controls label { font-size: 13px; color: var(--text-muted); white-space: nowrap; }
    .crop-controls input[type=range] {
      flex: 1;
      padding: 0;
      border: none;
      box-shadow: none;
      height: 4px;
      accent-color: var(--accent-blue);
    }
    #choosePhotoBtn { display: none; }
  </style>
</head>
<body>

<div class="auth-page">
  <div class="auth-card">

    <!-- Logo -->
    <div class="auth-logo">
      <?php if ($logoUrl !== ''): ?>
        <img src="<?= e($logoUrl) ?>" alt="<?= e($siteTitle) ?> logo">
      <?php else: ?>
        <span class="app-name"><?= e($siteTitle) ?></span>
      <?php endif; ?>
    </div>

    <!-- Step indicator (step 4 of 5) -->
    <div class="wizard-steps" aria-label="Step 4 of 5">
      <div class="wizard-step done"   title="Step 1: Email"></div>
      <div class="wizard-step done"   title="Step 2: Password"></div>
      <div class="wizard-step done"   title="Step 3: Your name"></div>
      <div class="wizard-step active" title="Step 4: Profile photo"></div>
      <div class="wizard-step"        title="Step 5: Phone number"></div>
    </div>

    <?php if ($firstName !== ''): ?>
      <p class="prompt"><em>Add a profile photo,</em> <?= e($firstName) ?>.</p>
    <?php else: ?>
      <p class="prompt"><em>Add a profile photo.</em></p>
    <?php endif; ?>

    <?php if ($errorMsg): ?>
      <div class="flash flash--error mt-4"><?= e($errorMsg) ?></div>
    <?php endif; ?>

    <!-- Photo upload / crop area -->
    <div id="uploadArea" class="photo-upload-area mt-6"
         onclick="document.getElementById('choosePhotoBtn').click()"
         ondragover="handleDragOver(event)"
         ondrop="handleDrop(event)">
      <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
           style="margin: 0 auto 12px; color: var(--text-muted);">
        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
        <circle cx="8.5" cy="8.5" r="1.5"/>
        <polyline points="21 15 16 10 5 21"/>
      </svg>
      <p>Click to choose a photo, or drag and drop here.</p>
      <p style="font-size:12px; color:var(--text-muted); margin-top:6px;">JPEG, PNG, WebP up to 8 MB</p>
    </div>

    <!-- Cropper (hidden until image chosen) -->
    <div id="cropperSection" style="display:none; text-align:center;">
      <div class="cropper-container" id="cropperContainer">
        <canvas id="cropCanvas" class="cropper-canvas"></canvas>
      </div>

      <div class="crop-controls">
        <label for="zoomRange">Zoom</label>
        <input type="range" id="zoomRange" min="0.5" max="3" step="0.01" value="1">
      </div>

      <button type="button" class="btn btn-secondary" style="margin-bottom:12px; font-size:13px;"
              onclick="document.getElementById('choosePhotoBtn').click()">
        Choose a different photo
      </button>
    </div>

    <!-- Hidden file input -->
    <input type="file" id="choosePhotoBtn" accept="image/jpeg,image/png,image/webp,image/gif"
           onchange="handleFileChosen(this)">

    <!-- Form to submit cropped image data -->
    <form method="POST" action="/users/create_flow/step_5_eval.php" id="photoForm" style="display:none;">
      <?= csrf_input() ?>
      <input type="hidden" name="photo_data" id="photoData">
      <button type="submit" class="btn btn-primary btn-block" id="savePhotoBtn">
        Save Photo &amp; Continue
      </button>
    </form>

    <a href="/users/create_flow/step_6.php" class="skip-link mt-4">Skip for now →</a>

  </div>
</div>

<script>
// ─── State ────────────────────────────────────────────────────────────────
let img      = null;   // loaded Image element
let scale    = 1;
let offsetX  = 0;
let offsetY  = 0;
let dragging = false;
let lastX    = 0;
let lastY    = 0;
const SIZE   = 280;    // canvas/circle diameter in px

const canvas   = document.getElementById('cropCanvas');
const ctx      = canvas.getContext('2d');
const zoomEl   = document.getElementById('zoomRange');

canvas.width  = SIZE;
canvas.height = SIZE;

// ─── File chooser handlers ─────────────────────────────────────────────────
function handleFileChosen(input) {
  const file = input.files[0];
  if (!file) return;
  loadImageFile(file);
}

function handleDragOver(e) {
  e.preventDefault();
  e.currentTarget.classList.add('drag-over');
}

function handleDrop(e) {
  e.preventDefault();
  e.currentTarget.classList.remove('drag-over');
  const file = e.dataTransfer.files[0];
  if (file && file.type.startsWith('image/')) loadImageFile(file);
}

function loadImageFile(file) {
  const reader = new FileReader();
  reader.onload = (ev) => {
    img = new Image();
    img.onload = () => {
      // Fit image to fill circle
      scale = Math.max(SIZE / img.width, SIZE / img.height);
      offsetX = (SIZE - img.width  * scale) / 2;
      offsetY = (SIZE - img.height * scale) / 2;
      zoomEl.value = scale;
      zoomEl.min   = Math.max(0.1, Math.min(scale * 0.5, 0.5));
      zoomEl.max   = scale * 4;
      showCropper();
      drawCanvas();
    };
    img.src = ev.target.result;
  };
  reader.readAsDataURL(file);
}

function showCropper() {
  document.getElementById('uploadArea').style.display   = 'none';
  document.getElementById('cropperSection').style.display = 'block';
  document.getElementById('photoForm').style.display    = 'block';
}

// ─── Draw ──────────────────────────────────────────────────────────────────
function drawCanvas() {
  ctx.clearRect(0, 0, SIZE, SIZE);
  if (!img) return;
  ctx.drawImage(img, offsetX, offsetY, img.width * scale, img.height * scale);
}

// ─── Zoom ──────────────────────────────────────────────────────────────────
zoomEl.addEventListener('input', () => {
  const newScale = parseFloat(zoomEl.value);
  // Keep centre in place
  const cx = SIZE / 2;
  const cy = SIZE / 2;
  offsetX = cx - (cx - offsetX) * (newScale / scale);
  offsetY = cy - (cy - offsetY) * (newScale / scale);
  scale = newScale;
  clampOffset();
  drawCanvas();
});

// ─── Drag to pan ──────────────────────────────────────────────────────────
canvas.addEventListener('mousedown',  (e) => { dragging = true; lastX = e.clientX; lastY = e.clientY; });
canvas.addEventListener('mousemove',  (e) => {
  if (!dragging) return;
  offsetX += e.clientX - lastX;
  offsetY += e.clientY - lastY;
  lastX = e.clientX; lastY = e.clientY;
  clampOffset();
  drawCanvas();
});
canvas.addEventListener('mouseup',   () => dragging = false);
canvas.addEventListener('mouseleave',() => dragging = false);

// Touch support
canvas.addEventListener('touchstart', (e) => {
  if (e.touches.length === 1) {
    dragging = true;
    lastX = e.touches[0].clientX;
    lastY = e.touches[0].clientY;
  }
}, { passive: true });
canvas.addEventListener('touchmove', (e) => {
  if (!dragging || e.touches.length !== 1) return;
  offsetX += e.touches[0].clientX - lastX;
  offsetY += e.touches[0].clientY - lastY;
  lastX = e.touches[0].clientX;
  lastY = e.touches[0].clientY;
  clampOffset();
  drawCanvas();
}, { passive: true });
canvas.addEventListener('touchend', () => dragging = false);

function clampOffset() {
  if (!img) return;
  const w = img.width  * scale;
  const h = img.height * scale;
  // Don't allow image to leave the circle boundary
  offsetX = Math.min(0, Math.max(offsetX, SIZE - w));
  offsetY = Math.min(0, Math.max(offsetY, SIZE - h));
}

// ─── Form submission ────────────────────────────────────────────────────────
document.getElementById('photoForm').addEventListener('submit', (e) => {
  // Export the canvas content as JPEG base64
  const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
  document.getElementById('photoData').value = dataUrl;
  // Basic validation
  if (!img) {
    e.preventDefault();
    alert('Please choose a photo first.');
  }
});
</script>

</body>
</html>
