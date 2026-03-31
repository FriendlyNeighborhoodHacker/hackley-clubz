<?php
declare(strict_types=1);

/**
 * Application homepage.
 * Redirects unauthenticated users to login.
 * Authenticated users see their club dashboard (stub for now — clubs feature next).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Application.php';
require_once __DIR__ . '/lib/Settings.php';
require_once __DIR__ . '/lib/Files.php';
require_once __DIR__ . '/auth.php';


Application::init();
Auth::requireLogin();


$user      = Auth::currentUser();
$firstName = $user['first_name'] ?? '';
$siteTitle = Settings::siteTitle();

$pageTitle = 'Home';

ob_start();
?>
<div style="max-width:640px; margin:0 auto; padding-top:24px;">

  <?php if ($firstName !== ''): ?>
    <p class="prompt" style="margin-bottom:8px;">
      Hello, <em><?= e($firstName) ?>.</em>
    </p>
  <?php else: ?>
    <p class="prompt" style="margin-bottom:8px;">
      <em>Welcome to <?= e($siteTitle) ?>.</em>
    </p>
  <?php endif; ?>

  <p style="color:var(--text-secondary); margin-bottom:32px;">
    Your clubs and events will appear here once clubs are set up.
  </p>

  <div style="background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
              padding:40px 32px; text-align:center;">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--border)"
         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
         style="margin:0 auto 16px;">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
      <circle cx="9" cy="7" r="4"/>
      <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
      <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
    </svg>
    <p style="color:var(--text-secondary); font-size:0.9rem;">
      You aren't a member of any clubs yet.
    </p>
    <p style="color:var(--text-muted); font-size:0.85rem; margin-top:6px;">
      Browse all clubs to find ones you'd like to join.
    </p>
  </div>

</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/templates/layout.php';
