<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/ClubManagement.php';
require_once __DIR__ . '/../lib/Files.php';
require_once __DIR__ . '/../lib/ApplicationUI.php';
require_once __DIR__ . '/../lib/ClubUI.php';
require_once __DIR__ . '/../lib/UserContext.php';

Application::init();
Auth::requireLogin();

$clubId       = (int)($_GET['club_id'] ?? 0);
$targetUserId = (int)($_GET['user_id'] ?? 0);

if ($clubId <= 0 || $targetUserId <= 0) {
    Flash::set('error', 'Invalid request.');
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
$isMember    = ClubManagement::isUserMember($ctx->id, $clubId);

if (!$canManage) {
    Flash::set('error', 'You do not have permission to manage this club.');
    redirect('/clubs/view.php?id=' . $clubId);
}

$member = ClubManagement::getMembership($clubId, $targetUserId);
if (!$member) {
    Flash::set('error', 'That person is not a member of this club.');
    redirect('/clubs/members.php?id=' . $clubId);
}

$fullName      = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
if ($fullName === '') $fullName = '(no name)';
$memberRole    = trim((string)($member['role'] ?? ''));
$isMemberAdmin = !empty($member['is_club_admin']);
$isFaculty     = ($member['user_type'] ?? '') === 'adult';
$isSelf        = ($ctx->id === $targetUserId);

$heroUrl      = $club['hero_public_file_id']
    ? Files::publicFileUrl((int)$club['hero_public_file_id'])
    : '';
$clubPhotoUrl = $club['photo_public_file_id']
    ? Files::publicFileUrl((int)$club['photo_public_file_id'])
    : '';

$photoUrl = Files::profilePhotoUrl($member['photo_public_file_id'] ?? null);
$initials  = strtoupper(
    substr($member['first_name'] ?? '', 0, 1) .
    substr($member['last_name']  ?? '', 0, 1)
);
if ($initials === '') $initials = strtoupper(substr($member['email'] ?? '', 0, 1));

$pageTitle     = 'Edit Membership — ' . $fullName;
$activeSidebar = 'browse-clubs';

ob_start();
?>
<div style="max-width:560px; margin:0 auto;">

  <!-- Crumbtrail -->
  <div style="font-size:14px; color:var(--text-secondary); margin-bottom:12px;">
    <a href="/clubs/view.php?id=<?= $clubId ?>"
       style="color:var(--text-secondary); text-decoration:none;">← <?= e($club['name']) ?></a>
    <span style="margin:0 5px; color:var(--border);">›</span>
    <a href="/clubs/members.php?id=<?= $clubId ?>"
       style="color:var(--text-secondary); text-decoration:none;">Members</a>
  </div>

<?php
  $mc           = (int)($club['member_count'] ?? 0);
  $meetingLine  = ClubUI::formatMeetingSubtext($club);
  $subtextLines = $meetingLine !== '' ? [$meetingLine] : [];
  $subtextLines[] = $mc . ' member' . ($mc !== 1 ? 's' : '');
?>
<?= ApplicationUI::titleBlock(
    $club['name'], 'Edit Membership', $clubPhotoUrl, strtoupper(substr($club['name'], 0, 1)),
    $subtextLines,
    ClubUI::buildClubMenuItems($clubId, '', $canManage, $isMember, $club['name']),
    $heroUrl
) ?>
<?php if ($isMember): ?>
<?= ClubUI::leaveClubForm($clubId) ?>
<?php endif; ?>

  <!-- Flash messages -->
  <?= Flash::render() ?>

  <!-- Member identity card -->
  <div style="display:flex; align-items:center; gap:16px; margin-bottom:28px;
              background:var(--surface); border:1px solid var(--border);
              border-radius:var(--radius); padding:20px 24px;">
    <?php if ($photoUrl !== ''): ?>
      <img src="<?= e($photoUrl) ?>" class="avatar" style="width:72px;height:72px;flex-shrink:0;" alt="">
    <?php else: ?>
      <div class="avatar-placeholder"
           style="width:72px;height:72px;font-size:28px;flex-shrink:0;background:var(--gradient-brand);">
        <?= e($initials) ?>
      </div>
    <?php endif; ?>

    <div style="flex:1; min-width:0; line-height:1.6;">
      <div style="font-weight:600; font-size:1.05rem; color:var(--text-primary); display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
        <?= e($fullName) ?>
        <?php if ($isMemberAdmin): ?>
          <span class="action-badge" style="color:var(--purple-mid);">Club Leader</span>
        <?php endif; ?>
        <?php if ($isFaculty): ?>
          <span class="action-badge" style="color:var(--coral);">Faculty Advisor</span>
        <?php endif; ?>
      </div>
      <?php if ($memberRole !== ''): ?>
        <div style="font-size:0.875rem; color:var(--text-secondary);"><?= e($memberRole) ?></div>
      <?php endif; ?>
      <div style="font-size:0.875rem; color:var(--text-muted);">
        <a href="mailto:<?= e($member['email'] ?? '') ?>" style="color:var(--accent-blue);"><?= e($member['email'] ?? '') ?></a>
      </div>
      <?php if (trim((string)($member['phone'] ?? '')) !== ''): ?>
        <div style="font-size:0.875rem; color:var(--text-muted);"><?= e($member['phone']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Actions ─────────────────────────────────────────────────────────── -->
  <div style="display:flex; flex-direction:column; gap:12px;">

    <!-- Make / Remove Club Leader -->
    <div style="background:var(--surface); border:1px solid var(--border);
                border-radius:var(--radius); padding:20px 24px;
                display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
      <div>
        <div style="font-weight:500; color:var(--text-primary); margin-bottom:2px;">
          Club Leader status
        </div>
        <div style="font-size:0.82rem; color:var(--text-muted);">
          <?= $isMemberAdmin
              ? 'This member can manage club settings and members.'
              : 'Grant this member club admin privileges.' ?>
        </div>
      </div>
      <form method="POST" action="/clubs/member_toggle_admin_eval.php" style="margin:0; flex-shrink:0;">
        <?= csrf_input() ?>
        <input type="hidden" name="club_id" value="<?= $clubId ?>">
        <input type="hidden" name="user_id" value="<?= $targetUserId ?>">
        <?php if ($isMemberAdmin): ?>
          <button type="submit" class="btn btn-secondary" style="font-size:13px; padding:7px 16px;"
                  onclick="return confirm('Remove Club Leader status from <?= e(addslashes($fullName)) ?>?')">
            Remove Leader
          </button>
        <?php else: ?>
          <input type="hidden" name="make_admin" value="1">
          <button type="submit" class="btn btn-secondary" style="font-size:13px; padding:7px 16px;"
                  onclick="return confirm('Make <?= e(addslashes($fullName)) ?> a Club Leader?')">
            Make Leader
          </button>
        <?php endif; ?>
      </form>
    </div>

    <!-- Edit Role -->
    <div style="background:var(--surface); border:1px solid var(--border);
                border-radius:var(--radius); padding:20px 24px;
                display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
      <div>
        <div style="font-weight:500; color:var(--text-primary); margin-bottom:2px;">
          Role
        </div>
        <div style="font-size:0.82rem; color:var(--text-muted);">
          <?= $memberRole !== '' ? e($memberRole) : 'No role set.' ?>
        </div>
      </div>
      <button type="button" class="btn btn-secondary" style="font-size:13px; padding:7px 16px; flex-shrink:0;"
              onclick="openRoleModal()">
        Edit Role
      </button>
    </div>

    <!-- Remove from Club -->
    <?php if (!$isSelf): ?>
    <div style="background:var(--surface); border:1px solid var(--border);
                border-radius:var(--radius); padding:20px 24px;
                display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
      <div>
        <div style="font-weight:500; color:var(--text-primary); margin-bottom:2px;">
          Remove from club
        </div>
        <div style="font-size:0.82rem; color:var(--text-muted);">
          This will permanently remove <?= e($member['first_name'] ?? 'this person') ?> from the club.
        </div>
      </div>
      <form method="POST" action="/clubs/member_remove_eval.php" style="margin:0; flex-shrink:0;">
        <?= csrf_input() ?>
        <input type="hidden" name="club_id" value="<?= $clubId ?>">
        <input type="hidden" name="user_id" value="<?= $targetUserId ?>">
        <button type="submit" class="btn btn-danger" style="font-size:13px; padding:7px 16px;"
                onclick="return confirm('Remove <?= e(addslashes($fullName)) ?> from <?= e(addslashes($club['name'])) ?>?')">
          Remove
        </button>
      </form>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- ── Edit Role Modal ──────────────────────────────────────────────────── -->
<div id="role-modal-backdrop"
     style="display:none; position:fixed; inset:0; background:rgba(0,0,0,.45);
            z-index:200; align-items:center; justify-content:center; padding:24px;">
  <div style="background:var(--surface); border-radius:var(--radius);
              box-shadow:var(--shadow-lg); width:100%; max-width:420px; padding:28px;">

    <h2 style="font-family:var(--font-title); font-weight:200; font-size:1.3rem;
               margin-bottom:4px;">Edit Role</h2>
    <p style="font-size:0.85rem; color:var(--text-muted); margin-bottom:20px;">
      E.g. Treasurer, Secretary, Captain.  Leave blank to clear.
    </p>

    <form method="POST" action="/clubs/member_update_role_eval.php" id="role-form">
      <?= csrf_input() ?>
      <input type="hidden" name="club_id" value="<?= $clubId ?>">
      <input type="hidden" name="user_id" value="<?= $targetUserId ?>">

      <div class="form-group">
        <label for="role-input">Role</label>
        <input type="text" id="role-input" name="role"
               placeholder="e.g. Treasurer"
               value="<?= e($memberRole) ?>"
               maxlength="100"
               autofocus>
      </div>

      <div style="display:flex; gap:10px; justify-content:flex-end; margin-top:20px;">
        <button type="button" class="btn btn-secondary" onclick="closeRoleModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<script>
function openRoleModal() {
  const bd = document.getElementById('role-modal-backdrop');
  bd.style.display = 'flex';
  document.getElementById('role-input').focus();
}

function closeRoleModal() {
  document.getElementById('role-modal-backdrop').style.display = 'none';
}

// Close on backdrop click
document.getElementById('role-modal-backdrop').addEventListener('click', function(e) {
  if (e.target === this) closeRoleModal();
});

// Close on Escape
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') closeRoleModal();
});

</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
