 
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/ClubManagement.php';
require_once __DIR__ . '/../../lib/Files.php';
require_once __DIR__ . '/../../lib/ApplicationUI.php';
require_once __DIR__ . '/../../lib/ClubUI.php';
require_once __DIR__ . '/../../lib/UserContext.php';

Application::init();
Auth::requireLogin();

$clubId = (int)($_GET['id'] ?? 0);
if ($clubId <= 0) {
    Flash::set('error', 'Invalid club.');
    redirect('/clubs/browse.php');
}

$club = ClubManagement::getClubById($clubId);
if (!$club) {
    Flash::set('error', 'Club not found.');
    redirect('/clubs/browse.php');
}

$ctx         = UserContext::getLoggedInUserContext();
$isClubAdmin = ClubManagement::isUserClubAdmin($ctx->id, $clubId);
$canManage   = $isClubAdmin || $ctx->admin;
$isMember    = ClubManagement::isUserMember($ctx->id, $clubId);

if (!$canManage) {
    Flash::set('error', 'You must be a club admin to create events.');
    redirect('/clubs/events/index.php?id=' . $clubId);
}

$heroUrl  = $club['hero_public_file_id']
    ? Files::publicFileUrl((int)$club['hero_public_file_id'])
    : '';
$photoUrl = $club['photo_public_file_id']
    ? Files::publicFileUrl((int)$club['photo_public_file_id'])
    : '';

$pageTitle     = 'Create Event — ' . $club['name'];
$activeClubId  = $clubId;
$activeSidebar = 'club-events';

ob_start();
?>
<div style="max-width:640px; margin:0 auto;">


  <form method="POST" action="/clubs/events/create_eval.php" novalidate>
    <?= csrf_input() ?>
    <input type="hidden" name="club_id" value="<?= $clubId ?>">

    <div class="card" style="background:var(--surface); border:1px solid var(--border);
                              border-radius:var(--radius); padding:24px; margin-bottom:20px;">
      <h2 style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:20px; font-weight:400;">
        Event details
      </h2>

      <div class="form-group">
        <label for="name">Event Name <span style="color:var(--error);">*</span></label>
        <input type="text" id="name" name="name" required autofocus
               placeholder="e.g. Spring Concert"
               value="<?= e($_POST['name'] ?? '') ?>">
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div class="form-group" style="margin-bottom:0;">
          <label for="starts_at">Starts at <span style="color:var(--error);">*</span></label>
          <input type="datetime-local" id="starts_at" name="starts_at" required
                 value="<?= e($_POST['starts_at'] ?? '') ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label for="ends_at">Ends at</label>
          <input type="datetime-local" id="ends_at" name="ends_at"
                 value="<?= e($_POST['ends_at'] ?? '') ?>">
        </div>
      </div>
    </div>

    <div class="card" style="background:var(--surface); border:1px solid var(--border);
                              border-radius:var(--radius); padding:24px; margin-bottom:20px;">
      <h2 style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:20px; font-weight:400;">
        Location
      </h2>

      <div class="form-group">
        <label for="location_name">Location Name</label>
        <input type="text" id="location_name" name="location_name"
               placeholder="e.g. Hackley Auditorium"
               value="<?= e($_POST['location_name'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label for="location_address">Location Address</label>
        <input type="text" id="location_address" name="location_address"
               placeholder="e.g. 293 Benedict Ave, Tarrytown, NY 10591"
               value="<?= e($_POST['location_address'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label for="google_maps_url">Google Maps Link <span style="color:var(--text-muted); font-weight:400;">(optional)</span></label>
        <input type="url" id="google_maps_url" name="google_maps_url"
               placeholder="https://maps.google.com/..."
               value="<?= e($_POST['google_maps_url'] ?? '') ?>">
      </div>
    </div>

    <div class="card" style="background:var(--surface); border:1px solid var(--border);
                              border-radius:var(--radius); padding:24px; margin-bottom:20px;">
      <h2 style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:20px; font-weight:400;">
        Description &amp; Image
      </h2>

      <div class="form-group">
        <label for="description">Description</label>
        <textarea id="description" name="description" rows="5"
                  placeholder="What should members know about this event?"><?= e($_POST['description'] ?? '') ?></textarea>
        <small style="color:var(--text-muted); font-size:12px; margin-top:6px; display:block;">
          Supports <a href="https://www.markdownguide.org/basic-syntax/" target="_blank"
                      rel="noopener" style="color:var(--accent-blue);">Markdown</a> formatting.
        </small>
      </div>

      <div class="form-group">
        <label>Event Image <span style="color:var(--text-muted); font-weight:400;">(optional)</span></label>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:12px;">
          Displayed as a wide banner at the top of the event. Drag to reposition, use the slider to zoom.
        </p>

        <div id="evImgUploadArea" class="photo-upload-area"
             style="text-align:center; cursor:pointer; margin-bottom:12px;"
             onclick="document.getElementById('evImgFileInput').click()">
          <p style="font-size:0.85rem; color:var(--text-secondary);">Click to choose an image</p>
          <p style="font-size:11px; color:var(--text-muted); margin-top:4px;">
            JPEG, PNG, WebP — displayed at 3:1 ratio
          </p>
        </div>

        <div id="evImgCropSection" style="display:none; text-align:center;">
          <div style="position:relative; width:100%; margin:0 auto 12px;
                      border-radius:var(--radius-sm); overflow:hidden;
                      background:var(--border); border:2px solid var(--border);
                      aspect-ratio:3/1;">
            <canvas id="evImgCanvas"
                    style="display:block; width:100%; height:100%;
                           cursor:grab; touch-action:none;"></canvas>
          </div>
          <div style="display:flex; align-items:center; gap:10px; margin-bottom:12px;">
            <label style="font-size:12px; color:var(--text-muted); white-space:nowrap;">Zoom</label>
            <input type="range" id="evImgZoom" min="0.5" max="3" step="0.01" value="1"
                   style="flex:1; padding:0; border:none; box-shadow:none;
                          height:4px; accent-color:var(--accent-blue);">
          </div>
          <button type="button" class="btn btn-secondary"
                  style="font-size:13px; margin-bottom:8px;"
                  onclick="document.getElementById('evImgFileInput').click()">
            Choose different image
          </button>
        </div>

        <input type="file" id="evImgFileInput" accept="image/jpeg,image/png,image/webp"
               style="display:none;" onchange="evImgInitCrop(this)">
        <input type="hidden" name="photo_data" id="evImgDataHidden">
      </div>
    </div>

    <div style="display:flex; gap:12px; justify-content:flex-end;">
      <a href="/clubs/events/index.php?id=<?= $clubId ?>" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Create Event</button>
    </div>
  </form>

</div>

<script>
// ── Event image 3:1 canvas crop ────────────────────────────────────────────
(function () {
  const EW = 1800, EH = 600; // logical canvas resolution (high-res for retina)
  let img = null, scale = 1, offX = 0, offY = 0, drag = false, lx = 0, ly = 0;

  const canvas = document.getElementById('evImgCanvas');
  const ctx2d  = canvas.getContext('2d');
  const zoom   = document.getElementById('evImgZoom');
  canvas.width  = EW;
  canvas.height = EH;

  window.evImgInitCrop = function (input) {
    const file = input.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function (ev) {
      img = new Image();
      img.onload = function () {
        scale = Math.max(EW / img.width, EH / img.height);
        offX  = (EW - img.width  * scale) / 2;
        offY  = (EH - img.height * scale) / 2;
        zoom.value = scale;
        zoom.min   = Math.max(0.1, scale * 0.5);
        zoom.max   = scale * 4;
        document.getElementById('evImgUploadArea').style.display  = 'none';
        document.getElementById('evImgCropSection').style.display = 'block';
        draw();
      };
      img.src = ev.target.result;
    };
    reader.readAsDataURL(file);
  };

  function draw() {
    ctx2d.clearRect(0, 0, EW, EH);
    if (!img) return;
    ctx2d.drawImage(img, offX, offY, img.width * scale, img.height * scale);
  }

  function clamp() {
    if (!img) return;
    offX = Math.min(0, Math.max(offX, EW - img.width  * scale));
    offY = Math.min(0, Math.max(offY, EH - img.height * scale));
  }

  zoom.addEventListener('input', function () {
    const ns = parseFloat(zoom.value);
    offX  = EW / 2 - (EW / 2 - offX) * (ns / scale);
    offY  = EH / 2 - (EH / 2 - offY) * (ns / scale);
    scale = ns;
    clamp();
    draw();
  });

  canvas.addEventListener('mousedown',  function (e) { drag = true; lx = e.clientX; ly = e.clientY; canvas.style.cursor = 'grabbing'; });
  canvas.addEventListener('mousemove',  function (e) {
    if (!drag) return;
    const rect   = canvas.getBoundingClientRect();
    const scaleX = EW / rect.width;
    const scaleY = EH / rect.height;
    offX += (e.clientX - lx) * scaleX;
    offY += (e.clientY - ly) * scaleY;
    lx = e.clientX; ly = e.clientY;
    clamp(); draw();
  });
  canvas.addEventListener('mouseup',    function () { drag = false; canvas.style.cursor = 'grab'; });
  canvas.addEventListener('mouseleave', function () { drag = false; canvas.style.cursor = 'grab'; });

  // Capture canvas on submit
  const form = canvas.closest('form');
  if (form) {
    form.addEventListener('submit', function () {
      if (img) document.getElementById('evImgDataHidden').value = canvas.toDataURL('image/jpeg', 0.95);
    });
  }
})();
</script>

<script>
// Auto-set end time to start + 90 min when start is chosen
(function () {
  const startsEl = document.getElementById('starts_at');
  const endsEl   = document.getElementById('ends_at');
  const DEFAULT_DURATION_MS = 90 * 60 * 1000;

  function pad(n) { return String(n).padStart(2, '0'); }
  function toLocal(dt) {
    return dt.getFullYear() + '-' + pad(dt.getMonth()+1) + '-' + pad(dt.getDate())
         + 'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
  }

  startsEl.addEventListener('change', function () {
    if (!this.value) return;
    const start = new Date(this.value);
    if (isNaN(start)) return;
    endsEl.value = toLocal(new Date(start.getTime() + DEFAULT_DURATION_MS));
  });
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/layout.php';
