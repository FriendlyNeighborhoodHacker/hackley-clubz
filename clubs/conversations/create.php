<?php
declare(strict_types=1);
/**
 * Create a new conversation (admin only).
 */
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/ClubManagement.php';
require_once __DIR__ . '/../../lib/UserContext.php';
require_once __DIR__ . '/../../lib/ApplicationUI.php';
require_once __DIR__ . '/../../lib/ClubUI.php';
require_once __DIR__ . '/../../lib/Files.php';

Application::init();
Auth::requireLogin();

$clubId = (int)($_GET['club_id'] ?? 0);
if ($clubId <= 0) {
    Flash::set('error', 'Invalid club.');
    redirect('/clubs/browse.php');
}

$club = ClubManagement::getClubById($clubId);
if (!$club) {
    Flash::set('error', 'Club not found.');
    redirect('/clubs/browse.php');
}

$ctx = UserContext::getLoggedInUserContext();
$isClubAdmin = ClubManagement::isUserClubAdmin($ctx->id, $clubId) || $ctx->admin;
if (!$isClubAdmin) {
    Flash::set('error', 'Only club leaders can create new conversations.');
    redirect('/clubs/view.php?id=' . $clubId);
}

$members  = ClubManagement::listClubMembers($clubId);
$photoUrl = $club['photo_public_file_id']
    ? Files::publicFileUrl((int)$club['photo_public_file_id'])
    : '';

$pageTitle    = 'New Chat — ' . $club['name'];
$activeClubId = $clubId;
$activeSidebar= 'conversation';

ob_start();
?>
<style>
.create-conv-wrap { max-width: 540px; margin: 0 auto; }
.member-pick { display:flex; flex-wrap:wrap; gap:8px; margin-top:8px; }
.member-pick label {
  display:flex; align-items:center; gap:6px;
  font-size:13px; cursor:pointer;
  background:var(--surface); border:1px solid var(--border);
  border-radius:20px; padding:4px 10px;
  transition:background .1s, border-color .1s;
}
.member-pick label:hover { border-color:var(--accent-blue); }
.member-pick input[type=checkbox] { accent-color:var(--accent-blue); }
.member-pick input:checked + span { color:var(--accent-blue); font-weight:600; }
</style>

<?php
echo ApplicationUI::titleBlock(
    $club['name'],
    'New Chat',
    $photoUrl,
    strtoupper(substr($club['name'], 0, 1)),
    [],
    ClubUI::buildClubMenuItems($clubId, 'conversation', $isClubAdmin, true, $club['name']),
    '',
    '',
    '',
    [['label' => $club['name'], 'href' => '/clubs/view.php?id=' . $clubId]]
);
?>

<div class="create-conv-wrap">
  <form method="POST" action="/clubs/conversations/create_eval.php">
    <?= csrf_input() ?>
    <input type="hidden" name="club_id" value="<?= $clubId ?>">

    <div class="form-group">
      <label class="form-label" for="name">Chat name</label>
      <input type="text" id="name" name="name" class="form-control"
             placeholder="e.g. Event Planning" maxlength="200" required
             value="<?= e($_POST['name'] ?? '') ?>">
    </div>

    <div class="form-group" style="margin-top:16px;">
      <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
        <input type="checkbox" name="is_secret" value="1"
               <?= !empty($_POST['is_secret']) ? 'checked' : '' ?>>
        <span>Secret chat (only invited members can see and join)</span>
      </label>
      <p class="form-hint" style="margin-top:4px;">
        If unchecked, any club member can discover and join this chat.
      </p>
    </div>

    <div class="form-group" style="margin-top:16px;">
      <label class="form-label">Add members</label>
      <p class="form-hint">You will always be added. Choose additional members to invite.</p>
      <div class="member-pick">
        <?php foreach ($members as $m): ?>
          <?php
            $uid  = (int)$m['id'];
            if ($uid === $ctx->id) continue; // current user added automatically
            $name = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''));
            if ($name === '') $name = $m['email'] ?? 'User #' . $uid;
            $checked = in_array((string)$uid, (array)($_POST['members'] ?? []), true) ? 'checked' : '';
          ?>
          <label>
            <input type="checkbox" name="members[]" value="<?= $uid ?>" <?= $checked ?>>
            <span><?= e($name) ?></span>
          </label>
        <?php endforeach; ?>
        <?php if (count($members) <= 1): ?>
          <p style="color:var(--text-muted);font-size:13px;">No other club members yet.</p>
        <?php endif; ?>
      </div>
    </div>

    <div style="margin-top:24px;display:flex;gap:10px;">
      <button type="submit" class="btn btn-primary">Create Chat</button>
      <a href="/clubs/view.php?id=<?= $clubId ?>" class="btn btn-secondary">Cancel</a>
    </div>
  </form>
</div>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/layout.php';
