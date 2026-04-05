<?php
declare(strict_types=1);
/**
 * Admin — Maintenance
 * A dashboard of one-off or periodic maintenance tasks.
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/AdminUI.php';

Application::init();
Auth::requireAdmin();

$pageTitle   = 'Maintenance';
$activeSidebar = 'admin-maintenance';

ob_start();
?>
<div class="admin-page">

  <?= AdminUI::adminBreadcrumb('Maintenance') ?>

  <h2>Maintenance</h2>
  <p style="color:var(--text-muted);margin-bottom:20px;">One-off and periodic maintenance tasks.</p>

  <table class="data-table" style="max-width:800px;">
    <thead>
      <tr>
        <th style="width:40%;">Task</th>
        <th>Description</th>
        <th style="width:120px;text-align:right;"></th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <strong>Backfill General &amp; Leadership Chats</strong>
        </td>
        <td style="color:var(--text-muted);font-size:13px;">
          Creates a <em>General</em> chat (all members) and a <em>Leadership</em> chat
          (admins only) for every club that is missing them, and syncs current
          members into any that already exist.  Safe to run multiple times.
        </td>
        <td style="text-align:right;">
          <form method="POST"
                action="/admin/maintenance_backfill_conversations_eval.php"
                onsubmit="return confirm('Run backfill now?');">
            <?= csrf_input() ?>
            <button type="submit" class="btn btn-primary btn-sm">Run</button>
          </form>
        </td>
      </tr>
    </tbody>
  </table>
</div><!-- .admin-page -->
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
