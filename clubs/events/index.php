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

$events = EventManagement::listClubEvents($clubId, false);

$heroUrl  = $club['hero_public_file_id']
    ? Files::publicFileUrl((int)$club['hero_public_file_id'])
    : '';
$photoUrl = $club['photo_public_file_id']
    ? Files::publicFileUrl((int)$club['photo_public_file_id'])
    : '';

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
      // Group by month
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
            $evPhotoUrl = $ev['photo_public_file_id']
                ? Files::publicFileUrl((int)$ev['photo_public_file_id'])
                : '';
            $dateRange  = EventManagement::formatDateRange(
                $ev['starts_at'],
                $ev['ends_at'] ?? null
            );
            $dayNum   = date('d', strtotime($ev['starts_at']));
            $monthAbb = strtoupper(date('M', strtotime($ev['starts_at'])));
            $eventUrl = '/clubs/events/event.php?id=' . (int)$ev['id'];
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
                <div style="font-size:0.8rem; color:var(--coral); font-weight:700; margin-bottom:4px;">
                  <?= e($dateRange) ?>
                </div>
                <a href="<?= e($eventUrl) ?>"
                   style="font-weight:700; color:var(--text-primary);
                          font-size:1.05rem; text-decoration:none; display:block;
                          margin-bottom:6px;">
                  <?= e($ev['name']) ?>
                </a>
                <?php if (trim((string)($ev['location_name'] ?? '')) !== ''): ?>
                  <div style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:8px;
                              display:flex; align-items:flex-start; gap:4px;">
                    <svg width="11" height="14" viewBox="0 0 14 18" fill="none"
                         xmlns="http://www.w3.org/2000/svg"
                         style="flex-shrink:0; margin-top:2px;">
                      <path d="M7 0C3.134 0 0 3.134 0 7C0 12.25 7 18 7 18C7 18 14 12.25 14 7C14 3.134 10.866 0 7 0Z" fill="#EA4335"/>
                      <circle cx="7" cy="7" r="2.5" fill="white"/>
                    </svg>
                    <strong><?= e($ev['location_name']) ?></strong>
                  </div>
                <?php endif; ?>
                <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap;">
                  <a href="<?= e($eventUrl) ?>" class="btn btn-secondary"
                     style="font-size:12px; padding:5px 14px;">
                    View Details
                  </a>
                  <?php if ($canManage): ?>
                    <a href="/clubs/events/edit.php?id=<?= (int)$ev['id'] ?>"
                       style="font-size:12px; color:var(--accent-blue);">
                      Edit
                    </a>
                  <?php endif; ?>
                </div>
              </div>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>

  <?php endif; ?>

</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/layout.php';
