<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/ClubManagement.php';
require_once __DIR__ . '/../../lib/AdminUI.php';
require_once __DIR__ . '/../../lib/Files.php';

Application::init();
Auth::requireAdmin();

$clubs      = ClubManagement::listAllClubs();
$successMsg = Flash::get('success');
$errorMsg   = Flash::get('error');

$pageTitle     = 'Manage Clubs';
$activeSidebar = 'admin';

ob_start();
?>
<div class="admin-page">

  <?= AdminUI::adminBreadcrumb('Clubs') ?>

  <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:20px;">
    <h2>Clubs</h2>
    <a href="/admin/clubs/add.php" class="btn btn-primary" style="font-size:14px;">+ Add Club</a>
  </div>

  <?php if ($successMsg): ?>
    <div class="flash flash--success"><?= e($successMsg) ?></div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
    <div class="flash flash--error"><?= e($errorMsg) ?></div>
  <?php endif; ?>

  <div class="club-browse-grid">
    <?php foreach ($clubs as $club): ?>
      <?php
        $photoUrl = ($club['photo_public_file_id'])
          ? Files::publicFileUrl((int)$club['photo_public_file_id'])
          : '';
        $initial  = strtoupper(substr($club['name'], 0, 1));

        $aDays  = trim((string)($club['meeting_days']     ?? ''));
        $aLoc   = trim((string)($club['meeting_location'] ?? ''));
        $aParts = [];
        if ($aDays !== '') {
            $dn = array_filter(explode(',', $aDays));
            sort($dn, SORT_NUMERIC);
            $aParts[] = implode(', ', array_map(fn($d) => 'Day ' . trim($d), $dn));
        }
        if ($aLoc !== '') $aParts[] = $aLoc;
        $aMeets = implode(' · ', $aParts);
      ?>
      <div class="club-browse-card">
        <!-- Photo -->
        <div class="club-browse-photo-link" style="flex-shrink:0;">
          <?php if ($photoUrl !== ''): ?>
            <img src="<?= e($photoUrl) ?>" class="avatar" style="width:52px;height:52px;" alt="">
          <?php else: ?>
            <div class="avatar-placeholder" style="width:52px;height:52px;font-size:20px;">
              <?= e($initial) ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Info stack -->
        <div class="club-browse-info">
          <a href="/admin/clubs/edit.php?id=<?= (int)$club['id'] ?>"
             style="font-weight:600; color:var(--text-primary); text-decoration:none; font-size:0.95rem;">
            <?= e($club['name']) ?>
          </a>
          <?php if ($aMeets !== ''): ?>
            <div style="font-size:0.82rem; color:var(--text-secondary); margin-top:2px;">
              <?= e($aMeets) ?>
            </div>
          <?php endif; ?>
          <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
            <?= (int)$club['member_count'] ?> member<?= $club['member_count'] == 1 ? '' : 's' ?>
          </div>
        </div>

        <!-- Action -->
        <div class="club-browse-action">
          <a href="/admin/clubs/edit.php?id=<?= (int)$club['id'] ?>"
             class="btn btn-secondary" style="font-size:12px; padding:5px 12px;">Edit</a>
        </div>
      </div>
    <?php endforeach; ?>

    <?php if (empty($clubs)): ?>
      <div style="padding:32px; text-align:center; color:var(--text-muted);">
        No clubs yet. <a href="/admin/clubs/add.php">Add the first one →</a>
      </div>
    <?php endif; ?>
  </div>

</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/layout.php';
