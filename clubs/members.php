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

$members  = ClubManagement::listClubMembers($clubId);
$heroUrl  = $club['hero_public_file_id']
    ? Files::publicFileUrl((int)$club['hero_public_file_id'])
    : '';

$pageTitle     = 'Members — ' . $club['name'];
$activeSidebar = 'browse-clubs';

ob_start();
?>
<div style="max-width:720px; margin:0 auto;">

  <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px; flex-wrap:wrap;">
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

  <?php if ($heroUrl !== ''): ?>
    <div style="margin-bottom:20px; border-radius:var(--radius); overflow:hidden;
                aspect-ratio:3/1; background:var(--border);">
      <img src="<?= e($heroUrl) ?>" alt="<?= e($club['name']) ?>"
           style="width:100%; height:100%; object-fit:cover; display:block;">
    </div>
  <?php endif; ?>

  <?php if (empty($members)): ?>
    <p style="color:var(--text-muted); padding:32px 0; text-align:center;">No members yet.</p>
  <?php else: ?>

    <div style="border:1px solid var(--border); border-radius:var(--radius); overflow:hidden;">
      <?php foreach ($members as $idx => $m): ?>
        <?php
          $photoUrl = Files::profilePhotoUrl($m['photo_public_file_id'] ?? null);
          $initials = strtoupper(
              substr($m['first_name'] ?? '', 0, 1) .
              substr($m['last_name']  ?? '', 0, 1)
          );
          if ($initials === '') $initials = strtoupper(substr($m['email'] ?? '', 0, 1));
          $fullName      = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''));
          if ($fullName === '') $fullName = '(no name)';
          $memberRole    = trim((string)($m['role'] ?? ''));
          $isMemberAdmin = !empty($m['is_club_admin']);
          $isFaculty     = ($m['user_type'] ?? '') === 'adult';
        ?>
        <div style="display:flex; align-items:center; gap:16px; padding:14px 18px;
                    background:var(--surface);
                    <?= $idx > 0 ? 'border-top:1px solid var(--border-light);' : '' ?>">

          <!-- Photo -->
          <div style="flex-shrink:0;">
            <?php if ($photoUrl !== ''): ?>
              <img src="<?= e($photoUrl) ?>" class="avatar" style="width:60px;height:60px;" alt="">
            <?php else: ?>
              <div class="avatar-placeholder"
                   style="width:60px;height:60px;font-size:24px;background:var(--gradient-brand);">
                <?= e($initials) ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Info block -->
          <div style="flex:1; min-width:0; line-height:1.5;">
            <div style="font-weight:600; color:var(--text-primary); display:flex; align-items:center; gap:6px; flex-wrap:wrap;">
              <?= e($fullName) ?>
              <?php if ($isMemberAdmin): ?>
                <span class="action-badge" style="color:var(--purple-mid);">Club Leader</span>
              <?php endif; ?>
            </div>
            <?php if ($memberRole !== ''): ?>
              <div style="font-size:0.82rem; color:var(--text-secondary);"><?= e($memberRole) ?></div>
            <?php endif; ?>
            <?php if ($isFaculty): ?>
              <div style="font-size:0.82rem; color:var(--coral);">Faculty Advisor</div>
            <?php endif; ?>
            <div style="font-size:0.82rem; color:var(--text-muted);">
              <a href="mailto:<?= e($m['email'] ?? '') ?>" style="color:var(--accent-blue);">
                <?= e($m['email'] ?? '') ?>
              </a>
            </div>
          </div>

          <!-- Edit button (admins only) -->
          <?php if ($canManage): ?>
            <div style="flex-shrink:0;">
              <a href="/clubs/membership_edit.php?club_id=<?= $clubId ?>&user_id=<?= (int)$m['id'] ?>"
                 class="btn btn-secondary" style="font-size:13px; padding:7px 16px;">
                Edit
              </a>
            </div>
          <?php endif; ?>

        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
