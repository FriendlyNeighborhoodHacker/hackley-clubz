<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';

Application::init();
Auth::requireLogin();

$pageTitle     = 'Calendar';
$activeSidebar = 'calendar';

ob_start();
?>
<div style="max-width:720px; margin:0 auto; text-align:center; padding:80px 24px;">
  <div style="font-size:3rem; margin-bottom:20px;">📅</div>
  <h1 style="font-family:var(--font-title); font-weight:200; font-size:2rem; margin-bottom:12px;">
    Calendar
  </h1>
  <p style="color:var(--text-secondary); font-size:1rem;">
    Coming soon — event calendar is under construction.
  </p>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/layout.php';
