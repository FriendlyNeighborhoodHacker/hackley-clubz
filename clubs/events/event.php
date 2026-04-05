<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/ClubManagement.php';
require_once __DIR__ . '/../../lib/EventManagement.php';
require_once __DIR__ . '/../../lib/EventUI.php';
require_once __DIR__ . '/../../lib/Files.php';
require_once __DIR__ . '/../../lib/ApplicationUI.php';
require_once __DIR__ . '/../../lib/ClubUI.php';
require_once __DIR__ . '/../../lib/UserContext.php';
require_once __DIR__ . '/../../lib/Parsedown.php';

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

$myAnswer  = EventManagement::getUserRsvp($eventId, $ctx->id);
$attendees = EventManagement::getEventAttendees($eventId);

$eventPhotoUrl = $event['photo_public_file_id']
    ? Files::publicFileUrl((int)$event['photo_public_file_id'])
    : '';
$clubPhotoUrl  = ($club && $club['photo_public_file_id'])
    ? Files::publicFileUrl((int)$club['photo_public_file_id'])
    : '';

$dateRange = EventManagement::formatDateRange($event['starts_at'], $event['ends_at'] ?? null);
$gcalUrl   = EventManagement::googleCalendarUrl($event);

$pageTitle     = $event['name'] . ' — ' . ($event['club_name'] ?? '');
$activeClubId  = $clubId;
$activeSidebar = 'club-events';

ob_start();
?>
<div style="max-width:680px; margin:0 auto;">

  <!-- Hero image -->
  <?php if ($eventPhotoUrl !== ''): ?>
    <div style="margin-bottom:20px; border-radius:var(--radius); overflow:hidden;">
      <img src="<?= e($eventPhotoUrl) ?>" alt="<?= e($event['name']) ?>"
           style="width:100%; max-height:320px; object-fit:cover; display:block;">
    </div>
  <?php endif; ?>

  <!-- RSVP card -->
  <div style="background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
              padding:14px 18px; margin-bottom:20px;">
    <div id="rsvp-area-<?= $eventId ?>">
      <?= EventUI::rsvpSectionHtml($eventId, $myAnswer) ?>
    </div>
  </div>

  <!-- Event detail card -->
  <div style="background:var(--surface); border:1px solid var(--border);
              border-radius:var(--radius); padding:28px; margin-bottom:20px;">

    <!-- Date / time -->
    <div style="font-size:0.9rem; color:var(--coral); font-weight:700; margin-bottom:8px;
                display:flex; align-items:center; gap:6px;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
           stroke="currentColor" stroke-width="2.5"
           stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
        <circle cx="12" cy="12" r="10"/>
        <polyline points="12 6 12 12 16 14"/>
      </svg>
      <?= e($dateRange) ?>
    </div>

    <!-- Title + edit button -->
    <div style="display:flex; align-items:flex-start; justify-content:space-between;
                gap:12px; margin-bottom:16px;">
      <h1 style="font-family:var(--font-title); font-weight:200; font-size:1.8rem;
                 line-height:1.2; margin:0;">
        <?= e($event['name']) ?>
      </h1>
      <?php if ($canManage): ?>
        <a href="/clubs/events/edit.php?id=<?= $eventId ?>"
           class="btn btn-secondary" style="font-size:13px; white-space:nowrap; flex-shrink:0;">
          Edit Event
        </a>
      <?php endif; ?>
    </div>

    <!-- Location -->
    <?php if (trim((string)($event['location_name'] ?? '')) !== ''): ?>
      <div style="margin-bottom:12px; font-size:0.9rem; color:var(--text-secondary);
                  display:flex; align-items:flex-start; gap:5px;">
        <svg width="13" height="17" viewBox="0 0 14 18" fill="none"
             xmlns="http://www.w3.org/2000/svg"
             style="flex-shrink:0; margin-top:2px;">
          <path d="M7 0C3.134 0 0 3.134 0 7C0 12.25 7 18 7 18C7 18 14 12.25 14 7C14 3.134 10.866 0 7 0Z" fill="#EA4335"/>
          <circle cx="7" cy="7" r="2.5" fill="white"/>
        </svg>
        <span>
          <?php if (trim((string)($event['google_maps_url'] ?? '')) !== ''): ?>
            <a href="<?= e($event['google_maps_url']) ?>" target="_blank" rel="noopener"
               style="color:var(--text-primary); font-weight:700; text-decoration:none;">
              <?= e($event['location_name']) ?>
            </a>
          <?php else: ?>
            <strong><?= e($event['location_name']) ?></strong>
          <?php endif; ?>
          <?php if (trim((string)($event['location_address'] ?? '')) !== ''): ?>
            <span style="color:var(--text-muted); font-weight:400;"> — <?= e($event['location_address']) ?></span>
          <?php endif; ?>
        </span>
      </div>
    <?php endif; ?>

    <!-- Google Calendar link -->
    <?php if ($gcalUrl !== ''): ?>
      <div style="margin-bottom:20px;">
        <a href="<?= e($gcalUrl) ?>" target="_blank" rel="noopener"
           class="btn btn-secondary"
           style="font-size:12px; padding:5px 11px; display:inline-flex; align-items:center; gap:6px; text-decoration:none;">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
            <line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8"  y1="2" x2="8"  y2="6"/>
            <line x1="3"  y1="10" x2="21" y2="10"/>
          </svg>
          Add to Google Calendar
        </a>
      </div>
    <?php endif; ?>

    <!-- Description -->
    <?php $desc = trim((string)($event['description'] ?? '')); ?>
    <?php if ($desc !== ''): ?>
      <hr style="border:none; border-top:1px solid var(--border); margin:20px 0;">
      <div class="prose" style="color:var(--text-primary); line-height:1.7;">
        <?php
          $pd = new Parsedown();
          $pd->setSafeMode(true);
          echo $pd->text($desc);
        ?>
      </div>
    <?php endif; ?>

    <!-- Going facepile (hr lives inside so AJAX refresh can add/remove it too) -->
    <div id="facepile-area">
      <?php $facepileHtml = EventUI::facepileHtml($attendees); ?>
      <?php if ($facepileHtml !== ''): ?>
        <hr style="border:none; border-top:1px solid var(--border); margin:20px 0;">
        <?= $facepileHtml ?>
      <?php endif; ?>
    </div>

  </div>

  <?php if ($canManage): ?>
  <!-- Admin delete -->
  <div style="text-align:right; margin-top:8px;">
    <form method="POST" action="/clubs/events/delete_eval.php" style="display:inline;">
      <?= csrf_input() ?>
      <input type="hidden" name="event_id" value="<?= $eventId ?>">
      <button type="submit" class="btn btn-danger" style="font-size:13px;"
              onclick="return confirm('Delete this event? This cannot be undone.')">
        Delete Event
      </button>
    </form>
  </div>
  <?php endif; ?>

</div>

<!-- ── RSVP Change Modal ──────────────────────────────────────────────────── -->
<div id="rsvp-modal"
     style="display:none; position:fixed; inset:0; z-index:1000;
            background:rgba(0,0,0,.45); align-items:center; justify-content:center;">
  <div style="background:var(--surface); border-radius:var(--radius); padding:28px 24px;
              min-width:270px; max-width:320px; width:90%;
              box-shadow:0 8px 32px rgba(0,0,0,.2);">
    <h3 style="margin:0 0 6px; font-size:1rem; font-weight:700;">Change your RSVP</h3>
    <p style="font-size:0.8rem; color:var(--text-muted); margin:0 0 18px;">
      Select an option, then tap Save.
    </p>
    <div style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px;">
      <button id="rsvp-modal-btn-yes" type="button" onclick="rsvpModalSelect('yes')"
              style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;
                     font-size:13px;border-radius:var(--radius-sm,6px);
                     border:1.5px solid var(--coral);color:var(--coral);
                     background:transparent;cursor:pointer;font-family:inherit;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <polyline points="20 6 9 17 4 12"/>
        </svg>
        Yes
      </button>
      <button id="rsvp-modal-btn-maybe" type="button" onclick="rsvpModalSelect('maybe')"
              style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;
                     font-size:13px;border-radius:var(--radius-sm,6px);
                     border:1.5px solid var(--coral);color:var(--coral);
                     background:transparent;cursor:pointer;font-family:inherit;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
        </svg>
        Maybe
      </button>
      <button id="rsvp-modal-btn-no" type="button" onclick="rsvpModalSelect('no')"
              style="display:inline-flex;align-items:center;gap:5px;padding:6px 14px;
                     font-size:13px;border-radius:var(--radius-sm,6px);
                     border:1.5px solid var(--coral);color:var(--coral);
                     background:transparent;cursor:pointer;font-family:inherit;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18"/>
          <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
        No
      </button>
    </div>
    <div id="rsvp-modal-error"
         style="display:none; color:var(--error,#ef4444); font-size:12px; margin-bottom:10px;"></div>
    <div style="display:flex; gap:8px; justify-content:flex-end;">
      <button type="button" onclick="rsvpModalClose()"
              class="btn btn-secondary" style="font-size:13px;">
        Cancel
      </button>
      <button id="rsvp-modal-save" type="button" onclick="rsvpModalSave()"
              class="btn btn-primary" style="font-size:13px;">
        Save
      </button>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';

  const RSVP_ENDPOINT = '/clubs/events/ajax_rsvp_eval.php';
  const RSVP_CSRF     = <?= json_encode(csrf_token()) ?>;

  const RSVP_ANSWER_COLORS = { yes: '#22c55e', maybe: '#f97316', no: '#ef4444' };

  let _modalEventId  = null;
  let _modalSelected = null;

  const EVENT_ID = <?= json_encode($eventId) ?>;

  // ── Facepile refresh ─────────────────────────────────────────────────────
  function refreshFacepile() {
    fetch('/clubs/events/ajax_attendees.php?event_id=' + EVENT_ID)
      .then(r => r.text())
      .then(html => {
        const area = document.getElementById('facepile-area');
        if (area) area.innerHTML = html;
      })
      .catch(() => {}); // facepile refresh failure is non-critical
  }

  // ── Inline submit ────────────────────────────────────────────────────────
  window.rsvpSubmit = function (eventId, answer, btn) {
    const area = document.getElementById('rsvp-area-' + eventId);
    if (!area) return;
    if (btn) btn.disabled = true;

    fetchRsvp(eventId, answer)
      .then(data => {
        if (data.success) {
          if (answer === 'yes' && btn) rsvpCelebrate(btn);
          area.innerHTML = data.html;
          refreshFacepile();
        } else {
          showAreaError(area, data.error);
          if (btn) btn.disabled = false;
        }
      })
      .catch(() => { if (btn) btn.disabled = false; });
  };

  // ── Celebration burst ────────────────────────────────────────────────────
  function rsvpCelebrate(btn, cx, cy) {
    if (btn) {
      const r = btn.getBoundingClientRect();
      cx = r.left + r.width  / 2;
      cy = r.top  + r.height / 2;
    }

    for (let i = 0; i < 18; i++) {
      const dot   = document.createElement('div');
      const angle = (i / 18) * 2 * Math.PI;
      const dist  = 34 + Math.random() * 30;
      const dx    = Math.cos(angle) * dist;
      const dy    = Math.sin(angle) * dist;
      const size  = 5 + Math.random() * 5;
      dot.style.cssText =
        'position:fixed;border-radius:50%;pointer-events:none;z-index:9999;'
        + `width:${size}px;height:${size}px;`
        + `left:${cx}px;top:${cy}px;`
        + 'transform:translate(-50%,-50%);background:#22c55e;opacity:1;'
        + 'transition:transform 580ms cubic-bezier(.15,.8,.25,1),'
        + 'opacity 580ms ease-out;will-change:transform,opacity;';
      document.body.appendChild(dot);
      requestAnimationFrame(() => requestAnimationFrame(() => {
        dot.style.transform = `translate(calc(-50% + ${dx}px),calc(-50% + ${dy}px))`;
        dot.style.opacity   = '0';
      }));
      setTimeout(() => dot.remove(), 640);
    }

    const ring = document.createElement('div');
    ring.style.cssText =
      'position:fixed;border-radius:50%;pointer-events:none;z-index:9998;'
      + `left:${cx}px;top:${cy}px;`
      + 'transform:translate(-50%,-50%) scale(1);'
      + 'width:6px;height:6px;border:2px solid #22c55e;opacity:.9;'
      + 'transition:transform 500ms ease-out,opacity 500ms ease-out;';
    document.body.appendChild(ring);
    requestAnimationFrame(() => requestAnimationFrame(() => {
      ring.style.transform = 'translate(-50%,-50%) scale(13)';
      ring.style.opacity   = '0';
    }));
    setTimeout(() => ring.remove(), 540);
  }

  // ── Modal ────────────────────────────────────────────────────────────────
  window.rsvpOpenModal = function (eventId, currentAnswer) {
    _modalEventId  = eventId;
    _modalSelected = currentAnswer;

    const errEl = document.getElementById('rsvp-modal-error');
    if (errEl) { errEl.style.display = 'none'; errEl.textContent = ''; }

    ['yes', 'maybe', 'no'].forEach(a => setModalBtnStyle(a, a === currentAnswer));
    document.getElementById('rsvp-modal').style.display = 'flex';
  };

  window.rsvpModalSelect = function (answer) {
    _modalSelected = answer;
    ['yes', 'maybe', 'no'].forEach(a => setModalBtnStyle(a, a === answer));
    if (answer === 'yes') {
      const yesBtnEl = document.getElementById('rsvp-modal-btn-yes');
      if (yesBtnEl) rsvpCelebrate(yesBtnEl);
    }
  };

  window.rsvpModalClose = function () {
    const modal = document.getElementById('rsvp-modal');
    if (modal) modal.style.display = 'none';
  };

  window.rsvpModalSave = function () {
    if (!_modalEventId || !_modalSelected) return;
    const saveBtn = document.getElementById('rsvp-modal-save');
    if (saveBtn) saveBtn.disabled = true;

    fetchRsvp(_modalEventId, _modalSelected)
      .then(data => {
        if (saveBtn) saveBtn.disabled = false;
        if (data.success) {
          rsvpModalClose();
          const area = document.getElementById('rsvp-area-' + _modalEventId);
          if (area) area.innerHTML = data.html;
          refreshFacepile();
        } else {
          const errEl = document.getElementById('rsvp-modal-error');
          if (errEl) {
            errEl.textContent = data.error || 'Something went wrong.';
            errEl.style.display = 'block';
          }
        }
      })
      .catch(() => { if (saveBtn) saveBtn.disabled = false; });
  };

  document.getElementById('rsvp-modal').addEventListener('click', function (e) {
    if (e.target === this) rsvpModalClose();
  });

  // ── Helpers ──────────────────────────────────────────────────────────────
  function fetchRsvp(eventId, answer) {
    return fetch(RSVP_ENDPOINT, {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    new URLSearchParams({
        event_id:    eventId,
        answer:      answer,
        _csrf_token: RSVP_CSRF,
      }),
    }).then(r => r.json());
  }

  function showAreaError(area, message) {
    let errEl = area.querySelector('.rsvp-inline-error');
    if (!errEl) {
      errEl = document.createElement('div');
      errEl.className = 'rsvp-inline-error';
      errEl.style.cssText = 'color:var(--error,#ef4444);font-size:12px;margin-bottom:6px;';
      area.prepend(errEl);
    }
    errEl.textContent = message || 'Something went wrong.';
  }

  function setModalBtnStyle(answer, active) {
    const btn = document.getElementById('rsvp-modal-btn-' + answer);
    if (!btn) return;
    const col = RSVP_ANSWER_COLORS[answer] || 'var(--coral)';
    if (active) {
      btn.style.background  = col;
      btn.style.color       = '#fff';
      btn.style.borderColor = col;
    } else {
      btn.style.background  = 'transparent';
      btn.style.color       = 'var(--coral)';
      btn.style.borderColor = 'var(--coral)';
    }
  }

})();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/layout.php';
