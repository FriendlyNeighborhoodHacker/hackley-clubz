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
require_once __DIR__ . '/../lib/ConversationManagement.php';

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
$userConversationsByClub = [];
if ($currentUser) {
    $userSidebarClubs        = ClubManagement::listUserMemberships((int)$currentUser['id']);
    $userConversationsByClub = ConversationManagement::listAllConversationsForUser((int)$currentUser['id']);
}

$activeSidebar        = $activeSidebar ?? '';
$activeClubId         = isset($activeClubId) ? (int)$activeClubId : 0;
$activeConversationId = isset($activeConversationId) ? (int)$activeConversationId : 0;
$layoutFullWidth      = !empty($layoutFullWidth); // pages can force full-width by setting this
// Optional page-level topbar overrides
$pageTopbarTitle   = $pageTopbarTitle   ?? null;  // if set, replaces the default context title, centered + bold
$pageTopbarActions = $pageTopbarActions ?? '';     // HTML rendered in the right slot of the topbar
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

// ── Topbar context: icon HTML + title text ─────────────────────────────────
$_isAdminPage = (strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/') !== false);

if ($activeClubId > 0 && $_activeClubRow) {
    $_topbarTitle   = $_activeClubRow['name'];
    $_topbarIconHtml = $_navBtnPhotoUrl !== ''
        ? '<img src="' . e($_navBtnPhotoUrl) . '" alt="">'
        : '<div style="width:100%;height:100%;background:var(--gradient-brand);color:#fff;'
          . 'display:flex;align-items:center;justify-content:center;'
          . 'font-size:13px;font-weight:700;">' . e($_navBtnInitial) . '</div>';
} elseif ($_isAdminPage) {
    $_topbarTitle    = 'Admin';
    $_topbarIconHtml = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none"'
        . ' stroke="currentColor" stroke-width="1.8" stroke-linecap="round"'
        . ' stroke-linejoin="round" aria-hidden="true">'
        . '<circle cx="12" cy="12" r="3"/>'
        . '<path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41'
        . 'M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/>'
        . '</svg>';
} else {
    $fn = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
    $_topbarTitle    = $fn !== '' ? $fn : ($currentUser['email'] ?? 'Me');
    $_topbarIconHtml = $photoUrl !== ''
        ? '<img src="' . e($photoUrl) . '" alt="">'
        : '<div style="width:100%;height:100%;background:var(--gradient-brand);color:#fff;'
          . 'display:flex;align-items:center;justify-content:center;'
          . 'font-size:13px;font-weight:700;">' . e($initials) . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= e($pageTitle) ?></title>
  <link rel="stylesheet" href="<?= Application::css_url() ?>">
  <style>
    /* ── Hamburger badge on the topbar nav button ── */
    .topbar-nav-btn { position: relative; }
    .topbar-nav-hamburger {
      position: absolute; bottom: 1px; right: 1px;
      width: 14px; height: 12px;
      background: rgba(0,0,0,0.52);
      border-radius: 3px;
      display: flex; align-items: center; justify-content: center;
      color: #fff; pointer-events: none;
    }
    /* ── Centered page topbar title (used on chat pages etc.) ── */
    .topbar-title--centered {
      position: absolute; left: 50%; transform: translateX(-50%);
      font-weight: 700; font-size: 15px;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
      max-width: 55%; pointer-events: none;
    }
    /* ── Right-side topbar action slot ── */
    .topbar-actions {
      margin-left: auto;
      display: flex; align-items: center; gap: 6px;
      padding-right: 4px;
      flex-shrink: 0;
    }
  </style>
</head>
<body>

<div class="app-shell">

  <!-- ===== Top Bar (Full Width mode) ===== -->
  <header class="topbar" id="topbar">
    <button class="topbar-nav-btn"
            onclick="enterMenuLayout(<?= json_encode($activeClubId) ?>)"
            title="Open navigation" aria-label="Open navigation">
      <?= $_topbarIconHtml ?>
      <span class="topbar-nav-hamburger" aria-hidden="true">
        <svg width="10" height="8" viewBox="0 0 10 8" fill="none">
          <rect y="0"    width="10" height="1.5" rx=".75" fill="white"/>
          <rect y="3.25" width="10" height="1.5" rx=".75" fill="white"/>
          <rect y="6.5"  width="10" height="1.5" rx=".75" fill="white"/>
        </svg>
      </span>
    </button>
    <?php if ($pageTopbarTitle !== null): ?>
      <span class="topbar-title topbar-title--centered"><?= $pageTopbarTitle ?></span>
    <?php else: ?>
      <span class="topbar-title"><?= e($_topbarTitle) ?></span>
    <?php endif; ?>
    <?php if ($pageTopbarActions !== ''): ?>
      <div class="topbar-actions"><?= $pageTopbarActions ?></div>
    <?php endif; ?>
  </header>

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

    <!-- Collapse to Full Width — up-chevron above calendar -->
    <button type="button"
            class="sidebar-icon-btn sidebar-collapse-btn"
            title="Hide menu" aria-label="Switch to full width view"
            onclick="enterFullWidth()">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <polyline points="18 15 12 9 6 15"/>
      </svg>
    </button>

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
      <a href="/admin/maintenance.php"  class="admin-panel-link<?= $activeSidebar === 'admin-maintenance' ? ' active' : '' ?>" style="display:flex;align-items:center;gap:7px;">
        <span style="display:inline-flex;flex-shrink:0;"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#10B981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg></span>
        Maintenance
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
        <?php
        // ── Chat Threads ──────────────────────────────────────────────────
        $scConvs = $userConversationsByClub[$scCid] ?? [];
        if (!empty($scConvs)):
        ?>
        <div style="padding:5px 12px 1px;font-size:10px;font-weight:700;letter-spacing:.5px;
                    text-transform:uppercase;color:var(--text-muted);">Chats</div>
        <?php foreach ($scConvs as $sc_conv): ?>
          <?php
            $convIsActive = ($activeConversationId > 0 && (int)$sc_conv['id'] === $activeConversationId);
            $lockMark     = $sc_conv['is_secret']
                ? ' <span title="Private" aria-label="Private" style="display:inline-flex;vertical-align:-2px;opacity:.7;">'
                  . '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
                  . ' stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                  . '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>'
                  . '<path d="M7 11V7a5 5 0 0 1 10 0v4"/>'
                  . '</svg></span>'
                : '';
          ?>
          <a href="/clubs/conversations/view.php?id=<?= (int)$sc_conv['id'] ?>"
             class="admin-panel-link<?= $convIsActive ? ' active' : '' ?>"
             style="display:flex;align-items:center;gap:7px;">
            <span style="display:inline-flex;flex-shrink:0;">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#06B6D4"
                   stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
              </svg>
            </span>
            <?= e($sc_conv['name']) ?><?= $lockMark ?>
          </a>
        <?php endforeach; ?>
        <?php endif; ?>
        <div id="joinable-area-<?= $scCid ?>" style="display:none;border-top:1px solid var(--border);padding:2px 0;"></div>

        <!-- ── Bottom actions: New Chat + Join more chats ─────────────────── -->
        <div style="margin-top:auto;border-top:1px solid var(--border);padding:6px 0 4px;">
          <?php if ($scIsClubAdmin): ?>
            <a href="/clubs/conversations/create.php?club_id=<?= $scCid ?>"
               class="admin-panel-link"
               style="display:flex;align-items:center;gap:7px;">
              <span style="display:inline-flex;flex-shrink:0;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#038BFF"
                     stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                  <circle cx="12" cy="12" r="10"/>
                  <line x1="12" y1="8" x2="12" y2="16"/>
                  <line x1="8" y1="12" x2="16" y2="12"/>
                </svg>
              </span>
              New Chat
            </a>
          <?php endif; ?>
          <div style="padding:4px 12px 2px;">
            <button type="button"
                    onclick="loadJoinable(<?= $scCid ?>)"
                    style="background:none;border:none;cursor:pointer;font-size:12px;
                           font-weight:700;color:#038BFF;padding:3px 0;">
              ＋ Join more chats
            </button>
          </div>
        </div>
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
  const SHAPES = ['rect', 'circle', 'ribbon'];

  // Maximally saturated colors — full hue wheel at 100% sat, 60% lightness
  const COLORS = Array.from({length: 36}, (_, i) =>
    'hsl(' + (i * 10) + ',100%,60%)'
  );
  // Sprinkle in some bright whites and near-whites for sparkle
  COLORS.push('#ffffff', '#fffbe6', '#e0f7ff');

  // Canvas — full screen, above everything, click-through
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

  function mkParticle() {
    const shape    = SHAPES[Math.floor(Math.random() * SHAPES.length)];
    const isRibbon = shape === 'ribbon';
    return {
      // Spread evenly across the full width; start at various depths in the
      // top 40% of the viewport so the whole page is covered instantly.
      x:    Math.random() * canvas.width,
      y:   -10 - Math.random() * (canvas.height * 0.4),
      vx:   (Math.random() - 0.5) * 9,
      vy:   4 + Math.random() * 7,        // fast initial fall
      g:    0.08 + Math.random() * 0.08,  // gentle gravity
      rot:  Math.random() * Math.PI * 2,
      rotV: (Math.random() - 0.5) * 0.28,
      wob:  Math.random() * Math.PI * 2,
      wobS: 0.05 + Math.random() * 0.10,
      wobA: 1.0  + Math.random() * 2.5,
      w:    isRibbon ? 3 + Math.random() * 4  : 8  + Math.random() * 10,
      h:    isRibbon ? 14 + Math.random() * 12 : 8  + Math.random() * 10,
      shape,
      color: COLORS[Math.floor(Math.random() * COLORS.length)],
      alpha: 1,
      // Faster decay so each piece vanishes quickly (ephemeral)
      decay: 0.012 + Math.random() * 0.010,
    };
  }

  // Single massive burst — 500 pieces, all at once, covering the whole page
  const particles = [];
  for (let i = 0; i < 500; i++) particles.push(mkParticle());

  let t0 = null;
  const FADE_AFTER = 800;  // ms — start fading almost immediately
  const GIVE_UP    = 3500; // ms hard stop

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
// ─── Navigation mode + panel toggle ──────────────────────────────────────
(function () {
  var shell          = document.querySelector('.app-shell');
  var activeClubId   = <?= json_encode($activeClubId) ?>;
  var forceFullWidth = <?= json_encode($layoutFullWidth) ?>;

  // ── Always start with all panels closed (server may render one open) ─────
  closeAllPanels();

  // ── Set initial mode based on viewport width ────────────────────────────
  // Desktop (≥ 769px): Menu Layout — show sidebar, open active club panel.
  // Mobile  (< 769px): Full Width  — CSS default, no extra class needed.
  // Pages that set $layoutFullWidth = true always stay in Full Width mode.
  if (!forceFullWidth && window.innerWidth >= 769) {
    shell.classList.add('nav-menu');
    if (activeClubId > 0) {
      var initPanel   = document.getElementById('club-panel-' + activeClubId);
      var initIconBtn = document.getElementById('club-icon-btn-' + activeClubId);
      if (initPanel)   initPanel.classList.remove('panel-hidden');
      if (initIconBtn) initIconBtn.classList.add('active');
    }
  }

  // ── Responsive resize: switch modes when crossing the 769px threshold ───
  var _resizeTimer;
  window.addEventListener('resize', function () {
    clearTimeout(_resizeTimer);
    _resizeTimer = setTimeout(function () {
      if (forceFullWidth) return; // page forces full-width regardless of viewport
      if (window.innerWidth < 769) {
        // Shrank to mobile — switch to Full Width
        if (shell.classList.contains('nav-menu')) {
          closeAllPanels();
          shell.classList.remove('nav-menu');
        }
      } else {
        // Expanded to desktop — switch to Menu Layout
        if (!shell.classList.contains('nav-menu')) {
          shell.classList.add('nav-menu');
          if (activeClubId > 0) {
            var rp = document.getElementById('club-panel-' + activeClubId);
            var rb = document.getElementById('club-icon-btn-' + activeClubId);
            if (rp) rp.classList.remove('panel-hidden');
            if (rb) rb.classList.add('active');
          }
        }
      }
    }, 100);
  });

  // ── Shared helper: close every panel without changing nav mode ──────────
  function closeAllPanels() {
    document.querySelectorAll('.admin-panel').forEach(function (p) {
      p.classList.add('panel-hidden');
    });
    document.querySelectorAll('[id^="club-icon-btn-"]').forEach(function (b) {
      b.classList.remove('active');
    });
    var adminBtn = document.getElementById('admin-panel-btn');
    if (adminBtn) {
      adminBtn.classList.remove('active');
      adminBtn.setAttribute('aria-expanded', 'false');
    }
  }

  // ── Enter Full Width mode (topbar visible, sidebar hidden) ───────────────
  window.enterFullWidth = function () {
    closeAllPanels();
    shell.classList.remove('nav-menu');
  };

  // ── Enter Menu Layout mode (sidebar visible, topbar hidden) ─────────────
  // clubId: if > 0, also open that club's panel immediately.
  window.enterMenuLayout = function (clubId) {
    shell.classList.add('nav-menu');
    clubId = clubId || 0;
    if (clubId > 0) {
      closeAllPanels();
      var p = document.getElementById('club-panel-' + clubId);
      var b = document.getElementById('club-icon-btn-' + clubId);
      if (p) p.classList.remove('panel-hidden');
      if (b) b.classList.add('active');
    }
  };

  // ── Toggle a club panel (clicking its icon in the sidebar) ──────────────
  window.toggleClubPanel = function (clubId) {
    var panel   = document.getElementById('club-panel-' + clubId);
    var iconBtn = document.getElementById('club-icon-btn-' + clubId);
    if (!panel) return;
    var wasHidden = panel.classList.contains('panel-hidden');
    closeAllPanels();
    if (wasHidden) {
      panel.classList.remove('panel-hidden');
      if (iconBtn) iconBtn.classList.add('active');
    }
    // Closing a panel does NOT exit menu-layout mode
  };

  // ── Join more chats (inline AJAX in club panel) ───────────────────────
  window.loadJoinable = function (clubId) {
    var area = document.getElementById('joinable-area-' + clubId);
    if (!area) return;
    if (area.style.display !== 'none') { area.style.display = 'none'; return; }
    area.innerHTML = '<p style="font-size:11px;color:var(--text-muted);padding:4px 8px;margin:0;">Loading…</p>';
    area.style.display = 'block';
    fetch('/clubs/conversations/ajax_joinable.php?club_id=' + clubId)
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (d.success) { area.innerHTML = d.html; }
        else { area.innerHTML = '<p style="font-size:11px;color:red;padding:4px 8px;margin:0;">Could not load chats.</p>'; }
      })
      .catch(function () { area.innerHTML = '<p style="font-size:11px;color:red;padding:4px 8px;margin:0;">Network error.</p>'; });
  };

  // ── Admin panel toggle ───────────────────────────────────────────────────
  var adminBtn   = document.getElementById('admin-panel-btn');
  var adminClose = document.getElementById('admin-panel-close');
  var adminPanel = document.getElementById('admin-panel');
  if (adminBtn && adminPanel) {
    adminBtn.addEventListener('click', function () {
      if (adminPanel.classList.contains('panel-hidden')) {
        closeAllPanels();
        adminPanel.classList.remove('panel-hidden');
        adminBtn.classList.add('active');
        adminBtn.setAttribute('aria-expanded', 'true');
      } else {
        closeAllPanels();
      }
    });
    if (adminClose) {
      adminClose.addEventListener('click', function () { closeAllPanels(); });
    }
  }

})();
</script>

</body>
</html>
