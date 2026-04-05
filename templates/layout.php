<?php
declare(strict_types=1);
/**
 * Main authenticated app shell layout.
 *
 * Usage:
 *   $pageTitle = 'Page Name';
 *   ob_start();
 *   // ... your page HTML ...
 *   $content = ob_get_clean();
 *   include __DIR__ . '/../templates/layout.php';
 *
 * Optional variables:
 *   $activeSidebar  — string matching a sidebar item id (e.g. 'calendar', 'profile')
 *   $activeClubId   — int club ID whose panel should auto-open on load
 *   $pageTitle      — string, shown in <title>
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Files.php';
require_once __DIR__ . '/../lib/ClubManagement.php';

$currentUser = Auth::currentUser();
$siteTitle   = Settings::siteTitle();
$logoFileId  = Settings::siteLogoFileId();

// Profile photo URL
$photoUrl = '';
if ($currentUser && !empty($currentUser['photo_public_file_id'])) {
    $photoUrl = Files::profilePhotoUrl((int)$currentUser['photo_public_file_id']);
}

// Initials fallback for avatar placeholder
$initials = '';
if ($currentUser) {
    $initials = strtoupper(
        substr($currentUser['first_name'] ?? '', 0, 1) .
        substr($currentUser['last_name']  ?? '', 0, 1)
    );
    if ($initials === '') $initials = strtoupper(substr($currentUser['email'] ?? '', 0, 1));
}

// Load the current user's club memberships for the sidebar icons
$userSidebarClubs = [];
if ($currentUser) {
    $userSidebarClubs = ClubManagement::listUserMemberships((int)$currentUser['id']);
}

$activeSidebar = $activeSidebar ?? '';
$activeClubId  = isset($activeClubId) ? (int)$activeClubId : 0;
$pageTitle     = isset($pageTitle) ? $pageTitle . ' — ' . $siteTitle : $siteTitle;
$announcement  = trim((string)(Settings::get('announcement') ?? ''));

// Resolve the active club row so we can render the floating nav button on club pages
$_activeClubRow = null;
if ($activeClubId > 0) {
    foreach ($userSidebarClubs as $_sc) {
        if ((int)$_sc['id'] === $activeClubId) {
            $_activeClubRow = $_sc;
            break;
        }
    }
}
$_navBtnPhotoUrl = ($_activeClubRow && $_activeClubRow['photo_public_file_id'])
    ? Files::profilePhotoUrl((int)$_activeClubRow['photo_public_file_id'])
    : '';
$_navBtnInitial  = $_activeClubRow ? strtoupper(substr($_activeClubRow['name'], 0, 1)) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= Application::css_url() ?>">
</head>
<body>

<div class="app-shell<?= $activeClubId > 0 ? ' sidebars-hidden' : '' ?>"<?= $activeClubId > 0 ? ' data-club-page="1"' : '' ?>>

  <?php if ($activeClubId > 0): ?>
  <!-- Floating club-nav button: shown when sidebars are hidden on club pages -->
  <button id="club-nav-toggle"
          title="Open navigation"
          aria-label="Open club navigation"
          onclick="openClubNavFromFloatingBtn(<?= $activeClubId ?>)">
    <?php if ($_navBtnPhotoUrl !== ''): ?>
      <img src="<?= e($_navBtnPhotoUrl) ?>" alt=""
           style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">
    <?php else: ?>
      <div style="width:100%;height:100%;border-radius:50%;
                  background:var(--gradient-brand);color:#fff;
                  display:flex;align-items:center;justify-content:center;
                  font-size:14px;font-weight:600;"><?= e($_navBtnInitial) ?></div>
    <?php endif; ?>
    <!-- Hamburger badge: hints this is a nav button -->
    <div aria-hidden="true"
         style="position:absolute;bottom:-2px;right:-2px;
                width:16px;height:16px;border-radius:50%;
                background:#fff;border:1.5px solid var(--border);
                display:flex;align-items:center;justify-content:center;
                pointer-events:none;box-shadow:0 1px 3px rgba(0,0,0,.15);">
      <svg width="9" height="7" viewBox="0 0 9 7" fill="none"
           xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <line x1="0.5" y1="0.75" x2="8.5" y2="0.75" stroke="#555" stroke-width="1.3" stroke-linecap="round"/>
        <line x1="0.5" y1="3.5"  x2="8.5" y2="3.5"  stroke="#555" stroke-width="1.3" stroke-linecap="round"/>
        <line x1="0.5" y1="6.25" x2="8.5" y2="6.25" stroke="#555" stroke-width="1.3" stroke-linecap="round"/>
      </svg>
    </div>
  </button>
  <?php endif; ?>

  <!-- ===== Left Icon Sidebar ===== -->
  <nav class="sidebar" aria-label="Main navigation">

    <!-- Club icons — one per club the user has joined -->
    <div id="sidebar-clubs" class="sidebar-clubs">
      <?php foreach ($userSidebarClubs as $sc): ?>
        <?php
          $scPhoto   = $sc['photo_public_file_id']
              ? Files::profilePhotoUrl((int)$sc['photo_public_file_id'])
              : '';
          $scInitial = strtoupper(substr($sc['name'], 0, 1));
          $scCid     = (int)$sc['id'];
        ?>
        <button type="button"
                id="club-icon-btn-<?= $scCid ?>"
                class="sidebar-icon-btn<?= $activeClubId === $scCid ? ' active' : '' ?>"
                title="<?= e($sc['name']) ?>"
                aria-label="<?= e($sc['name']) ?>"
                style="padding:2px; background:none; border:none; cursor:pointer;"
                onclick="toggleClubPanel(<?= $scCid ?>)">
          <?php if ($scPhoto !== ''): ?>
            <img src="<?= e($scPhoto) ?>" alt="<?= e($sc['name']) ?>" class="sidebar-avatar">
          <?php else: ?>
            <div class="avatar-placeholder avatar-sm"
                 style="background:var(--gradient-brand);"><?= e($scInitial) ?></div>
          <?php endif; ?>
        </button>
      <?php endforeach; ?>
    </div>

    <!-- Browse / join clubs -->
    <a href="/clubs/browse.php"
       class="sidebar-icon-btn <?= $activeSidebar === 'browse-clubs' ? 'active' : '' ?>"
       title="Browse &amp; Join Clubs" aria-label="Browse and join clubs">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <line x1="12" y1="5" x2="12" y2="19"/>
        <line x1="5"  y1="12" x2="19" y2="12"/>
      </svg>
    </a>

    <div class="sidebar-spacer"></div>

    <!-- Calendar -->
    <a href="/clubs/events/calendar.php"
       class="sidebar-icon-btn <?= $activeSidebar === 'calendar' ? 'active' : '' ?>"
       title="Calendar" aria-label="Calendar">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
        <line x1="16" y1="2" x2="16" y2="6"/>
        <line x1="8"  y1="2" x2="8"  y2="6"/>
        <line x1="3"  y1="10" x2="21" y2="10"/>
      </svg>
    </a>

    <?php if ($currentUser && !empty($currentUser['is_admin'])): ?>
    <!-- Admin toggle button (app admin only) -->
    <button id="admin-panel-btn"
            class="sidebar-icon-btn"
            title="Admin Menu" aria-label="Toggle Admin Menu"
            aria-expanded="false" aria-controls="admin-panel">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="3"/>
        <path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/>
        <path d="M12 2v2M12 20v2M2 12h2M20 12h2"/>
      </svg>
    </button>
    <?php endif; ?>

    <!-- Profile photo / avatar -->
    <a href="/profile/index.php"
       class="sidebar-icon-btn <?= $activeSidebar === 'profile' ? 'active' : '' ?>"
       title="My Profile" aria-label="My Profile"
       style="padding: 2px;">
      <?php if ($photoUrl !== ''): ?>
        <img src="<?= e($photoUrl) ?>" alt="Your profile photo" class="sidebar-avatar">
      <?php else: ?>
        <div class="avatar-placeholder avatar-sm" aria-hidden="true"><?= e($initials) ?></div>
      <?php endif; ?>
    </a>

  </nav>

  <?php if ($currentUser && !empty($currentUser['is_admin'])): ?>
  <!-- ===== Admin Panel ===== -->
  <aside class="admin-panel panel-hidden" id="admin-panel" aria-label="Admin navigation">
    <div class="club-panel-header">
      <span class="club-panel-title">Admin</span>
      <button class="club-panel-toggle" id="admin-panel-close"
              title="Close admin menu" aria-label="Close admin menu">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <line x1="18" y1="6" x2="6" y2="18"/>
          <line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <nav class="admin-panel-nav">
      <a href="/admin/clubs/index.php"  class="admin-panel-link" style="display:flex;align-items:center;gap:7px;">
        <span style="display:inline-flex;flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#7C3AED" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
        Clubs
      </a>
      <a href="/admin/users/index.php"  class="admin-panel-link" style="display:flex;align-items:center;gap:7px;">
        <span style="display:inline-flex;flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#038BFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg></span>
        Users
      </a>
      <a href="/admin/settings.php"     class="admin-panel-link" style="display:flex;align-items:center;gap:7px;">
        <span style="display:inline-flex;flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg></span>
        Settings
      </a>
      <a href="/admin/reports.php"      class="admin-panel-link" style="display:flex;align-items:center;gap:7px;">
        <span style="display:inline-flex;flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#F59E0B" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg></span>
        Reports
      </a>
    </nav>
  </aside>
  <?php endif; ?>

  <!-- ===== Club Panels ===== -->
  <?php foreach ($userSidebarClubs as $sc): ?>
    <?php
      $scPanelPhoto  = $sc['photo_public_file_id']
          ? Files::profilePhotoUrl((int)$sc['photo_public_file_id'])
          : '';
      $scPanelInitial = strtoupper(substr($sc['name'], 0, 1));
      $scIsClubAdmin  = !empty($sc['is_club_admin']) || !empty($currentUser['is_admin']);
      $scCid          = (int)$sc['id'];
      $scIsActive     = ($activeClubId === $scCid);
    ?>
    <aside class="admin-panel<?= $scIsActive ? '' : ' panel-hidden' ?>"
           id="club-panel-<?= $scCid ?>"
           data-club-id="<?= $scCid ?>"
           aria-label="<?= e($sc['name']) ?> navigation">

      <div class="club-panel-header">
        <div style="display:flex; align-items:center; gap:10px; flex:1; min-width:0; overflow:hidden;">
          <?php if ($scPanelPhoto !== ''): ?>
            <img src="<?= e($scPanelPhoto) ?>" class="avatar"
                 style="width:32px; height:32px; flex-shrink:0;" alt="">
          <?php else: ?>
            <div class="avatar-placeholder"
                 style="width:32px; height:32px; font-size:13px; flex-shrink:0;
                        background:var(--gradient-brand);"><?= e($scPanelInitial) ?></div>
          <?php endif; ?>
          <span class="club-panel-title"
                style="font-weight:700; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
            <?= e($sc['name']) ?>
          </span>
        </div>
        <button class="club-panel-toggle"
                onclick="toggleClubPanel(<?= $scCid ?>)"
                title="Close" aria-label="Close <?= e($sc['name']) ?> panel">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2"
               stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18"/>
            <line x1="6" y1="6" x2="18" y2="18"/>
          </svg>
        </button>
      </div>

      <nav class="admin-panel-nav">
        <a href="/clubs/view.php?id=<?= $scCid ?>"
           class="admin-panel-link<?= ($scIsActive && $activeSidebar === 'club-info') ? ' active' : '' ?>"
           style="display:flex;align-items:center;gap:7px;">
          <span style="display:inline-flex;flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#038BFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
          Club Info
        </a>
        <a href="/clubs/members.php?id=<?= $scCid ?>"
           class="admin-panel-link<?= ($scIsActive && $activeSidebar === 'club-members') ? ' active' : '' ?>"
           style="display:flex;align-items:center;gap:7px;">
          <span style="display:inline-flex;flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#7C3AED" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
          Members
        </a>
        <a href="/clubs/events/index.php?id=<?= $scCid ?>"
           class="admin-panel-link<?= ($scIsActive && $activeSidebar === 'club-events') ? ' active' : '' ?>"
           style="display:flex;align-items:center;gap:7px;">
          <span style="display:inline-flex;flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FF6B47" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg></span>
          Events
        </a>
        <?php if ($scIsClubAdmin): ?>
          <a href="/clubs/settings.php?id=<?= $scCid ?>"
             class="admin-panel-link<?= ($scIsActive && $activeSidebar === 'club-settings') ? ' active' : '' ?>"
             style="display:flex;align-items:center;gap:7px;">
            <span style="display:inline-flex;flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg></span>
            Settings
          </a>
        <?php endif; ?>
        <span class="admin-panel-link"
              style="display:flex;align-items:flex-start;gap:7px;color:var(--text-muted); cursor:default; font-style:italic;">
          <span style="display:inline-flex;flex-shrink:0;margin-top:1px;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#06B6D4" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></span>
          <span>Chat Threads<small style="font-size:10px; display:block; margin-top:1px;">coming soon</small></span>
        </span>
      </nav>

    </aside>
  <?php endforeach; ?>

  <!-- ===== Main Content ===== -->
  <main class="main-content" id="main-content">
    <?= Flash::render() ?>
    <?php if ($announcement !== ''): ?>
      <div class="announcement-banner" role="alert">
        <span class="announcement-icon" aria-hidden="true">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M22 3L2 10l8 3 3 8 9-18z"/>
          </svg>
        </span>
        <span class="announcement-text"><?= e($announcement) ?></span>
      </div>
    <?php endif; ?>
    <?php
      // On club pages, show a centered bold section heading above the content.
      $_sidebarLabels = [
          'club-info'     => 'Club Info',
          'club-events'   => 'Events',
          'club-members'  => 'Members',
          'club-settings' => 'Settings',
      ];
      $_sectionLabel = ($activeClubId > 0 && isset($_sidebarLabels[$activeSidebar]))
          ? $_sidebarLabels[$activeSidebar]
          : '';
    ?>
    <?php if ($_sectionLabel !== ''): ?>
      <div id="club-page-title" aria-hidden="true">
        <?= e($_sectionLabel) ?>
      </div>
    <?php endif; ?>
    <?= $content ?? '' ?>
  </main>

</div><!-- .app-shell -->

<?php if (isset($extraJs)) echo $extraJs; ?>

<?php
// Consume the session confetti flag — set by join_eval.php on a successful join.
$_layoutConfetti = !empty($_SESSION['_confetti']);
if ($_layoutConfetti) unset($_SESSION['_confetti']);
?>
<?php if ($_layoutConfetti): ?>
<script>
/* ── Club-join confetti ──────────────────────────────────────────────────── */
(function () {
  // Clean the ?celebrate=1 from the URL immediately so a refresh doesn't re-fire.
  try {
    const _url = new URL(window.location.href);
    _url.searchParams.delete('celebrate');
    history.replaceState(null, '', _url.toString());
  } catch (e) {}

  const COLORS = [
    '#FF2200', // neon red-orange
    '#FF6600', // neon orange
    '#FFD700', // gold
    '#FFEE00', // electric yellow
    '#00FF66', // neon green
    '#00EEFF', // electric cyan
    '#0088FF', // vivid blue
    '#CC00FF', // neon purple
    '#FF00CC', // neon magenta
    '#FF3399', // hot pink
    '#FFFFFF', // white (for sparkle)
    '#FF9900', // bright amber
  ];
  const SHAPES = ['rect', 'circle', 'ribbon'];

  // Canvas
  const canvas = document.createElement('canvas');
  canvas.style.cssText =
    'position:fixed;top:0;left:0;width:100%;height:100%;' +
    'z-index:9999;pointer-events:none;';
  document.body.appendChild(canvas);
  const ctx2d = canvas.getContext('2d');

  function resize() {
    canvas.width  = window.innerWidth;
    canvas.height = window.innerHeight;
  }
  resize();
  window.addEventListener('resize', resize);

  const particles = [];

  function mkParticle(forcedX) {
    const shape = SHAPES[Math.floor(Math.random() * SHAPES.length)];
    const isRibbon = shape === 'ribbon';
    return {
      x:  forcedX != null ? forcedX : Math.random() * canvas.width,
      y:  -20 - Math.random() * 120,
      vx: (Math.random() - 0.5) * 7,
      vy:  2.5 + Math.random() * 5,
      g:   0.10 + Math.random() * 0.10,
      rot:  Math.random() * Math.PI * 2,
      rotV: (Math.random() - 0.5) * 0.22,
      wob:  Math.random() * Math.PI * 2,
      wobS: 0.04 + Math.random() * 0.09,
      wobA: 0.8  + Math.random() * 2.2,
      w:   isRibbon ? 4 + Math.random() * 4  : 7  + Math.random() * 9,
      h:   isRibbon ? 14 + Math.random() * 10 : 7  + Math.random() * 9,
      shape,
      color: COLORS[Math.floor(Math.random() * COLORS.length)],
      alpha: 1,
      decay: 0.0035 + Math.random() * 0.004,
    };
  }

  function wave(n, delay) {
    setTimeout(() => {
      for (let i = 0; i < n; i++) particles.push(mkParticle());
    }, delay);
  }

  // Three staggered waves for a big dramatic cascade.
  wave(90, 0);
  wave(90, 350);
  wave(80, 720);

  let t0 = null;
  const FADE_AFTER = 3200; // ms before particles start fading
  const GIVE_UP   = 8000; // ms hard stop

  function frame(ts) {
    if (!t0) t0 = ts;
    ctx2d.clearRect(0, 0, canvas.width, canvas.height);

    for (let i = particles.length - 1; i >= 0; i--) {
      const p = particles[i];
      p.vy  += p.g;
      p.wob += p.wobS;
      p.x   += p.vx + Math.sin(p.wob) * p.wobA;
      p.y   += p.vy;
      p.rot += p.rotV;

      if (ts - t0 > FADE_AFTER) p.alpha -= p.decay;

      if (p.y > canvas.height + 60 || p.alpha <= 0) {
        particles.splice(i, 1);
        continue;
      }

      ctx2d.save();
      ctx2d.globalAlpha = Math.max(0, p.alpha);
      ctx2d.translate(p.x, p.y);
      ctx2d.rotate(p.rot);
      ctx2d.fillStyle = p.color;

      if (p.shape === 'circle') {
        ctx2d.beginPath();
        ctx2d.arc(0, 0, p.w / 2, 0, Math.PI * 2);
        ctx2d.fill();
      } else {
        ctx2d.fillRect(-p.w / 2, -p.h / 2, p.w, p.h);
      }
      ctx2d.restore();
    }

    if (particles.length > 0 && ts - t0 < GIVE_UP) {
      requestAnimationFrame(frame);
    } else {
      canvas.remove();
      window.removeEventListener('resize', resize);
    }
  }

  requestAnimationFrame(frame);
})();
</script>
<?php endif; ?>

<script>
// ─── Club panel toggle ─────────────────────────────────────────────────────
(function () {
  // On club pages ($activeClubId > 0) the app-shell starts with .sidebars-hidden
  // so the content fills the whole viewport.  The floating #club-nav-toggle button
  // (top-left corner) reveals the sidebar + club panel.  Closing the panel via the
  // ✕ button or clicking the same icon again returns to full-width mode.

  function isClubPage() {
    const shell = document.querySelector('.app-shell');
    return shell ? shell.hasAttribute('data-club-page') : false;
  }

  function hideSidebarsIfClubPage() {
    if (!isClubPage()) return;
    const shell = document.querySelector('.app-shell');
    if (shell) shell.classList.add('sidebars-hidden');
  }

  function closeAllPanels() {
    document.querySelectorAll('.admin-panel').forEach(p => p.classList.add('panel-hidden'));
    document.querySelectorAll('[id^="club-icon-btn-"]').forEach(b => b.classList.remove('active'));
    const adminBtn = document.getElementById('admin-panel-btn');
    if (adminBtn) {
      adminBtn.classList.remove('active');
      adminBtn.setAttribute('aria-expanded', 'false');
    }
  }

  window.toggleClubPanel = function (clubId) {
    const panel   = document.getElementById('club-panel-' + clubId);
    const iconBtn = document.getElementById('club-icon-btn-' + clubId);
    if (!panel) return;
    const wasHidden = panel.classList.contains('panel-hidden');
    closeAllPanels();
    if (wasHidden) {
      // Opening a panel — make sure the sidebar rail is visible
      const shell = document.querySelector('.app-shell');
      if (shell) shell.classList.remove('sidebars-hidden');
      panel.classList.remove('panel-hidden');
      if (iconBtn) iconBtn.classList.add('active');
    } else {
      // Closing the last panel — on club pages return to full-width mode
      hideSidebarsIfClubPage();
    }
  };

  // ── Floating nav button (club pages only) ────────────────────────────────
  window.openClubNavFromFloatingBtn = function (clubId) {
    // Show the icon rail, then open the panel
    const shell = document.querySelector('.app-shell');
    if (shell) shell.classList.remove('sidebars-hidden');
    toggleClubPanel(clubId);
  };

  // ─── Admin panel toggle ──────────────────────────────────────────────────
  const adminBtn   = document.getElementById('admin-panel-btn');
  const closeBtn   = document.getElementById('admin-panel-close');
  const adminPanel = document.getElementById('admin-panel');
  if (adminBtn && adminPanel) {
    adminBtn.addEventListener('click', () => {
      if (adminPanel.classList.contains('panel-hidden')) {
        closeAllPanels();
        const shell = document.querySelector('.app-shell');
        if (shell) shell.classList.remove('sidebars-hidden');
        adminPanel.classList.remove('panel-hidden');
        adminBtn.classList.add('active');
        adminBtn.setAttribute('aria-expanded', 'true');
      } else {
        closeAllPanels();
        hideSidebarsIfClubPage();
      }
    });
    if (closeBtn) {
      closeBtn.addEventListener('click', () => {
        closeAllPanels();
        hideSidebarsIfClubPage();
      });
    }
  }

  // On mobile, close any auto-opened panel so the user isn't blocked after
  // clicking a nav link inside the panel.
  if (window.matchMedia('(max-width: 768px)').matches) {
    closeAllPanels();
    // Don't hide sidebars on mobile — the sidebar is already off-screen
  }
})();
</script>

</body>
</html>
