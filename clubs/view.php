<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/ClubManagement.php';
require_once __DIR__ . '/../lib/Files.php';
require_once __DIR__ . '/../lib/UserContext.php';

Application::init();
Auth::requireLogin();

$clubId = (int)($_GET['id'] ?? 0);
if ($clubId <= 0) {
    Flash::set('error', 'Invalid club.');
    redirect('/clubs/browse.php');
}

$club = ClubManagement::getClubById($clubId);
if (!$club || $club['is_secret']) {
    Flash::set('error', 'Club not found.');
    redirect('/clubs/browse.php');
}

$ctx         = UserContext::getLoggedInUserContext();
$isMember    = ClubManagement::isUserMember($ctx->id, $clubId);
$isClubAdmin = ClubManagement::isUserClubAdmin($ctx->id, $clubId);
$canManage   = $isClubAdmin || $ctx->admin;

$heroUrl  = $club['hero_public_file_id']
    ? Files::publicFileUrl((int)$club['hero_public_file_id'])
    : '';
$photoUrl = $club['photo_public_file_id']
    ? Files::publicFileUrl((int)$club['photo_public_file_id'])
    : '';

$memberCount = (int)($club['member_count'] ?? 0);
$initial     = strtoupper(substr($club['name'], 0, 1));

$pageTitle     = $club['name'];
$activeSidebar = 'browse-clubs';

// ── Club panel nav ──────────────────────────────────────────────────────────
$clubPanelTitle = $club['name'];
ob_start(); ?>
<div style="padding:4px 0 8px;">
  <a href="/clubs/view.php?id=<?= $clubId ?>" class="admin-panel-link active">
    Club Info
  </a>
  <?php if ($canManage): ?>
    <a href="/clubs/settings.php?id=<?= $clubId ?>" class="admin-panel-link">
      ⚙️ Settings
    </a>
    <a href="/clubs/members.php?id=<?= $clubId ?>" class="admin-panel-link">
      👑 Members
    </a>
  <?php endif; ?>
</div>
<?php $clubPanelContent = ob_get_clean();

ob_start();
?>
<div style="max-width:720px; margin:0 auto;">

  <!-- Back link -->
  <a href="/clubs/browse.php"
     style="color:var(--text-secondary); font-size:14px; text-decoration:none;">
    ← Back to clubs
  </a>

  <!-- Hero image -->
  <?php if ($heroUrl !== ''): ?>
    <div style="margin:16px 0; border-radius:var(--radius); overflow:hidden;
                aspect-ratio: 3/1; background:var(--border);">
      <img src="<?= e($heroUrl) ?>" alt="<?= e($club['name']) ?>"
           style="width:100%; height:100%; object-fit:cover; display:block;">
    </div>
  <?php else: ?>
    <div style="margin:16px 0;"></div>
  <?php endif; ?>

  <!-- Club header: photo + name + join/leave -->
  <div style="display:flex; align-items:flex-start; gap:16px; margin-bottom:24px; flex-wrap:wrap;">

    <!-- Profile photo -->
    <?php if ($photoUrl !== ''): ?>
      <img src="<?= e($photoUrl) ?>" alt="" class="avatar"
           style="width:72px; height:72px; flex-shrink:0;">
    <?php else: ?>
      <div class="avatar-placeholder"
           style="width:72px; height:72px; font-size:28px; flex-shrink:0;
                  background:var(--gradient-brand);">
        <?= e($initial) ?>
      </div>
    <?php endif; ?>

    <div style="flex:1; min-width:0;">
      <h1 style="font-family:var(--font-title); font-weight:200; font-size:1.8rem;
                 margin:0 0 4px; line-height:1.2;">
        <?= e($club['name']) ?>
      </h1>
      <div style="font-size:0.85rem; color:var(--text-muted);">
        <?= $memberCount ?> member<?= $memberCount !== 1 ? 's' : '' ?>
      </div>
    </div>

    <!-- Join / Leave button -->
    <div style="flex-shrink:0; margin-top:4px;">
      <?php if ($isMember): ?>
        <form method="POST" action="/clubs/leave_eval.php" style="margin:0;">
          <?= csrf_input() ?>
          <input type="hidden" name="club_id" value="<?= $clubId ?>">
          <input type="hidden" name="return_to" value="/clubs/view.php?id=<?= $clubId ?>">
          <button type="submit" class="btn btn-secondary"
                  onclick="return confirm('Leave <?= e(addslashes($club['name'])) ?>?')">
            Leave Club
          </button>
        </form>
      <?php else: ?>
        <form method="POST" action="/clubs/join_eval.php" style="margin:0;">
          <?= csrf_input() ?>
          <input type="hidden" name="club_id" value="<?= $clubId ?>">
          <input type="hidden" name="return_to" value="/clubs/view.php?id=<?= $clubId ?>">
          <button type="submit" class="btn btn-primary">Join Club</button>
        </form>
      <?php endif; ?>
    </div>

    <!-- Three-dot admin menu (club admins / app admins only) -->
    <?php if ($canManage): ?>
    <div style="flex-shrink:0; margin-top:4px; position:relative;" id="club-admin-menu-wrap">
      <button type="button" id="club-admin-menu-btn"
              style="background:none; border:1.5px solid var(--border); border-radius:var(--radius-sm);
                     padding:7px 13px; cursor:pointer; font-size:18px; color:var(--text-secondary);
                     line-height:1; transition:background .15s, color .15s;"
              onmouseenter="this.style.background='var(--border-light)';this.style.color='var(--purple-dark)'"
              onmouseleave="this.style.background='none';this.style.color='var(--text-secondary)'"
              onclick="toggleClubAdminMenu(event)"
              title="Club admin menu"
              aria-label="Club admin options">⋯</button>
      <div id="club-admin-menu"
           style="display:none; position:absolute; right:0; top:100%; margin-top:4px;
                  background:var(--surface); border:1px solid var(--border);
                  border-radius:var(--radius-sm); box-shadow:var(--shadow-md);
                  min-width:160px; z-index:50; overflow:hidden;">
        <a href="/clubs/settings.php?id=<?= $clubId ?>" class="admin-panel-link"
           onclick="localStorage.setItem('adminPanelOpen','0')">
          ⚙️ Settings
        </a>
        <a href="/clubs/members.php?id=<?= $clubId ?>" class="admin-panel-link"
           onclick="localStorage.setItem('adminPanelOpen','0')">
          👑 Members
        </a>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Club info card -->
  <div class="card" style="background:var(--surface); border:1px solid var(--border);
                            border-radius:var(--radius); padding:24px;">

    <?php
      $vMeetDays = trim((string)($club['meeting_days'] ?? ''));
      $vMeetLoc  = trim((string)($club['meeting_location'] ?? ''));
      $vDayStr   = '';
      if ($vMeetDays !== '') {
          $dn = array_filter(explode(',', $vMeetDays));
          sort($dn, SORT_NUMERIC);
          $vDayStr = implode(', ', array_map(fn($d) => 'Day ' . trim($d), $dn));
      }
    ?>
    <?php if ($vDayStr !== '' || $vMeetLoc !== ''): ?>
      <div style="margin-bottom:20px;">
        <div style="font-size:0.75rem; font-weight:600; color:var(--text-muted);
                    text-transform:uppercase; letter-spacing:0.06em; margin-bottom:6px;">
          Meets
        </div>
        <?php if ($vDayStr !== ''): ?>
          <div style="color:var(--text-primary); margin-bottom:2px;"><?= e($vDayStr) ?></div>
        <?php endif; ?>
        <?php if ($vMeetLoc !== ''): ?>
          <div style="color:var(--text-secondary); font-size:0.875rem;"><?= e($vMeetLoc) ?></div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php $desc = trim((string)($club['description'] ?? '')); ?>
    <?php if ($desc !== ''): ?>
      <div>
        <div style="font-size:0.75rem; font-weight:600; color:var(--text-muted);
                    text-transform:uppercase; letter-spacing:0.06em; margin-bottom:8px;">
          About
        </div>
        <div style="color:var(--text-primary); line-height:1.65; white-space:pre-line;"><?= e($desc) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($vDayStr === '' && $vMeetLoc === '' && $desc === ''): ?>
      <p style="color:var(--text-muted); font-size:0.875rem;">No details available yet.</p>
    <?php endif; ?>

  </div>

</div>

<script>
function toggleClubAdminMenu(e) {
  e.stopPropagation();
  const m = document.getElementById('club-admin-menu');
  m.style.display = m.style.display === 'block' ? 'none' : 'block';
}
document.addEventListener('click', function() {
  const m = document.getElementById('club-admin-menu');
  if (m) m.style.display = 'none';
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
