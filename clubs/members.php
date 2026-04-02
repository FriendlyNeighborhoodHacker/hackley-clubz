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

$clubId = (int)($_GET['id'] ?? 0);
if ($clubId <= 0) {
    Flash::set('error', 'Invalid club.');
    redirect('/clubs/browse.php');
}

$club = ClubManagement::getClubById($clubId);
if (!$club) {
    Flash::set('error', 'Club not found.');
    redirect('/clubs/browse.php');
}

$ctx         = UserContext::getLoggedInUserContext();
$isClubAdmin = ClubManagement::isUserClubAdmin($ctx->id, $clubId);
$canManage   = $isClubAdmin || $ctx->admin;

$members = ClubManagement::listClubMembers($clubId);

$pageTitle     = 'Members — ' . $club['name'];
$activeSidebar = 'browse-clubs';

ob_start();
?>
<div style="max-width:960px; margin:0 auto;">

  <div style="display:flex; align-items:center; gap:12px; margin-bottom:24px; flex-wrap:wrap;">
    <a href="/clubs/view.php?id=<?= $clubId ?>"
       style="color:var(--text-secondary); font-size:14px; text-decoration:none; flex-shrink:0;">
      ← <?= e($club['name']) ?>
    </a>
    <h1 style="font-family:var(--font-title); font-weight:200; font-size:1.5rem; flex:1;">
      Members
    </h1>
    <div style="color:var(--text-secondary); font-size:0.875rem;">
      <?= count($members) ?> member<?= count($members) !== 1 ? 's' : '' ?>
    </div>
  </div>

  <div class="table-wrap">
    <table class="log-table">
      <thead>
        <tr>
          <th style="width:44px;"></th>
          <th>Name</th>
          <th>Role</th>
          <th>Email</th>
          <th>Phone</th>
          <?php if ($canManage): ?>
            <th style="width:1%;"></th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($members as $m): ?>
          <?php
            $photoUrl = Files::profilePhotoUrl($m['photo_public_file_id'] ?? null);
            $initials = strtoupper(
                substr($m['first_name'] ?? '', 0, 1) .
                substr($m['last_name']  ?? '', 0, 1)
            );
            if ($initials === '') $initials = strtoupper(substr($m['email'] ?? '', 0, 1));
            $fullName = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''));
            if ($fullName === '') $fullName = '(no name)';
            $memberRole    = trim((string)($m['role'] ?? ''));
            $isMemberAdmin = !empty($m['is_club_admin']);
          ?>
          <tr>
            <!-- Avatar -->
            <td style="padding:8px 10px;">
              <?php if ($photoUrl !== ''): ?>
                <img src="<?= e($photoUrl) ?>" class="avatar avatar-sm" alt="">
              <?php else: ?>
                <div class="avatar-placeholder avatar-sm" style="font-size:11px;"><?= e($initials) ?></div>
              <?php endif; ?>
            </td>

            <!-- Name + admin badge -->
            <td>
              <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                <span style="font-weight:500; color:var(--text-primary);"><?= e($fullName) ?></span>
                <?php if ($isMemberAdmin): ?>
                  <span class="action-badge" style="color:var(--purple-mid);">Club Admin</span>
                <?php endif; ?>
                <?php if (($m['user_type'] ?? '') === 'adult'): ?>
                  <span class="action-badge" style="color:var(--coral);">Faculty</span>
                <?php endif; ?>
              </div>
            </td>

            <!-- Role -->
            <td style="color:var(--text-secondary); font-size:0.875rem;">
              <?= $memberRole !== '' ? e($memberRole) : '<span style="color:var(--text-muted);">—</span>' ?>
            </td>

            <!-- Email -->
            <td class="log-ts">
              <a href="mailto:<?= e($m['email'] ?? '') ?>" style="color:var(--accent-blue);">
                <?= e($m['email'] ?? '') ?>
              </a>
            </td>

            <!-- Phone -->
            <td class="log-ts">
              <?php $phone = trim((string)($m['phone'] ?? '')); ?>
              <?= $phone !== '' ? e($phone) : '<span style="color:var(--text-muted);">—</span>' ?>
            </td>

            <!-- Actions (club admins only) -->
            <?php if ($canManage): ?>
              <td style="white-space:nowrap;">
                <div style="display:flex; gap:6px; align-items:center; justify-content:flex-end;">
                  <?php if (!$isMemberAdmin): ?>
                    <form method="POST" action="/clubs/member_make_admin_eval.php" style="margin:0;"
                          onsubmit="return confirm('Make <?= e(addslashes($fullName)) ?> a Club Admin?')">
                      <?= csrf_input() ?>
                      <input type="hidden" name="club_id"  value="<?= $clubId ?>">
                      <input type="hidden" name="user_id"  value="<?= (int)$m['id'] ?>">
                      <button type="submit" class="btn btn-secondary" style="font-size:12px;padding:5px 12px;">
                        Make Admin
                      </button>
                    </form>
                  <?php else: ?>
                    <form method="POST" action="/clubs/member_remove_admin_eval.php" style="margin:0;"
                          onsubmit="return confirm('Remove admin rights from <?= e(addslashes($fullName)) ?>?')">
                      <?= csrf_input() ?>
                      <input type="hidden" name="club_id" value="<?= $clubId ?>">
                      <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                      <button type="submit" class="btn btn-secondary" style="font-size:12px;padding:5px 12px;">
                        Remove Admin
                      </button>
                    </form>
                  <?php endif; ?>

                  <a href="/clubs/member_edit_role.php?club_id=<?= $clubId ?>&user_id=<?= (int)$m['id'] ?>"
                     class="btn btn-secondary" style="font-size:12px;padding:5px 12px;">
                    Edit Role
                  </a>

                  <?php if ((int)$m['id'] !== $ctx->id): ?>
                    <form method="POST" action="/clubs/member_remove_eval.php" style="margin:0;"
                          onsubmit="return confirm('Remove <?= e(addslashes($fullName)) ?> from this club?')">
                      <?= csrf_input() ?>
                      <input type="hidden" name="club_id" value="<?= $clubId ?>">
                      <input type="hidden" name="user_id" value="<?= (int)$m['id'] ?>">
                      <button type="submit" class="btn btn-danger" style="font-size:12px;padding:5px 12px;">
                        Remove
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>

        <?php if (empty($members)): ?>
          <tr>
            <td colspan="<?= $canManage ? 6 : 5 ?>"
                style="padding:32px; text-align:center; color:var(--text-muted);">
              No members yet.
            </td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
