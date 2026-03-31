<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/EmailLog.php';
require_once __DIR__ . '/../lib/UserManagement.php';

Application::init();
Auth::requireAdmin();

// ── Helpers ────────────────────────────────────────────────────────────────
function elIntParam(string $key, int $default = 0): int {
    $v = $_GET[$key] ?? null;
    return $v !== null ? max(0, (int)$v) : $default;
}
function elStrParam(string $key, string $default = ''): string {
    $v = $_GET[$key] ?? null;
    return $v !== null ? trim((string)$v) : $default;
}

// ── Filters & pagination ───────────────────────────────────────────────────
$limitOptions      = [10, 25, 50, 100];
$qSentByUserId     = elIntParam('sent_by_user_id');
$qToEmail          = elStrParam('to_email');
$qSuccess          = elStrParam('success');      // '', 'success', 'failed'
$qLimit            = elIntParam('limit', 25);
if (!in_array($qLimit, $limitOptions, true)) $qLimit = 25;
$qPage             = max(1, elIntParam('page', 1));

$filters = [];
if ($qSentByUserId > 0) $filters['sent_by_user_id'] = $qSentByUserId;
if ($qToEmail !== '')   $filters['to_email']         = $qToEmail;
if ($qSuccess === 'success') $filters['success'] = true;
if ($qSuccess === 'failed')  $filters['success'] = false;

$total      = EmailLog::count($filters);
$totalPages = max(1, (int)ceil($total / $qLimit));
if ($qPage > $totalPages) $qPage = $totalPages;
$offset     = ($qPage - 1) * $qLimit;

$rows  = EmailLog::list($filters, $qLimit, $offset);
$users = UserManagement::listAllForSelect();

// Build user display map
$userMap = [];
foreach ($users as $u) {
    $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    if ($name === '') $name = 'User #' . (int)$u['id'];
    $userMap[(int)$u['id']] = $name . ' (' . ($u['email'] ?? '') . ')';
}

// URL builder for filter/pagination links
function buildElUrl(array $overrides): string {
    $base = [
        'sent_by_user_id' => $_GET['sent_by_user_id'] ?? '',
        'to_email'        => $_GET['to_email']        ?? '',
        'success'         => $_GET['success']         ?? '',
        'limit'           => $_GET['limit']           ?? '',
        'page'            => $_GET['page']            ?? '',
    ];
    foreach ($overrides as $k => $v) {
        $base[$k] = $v;
    }
    $base = array_filter($base, fn($v) => $v !== '' && $v !== null);
    $qs = http_build_query($base);
    return '/admin/email_log.php' . ($qs ? '?' . $qs : '');
}

$pageTitle     = 'Email Log';
$activeSidebar = 'admin';

ob_start();
?>
<div class="admin-page">

  <div class="admin-subnav">
    <a href="/admin/settings.php">Settings</a>
    <a href="/admin/activity_log.php">Activity Log</a>
    <a href="/admin/email_log.php" class="active">Email Log</a>
    <a href="/admin/users/index.php">Users</a>
  </div>

  <h2>Email Log</h2>

  <!-- Filters -->
  <div class="card" style="margin-bottom:20px;padding:20px 24px;">
    <form method="get" style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
      <!-- Sent-by user typeahead -->
      <div class="form-group" style="margin:0;flex:1;min-width:200px;position:relative;">
        <label for="el-user-label">Sent By</label>
        <input type="text" id="el-user-label"
               placeholder="Type name or email…"
               value="<?= e($qSentByUserId > 0 ? ($userMap[$qSentByUserId] ?? 'User #'.$qSentByUserId) : '') ?>"
               autocomplete="off">
        <input type="hidden" id="el-user" name="sent_by_user_id"
               value="<?= $qSentByUserId > 0 ? $qSentByUserId : '' ?>">
        <div id="el-user-results" class="typeahead-dropdown" style="display:none;"></div>
      </div>
      <!-- To email -->
      <div class="form-group" style="margin:0;flex:1;min-width:180px;">
        <label for="el-to">To Email</label>
        <input type="email" id="el-to" name="to_email"
               value="<?= e($qToEmail) ?>" placeholder="recipient@example.com">
      </div>
      <!-- Status -->
      <div class="form-group" style="margin:0;min-width:140px;">
        <label for="el-status">Status</label>
        <select id="el-status" name="success">
          <option value="">All</option>
          <option value="success"<?= $qSuccess === 'success' ? ' selected' : '' ?>>Success</option>
          <option value="failed"<?= $qSuccess === 'failed'  ? ' selected' : '' ?>>Failed</option>
        </select>
      </div>
      <!-- Page size -->
      <div class="form-group" style="margin:0;">
        <label for="el-limit">Per page</label>
        <select id="el-limit" name="limit">
          <?php foreach ($limitOptions as $opt): ?>
            <option value="<?= $opt ?>"<?= $qLimit === $opt ? ' selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:8px;padding-bottom:1px;">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="/admin/email_log.php" class="btn btn-secondary">Reset</a>
      </div>
    </form>
  </div>

  <!-- Result count -->
  <div style="margin-bottom:10px;color:var(--text-secondary);font-size:0.875rem;">
    <?= number_format($total) ?> total &middot; Page <?= $qPage ?> of <?= $totalPages ?>
  </div>

  <?php if (empty($rows)): ?>
    <p style="color:var(--text-muted);padding:24px 0;">No email entries found.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="log-table">
        <thead>
          <tr>
            <th>When</th>
            <th>Sent By</th>
            <th>To</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Error</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="log-ts"><?= e($r['created_at'] ?? '') ?></td>
              <td>
                <?php
                  $uid = isset($r['sent_by_user_id']) ? (int)$r['sent_by_user_id'] : 0;
                  if ($uid > 0) {
                    echo e($userMap[$uid] ?? 'User #' . $uid);
                  } else {
                    echo '<span style="color:var(--text-muted)">System</span>';
                  }
                ?>
              </td>
              <td class="log-ts">
                <?php
                  $toName  = trim((string)($r['to_name']  ?? ''));
                  $toEmail = trim((string)($r['to_email'] ?? ''));
                  if ($toName !== '' && $toName !== $toEmail) {
                    echo e($toName) . '<br><small style="color:var(--text-muted)">' . e($toEmail) . '</small>';
                  } else {
                    echo e($toEmail);
                  }
                ?>
              </td>
              <td><?= e($r['subject'] ?? '') ?></td>
              <td>
                <?php if (!empty($r['success'])): ?>
                  <span class="status-success">✓ Sent</span>
                <?php else: ?>
                  <span class="status-failed">✗ Failed</span>
                <?php endif; ?>
              </td>
              <td class="log-meta">
                <?php
                  $err = trim((string)($r['error_message'] ?? ''));
                  if ($err !== '') {
                    $display = mb_strlen($err) > 120 ? mb_substr($err, 0, 120) . '…' : $err;
                    echo '<span style="color:var(--error)">' . e($display) . '</span>';
                  } else {
                    echo '<span style="color:var(--text-muted)">—</span>';
                  }
                ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <div style="display:flex;gap:8px;margin-top:16px;justify-content:flex-end;">
      <?php if ($qPage > 1): ?>
        <a href="<?= e(buildElUrl(['page' => $qPage - 1])) ?>" class="btn btn-secondary">← Prev</a>
      <?php else: ?>
        <span class="btn btn-secondary" style="opacity:.4;cursor:default;">← Prev</span>
      <?php endif; ?>
      <?php if ($qPage < $totalPages): ?>
        <a href="<?= e(buildElUrl(['page' => $qPage + 1])) ?>" class="btn btn-secondary">Next →</a>
      <?php else: ?>
        <span class="btn btn-secondary" style="opacity:.4;cursor:default;">Next →</span>
      <?php endif; ?>
    </div>
  <?php endif; ?>

</div><!-- .admin-page -->

<script>
// ── User typeahead ──────────────────────────────────────────────────────────
(function () {
  const users = <?= json_encode(array_values($users), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
  const labelInput  = document.getElementById('el-user-label');
  const hiddenInput = document.getElementById('el-user');
  const dropdown    = document.getElementById('el-user-results');
  let timeout;

  labelInput.addEventListener('input', function () {
    clearTimeout(timeout);
    const q = this.value.toLowerCase().trim();
    hiddenInput.value = '';
    if (q.length < 2) { dropdown.style.display = 'none'; return; }

    timeout = setTimeout(() => {
      const matches = users.filter(u =>
        ((u.first_name || '') + ' ' + (u.last_name || '') + ' ' + (u.email || ''))
          .toLowerCase().includes(q)
      ).slice(0, 10);

      if (!matches.length) { dropdown.style.display = 'none'; return; }

      dropdown.innerHTML = matches.map(u =>
        `<div class="typeahead-item"
              data-id="${u.id}"
              data-label="${u.first_name || ''} ${u.last_name || ''} (${u.email || ''})">
          ${u.first_name || ''} ${u.last_name || ''}
          <small>${u.email || ''}</small>
        </div>`
      ).join('');
      dropdown.style.display = 'block';
    }, 200);
  });

  dropdown.addEventListener('click', e => {
    const item = e.target.closest('.typeahead-item');
    if (!item) return;
    hiddenInput.value = item.dataset.id;
    labelInput.value  = item.dataset.label;
    dropdown.style.display = 'none';
  });

  document.addEventListener('click', e => {
    if (!labelInput.contains(e.target) && !dropdown.contains(e.target)) {
      dropdown.style.display = 'none';
    }
  });
})();
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
