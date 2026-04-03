<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/AdminUI.php';

Application::init();
Auth::requireAdmin();

$pageTitle     = 'Reports';
$activeSidebar = 'admin';

ob_start();
?>
<div class="admin-page">

  <?= AdminUI::adminBreadcrumb('Reports') ?>

  <h2 style="margin-bottom:24px;">Reports</h2>

  <div style="display:flex; flex-direction:column; gap:12px; max-width:480px;">

    <a href="/admin/activity_log.php" class="card"
       style="display:flex; align-items:center; gap:16px; padding:20px 24px;
              text-decoration:none; color:inherit;
              background:var(--surface); border:1px solid var(--border);
              border-radius:var(--radius); transition:box-shadow .15s;">
      <div style="flex-shrink:0; color:var(--text-secondary);">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
          <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
          <polyline points="14 2 14 8 20 8"/>
          <line x1="16" y1="13" x2="8" y2="13"/>
          <line x1="16" y1="17" x2="8" y2="17"/>
          <polyline points="10 9 9 9 8 9"/>
        </svg>
      </div>
      <div>
        <div style="font-weight:600; font-size:0.95rem; color:var(--text-primary);">Activity Log</div>
        <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
          All user actions and logins recorded in the system.
        </div>
      </div>
      <div style="margin-left:auto; color:var(--text-muted);">→</div>
    </a>

    <a href="/admin/email_log.php" class="card"
       style="display:flex; align-items:center; gap:16px; padding:20px 24px;
              text-decoration:none; color:inherit;
              background:var(--surface); border:1px solid var(--border);
              border-radius:var(--radius); transition:box-shadow .15s;">
      <div style="flex-shrink:0; color:var(--text-secondary);">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
          <polyline points="22,6 12,13 2,6"/>
        </svg>
      </div>
      <div>
        <div style="font-weight:600; font-size:0.95rem; color:var(--text-primary);">Email Log</div>
        <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
          All emails sent by the system.
        </div>
      </div>
      <div style="margin-left:auto; color:var(--text-muted);">→</div>
    </a>

  </div>

</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
