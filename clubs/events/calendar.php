<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/EventManagement.php';
require_once __DIR__ . '/../../lib/EventUI.php';
require_once __DIR__ . '/../../lib/Files.php';
require_once __DIR__ . '/../../lib/UserContext.php';

Application::init();
Auth::requireLogin();

$ctx = UserContext::getLoggedInUserContext();

$events   = EventManagement::listUpcomingEventsForUser($ctx->id);
$eventIds = array_map(fn($e) => (int)$e['id'], $events);
$rsvpMap  = EventManagement::getUserRsvpsForEvents($ctx->id, $eventIds);

$pageTitle     = 'My Calendar';
$activeSidebar = 'calendar';

ob_start();
?>
<div style="max-width:720px; margin:0 auto;">

  <div style="margin-bottom:28px;">
    <h1 style="font-family:var(--font-title); font-weight:200; font-size:1.8rem;
                margin:0 0 4px; color:var(--text-primary);">
      <em><strong>My Calendar</strong></em>
    </h1>
    <p style="font-size:0.85rem; color:var(--text-muted); margin:0;">
      Upcoming events across all your clubs.
    </p>
  </div>

  <?php if (empty($events)): ?>
    <div style="text-align:center; padding:60px 24px; color:var(--text-muted);">
      <div style="margin-bottom:16px; color:var(--text-muted);">
        <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="1.5"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
          <line x1="16" y1="2" x2="16" y2="6"/>
          <line x1="8"  y1="2" x2="8"  y2="6"/>
          <line x1="3"  y1="10" x2="21" y2="10"/>
        </svg>
      </div>
      <p style="font-size:1rem; color:var(--text-secondary); margin-bottom:16px;">
        No upcoming events across your clubs.
      </p>
      <a href="/clubs/browse.php" class="btn btn-primary">
        Browse Clubs
      </a>
    </div>
  <?php else: ?>

    <?php
      // Group events by "Month Year" label
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
            $dayNum     = date('d', strtotime($ev['starts_at']));
            $monthAbb   = strtoupper(date('M', strtotime($ev['starts_at'])));
            $eventUrl   = '/clubs/events/event.php?id=' . $evId;
            $myAnswer   = $rsvpMap[$evId] ?? null;

            // Club badge data
            $clubId      = (int)$ev['club_id'];
            $clubName    = (string)($ev['club_name'] ?? '');
            $clubPhotoId = $ev['club_photo_file_id'] ? (int)$ev['club_photo_file_id'] : null;
            $clubPhotoUrl = $clubPhotoId ? Files::profilePhotoUrl($clubPhotoId) : '';
            $clubInitial  = strtoupper(substr($clubName, 0, 1));
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

                <!-- Club badge -->
                <a href="/clubs/events/index.php?id=<?= $clubId ?>"
                   style="display:inline-flex; align-items:center; gap:6px;
                          text-decoration:none; margin-bottom:8px;">
                  <?php if ($clubPhotoUrl !== ''): ?>
                    <img src="<?= e($clubPhotoUrl) ?>" alt="<?= e($clubName) ?>"
                         style="width:18px; height:18px; border-radius:50%;
                                object-fit:cover; flex-shrink:0;">
                  <?php else: ?>
                    <div style="width:18px; height:18px; border-radius:50%;
                                background:var(--gradient-brand); flex-shrink:0;
                                display:flex; align-items:center; justify-content:center;
                                font-size:9px; font-weight:700; color:#fff;">
                      <?= e($clubInitial) ?>
                    </div>
                  <?php endif; ?>
                  <span style="font-size:0.78rem; color:var(--text-secondary);
                               font-weight:600; white-space:nowrap; overflow:hidden;
                               text-overflow:ellipsis; max-width:200px;">
                    <?= e($clubName) ?>
                  </span>
                </a>

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
                         style="flex-shrink:0; margin-top:2px;" aria-hidden="true">
                      <path d="M7 0C3.134 0 0 3.134 0 7C0 12.25 7 18 7 18C7 18 14 12.25 14 7C14 3.134 10.866 0 7 0Z" fill="#EA4335"/>
                      <circle cx="7" cy="7" r="2.5" fill="white"/>
                    </svg>
                    <strong><?= e($ev['location_name']) ?></strong>
                  </div>
                <?php endif; ?>

                <!-- View Details button -->
                <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                  <a href="<?= e($eventUrl) ?>" class="btn btn-secondary"
                     style="font-size:12px; padding:5px 14px;">
                    View Details
                  </a>
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
