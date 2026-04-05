<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/ClubManagement.php';
require_once __DIR__ . '/../../lib/ConversationManagement.php';
require_once __DIR__ . '/../../lib/ConversationUI.php';
require_once __DIR__ . '/../../lib/Files.php';
require_once __DIR__ . '/../../lib/ApplicationUI.php';
require_once __DIR__ . '/../../lib/ClubUI.php';
require_once __DIR__ . '/../../lib/UserContext.php';

Application::init();
Auth::requireLogin();

$convId = (int)($_GET['id'] ?? 0);
if ($convId <= 0) {
    Flash::set('error', 'Invalid conversation.');
    redirect('/clubs/browse.php');
}

$conv = ConversationManagement::getConversationById($convId);
if (!$conv) {
    Flash::set('error', 'Conversation not found.');
    redirect('/clubs/browse.php');
}

$ctx    = UserContext::getLoggedInUserContext();
$clubId = (int)$conv['club_id'];

// Verify the user is a member of this conversation
if (!ConversationManagement::isUserMemberOfConversation($ctx->id, $convId)) {
    Flash::set('error', 'You are not a member of this conversation.');
    redirect('/clubs/view.php?id=' . $clubId);
}

// Load club for breadcrumbs / header
$club = ClubManagement::getClubById($clubId);
if (!$club) {
    Flash::set('error', 'Club not found.');
    redirect('/clubs/browse.php');
}

$isClubAdmin = ClubManagement::isUserClubAdmin($ctx->id, $clubId) || $ctx->admin;
$isMember    = ClubManagement::isUserMember($ctx->id, $clubId);

// Load messages and pinned messages
$messages       = ConversationManagement::listMessages($convId);
$pinnedMessages = ConversationManagement::listPinnedMessages($convId);

// Pre-compute which messages the current user has reacted to
$reactedIds = [];
foreach ($messages as $m) {
    if ($m['deleted_at'] === null
        && ConversationManagement::hasReacted($ctx->id, (int)$m['id'])) {
        $reactedIds[(int)$m['id']] = true;
    }
}

$maxMessageId = 0;
foreach ($messages as $m) {
    if ((int)$m['id'] > $maxMessageId) $maxMessageId = (int)$m['id'];
}

$photoUrl   = $club['photo_public_file_id']
    ? Files::publicFileUrl((int)$club['photo_public_file_id'])
    : '';
$heroUrl    = $club['hero_public_file_id']
    ? Files::publicFileUrl((int)$club['hero_public_file_id'])
    : '';

$pageTitle            = e($conv['name']);
$activeClubId         = $clubId;
$activeConversationId = $convId;
$activeSidebar        = 'conversation';
$layoutFullWidth      = true;   // conversation view always uses full-width layout

// ── Conversation members (for members panel in topbar) ────────────────────
$convMembers = ConversationManagement::listConversationMembers($convId);

// ── Topbar overrides: centered bold title; members icon on the right ──────
$pageTopbarTitle = e($conv['name'])
    . ($conv['is_secret']
        ? ' <span title="Private" aria-label="Private"'
          . ' style="display:inline-flex;vertical-align:-1px;opacity:.7;">'
          . '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
          . ' stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
          . '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>'
          . '<path d="M7 11V7a5 5 0 0 1 10 0v4"/>'
          . '</svg></span>'
        : '');

// Members icon button (visible to all members)
$pageTopbarActions = '<button type="button" id="conv-members-btn"'
    . ' onclick="toggleMembersPanel()"'
    . ' title="Chat members" aria-label="Show chat members"'
    . ' style="background:none;border:none;cursor:pointer;padding:4px;'
    . 'color:inherit;display:flex;align-items:center;">'
    . '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
    . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
    . '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>'
    . '<circle cx="9" cy="7" r="4"/>'
    . '<path d="M23 21v-2a4 4 0 0 0-3-3.87"/>'
    . '<path d="M16 3.13a4 4 0 0 1 0 7.75"/>'
    . '</svg>'
    . '<span style="font-size:11px;margin-left:3px;font-weight:600;">'
    . count($convMembers) . '</span>'
    . '</button>';

// Delete Chat — admins only, custom conversations only
if ($isClubAdmin && $conv['type'] === 'custom') {
    $pageTopbarActions .= '<form method="POST"'
        . ' action="/clubs/conversations/delete_conversation_eval.php"'
        . ' style="display:inline;"'
        . ' onsubmit="return confirm(\'Delete this conversation and all its messages?\')">'
        . csrf_input()
        . '<input type="hidden" name="conversation_id" value="' . $convId . '">'
        . '<input type="hidden" name="club_id" value="' . $clubId . '">'
        . '<button type="submit" class="btn btn-secondary"'
        . ' style="font-size:11px;padding:3px 8px;color:var(--error);">Delete</button>'
        . '</form>';
}

$csrfToken = csrf_token();

ob_start();
?>
<style>
/* ── Lock page scroll so only the thread scrolls ────────────────────────── */
html, body { height:100%; overflow:hidden; }
.main-content  { overflow:hidden !important; padding:0 !important; }

/* ── Conversation page outer: fills full viewport below topbar ─────────── */
/* 100dvh = "dynamic viewport height" — automatically shrinks on mobile     */
/* when the soft keyboard appears, which keeps the composer pinned at bottom */
.conv-page-outer {
  display:flex; flex-direction:column;
  height: calc(100dvh - 56px); /* 56px = topbar; dvh is keyboard-aware      */
  overflow:hidden;
}

/* ── Inner wrap: thread + composer, fills remaining height ──────────────── */
.conv-wrap {
  flex:1; overflow:hidden;
  display:flex; flex-direction:column;
  max-width:900px; width:100%;
  margin:0 auto; padding:0 16px;
  box-sizing:border-box;
}
.conv-pinned-banner {
  background:#fffbea; border:1px solid #fde68a; border-radius:6px;
  padding:8px 12px; margin-bottom:8px; font-size:12px; color:#92400e;
  flex-shrink:0;
}
.conv-pinned-banner summary { cursor:pointer; font-weight:600; }
.conv-pinned-item { margin-top:4px; padding:4px 0;
  border-top:1px solid #fde68a; line-height:1.4; }
.conv-thread {
  flex:1; overflow-y:auto; padding:8px 0;
  display:flex; flex-direction:column; justify-content:flex-end; gap:2px;
  /* Smooth momentum scroll on iOS */
  -webkit-overflow-scrolling:touch;
  overscroll-behavior:contain; /* prevent body scroll bleed-through */
}
.conv-input-bar {
  flex-shrink:0; display:flex; gap:8px; align-items:flex-end;
  padding:10px 0;
  /* Add safe-area padding for iPhones with home indicator */
  padding-bottom: max(10px, env(safe-area-inset-bottom, 10px));
  border-top:1px solid var(--border);
}
.conv-input-bar textarea {
  flex:1; resize:none; min-height:40px; max-height:120px;
  padding:8px 12px; border:1px solid var(--border);
  border-radius:20px; font-size:14px; font-family:inherit;
  background:var(--surface); color:var(--text-primary);
  overflow-y:auto; line-height:1.4;
}
.conv-input-bar textarea:focus { outline:none; border-color:var(--accent-blue); }
.conv-send-btn {
  flex-shrink:0; width:38px; height:38px; border-radius:50%;
  background:var(--accent-blue); color:#fff; border:none; cursor:pointer;
  display:flex; align-items:center; justify-content:center; font-size:16px;
  transition:opacity .15s;
}
.conv-send-btn:disabled { opacity:.5; cursor:default; }

/* ── Message rows: iMessage-style bubbles ───────────────────────────────── */
.msg-row {
  display:flex; align-items:flex-end; gap:8px; padding:2px 4px;
}

/* ─ Self (right side) ─ */
.msg-row--self { flex-direction:row-reverse; }
.msg-content--self {
  display:flex; flex-direction:column; align-items:flex-end;
  max-width:72%;
}
.msg-author-line { margin-bottom:2px; }
.msg-meta--self {
  display:flex; align-items:center; gap:5px; justify-content:flex-end;
  margin-top:3px;
}
.msg-bubble--self {
  background:#038BFF;
  color:#fff;
  border-radius:18px 18px 4px 18px;
  padding:8px 14px;
  font-size:14px; line-height:1.45; word-break:break-word; max-width:100%;
}
.msg-actions--self {
  display:flex; align-items:center; gap:6px; justify-content:flex-end;
  margin-top:3px; opacity:0; transition:opacity .1s;
}
.msg-row--self:hover .msg-actions--self { opacity:1; }

/* ─ Other (left side) ─ */
.msg-row--other { flex-direction:row; }
.msg-avatar { flex-shrink:0; }
.msg-content--other {
  display:flex; flex-direction:column; align-items:flex-start;
  max-width:72%;
}
.msg-meta--other {
  display:flex; align-items:center; gap:5px;
  margin-top:3px;
}
.msg-author { font-weight:600; font-size:12px; color:var(--text-primary); }
.msg-time { font-size:10px; color:var(--text-muted); }
.msg-pin-marker { font-size:10px; }
.msg-bubble--other {
  background:#DBEAFE;
  color:var(--text-primary);
  border-radius:18px 18px 18px 4px;
  padding:8px 14px;
  font-size:14px; line-height:1.45; word-break:break-word; max-width:100%;
}
.msg-actions--other {
  display:flex; align-items:center; gap:6px; justify-content:flex-start;
  margin-top:3px; opacity:0; transition:opacity .1s;
}
.msg-row--other:hover .msg-actions--other { opacity:1; }

/* ─ Deleted state: strip bubble background ─ */
.msg-row--deleted .msg-bubble--self,
.msg-row--deleted .msg-bubble--other {
  background:transparent; padding:4px 0;
}
.msg-deleted-text { color:var(--text-muted); font-style:italic; font-size:13px; }

/* ─ Pinned state ─ */
.msg-row--pinned .msg-bubble--other { background:#fffbea; outline:1px solid #fde68a; }
.msg-row--pinned .msg-bubble--self  { filter:brightness(.9); }

/* ─ Shared body extras ─ */
.msg-body-text { display:inline; }
.msg-edited-label { font-size:10px; opacity:.7; }

/* ─ Heart button ─ */
.msg-heart-btn {
  background:none; border:none; cursor:pointer; font-size:12px;
  color:var(--text-muted); padding:2px 4px; border-radius:4px;
  transition:color .1s, transform .1s;
}
.msg-heart-btn:hover { color:#f43f5e; transform:scale(1.15); }
.msg-heart-active { color:#f43f5e !important; }

/* ─ Action buttons ─ */
.msg-action-btn {
  background:none; border:none; cursor:pointer; font-size:11px;
  color:var(--text-muted); padding:2px 5px; border-radius:3px;
  transition:background .1s, color .1s;
}
.msg-action-btn:hover { background:var(--border); color:var(--text-primary); }
.msg-action-delete:hover { background:#fee2e2; color:#dc2626; }

/* ─ Edit form ─ */
.msg-edit-form { margin-top:4px; width:100%; }
.msg-edit-textarea {
  width:100%; box-sizing:border-box; padding:6px 10px;
  border:1px solid var(--accent-blue); border-radius:10px;
  font-size:13px; font-family:inherit; resize:none;
}

/* ─ Touch devices: always show actions (no hover) ─ */
@media (hover:none) {
  .msg-actions--self,
  .msg-actions--other { opacity:1 !important; }
}

.conv-day-divider {
  text-align:center; font-size:11px; color:var(--text-muted);
  margin:8px 0; position:relative;
}
.conv-day-divider::before {
  content:''; position:absolute; top:50%; left:0; right:0;
  height:1px; background:var(--border);
}
.conv-day-divider span {
  position:relative; background:var(--surface, #fff); padding:0 8px;
}

/* ── Members panel ──────────────────────────────────────────────────────── */
.conv-members-panel {
  position:fixed; top:56px; right:0; z-index:200;
  width:240px; max-height:calc(100dvh - 76px);
  overflow-y:auto; background:var(--bg,#fff);
  border-left:1px solid var(--border); border-bottom:1px solid var(--border);
  border-radius:0 0 0 8px; box-shadow:-2px 4px 12px rgba(0,0,0,.12);
  display:none; /* hidden by default */
}
.conv-members-panel.open { display:block; }
.conv-members-panel-header {
  padding:10px 14px 6px; font-size:11px; font-weight:700;
  letter-spacing:.5px; text-transform:uppercase; color:var(--text-muted);
  border-bottom:1px solid var(--border);
}
.conv-member-row {
  display:flex; align-items:center; gap:10px;
  padding:8px 14px; font-size:13px; color:var(--text-primary);
}
.conv-member-row:not(:last-child) { border-bottom:1px solid var(--border); }
</style>

<!-- ── Members panel dropdown ────────────────────────────────────────────── -->
<div id="conv-members-panel" class="conv-members-panel" role="dialog" aria-label="Chat members">
  <div class="conv-members-panel-header">
    <?= count($convMembers) ?> member<?= count($convMembers) !== 1 ? 's' : '' ?>
  </div>
  <?php foreach ($convMembers as $m): ?>
    <?php
      $mPhotoId = (int)($m['photo_public_file_id'] ?? 0);
      $mFn      = trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? ''));
      $mName    = $mFn !== '' ? $mFn : ($m['email'] ?? 'Unknown');
      $mInitial = strtoupper(substr($mFn !== '' ? $mFn : ($m['email'] ?? '?'), 0, 1));
      $mPhotoUrl = $mPhotoId > 0 ? Files::profilePhotoUrl($mPhotoId) : '';
    ?>
    <div class="conv-member-row">
      <?php if ($mPhotoUrl !== ''): ?>
        <img src="<?= e($mPhotoUrl) ?>" class="avatar" style="width:28px;height:28px;flex-shrink:0;" alt="">
      <?php else: ?>
        <div class="avatar-placeholder"
             style="width:28px;height:28px;font-size:10px;flex-shrink:0;
                    background:var(--gradient-brand);"><?= e($mInitial) ?></div>
      <?php endif; ?>
      <span><?= e($mName) ?></span>
    </div>
  <?php endforeach; ?>
</div>

<div class="conv-page-outer">

  <div class="conv-wrap">

  <?php if (!empty($pinnedMessages)): ?>
  <details class="conv-pinned-banner">
    <summary>
      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#F59E0B"
           stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
           aria-hidden="true" style="vertical-align:-1px;margin-right:3px;">
        <line x1="12" y1="17" x2="12" y2="22"/>
        <path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24z"/>
      </svg>
      <?= count($pinnedMessages) ?> pinned message<?= count($pinnedMessages) !== 1 ? 's' : '' ?>
    </summary>
    <?php foreach ($pinnedMessages as $pm): ?>
      <div class="conv-pinned-item">
        <strong><?= e(trim(($pm['first_name'] ?? '') . ' ' . ($pm['last_name'] ?? ''))) ?></strong>:
        <?= nl2br(e(mb_strimwidth($pm['body'], 0, 200, '…'))) ?>
      </div>
    <?php endforeach; ?>
  </details>
  <?php endif; ?>

  <div class="conv-thread" id="conv-thread">
    <?php
    $prevDate = '';
    foreach ($messages as $msg) {
        $msgDate = date('Y-m-d', strtotime($msg['created_at']));
        if ($msgDate !== $prevDate) {
            $today     = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $label     = match(true) {
                $msgDate === $today     => 'Today',
                $msgDate === $yesterday => 'Yesterday',
                default                 => date('F j, Y', strtotime($msg['created_at'])),
            };
            echo '<div class="conv-day-divider"><span>' . e($label) . '</span></div>';
            $prevDate = $msgDate;
        }
        $hasReacted = isset($reactedIds[(int)$msg['id']]);
        echo ConversationUI::renderMessage($msg, $ctx->id, $isClubAdmin, $hasReacted);
    }
    if (empty($messages)) {
        echo '<p style="text-align:center;color:var(--text-muted);margin:40px 0;font-size:14px;">'
           . 'No messages yet. Be the first to say something!'
           . ' <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#038BFF"'
           . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
           . ' aria-hidden="true" style="vertical-align:-3px;">'
           . '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'
           . '</svg></p>';
    }
    ?>
  </div>

  <div class="conv-input-bar">
    <textarea id="msg-input" placeholder="Message <?= e($conv['name']) ?>…"
              rows="1" oninput="autoResize(this)"
              onkeydown="handleMsgKey(event)"></textarea>
    <button type="button" class="conv-send-btn" id="send-btn"
            onclick="sendMessage()" title="Send (Enter)">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
           stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <line x1="22" y1="2" x2="11" y2="13"/>
        <polygon points="22 2 15 22 11 13 2 9 22 2"/>
      </svg>
    </button>
  </div>

  </div><!-- .conv-wrap -->

</div><!-- .conv-page-outer -->

<script>
// ── Members panel toggle ──────────────────────────────────────────────────
function toggleMembersPanel() {
  const panel = document.getElementById('conv-members-panel');
  if (panel) panel.classList.toggle('open');
}
// Close members panel when clicking outside it
document.addEventListener('click', function(e) {
  const panel = document.getElementById('conv-members-panel');
  const btn   = document.getElementById('conv-members-btn');
  if (panel && panel.classList.contains('open')
      && !panel.contains(e.target) && btn && !btn.contains(e.target)) {
    panel.classList.remove('open');
  }
}, true);

// ── Constants ─────────────────────────────────────────────────────────────
const CONV_ID   = <?= json_encode($convId) ?>;
const CSRF      = <?= json_encode($csrfToken) ?>;
const IS_ADMIN  = <?= json_encode($isClubAdmin) ?>;
let   lastMsgId = <?= json_encode($maxMessageId) ?>;

// ── Scroll to bottom on load ──────────────────────────────────────────────
const thread = document.getElementById('conv-thread');
thread.scrollTop = thread.scrollHeight;

// ── Auto-resize textarea ──────────────────────────────────────────────────
function autoResize(ta) {
  ta.style.height = 'auto';
  ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
}

// ── Submit on Enter (Shift+Enter = newline) ───────────────────────────────
function handleMsgKey(e) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault();
    sendMessage();
  }
}

// ── Send message (AJAX) ───────────────────────────────────────────────────
function sendMessage() {
  const input   = document.getElementById('msg-input');
  const sendBtn = document.getElementById('send-btn');
  const body    = input.value.trim();
  if (!body) return;

  sendBtn.disabled = true;

  fetch('/clubs/conversations/post_eval.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    new URLSearchParams({ conversation_id: CONV_ID, body, _csrf_token: CSRF }),
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        input.value = '';
        input.style.height = 'auto';
        appendMessages(d.html, d.last_id);
      } else {
        alert(d.error || 'Could not send message.');
      }
    })
    .catch(() => alert('Network error. Please try again.'))
    .finally(() => { sendBtn.disabled = false; input.focus(); });
}

// ── Append new message HTML to thread ────────────────────────────────────
function appendMessages(html, newLastId) {
  if (!html) return;
  const atBottom = thread.scrollHeight - thread.clientHeight - thread.scrollTop < 80;
  const div = document.createElement('div');
  div.innerHTML = html;
  while (div.firstChild) thread.appendChild(div.firstChild);
  if (newLastId > lastMsgId) lastMsgId = newLastId;
  if (atBottom) thread.scrollTop = thread.scrollHeight;
}

// ── Poll for new messages every 5 seconds ────────────────────────────────
function pollMessages() {
  fetch('/clubs/conversations/ajax_poll.php?conversation_id=' + CONV_ID + '&after_id=' + lastMsgId)
    .then(r => r.json())
    .then(d => { if (d.success && d.html) appendMessages(d.html, d.last_id); })
    .catch(() => {}); // silently ignore poll errors
}
setInterval(pollMessages, 5000);

// ── Heart reaction ────────────────────────────────────────────────────────
function toggleHeart(msgId, btn) {
  fetch('/clubs/conversations/react_eval.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    new URLSearchParams({ message_id: msgId, reaction: 'heart', _csrf_token: CSRF }),
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        btn.classList.toggle('msg-heart-active', d.reacted);
        btn.title = d.reacted ? 'Remove reaction' : 'React with ♥';
        btn.textContent = '♥' + (d.count > 0 ? ' ' + d.count : '');
      }
    })
    .catch(() => {});
}

// ── Delete message ────────────────────────────────────────────────────────
function deleteMessage(msgId) {
  if (!confirm('Delete this message?')) return;
  fetch('/clubs/conversations/delete_eval.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    new URLSearchParams({ message_id: msgId, _csrf_token: CSRF }),
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        const row = document.getElementById('msg-' + msgId);
        if (row) row.outerHTML = d.html;
      } else {
        alert(d.error || 'Could not delete message.');
      }
    })
    .catch(() => {});
}

// ── Pin / unpin ───────────────────────────────────────────────────────────
function togglePin(msgId, btn) {
  fetch('/clubs/conversations/pin_eval.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    new URLSearchParams({ message_id: msgId, _csrf_token: CSRF }),
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        btn.textContent = d.pinned ? 'Unpin' : 'Pin';
        const row = document.getElementById('msg-' + msgId);
        if (row) row.classList.toggle('msg-row--pinned', d.pinned);
      } else {
        alert(d.error || 'Could not pin message.');
      }
    })
    .catch(() => {});
}

// ── Edit message ──────────────────────────────────────────────────────────
function startEditMessage(msgId) {
  document.getElementById('msg-body-' + msgId).style.display = 'none';
  document.getElementById('edit-form-' + msgId).style.display = 'block';
  document.getElementById('edit-body-' + msgId).focus();
}

function cancelEdit(msgId) {
  document.getElementById('msg-body-' + msgId).style.display = '';
  document.getElementById('edit-form-' + msgId).style.display = 'none';
}

function submitEdit(msgId) {
  const newBody = document.getElementById('edit-body-' + msgId).value.trim();
  if (!newBody) return;

  fetch('/clubs/conversations/edit_eval.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body:    new URLSearchParams({ message_id: msgId, body: newBody, _csrf_token: CSRF }),
  })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        const bodyEl = document.getElementById('msg-body-' + msgId);
        bodyEl.innerHTML = d.body_html;
        bodyEl.style.display = '';
        document.getElementById('edit-form-' + msgId).style.display = 'none';
      } else {
        alert(d.error || 'Could not save edit.');
      }
    })
    .catch(() => {});
}
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/../../templates/layout.php';
