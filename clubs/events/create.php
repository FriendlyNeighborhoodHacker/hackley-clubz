<?php
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
    <?php
      // JSON-encode previously selected occurrence dates so JS can restore them
      // after a failed form submission (server redirects back here with $_POST preserved).
      $prevOccurrenceDates = array_values(array_filter(
          array_map('trim', (array)($_POST['occurrence_dates'] ?? []))
      ));
    ?>
    <input type="hidden" id="prev_occurrence_dates"
           value="<?= e(json_encode($prevOccurrenceDates)) ?>">

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

      <div class="form-group" style="margin-top:16px; margin-bottom:0;">
        <label for="recurrence_rule">Repeat</label>
        <select id="recurrence_rule" name="recurrence_rule"
                data-saved="<?= e($_POST['recurrence_rule'] ?? 'none') ?>">
          <option value="none">Does not repeat</option>
          <option value="weekly"              id="rec-opt-weekly"  disabled>Weekly on …</option>
          <option value="monthly_nth_weekday" id="rec-opt-monthly" disabled>Monthly on …</option>
          <option value="custom"              id="rec-opt-custom"  disabled
                  style="color:var(--text-muted);">Custom… (coming soon)</option>
        </select>
      </div>

      <!-- Occurrence list — shown when a repeat rule is selected ─────────── -->
      <div id="rec-occurrences-section"
           style="display:none; margin-top:20px; padding-top:18px;
                  border-top:1px solid var(--border-light);">
        <div style="display:flex; align-items:center; justify-content:space-between;
                    margin-bottom:6px;">
          <span style="font-size:0.82rem; font-weight:600; color:var(--text-secondary);">
            Occurrences
          </span>
          <span style="font-size:0.78rem; display:flex; gap:14px;">
            <a href="#" id="rec-check-all"
               style="color:var(--accent-blue); text-decoration:none;">Select all</a>
            <a href="#" id="rec-uncheck-all"
               style="color:var(--accent-blue); text-decoration:none;">Deselect all</a>
          </span>
        </div>
        <p style="font-size:0.78rem; color:var(--text-muted); margin:0 0 10px;">
          All dates are selected. Uncheck any you'd like to skip.
        </p>
        <div id="rec-occurrences-list"
             style="max-height:280px; overflow-y:auto;
                    border:1px solid var(--border); border-radius:var(--radius-sm);">
          <!-- populated by JS -->
        </div>
        <div id="rec-occurrences-count"
             style="font-size:0.78rem; color:var(--text-muted); margin-top:6px;
                    text-align:right;"></div>
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
// ── Recurrence dropdown + occurrence date list ────────────────────────────
(function () {
  const DAY_NAMES = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
  const NTH_NAMES = ['first','second','third','fourth','fifth'];
  const MON_ABBR  = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const DOW_ABBR  = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

  const startsEl       = document.getElementById('starts_at');
  const recSelect      = document.getElementById('recurrence_rule');
  const weeklyOpt      = document.getElementById('rec-opt-weekly');
  const monthlyOpt     = document.getElementById('rec-opt-monthly');
  const customOpt      = document.getElementById('rec-opt-custom');
  const occSection     = document.getElementById('rec-occurrences-section');
  const occList        = document.getElementById('rec-occurrences-list');
  const occCount       = document.getElementById('rec-occurrences-count');
  const checkAllBtn    = document.getElementById('rec-check-all');
  const uncheckAllBtn  = document.getElementById('rec-uncheck-all');
  const submitBtn      = document.querySelector('button[type="submit"].btn.btn-primary');
  const prevDatesInput = document.getElementById('prev_occurrence_dates');

  // ── Date helpers ──────────────────────────────────────────────────────────

  /** Format Date → 'YYYY-MM-DD HH:MM:SS' (MySQL datetime, local time). */
  function toMysql(dt) {
    const p = n => String(n).padStart(2, '0');
    return dt.getFullYear() + '-' + p(dt.getMonth()+1) + '-' + p(dt.getDate())
         + ' ' + p(dt.getHours()) + ':' + p(dt.getMinutes()) + ':00';
  }

  /** Format Date → human-readable like 'Sat, Apr 5, 2025 at 3:00 PM'. */
  function formatDate(dt) {
    const h  = dt.getHours() % 12 || 12;
    const m  = dt.getMinutes() > 0 ? ':' + String(dt.getMinutes()).padStart(2,'0') : '';
    const ap = dt.getHours() >= 12 ? 'PM' : 'AM';
    return DOW_ABBR[dt.getDay()] + ', ' + MON_ABBR[dt.getMonth()]
         + ' ' + dt.getDate() + ', ' + dt.getFullYear()
         + ' at ' + h + m + '\u202f' + ap;
  }

  /** Compute the Nth occurrence of targetDow in the month after 'current'. */
  function nextNthWeekday(current, targetDow, nth) {
    const next       = new Date(current.getFullYear(), current.getMonth() + 1, 1,
                                current.getHours(), current.getMinutes(), 0);
    const firstDow   = next.getDay();
    const toFirst    = (targetDow - firstDow + 7) % 7;
    let   day        = 1 + toFirst + (nth - 1) * 7;
    const daysInMon  = new Date(next.getFullYear(), next.getMonth() + 1, 0).getDate();
    if (day > daysInMon) day -= 7;   // fall back if month is too short
    return new Date(next.getFullYear(), next.getMonth(), day,
                    current.getHours(), current.getMinutes(), 0);
  }

  /** Generate all occurrence Date objects for a given rule from startsAtVal. */
  function generateDates(startsAtVal, rule) {
    const start = new Date(startsAtVal);
    if (isNaN(start.getTime())) return [];

    const cutoff   = new Date(start.getFullYear() + 1, start.getMonth(),
                               start.getDate(), start.getHours(), start.getMinutes(), 0);
    const maxCount = rule === 'weekly' ? 52 : 12;
    const targetDow = start.getDay();
    const nth       = Math.ceil(start.getDate() / 7);

    const dates = [new Date(start)];
    let current = new Date(start);

    for (let i = 0; i < maxCount; i++) {
      if (rule === 'weekly') {
        current = new Date(current.getTime() + 7 * 24 * 60 * 60 * 1000);
      } else {
        current = nextNthWeekday(current, targetDow, nth);
      }
      if (current > cutoff) break;
      dates.push(new Date(current));
    }
    return dates;
  }

  // ── Render checkboxes ─────────────────────────────────────────────────────

  function renderOccurrences(dates) {
    occList.innerHTML = '';

    // Recover previously-checked dates for form repopulation after a failed submit.
    let prevSelected = [];
    try {
      const raw = prevDatesInput ? JSON.parse(prevDatesInput.value || '[]') : [];
      prevSelected = Array.isArray(raw) ? raw : [];
    } catch (e) {}
    const hasHistory = prevSelected.length > 0;

    dates.forEach(function (dt, idx) {
      const val     = toMysql(dt);
      const checked = !hasHistory || prevSelected.includes(val);

      const row = document.createElement('label');
      row.style.cssText = 'display:flex; align-items:center; gap:10px; padding:9px 14px;'
        + 'cursor:pointer; font-size:0.875rem; color:var(--text-primary);'
        + 'border-bottom:1px solid var(--border-light); margin:0; font-weight:400;'
        + (idx === dates.length - 1 ? 'border-bottom:none;' : '');

      const cb = document.createElement('input');
      cb.type    = 'checkbox';
      cb.name    = 'occurrence_dates[]';
      cb.value   = val;
      cb.checked = checked;
      cb.style.cssText = 'width:auto; padding:0; flex-shrink:0;';
      cb.addEventListener('change', updateCount);

      const span = document.createElement('span');
      span.textContent = formatDate(dt);

      row.appendChild(cb);
      row.appendChild(span);
      occList.appendChild(row);
    });

    updateCount();
  }

  function updateCount() {
    const total   = occList.querySelectorAll('input[type="checkbox"]').length;
    const checked = occList.querySelectorAll('input[type="checkbox"]:checked').length;
    if (occCount) {
      occCount.textContent = checked + ' of ' + total
        + ' date' + (total !== 1 ? 's' : '') + ' selected';
    }
  }

  // ── Check / Uncheck all ───────────────────────────────────────────────────

  if (checkAllBtn) {
    checkAllBtn.addEventListener('click', function (e) {
      e.preventDefault();
      occList.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = true; });
      updateCount();
    });
  }
  if (uncheckAllBtn) {
    uncheckAllBtn.addEventListener('click', function (e) {
      e.preventDefault();
      occList.querySelectorAll('input[type="checkbox"]').forEach(cb => { cb.checked = false; });
      updateCount();
    });
  }

  // ── Main update ───────────────────────────────────────────────────────────

  function updateAll() {
    const startsVal  = startsEl.value;
    const rule       = recSelect.value;
    const isRecurring = (rule === 'weekly' || rule === 'monthly_nth_weekday');

    // Update dropdown option labels whenever a start date is present.
    if (startsVal) {
      const dt = new Date(startsVal);
      if (!isNaN(dt.getTime())) {
        const dayName  = DAY_NAMES[dt.getDay()];
        const nthIndex = Math.min(Math.ceil(dt.getDate() / 7) - 1, 4);
        const nthName  = NTH_NAMES[nthIndex];

        weeklyOpt.textContent  = 'Weekly on ' + dayName;
        weeklyOpt.disabled     = false;
        monthlyOpt.textContent = 'Monthly on ' + nthName + ' ' + dayName;
        monthlyOpt.disabled    = false;
        customOpt.disabled     = false;
      }
    }

    // Show / hide occurrence list.
    occSection.style.display = (isRecurring && startsVal) ? 'block' : 'none';
    if (isRecurring && startsVal) {
      renderOccurrences(generateDates(startsVal, rule));
    }

    // Update submit button label.
    if (submitBtn) submitBtn.textContent = isRecurring ? 'Create Events' : 'Create Event';
  }

  // ── Client-side validation before submit ──────────────────────────────────

  const form = recSelect.closest('form');
  if (form) {
    form.addEventListener('submit', function (e) {
      const rule = recSelect.value;
      if (rule === 'weekly' || rule === 'monthly_nth_weekday') {
        const checked = occList.querySelectorAll('input[type="checkbox"]:checked').length;
        if (checked === 0) {
          e.preventDefault();
          alert('Please select at least one date for the recurring event.');
        }
      }
    });
  }

  // ── Init: restore saved rule on form repopulation ─────────────────────────

  const savedRule = recSelect.getAttribute('data-saved');
  if (savedRule && savedRule !== 'none') {
    recSelect.value = savedRule;
  }

  startsEl.addEventListener('change', updateAll);
  recSelect.addEventListener('change', updateAll);
  if (startsEl.value) updateAll();
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
