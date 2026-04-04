<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/ClubManagement.php';
require_once __DIR__ . '/../../lib/EventManagement.php';
require_once __DIR__ . '/../../lib/Files.php';
require_once __DIR__ . '/../../lib/ApplicationUI.php';
require_once __DIR__ . '/../../lib/ClubUI.php';
require_once __DIR__ . '/../../lib/UserContext.php';

Application::init();
Auth::requireLogin();

$eventId = (int)($_GET['id'] ?? 0);
if ($eventId <= 0) {
    Flash::set('error', 'Invalid event.');
    redirect('/clubs/browse.php');
}

$event = EventManagement::getEventById($eventId);
if (!$event) {
    Flash::set('error', 'Event not found.');
    redirect('/clubs/browse.php');
}

$clubId      = (int)$event['club_id'];
$club        = ClubManagement::getClubById($clubId);
$ctx         = UserContext::getLoggedInUserContext();
$isClubAdmin = ClubManagement::isUserClubAdmin($ctx->id, $clubId);
$canManage   = $isClubAdmin || $ctx->admin;
$isMember    = ClubManagement::isUserMember($ctx->id, $clubId);

if (!$canManage) {
    Flash::set('error', 'You must be a club admin to edit events.');
    redirect('/clubs/events/event.php?id=' . $eventId);
}

$eventPhotoUrl = $event['photo_public_file_id']
    ? Files::publicFileUrl((int)$event['photo_public_file_id'])
    : '';

// Pre-populate datetime-local inputs (MySQL "Y-m-d H:i:s" -> HTML "Y-m-d\TH:i")
$startsAtHtml = $event['starts_at']
    ? (new \DateTime($event['starts_at']))->format('Y-m-d\TH:i')
    : '';
$endsAtHtml = $event['ends_at']
    ? (new \DateTime($event['ends_at']))->format('Y-m-d\TH:i')
    : '';

$pageTitle     = 'Edit Event — ' . $event['name'];
$activeClubId  = $clubId;
$activeSidebar = 'club-events';

ob_start();
?>
<div style="max-width:640px; margin:0 auto;">

  <form method="POST" action="/clubs/events/edit_eval.php" novalidate>
    <?= csrf_input() ?>
    <input type="hidden" name="event_id" value="<?= $eventId ?>">

    <div class="card" style="background:var(--surface); border:1px solid var(--border);
                              border-radius:var(--radius); padding:24px; margin-bottom:20px;">
      <h2 style="font-size:0.9rem; color:var(--text-secondary); margin-bottom:20px; font-weight:400;">
        Event details
      </h2>

      <div class="form-group">
        <label for="name">Event Name <span style="color:var(--error);">*</span></label>
        <input type="text" id="name" name="name" required autofocus
               placeholder="e.g. Spring Concert"
               value="<?= e($_POST['name'] ?? $event['name']) ?>">
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
        <div class="form-group" style="margin-bottom:0;">
          <label for="starts_at">Starts at <span style="color:var(--error);">*</span></label>
          <input type="datetime-local" id="starts_at" name="starts_at" required
                 value="<?= e($_POST['starts_at'] ?? $startsAtHtml) ?>">
        </div>
        <div class="form-group" style="margin-bottom:0;">
          <label for="ends_at">Ends at</label>
          <input type="datetime-local" id="ends_at" name="ends_at"
                 value="<?= e($_POST['ends_at'] ?? $endsAtHtml) ?>">
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
               value="<?= e($_POST['location_name'] ?? $event['location_name'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label for="location_address">Location Address</label>
        <input type="text" id="location_address" name="location_address"
               placeholder="e.g. 293 Benedict Ave, Tarrytown, NY 10591"
               value="<?= e($_POST['location_address'] ?? $event['location_address'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label for="google_maps_url">Google Maps Link <span style="color:var(--text-muted); font-weight:400;">(optional)</span></label>
        <input type="url" id="google_maps_url" name="google_maps_url"
               placeholder="https://maps.google.com/..."
               value="<?= e($_POST['google_maps_url'] ?? $event['google_maps_url'] ?? '') ?>">
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
                  placeholder="What should members know about this event?"><?= e($_POST['description'] ?? $event['description'] ?? '') ?></textarea>
        <small style="color:var(--text-muted); font-size:12px; margin-top:6px; display:block;">
          Supports <a href="https://www.markdownguide.org/basic-syntax/" target="_blank"
                      rel="noopener" style="color:var(--accent-blue);">Markdown</a> formatting.
        </small>
      </div>

      <div class="form-group">
        <label>Event Image</label>
        <p style="font-size:0.8rem; color:var(--text-muted); margin-bottom:12px;">
          Displayed as a wide banner at the top of the event. Drag to reposition, use the slider to zoom.
        </p>

        <?php if ($eventPhotoUrl !== ''): ?>
          <div id="evImgCurrentWrap" style="margin-bottom:14px;">
            <img src="<?= e($eventPhotoUrl) ?>" alt="Current event image"
                 style="max-width:100%; max-height:160px; object-fit:cover;
                        border-radius:var(--radius-sm); display:block; width:100%; margin-bottom:8px;">
            <div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap;">
              <label style="display:flex; align-items:center; gap:6px; cursor:pointer;
                            font-size:13px; color:var(--error);">
                <input type="checkbox" name="clear_photo" value="1"
                       style="width:auto; padding:0;" id="clearPhotoCheck">
                Remove image
              </label>
              <button type="button" class="btn btn-secondary"
                      style="font-size:12px; padding:5px 14px;"
                      onclick="document.getElementById('evImgFileInput').click()">
                Replace image
              </button>
            </div>
          </div>
        <?php else: ?>
          <div id="evImgUploadArea" class="photo-upload-area"
               style="text-align:center; cursor:pointer; margin-bottom:12px;"
               onclick="document.getElementById('evImgFileInput').click()">
            <p style="font-size:0.85rem; color:var(--text-secondary);">Click to choose an image</p>
            <p style="font-size:11px; color:var(--text-muted); margin-top:4px;">
              JPEG, PNG, WebP — displayed at 3:1 ratio
            </p>
          </div>
        <?php endif; ?>

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
      <a href="/clubs/events/event.php?id=<?= $eventId ?>" class="btn btn-secondary">Cancel</a>
      <button type="submit" class="btn btn-primary">Save Changes</button>
    </div>
  </form>

</div>

<script>
// -- Event image 3:1 canvas crop -------------------------------------------
(function () {
  const EW = 1800, EH = 600; // high-res for retina displays
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
        const ua = document.getElementById('evImgUploadArea');
        const cw = document.getElementById('evImgCurrentWrap');
        if (ua) ua.style.display = 'none';
        if (cw) cw.style.display = 'none';
        document.getElementById('evImgCropSection').style.display = 'block';
        const cc = document.getElementById('clearPhotoCheck');
        if (cc) cc.checked = false;
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
    clamp(); draw();
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

  const form = canvas.closest('form');
  if (form) {
    form.addEventListener('submit', function () {
      if (img) document.getElementById('evImgDataHidden').value = canvas.toDataURL('image/jpeg', 0.95);
    });
  }
})();
</script>

<script>
// Preserve original event duration when start time is changed
(function () {
  const startsEl = document.getElementById('starts_at');
  const endsEl   = document.getElementById('ends_at');
  const DEFAULT_DURATION_MS = 90 * 60 * 1000;

  function pad(n) { return String(n).padStart(2, '0'); }
  function toLocal(dt) {
    return dt.getFullYear() + '-' + pad(dt.getMonth()+1) + '-' + pad(dt.getDate())
         + 'T' + pad(dt.getHours()) + ':' + pad(dt.getMinutes());
  }

  // Compute original duration from the form's initial values on page load
  let durationMs = DEFAULT_DURATION_MS;
  if (startsEl.value && endsEl.value) {
    const s = new Date(startsEl.value);
    const e = new Date(endsEl.value);
    if (!isNaN(s) && !isNaN(e) && e > s) {
      durationMs = e.getTime() - s.getTime();
    }
  }

  // Track whether the user has manually changed the end time
  let userEditedEnd = false;
  endsEl.addEventListener('change', function () {
    userEditedEnd = true;
  });

  startsEl.addEventListener('change', function () {
    if (!this.value || userEditedEnd) return;
    const start = new Date(this.value);
    if (isNaN(start)) return;
    endsEl.value = toLocal(new Date(start.getTime() + durationMs));
  });
})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/layout.php';
