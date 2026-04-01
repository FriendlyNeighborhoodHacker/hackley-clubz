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

  <?= AdminUI::adminSubnav('clubs') ?>

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

  <div class="table-wrap">
    <table class="log-table">
      <thead>
        <tr>
          <th style="width:52px;"></th>
          <th>Name</th>
          <th>Meets</th>
          <th>Members</th>
          <th>Visibility</th>
          <th>Created</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clubs as $club): ?>
          <?php
            $photoUrl = ($club['photo_public_file_id'])
              ? Files::publicFileUrl((int)$club['photo_public_file_id'])
              : '';
            $initial  = strtoupper(substr($club['name'], 0, 1));
          ?>
          <tr>
            <td style="padding:8px 10px;">
              <?php if ($photoUrl !== ''): ?>
                <img src="<?= e($photoUrl) ?>" class="avatar avatar-sm" alt="">
              <?php else: ?>
                <div class="avatar-placeholder avatar-sm" style="font-size:11px; background:var(--gradient-brand);">
                  <?= e($initial) ?>
                </div>
              <?php endif; ?>
            </td>
            <td>
              <a href="/admin/clubs/edit.php?id=<?= (int)$club['id'] ?>"
                 style="color:var(--text-primary); font-weight:500;">
                <?= e($club['name']) ?>
              </a>
              <?php if (!empty($club['description'])): ?>
                <div style="font-size:0.78rem; color:var(--text-muted); margin-top:2px;">
                  <?= e(mb_strimwidth($club['description'], 0, 80, '…')) ?>
                </div>
              <?php endif; ?>
            </td>
            <td style="color:var(--text-secondary); font-size:0.875rem;">
              <?= $club['meets'] !== '' ? e($club['meets']) : '<span style="color:var(--text-muted);">—</span>' ?>
            </td>
            <td style="color:var(--text-secondary);"><?= (int)$club['member_count'] ?></td>
            <td>
              <?php if ($club['is_secret']): ?>
                <span class="action-badge" style="color:var(--text-muted);">Secret</span>
              <?php else: ?>
                <span class="action-badge" style="color:var(--success);">Public</span>
              <?php endif; ?>
            </td>
            <td class="log-ts"><?= e(substr((string)$club['created_at'], 0, 10)) ?></td>
            <td>
              <a href="/admin/clubs/edit.php?id=<?= (int)$club['id'] ?>"
                 class="btn btn-secondary" style="font-size:12px; padding:5px 12px;">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($clubs)): ?>
          <tr>
            <td colspan="7" style="padding:32px; text-align:center; color:var(--text-muted);">
              No clubs yet. <a href="/admin/clubs/add.php">Add the first one →</a>
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/layout.php';
