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

$eventPhotoUrl = $event['photo_public_file_id']
    ? Files::publicFileUrl((int)$event['photo_public_file_id'])
    : '';
$clubPhotoUrl  = ($club && $club['photo_public_file_id'])
    ? Files::publicFileUrl((int)$club['photo_public_file_id'])
    : '';

$dateRange   = EventManagement::formatDateRange($event['starts_at'], $event['ends_at'] ?? null);
$gcalUrl     = EventManagement::googleCalendarUrl($event);

$pageTitle     = $event['name'] . ' — ' . ($event['club_name'] ?? '');
$activeClubId  = $clubId;
$activeSidebar = 'club-events';

ob_start();
?>
<div style="max-width:680px; margin:0 auto;">

  <!-- RSVP placeholder (coming soon) -->
  <div style="background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
              padding:14px 18px; margin-bottom:20px; display:flex; align-items:center; gap:12px;
              flex-wrap:wrap;">
    <span style="font-size:0.85rem; color:var(--text-muted); font-style:italic;">
      RSVP coming soon
    </span>
  </div>

  <!-- Hero image -->
  <?php if ($eventPhotoUrl !== ''): ?>
    <div style="margin-bottom:24px; border-radius:var(--radius); overflow:hidden;">
      <img src="<?= e($eventPhotoUrl) ?>" alt="<?= e($event['name']) ?>"
           style="width:100%; max-height:320px; object-fit:cover; display:block;">
    </div>
  <?php endif; ?>

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
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/layout.php';
