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

$clubId = (int)($_GET['id'] ?? 0);
if ($clubId <= 0) {
    Flash::set('error', 'Invalid club.');
    redirect('/clubs/browse.php');
}

$club = ClubManagement::getClubById($clubId);
if (!$club || $club['is_secret']) {
    Flash::set('error', 'Club not found.');
    redirect('/clubs/browse.php');
}

$ctx         = UserContext::getLoggedInUserContext();
$isMember    = ClubManagement::isUserMember($ctx->id, $clubId);
$isClubAdmin = ClubManagement::isUserClubAdmin($ctx->id, $clubId);
$canManage   = $isClubAdmin || $ctx->admin;

$heroUrl  = $club['hero_public_file_id']
    ? Files::publicFileUrl((int)$club['hero_public_file_id'])
    : '';
$photoUrl = $club['photo_public_file_id']
    ? Files::publicFileUrl((int)$club['photo_public_file_id'])
    : '';

$memberCount = (int)($club['member_count'] ?? 0);
$initial     = strtoupper(substr($club['name'], 0, 1));

$pageTitle     = $club['name'];
$activeClubId  = $clubId;
$activeSidebar = 'club-info';

ob_start();
?>
<div style="max-width:720px; margin:0 auto;">

<?php
  $menuItems = $isMember
      ? ClubUI::buildClubMenuItems($clubId, 'info', $canManage, true, $club['name'])
      : [];
  $joinHtml = !$isMember
      ? '<form method="POST" action="/clubs/join_eval.php" style="margin:0;">'
        . csrf_input()
        . '<input type="hidden" name="club_id" value="' . $clubId . '">'
        . '<input type="hidden" name="return_to" value="/clubs/view.php?id=' . $clubId . '">'
        . '<button type="submit" class="btn btn-primary">Join Club</button>'
        . '</form>'
      : '';
  $meetingLine  = ClubUI::formatMeetingSubtext($club);
  $subtextLines = $meetingLine !== '' ? [$meetingLine] : [];
  $subtextLines[] = $memberCount . ' member' . ($memberCount !== 1 ? 's' : '');
?>
<?= ApplicationUI::titleBlock(
    $club['name'], '', $photoUrl, $initial,
    $subtextLines,
    $menuItems,
    $heroUrl,
    '',
    $joinHtml,
    [['label' => 'Back to clubs', 'href' => '/clubs/browse.php']]
) ?>
<?php if ($isMember): ?>
<?= ClubUI::leaveClubForm($clubId) ?>
<?php endif; ?>

  <!-- Club info card -->
  <?php $desc = trim((string)($club['description'] ?? '')); ?>
  <?php if ($desc !== ''): ?>
  <div class="card" style="background:var(--surface); border:1px solid var(--border);
                            border-radius:var(--radius); padding:24px;">
    <?= ClubUI::renderDescription($desc) ?>
  </div>
  <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
