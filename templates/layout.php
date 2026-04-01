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
 *   $pageTitle      — string, shown in <title>
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Files.php';

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

$activeSidebar = $activeSidebar ?? '';
$pageTitle     = isset($pageTitle) ? $pageTitle . ' — ' . $siteTitle : $siteTitle;
$announcement  = trim((string)(Settings::get('announcement') ?? ''));
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

<div class="app-shell">

  <!-- ===== Left Icon Sidebar ===== -->
  <nav class="sidebar" aria-label="Main navigation">

    <!-- Club icons scroll area (populated by clubs feature) -->
    <div id="sidebar-clubs" class="sidebar-clubs">
      <!-- Club profile photos injected here by clubs feature -->
    </div>

    <div class="sidebar-spacer"></div>

    <!-- Calendar -->
    <a href="/clubs/events/calendar.php"
       class="sidebar-icon-btn <?= $activeSidebar === 'calendar' ? 'active' : '' ?>"
       title="Calendar" aria-label="Calendar">
      <!-- Calendar icon (inline SVG, no external dependency) -->
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
      <a href="/admin/settings.php"     class="admin-panel-link">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.07 4.93a10 10 0 0 1 0 14.14M4.93 4.93a10 10 0 0 0 0 14.14"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2"/></svg>
        Settings
      </a>
      <a href="/admin/activity_log.php" class="admin-panel-link">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
        Activity Log
      </a>
      <a href="/admin/email_log.php"    class="admin-panel-link">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        Email Log
      </a>
      <a href="/admin/users/index.php"  class="admin-panel-link">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Users
      </a>
    </nav>
  </aside>
  <?php endif; ?>

  <!-- ===== Club Panel (injected by club pages) ===== -->
  <aside class="club-panel" id="club-panel" aria-label="Club navigation">
    <div class="club-panel-header">
      <span class="club-panel-title">Clubs</span>
      <button class="club-panel-toggle" id="club-panel-toggle"
              title="Collapse panel" aria-label="Toggle club panel"
              aria-expanded="true" aria-controls="club-panel-body">
        <!-- Chevron left — rotates to point right when collapsed -->
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <polyline points="15 18 9 12 15 6"/>
        </svg>
      </button>
    </div>
    <div class="club-panel-body" id="club-panel-body">
      <?php if (isset($clubPanelContent)) echo $clubPanelContent; ?>
    </div>
  </aside>

  <!-- ===== Main Content ===== -->
  <main class="main-content" id="main-content">
    <?= Flash::render() ?>
    <?php if ($announcement !== ''): ?>
      <div class="announcement-banner" role="alert">
        <span class="announcement-icon" aria-hidden="true">📢</span>
        <span class="announcement-text"><?= e($announcement) ?></span>
      </div>
    <?php endif; ?>
    <?= $content ?? '' ?>
  </main>

</div><!-- .app-shell -->

<?php if (isset($extraJs)) echo $extraJs; ?>

<script>
// ─── Club panel collapse/expand ───────────────────────────────────────────
(function () {
  const panel = document.getElementById('club-panel');
  const btn   = document.getElementById('club-panel-toggle');
  if (!panel || !btn) return;

  const STORAGE_KEY = 'clubPanelCollapsed';

  function setCollapsed(collapsed, animate) {
    if (!animate) panel.style.transition = 'none';
    panel.classList.toggle('collapsed', collapsed);
    btn.setAttribute('aria-expanded', String(!collapsed));
    btn.title = collapsed ? 'Expand panel' : 'Collapse panel';
    localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
    if (!animate) {
      requestAnimationFrame(() => { panel.style.transition = ''; });
    }
  }

  const saved = localStorage.getItem(STORAGE_KEY);
  if (saved === '1') setCollapsed(true, false);

  btn.addEventListener('click', () => {
    setCollapsed(!panel.classList.contains('collapsed'), true);
  });
})();

// ─── Admin panel toggle ────────────────────────────────────────────────────
(function () {
  const adminBtn   = document.getElementById('admin-panel-btn');
  const closeBtn   = document.getElementById('admin-panel-close');
  const adminPanel = document.getElementById('admin-panel');
  const clubPanel  = document.getElementById('club-panel');
  if (!adminBtn || !adminPanel) return;

  const STORAGE_KEY = 'adminPanelOpen';

  function openAdminPanel() {
    // Hide the club panel while admin is showing
    if (clubPanel) clubPanel.classList.add('panel-hidden');
    adminPanel.classList.remove('panel-hidden');
    adminBtn.classList.add('active');
    adminBtn.setAttribute('aria-expanded', 'true');
    localStorage.setItem(STORAGE_KEY, '1');
  }

  function closeAdminPanel() {
    adminPanel.classList.add('panel-hidden');
    // Restore club panel
    if (clubPanel) clubPanel.classList.remove('panel-hidden');
    adminBtn.classList.remove('active');
    adminBtn.setAttribute('aria-expanded', 'false');
    localStorage.setItem(STORAGE_KEY, '0');
  }

  // Restore saved state on load (no animation needed)
  if (localStorage.getItem(STORAGE_KEY) === '1') {
    openAdminPanel();
  }

  adminBtn.addEventListener('click', () => {
    if (adminPanel.classList.contains('panel-hidden')) {
      openAdminPanel();
    } else {
      closeAdminPanel();
    }
  });

  if (closeBtn) {
    closeBtn.addEventListener('click', () => closeAdminPanel());
  }
})();
</script>

</body>
</html>
