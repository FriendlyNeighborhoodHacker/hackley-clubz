<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/ClubManagement.php';
require_once __DIR__ . '/../lib/Files.php';
require_once __DIR__ . '/../lib/UserContext.php';

Application::init();
Auth::requireLogin();

// ── Filters ────────────────────────────────────────────────────────────────
$filterKeyword = trim((string)($_GET['q']    ?? ''));
$filterDays    = array_filter(array_map('intval', (array)($_GET['days'] ?? [])),
                              fn($d) => $d >= 1 && $d <= 8);
$filterDays    = array_values($filterDays);
$hasFilter     = $filterKeyword !== '' || !empty($filterDays);

// ── Pagination ─────────────────────────────────────────────────────────────
const BROWSE_PAGE_SIZE = 15;
$qPage = max(1, (int)($_GET['page'] ?? 1));

$ctx        = UserContext::getLoggedInUserContext();
$total      = ClubManagement::countPublicClubs($filterKeyword, $filterDays);
$totalPages = max(1, (int)ceil($total / BROWSE_PAGE_SIZE));
if ($qPage > $totalPages) $qPage = 1;
$offset     = ($qPage - 1) * BROWSE_PAGE_SIZE;

$clubs = ClubManagement::listPublicClubsPaginated(
    BROWSE_PAGE_SIZE, $offset, $ctx->id, $filterKeyword, $filterDays
);

/**
 * Build a paginated browse URL that preserves the current filters.
 */
function buildBrowseUrl(int $page, string $keyword = '', array $days = []): string {
    $params = [];
    if ($keyword !== '') $params['q'] = $keyword;
    foreach ($days as $d)  $params['days'][] = $d;
    if ($page > 1) $params['page'] = $page;
    $qs = $params ? '?' . http_build_query($params) : '';
    return '/clubs/browse.php' . $qs;
}

$pageTitle     = 'Browse Clubs';
$activeSidebar = 'browse-clubs';

ob_start();
?>
<div style="max-width:860px; margin:0 auto; padding:0 4px;">

  <div style="display:flex; align-items:baseline; justify-content:space-between;
              margin-bottom:20px; flex-wrap:wrap; gap:8px;">
    <h1 style="font-family:var(--font-title); font-weight:200; font-size:1.6rem; margin:0;">
      Browse Clubs
    </h1>
    <span style="color:var(--text-muted); font-size:0.875rem;">
      <?= number_format($total) ?> club<?= $total !== 1 ? 's' : '' ?>
      <?php if ($totalPages > 1): ?>
        &middot; Page <?= $qPage ?> of <?= $totalPages ?>
      <?php endif; ?>
    </span>
  </div>

  <!-- ── Filter / Search panel ────────────────────────────────────────────── -->
  <form method="GET" action="/clubs/browse.php" id="browse-filter-form"
        style="background:var(--surface); border:1px solid var(--border);
               border-radius:var(--radius); padding:18px 20px; margin-bottom:24px;">

    <!-- Keyword search + filter toggle -->
    <div style="display:flex; gap:10px; align-items:center;">
      <input type="text" name="q" id="browse-search" placeholder="Search clubs…"
             value="<?= e($filterKeyword) ?>"
             style="flex:1; max-width:400px;"
             autocomplete="off">
      <button type="submit" class="btn btn-primary" style="padding:10px 20px;">Search</button>
      <!-- Expand/collapse filter button -->
      <button type="button" id="browse-filter-toggle"
              title="More filters" aria-expanded="<?= !empty($filterDays) ? 'true' : 'false' ?>"
              aria-controls="browse-day-filter"
              style="padding:9px 12px; border:1.5px solid var(--border);
                     border-radius:var(--radius-sm); background:none; cursor:pointer;
                     font-size:15px; line-height:1; white-space:nowrap;
                     transition:background .15s, color .15s, border-color .15s;
                     color:<?= !empty($filterDays) ? 'var(--purple-dark)' : 'var(--text-secondary)' ?>;
                     border-color:<?= !empty($filterDays) ? 'var(--purple-mid)' : 'var(--border)' ?>;">
        <span id="browse-filter-icon"><?= !empty($filterDays) ? '−' : '+' ?></span>
      </button>
      <?php if ($hasFilter): ?>
        <a href="/clubs/browse.php" class="btn btn-secondary"
           style="padding:10px 16px; white-space:nowrap;">✕ Clear</a>
      <?php endif; ?>
    </div>

    <!-- Day filter — hidden by default, shown when toggle is clicked or days are active -->
    <div id="browse-day-filter"
         style="<?= !empty($filterDays) ? '' : 'display:none;' ?> margin-top:14px;">
      <div style="font-size:0.78rem; font-weight:600; color:var(--text-muted);
                  text-transform:uppercase; letter-spacing:0.05em; margin-bottom:8px;">
        Filter by meeting day
      </div>
      <div style="display:flex; flex-wrap:wrap; gap:6px 16px;">
        <?php for ($d = 1; $d <= 8; $d++): ?>
          <label style="display:flex; align-items:center; gap:5px; cursor:pointer;
                         font-size:0.875rem; color:var(--text-primary); font-weight:400; margin:0;">
            <input type="checkbox" name="days[]" value="<?= $d ?>"
                   class="browse-day-cb"
                   style="width:auto; padding:0;"
                   <?= in_array($d, $filterDays, true) ? 'checked' : '' ?>>
            Day <?= $d ?>
          </label>
        <?php endfor; ?>
      </div>
    </div>
  </form>

  <script>
  (function () {
    const toggle = document.getElementById('browse-filter-toggle');
    const panel  = document.getElementById('browse-day-filter');
    const icon   = document.getElementById('browse-filter-icon');
    if (!toggle || !panel) return;

    toggle.addEventListener('click', () => {
      const isOpen = panel.style.display !== 'none';
      panel.style.display      = isOpen ? 'none' : 'block';
      icon.textContent          = isOpen ? '+' : '−';
      toggle.style.color        = isOpen ? 'var(--text-secondary)' : 'var(--purple-dark)';
      toggle.style.borderColor  = isOpen ? 'var(--border)' : 'var(--purple-mid)';
      toggle.setAttribute('aria-expanded', String(!isOpen));
    });
  })();

  // Auto-submit when a day checkbox changes
  document.querySelectorAll('.browse-day-cb').forEach(cb => {
    cb.addEventListener('change', () => document.getElementById('browse-filter-form').submit());
  });
  </script>

  <?php if (empty($clubs)): ?>
    <p style="color:var(--text-muted); padding:32px 0; text-align:center;">
      No clubs available yet. Check back soon!
    </p>
  <?php else: ?>

    <div class="club-browse-grid">
      <?php foreach ($clubs as $club): ?>
        <?php
          $photoUrl  = $club['photo_public_file_id']
              ? Files::publicFileUrl((int)$club['photo_public_file_id'])
              : '';
          $initial   = strtoupper(substr($club['name'], 0, 1));
          $isMember  = (int)($club['is_member'] ?? 0) > 0;
          $clubId    = (int)$club['id'];
          $desc      = trim((string)($club['description'] ?? ''));
          $descShort = $desc !== '' ? mb_strimwidth($desc, 0, 100, '…') : '';
          // Build meeting display string
          $bMeetDays = trim((string)($club['meeting_days'] ?? ''));
          $bMeetLoc  = trim((string)($club['meeting_location'] ?? ''));
          $meetParts = [];
          if ($bMeetDays !== '') {
              $dn = array_filter(explode(',', $bMeetDays));
              sort($dn, SORT_NUMERIC);
              $meetParts[] = implode(', ', array_map(fn($d) => 'Day ' . trim($d), $dn));
          }
          if ($bMeetLoc !== '') $meetParts[] = $bMeetLoc;
          $meetDisplay = implode(' · ', $meetParts);
        ?>
        <div class="club-browse-card">
          <!-- Photo -->
          <a href="/clubs/view.php?id=<?= $clubId ?>" class="club-browse-photo-link" tabindex="-1" aria-hidden="true">
            <?php if ($photoUrl !== ''): ?>
              <img src="<?= e($photoUrl) ?>" alt="" class="avatar" style="width:60px;height:60px;">
            <?php else: ?>
              <div class="avatar-placeholder" style="width:60px;height:60px;font-size:24px;
                          background:var(--gradient-brand);"><?= e($initial) ?></div>
            <?php endif; ?>
          </a>

          <!-- Info -->
          <div class="club-browse-info">
            <a href="/clubs/view.php?id=<?= $clubId ?>"
               style="color:var(--text-primary); font-weight:600; font-size:0.975rem;
                      text-decoration:none;">
              <?= e($club['name']) ?>
            </a>

            <?php if ($meetDisplay !== ''): ?>
              <div style="font-size:0.78rem; color:var(--text-secondary); margin-top:2px;
                          display:flex; align-items:center; gap:4px;">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     style="flex-shrink:0;" aria-hidden="true">
                  <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                  <line x1="16" y1="2" x2="16" y2="6"/>
                  <line x1="8"  y1="2" x2="8"  y2="6"/>
                  <line x1="3"  y1="10" x2="21" y2="10"/>
                </svg>
                <?= e($meetDisplay) ?>
              </div>
            <?php endif; ?>

            <?php if ($descShort !== ''): ?>
              <div style="font-size:0.82rem; color:var(--text-muted); margin-top:4px; line-height:1.4;">
                <?= e($descShort) ?>
              </div>
            <?php endif; ?>

          </div>

          <!-- Join / Joined -->
          <div class="club-browse-action">
            <?php if ($isMember): ?>
              <a href="/clubs/view.php?id=<?= $clubId ?>"
                 class="btn btn-secondary" style="font-size:13px; padding:6px 14px;">
                Joined ✓
              </a>
            <?php else: ?>
              <form method="POST" action="/clubs/join_eval.php" style="margin:0;">
                <?= csrf_input() ?>
                <input type="hidden" name="club_id" value="<?= $clubId ?>">
                <input type="hidden" name="return_to" value="<?= e(buildBrowseUrl($qPage, $filterKeyword, $filterDays)) ?>">
                <button type="submit" class="btn btn-primary" style="font-size:13px; padding:6px 14px;">
                  Join
                </button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
      <div style="display:flex; gap:8px; margin-top:28px; justify-content:center;">
        <?php if ($qPage > 1): ?>
          <a href="<?= e(buildBrowseUrl($qPage - 1, $filterKeyword, $filterDays)) ?>" class="btn btn-secondary">← Prev</a>
        <?php else: ?>
          <span class="btn btn-secondary" style="opacity:.4; cursor:default;">← Prev</span>
        <?php endif; ?>
        <?php if ($qPage < $totalPages): ?>
          <a href="<?= e(buildBrowseUrl($qPage + 1, $filterKeyword, $filterDays)) ?>" class="btn btn-secondary">Next →</a>
        <?php else: ?>
          <span class="btn btn-secondary" style="opacity:.4; cursor:default;">Next →</span>
        <?php endif; ?>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
