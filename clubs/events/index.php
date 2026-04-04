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

$events   = EventManagement::listClubEvents($clubId, false);
$eventIds = array_map(fn($e) => (int)$e['id'], $events);
$rsvpMap  = EventManagement::getUserRsvpsForEvents($ctx->id, $eventIds);

$pageTitle     = 'Events — ' . $club['name'];
$activeClubId  = $clubId;
$activeSidebar = 'club-events';

ob_start();
?>
<div style="max-width:720px; margin:0 auto;">

  <?php if ($canManage): ?>
  <div style="display:flex; justify-content:flex-end; margin-bottom:24px;">
    <a href="/clubs/events/create.php?id=<?= $clubId ?>"
       class="btn btn-primary" style="font-size:13px;">
      + Create Event
    </a>
  </div>
  <?php endif; ?>

  <?php if (empty($events)): ?>
    <div style="text-align:center; padding:60px 24px; color:var(--text-muted);">
      <div style="margin-bottom:16px; color:var(--text-muted);">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.5"
             stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8"  y1="2" x2="8"  y2="6"/>
          <line x1="3"  y1="10" x2="21" y2="10"/>
        </svg>
      </div>
      <p style="font-size:1rem; color:var(--text-secondary);">No upcoming events.</p>
      <?php if ($canManage): ?>
        <a href="/clubs/events/create.php?id=<?= $clubId ?>"
           class="btn btn-primary" style="margin-top:16px;">
          Create the first event
        </a>
      <?php endif; ?>
    </div>
  <?php else: ?>

    <?php
      $grouped = [];
      foreach ($events as $ev) {
          $monthKey = date('F Y', strtotime($ev['starts_at']));
          $grouped[$monthKey][] = $ev;
      }
    ?>

    <?php foreach ($grouped as $monthLabel => $monthEvents): ?>
      <h2 style="font-size:0.9rem; font-weight:700; text-transform:uppercase;
                 letter-spacing:.06em; color:var(--text-secondary);
                 margin:0 0 14px; padding-bottom:8px;
                 border-bottom:1px solid var(--border);">
        <?= e($monthLabel) ?>
      </h2>

      <div style="display:flex; flex-direction:column; gap:16px; margin-bottom:28px;">
        <?php foreach ($monthEvents as $ev): ?>
          <?php
            $evId       = (int)$ev['id'];
            $evPhotoUrl = $ev['photo_public_file_id']
                ? Files::publicFileUrl((int)$ev['photo_public_file_id'])
                : '';
            $dateRange  = EventManagement::formatDateRange(
                $ev['starts_at'],
                $ev['ends_at'] ?? null
            );
            $dayNum    = date('d', strtotime($ev['starts_at']));
            $monthAbb  = strtoupper(date('M', strtotime($ev['starts_at'])));
            $eventUrl  = '/clubs/events/event.php?id=' . $evId;
            $myAnswer  = $rsvpMap[$evId] ?? null;
          ?>
          <div style="display:flex; gap:16px; align-items:flex-start;">

            <!-- Date column -->
            <div style="flex-shrink:0; width:44px; text-align:center; padding-top:4px;">
              <div style="font-size:9px; font-weight:700; letter-spacing:.06em;
                           color:var(--text-secondary); text-transform:uppercase;">
                <?= e($monthAbb) ?>
              </div>
              <div style="font-size:1.6rem; font-weight:700; line-height:1;
                           color:var(--text-primary);">
                <?= e($dayNum) ?>
              </div>
            </div>

            <!-- Event card -->
            <div style="flex:1; min-width:0; background:var(--surface);
                        border:1px solid var(--border); border-radius:var(--radius);
                        overflow:hidden;">
              <?php if ($evPhotoUrl !== ''): ?>
                <a href="<?= e($eventUrl) ?>">
                  <img src="<?= e($evPhotoUrl) ?>" alt=""
                       style="width:100%; height:160px; object-fit:cover; display:block;">
                </a>
              <?php endif; ?>

              <div style="padding:16px 18px;">
                <!-- Date/time -->
                <div style="font-size:0.8rem; color:var(--coral); font-weight:700; margin-bottom:4px;">
                  <?= e($dateRange) ?>
                </div>

                <!-- Title -->
                <a href="<?= e($eventUrl) ?>"
                   style="font-weight:700; color:var(--text-primary);
                          font-size:1.05rem; text-decoration:none; display:block;
                          margin-bottom:6px;">
                  <?= e($ev['name']) ?>
                </a>

                <!-- Location -->
                <?php if (trim((string)($ev['location_name'] ?? '')) !== ''): ?>
                  <div style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:8px;
                              display:flex; align-items:flex-start; gap:4px;">
                    <svg width="11" height="14" viewBox="0 0 14 18" fill="none"
                         style="flex-shrink:0; margin-top:2px;">
                      <path d="M7 0C3.134 0 0 3.134 0 7C0 12.25 7 18 7 18C7 18 14 12.25 14 7C14 3.134 10.866 0 7 0Z" fill="#EA4335"/>
                      <circle cx="7" cy="7" r="2.5" fill="white"/>
                    </svg>
                    <strong><?= e($ev['location_name']) ?></strong>
                  </div>
                <?php endif; ?>

                <!-- View Details / Edit buttons -->
                <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                  <a href="<?= e($eventUrl) ?>" class="btn btn-secondary"
                     style="font-size:12px; padding:5px 14px;">
                    View Details
                  </a>
                  <?php if ($canManage): ?>
                    <a href="/clubs/events/edit.php?id=<?= $evId ?>"
                       style="font-size:12px; color:var(--accent-blue);">
                      Edit
                    </a>
                  <?php endif; ?>
                </div>

                <!-- RSVP section -->
                <hr style="border:none; border-top:1px solid var(--border); margin:12px 0 10px;">
                <div id="rsvp-area-<?= $evId ?>">
                  <?= EventUI::rsvpSectionHtml($evId, $myAnswer) ?>
                </div>

              </div><!-- /padding -->
            </div><!-- /event card -->

          </div><!-- /flex row -->
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

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

  // Colors by answer
  const RSVP_ANSWER_COLORS = { yes: '#22c55e', maybe: '#f97316', no: '#ef4444' };

  let _modalEventId  = null;
  let _modalSelected = null;

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
        } else {
          showAreaError(area, data.error);
          if (btn) btn.disabled = false;
        }
      })
      .catch(() => { if (btn) btn.disabled = false; });
  };

  // ── Celebration burst ────────────────────────────────────────────────────
  // btn: DOM element (used for inline), OR pass null and supply cx/cy directly
  // (used from modal where the button may be hidden by the time we animate).
  function rsvpCelebrate(btn, cx, cy) {
    if (btn) {
      const r = btn.getBoundingClientRect();
      cx = r.left + r.width  / 2;
      cy = r.top  + r.height / 2;
    }

    // Particle dots
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

    // Expanding ring
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

    ['yes', 'maybe', 'no'].forEach(a => {
      setModalBtnStyle(a, a === currentAnswer);
    });

    document.getElementById('rsvp-modal').style.display = 'flex';
  };

  window.rsvpModalSelect = function (answer) {
    _modalSelected = answer;
    ['yes', 'maybe', 'no'].forEach(a => setModalBtnStyle(a, a === answer));
    // Celebrate immediately when the user taps Yes — before they even hit Save.
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

  // Close modal when clicking the backdrop
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
