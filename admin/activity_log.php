<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/ActivityLog.php';
require_once __DIR__ . '/../lib/UserManagement.php';

Application::init();
Auth::requireAdmin();

// ── Helpers ────────────────────────────────────────────────────────────────
function alIntParam(string $key, int $default = 0): int {
    $v = $_GET[$key] ?? null;
    return $v !== null ? max(0, (int)$v) : $default;
}
function alStrParam(string $key, string $default = ''): string {
    $v = $_GET[$key] ?? null;
    return $v !== null ? trim((string)$v) : $default;
}

// ── Filters & pagination ───────────────────────────────────────────────────
$limitOptions = [10, 25, 50, 100];
$qUserId      = alIntParam('user_id');
$qActionType  = alStrParam('action_type');
$qLimit       = alIntParam('limit', 25);
if (!in_array($qLimit, $limitOptions, true)) $qLimit = 25;
$qPage        = max(1, alIntParam('page', 1));

$filters = [];
if ($qUserId > 0)       $filters['user_id']     = $qUserId;
if ($qActionType !== '') $filters['action_type'] = $qActionType;

$total      = ActivityLog::count($filters);
$totalPages = max(1, (int)ceil($total / $qLimit));
if ($qPage > $totalPages) $qPage = $totalPages;
$offset     = ($qPage - 1) * $qLimit;

$rows        = ActivityLog::list($filters, $qLimit, $offset);
$actionTypes = ActivityLog::distinctActionTypes();
$users       = UserManagement::listAllForSelect();

// Build user display map
$userMap = [];
foreach ($users as $u) {
    $name = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
    if ($name === '') $name = 'User #' . (int)$u['id'];
    $userMap[(int)$u['id']] = $name . ' (' . ($u['email'] ?? '') . ')';
}

// URL builder for filter/pagination links
function buildAlUrl(array $overrides): string {
    $base = [
        'user_id'     => $_GET['user_id']     ?? '',
        'action_type' => $_GET['action_type'] ?? '',
        'limit'       => $_GET['limit']       ?? '',
        'page'        => $_GET['page']        ?? '',
    ];
    foreach ($overrides as $k => $v) {
        $base[$k] = $v;
    }
    $base = array_filter($base, fn($v) => $v !== '' && $v !== null);
    $qs = http_build_query($base);
    return '/admin/activity_log.php' . ($qs ? '?' . $qs : '');
}

$pageTitle     = 'Activity Log';
$activeSidebar = 'admin';

ob_start();
?>
<div class="admin-page">

  <div class="admin-subnav">
    <a href="/admin/settings.php">Settings</a>
    <a href="/admin/activity_log.php" class="active">Activity Log</a>
    <a href="/admin/email_log.php">Email Log</a>
    <a href="/admin/users/index.php">Users</a>
  </div>

  <h2>Activity Log</h2>

  <!-- Filters -->
  <div class="card" style="margin-bottom:20px;padding:20px 24px;">
    <form method="get" style="display:flex;flex-wrap:wrap;gap:16px;align-items:flex-end;">
      <!-- User typeahead -->
      <div class="form-group" style="margin:0;flex:1;min-width:200px;position:relative;">
        <label for="al-user-label">User</label>
        <input type="text" id="al-user-label"
               placeholder="Type name or email…"
               value="<?= e($qUserId > 0 ? ($userMap[$qUserId] ?? 'User #'.$qUserId) : '') ?>"
               autocomplete="off">
        <input type="hidden" id="al-user" name="user_id"
               value="<?= $qUserId > 0 ? $qUserId : '' ?>">
        <div id="al-user-results" class="typeahead-dropdown" style="display:none;"></div>
      </div>
      <!-- Action type -->
      <div class="form-group" style="margin:0;min-width:180px;">
        <label for="al-action">Action Type</label>
        <select id="al-action" name="action_type">
          <option value="">Any</option>
          <?php foreach ($actionTypes as $t): ?>
            <option value="<?= e($t) ?>"<?= $qActionType === $t ? ' selected' : '' ?>>
              <?= e($t) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <!-- Page size -->
      <div class="form-group" style="margin:0;">
        <label for="al-limit">Per page</label>
        <select id="al-limit" name="limit">
          <?php foreach ($limitOptions as $opt): ?>
            <option value="<?= $opt ?>"<?= $qLimit === $opt ? ' selected' : '' ?>><?= $opt ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;gap:8px;padding-bottom:1px;">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="/admin/activity_log.php" class="btn btn-secondary">Reset</a>
      </div>
    </form>
  </div>

  <!-- Result count -->
  <div style="margin-bottom:10px;color:var(--text-secondary);font-size:0.875rem;">
    <?= number_format($total) ?> total &middot; Page <?= $qPage ?> of <?= $totalPages ?>
  </div>

  <?php if (empty($rows)): ?>
    <p style="color:var(--text-muted);padding:24px 0;">No activity entries found.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="log-table">
        <thead>
          <tr>
            <th>When</th>
            <th>User</th>
            <th>Action</th>
            <th>Metadata</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="log-ts"><?= e($r['created_at'] ?? '') ?></td>
              <td>
                <?php
                  $uid = isset($r['user_id']) ? (int)$r['user_id'] : 0;
                  if ($uid > 0) {
                    echo e($userMap[$uid] ?? 'User #' . $uid);
                  } else {
                    echo '<span style="color:var(--text-muted)">System</span>';
                  }
                ?>
              </td>
              <td><code class="action-badge"><?= e($r['action_type'] ?? '') ?></code></td>
              <td class="log-meta">
                <?php
                  $meta = (string)($r['json_metadata'] ?? '');
                  if ($meta === '' || $meta === 'null') {
                    echo '<span style="color:var(--text-muted)">—</span>';
                  } else {
                    $display = mb_strlen($meta) > 300 ? mb_substr($meta, 0, 300) . '…' : $meta;
                    echo '<code>' . e($display) . '</code>';
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
        <a href="<?= e(buildAlUrl(['page' => $qPage - 1])) ?>" class="btn btn-secondary">← Prev</a>
      <?php else: ?>
        <span class="btn btn-secondary" style="opacity:.4;cursor:default;">← Prev</span>
      <?php endif; ?>
      <?php if ($qPage < $totalPages): ?>
        <a href="<?= e(buildAlUrl(['page' => $qPage + 1])) ?>" class="btn btn-secondary">Next →</a>
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
  const labelInput  = document.getElementById('al-user-label');
  const hiddenInput = document.getElementById('al-user');
  const dropdown    = document.getElementById('al-user-results');
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
