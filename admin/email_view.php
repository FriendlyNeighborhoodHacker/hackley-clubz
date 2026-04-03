<?php
declare(strict_types=1);

/**
 * Email detail / preview page — shows metadata and the full HTML body in an iframe.
 * The HTML body is loaded from admin/email_body.php to keep it isolated from the app CSS.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/EmailLog.php';
require_once __DIR__ . '/../lib/UserManagement.php';
require_once __DIR__ . '/../lib/AdminUI.php';

Application::init();
Auth::requireAdmin();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    Flash::set('error', 'Invalid email ID.');
    redirect('/admin/email_log.php');
}

$email = EmailLog::findById($id);
if (!$email) {
    Flash::set('error', 'Email record not found.');
    redirect('/admin/email_log.php');
}

// Resolve sender name
$senderName = null;
if (!empty($email['sent_by_user_id'])) {
    $sender = UserManagement::findUserById((int)$email['sent_by_user_id']);
    if ($sender) {
        $senderName = trim(($sender['first_name'] ?? '') . ' ' . ($sender['last_name'] ?? ''));
        if ($senderName === '') $senderName = $sender['email'];
    }
}

$pageTitle     = 'Email — ' . ($email['subject'] ?? '');
$activeSidebar = 'admin';

ob_start();
?>
<div class="admin-page">

  <?= AdminUI::adminBreadcrumb('Email Log') ?>

  <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
    <a href="/admin/email_log.php" style="color:var(--text-secondary);font-size:14px;">← Email Log</a>
    <h2 style="font-size:1.2rem;font-weight:500;"><?= e($email['subject'] ?? '') ?></h2>
  </div>

  <!-- Metadata card -->
  <div class="card" style="padding:20px 24px;margin-bottom:20px;font-size:0.875rem;">
    <dl style="display:grid;grid-template-columns:max-content 1fr;gap:6px 20px;margin:0;">
      <dt style="color:var(--text-muted);font-weight:500;">To</dt>
      <dd style="margin:0;">
        <?php
          $toName  = trim((string)($email['to_name']  ?? ''));
          $toEmail = trim((string)($email['to_email'] ?? ''));
          if ($toName !== '' && $toName !== $toEmail) {
              echo e($toName) . ' &lt;' . e($toEmail) . '&gt;';
          } else {
              echo e($toEmail);
          }
        ?>
      </dd>

      <?php if ($senderName !== null): ?>
      <dt style="color:var(--text-muted);font-weight:500;">Sent by</dt>
      <dd style="margin:0;"><?= e($senderName) ?></dd>
      <?php endif; ?>

      <dt style="color:var(--text-muted);font-weight:500;">Date</dt>
      <dd style="margin:0;"><?= e($email['created_at'] ?? '') ?></dd>

      <dt style="color:var(--text-muted);font-weight:500;">Status</dt>
      <dd style="margin:0;">
        <?php if (!empty($email['success'])): ?>
          <span class="status-success">✓ Delivered</span>
        <?php else: ?>
          <span class="status-failed">✗ Failed</span>
          <?php if (!empty($email['error_message'])): ?>
            <span style="color:var(--error);margin-left:8px;"><?= e($email['error_message']) ?></span>
          <?php endif; ?>
        <?php endif; ?>
      </dd>
    </dl>
  </div>

  <!-- Email body in iframe (isolated from app CSS) -->
  <div class="card" style="padding:0;overflow:hidden;">
    <div style="padding:10px 16px;border-bottom:1px solid var(--border);
                font-size:0.75rem;color:var(--text-muted);background:var(--bg);">
      Email body preview
    </div>
    <iframe
      src="/admin/email_body.php?id=<?= $id ?>"
      style="width:100%;border:none;min-height:500px;"
      title="Email body"
      sandbox="allow-same-origin"
      id="emailBodyFrame">
    </iframe>
  </div>

</div>

<script>
// Auto-resize iframe to fit content once loaded
document.getElementById('emailBodyFrame').addEventListener('load', function () {
  try {
    const h = this.contentDocument.documentElement.scrollHeight;
    if (h > 100) this.style.minHeight = h + 'px';
  } catch (e) { /* cross-origin guard */ }
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
