<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib/Files.php';

/**
 * Rendering helpers for conversation/message UI fragments.
 *
 * These methods return HTML strings that are used both in the full page render
 * and in AJAX responses so that both paths produce identical markup.
 */
final class ConversationUI {

    /**
     * Render a single message row as an HTML string.
     *
     * @param array $msg          Row from listMessages() — includes user_* columns and heart_count.
     * @param int   $currentUserId
     * @param bool  $isClubAdmin   Whether the viewing user has club-admin rights.
     * @param bool  $hasReacted    Whether the current user has reacted with a heart.
     * @return string
     */
    public static function renderMessage(
        array $msg,
        int   $currentUserId,
        bool  $isClubAdmin,
        bool  $hasReacted
    ): string {
        $msgId     = (int)$msg['id'];
        $isDeleted = ($msg['deleted_at'] !== null);
        $isOwner   = ((int)($msg['user_id'] ?? -1) === $currentUserId);
        $isPinned  = (bool)($msg['is_pinned'] ?? false);
        $isSelf    = $isOwner; // alias for readability in layout decisions

        // ── Avatar (only shown for others, not for self) ──────────────────────
        $photoId = (int)($msg['photo_public_file_id'] ?? 0);
        if ($photoId > 0) {
            $avatarHtml = '<img src="' . e(Files::profilePhotoUrl($photoId)) . '" '
                        . 'class="avatar" style="width:32px;height:32px;" alt="">';
        } else {
            $fn       = $msg['first_name'] ?? '';
            $ln       = $msg['last_name']  ?? '';
            $initials = strtoupper(substr($fn, 0, 1) . substr($ln, 0, 1));
            if ($initials === '') {
                $initials = strtoupper(substr($msg['user_email'] ?? '?', 0, 1));
            }
            $avatarHtml = '<div class="avatar-placeholder" '
                        . 'style="width:32px;height:32px;font-size:11px;flex-shrink:0;'
                        . 'background:var(--gradient-brand);">'
                        . e($initials) . '</div>';
        }

        // ── Author name ───────────────────────────────────────────────────────
        $fn         = trim(($msg['first_name'] ?? '') . ' ' . ($msg['last_name'] ?? ''));
        $authorName = $fn !== '' ? $fn : ($msg['user_email'] ?? 'Unknown');

        // ── Timestamp ─────────────────────────────────────────────────────────
        $createdTs = strtotime($msg['created_at'] ?? 'now');
        $updatedTs = strtotime($msg['updated_at'] ?? $msg['created_at'] ?? 'now');
        $wasEdited = (!$isDeleted && ($updatedTs - $createdTs) > 2);
        $timeStr   = date('g:i a', $createdTs);
        $dateStr   = date('M j', $createdTs);
        $today     = date('M j');
        $timeLabel = ($dateStr === $today) ? $timeStr : $dateStr . ' ' . $timeStr;

        // ── Pinned marker ─────────────────────────────────────────────────────
        $pinnedMarker = $isPinned
            ? '<span class="msg-pin-marker" title="Pinned" aria-label="Pinned">'
              . '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#F59E0B"'
              . ' stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
              . ' aria-hidden="true" style="vertical-align:-1px;">'
              . '<line x1="12" y1="17" x2="12" y2="22"/>'
              . '<path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24z"/>'
              . '</svg>'
              . '</span>'
            : '';

        // ── Message body ──────────────────────────────────────────────────────
        if ($isDeleted) {
            $bodyHtml = '<span class="msg-deleted-text">This message was deleted.</span>';
        } else {
            $bodyHtml = '<span class="msg-body-text">' . nl2br(e($msg['body'])) . '</span>';
            if ($wasEdited) {
                $bodyHtml .= ' <span class="msg-edited-label">(edited)</span>';
            }
        }

        // ── Reaction button ───────────────────────────────────────────────────
        $heartCount  = (int)($msg['heart_count'] ?? 0);
        $heartActive = $hasReacted ? ' msg-heart-active' : '';
        $heartTitle  = $hasReacted ? 'Remove reaction' : 'React with ♥';
        $heartLabel  = $heartCount > 0 ? ' ' . $heartCount : '';
        $reactionHtml = '';
        if (!$isDeleted) {
            $reactionHtml = '<button type="button" '
                . 'class="msg-heart-btn' . $heartActive . '" '
                . 'data-msg-id="' . $msgId . '" '
                . 'title="' . e($heartTitle) . '" '
                . 'onclick="toggleHeart(' . $msgId . ', this)">'
                . '♥' . $heartLabel
                . '</button>';
        }

        // ── Admin / owner action buttons ──────────────────────────────────────
        $actionHtml = '';
        if (!$isDeleted) {
            if ($isOwner) {
                $actionHtml .= '<button type="button" class="msg-action-btn" '
                    . 'onclick="startEditMessage(' . $msgId . ')" title="Edit">Edit</button>';
            }
            if ($isOwner || $isClubAdmin) {
                $actionHtml .= '<button type="button" class="msg-action-btn msg-action-delete" '
                    . 'onclick="deleteMessage(' . $msgId . ')" title="Delete">Delete</button>';
            }
            if ($isClubAdmin) {
                $pinLabel = $isPinned ? 'Unpin' : 'Pin';
                $actionHtml .= '<button type="button" class="msg-action-btn" '
                    . 'onclick="togglePin(' . $msgId . ', this)" '
                    . 'title="' . $pinLabel . '">' . $pinLabel . '</button>';
            }
        }

        $actionsClass   = 'msg-actions' . ($isSelf ? ' msg-actions--self' : ' msg-actions--other');
        $actionsRowHtml = ($reactionHtml !== '' || $actionHtml !== '')
            ? '<div class="' . $actionsClass . '">' . $reactionHtml . $actionHtml . '</div>'
            : '';

        // ── Edit form (hidden by default) ─────────────────────────────────────
        $editFormHtml = '';
        if (!$isDeleted && $isOwner) {
            $editFormHtml = '<div id="edit-form-' . $msgId . '" class="msg-edit-form" style="display:none;">'
                . '<textarea id="edit-body-' . $msgId . '" class="msg-edit-textarea" rows="2">'
                . e($msg['body'])
                . '</textarea>'
                . '<div style="display:flex;gap:6px;margin-top:4px;">'
                . '<button type="button" class="btn btn-primary" style="font-size:12px;padding:4px 10px;" '
                . 'onclick="submitEdit(' . $msgId . ')">Save</button>'
                . '<button type="button" class="btn btn-secondary" style="font-size:12px;padding:4px 10px;" '
                . 'onclick="cancelEdit(' . $msgId . ')">Cancel</button>'
                . '</div>'
                . '</div>';
        }

        // ── Compose final HTML — self vs other ────────────────────────────────
        $deletedClass = $isDeleted ? ' msg-row--deleted' : '';
        $pinnedClass  = $isPinned  ? ' msg-row--pinned'  : '';
        $sideClass    = $isSelf    ? ' msg-row--self'    : ' msg-row--other';

        if ($isSelf) {
            // ── Self: bubble on the right, no avatar, no name; timestamp below ──
            $timeHtml = '<div class="msg-meta msg-meta--self">'
                . $pinnedMarker
                . '<span class="msg-time">' . e($timeLabel) . '</span>'
                . '</div>';
            return '<div class="msg-row' . $sideClass . $deletedClass . $pinnedClass . '"'
                . ' id="msg-' . $msgId . '" data-id="' . $msgId . '">'
                . '<div class="msg-content msg-content--self">'
                .   '<div class="msg-bubble msg-bubble--self" id="msg-body-' . $msgId . '">'
                .     $bodyHtml
                .   '</div>'
                .   $timeHtml          // timestamp below bubble
                .   $editFormHtml
                .   $actionsRowHtml
                . '</div>'
                . '</div>';
        } else {
            // ── Other: avatar left; author name above bubble, timestamp below ──
            $authorHtml = '<div class="msg-author-line">'
                . '<span class="msg-author">' . e($authorName) . '</span>'
                . $pinnedMarker
                . '</div>';
            $timeHtml = '<div class="msg-meta msg-meta--other">'
                . '<span class="msg-time">' . e($timeLabel) . '</span>'
                . '</div>';
            return '<div class="msg-row' . $sideClass . $deletedClass . $pinnedClass . '"'
                . ' id="msg-' . $msgId . '" data-id="' . $msgId . '">'
                . '<div class="msg-avatar">' . $avatarHtml . '</div>'
                . '<div class="msg-content msg-content--other">'
                .   $authorHtml        // author name above bubble
                .   '<div class="msg-bubble msg-bubble--other" id="msg-body-' . $msgId . '">'
                .     $bodyHtml
                .   '</div>'
                .   $timeHtml          // timestamp below bubble
                .   $editFormHtml
                .   $actionsRowHtml
                . '</div>'
                . '</div>';
        }
    }

    /**
     * Render a list of joinable conversations as an HTML fragment for AJAX responses.
     * Returns the inner HTML of the joinable list container.
     *
     * @param array[] $conversations
     * @param int     $clubId
     * @param string  $csrfToken
     */
    public static function renderJoinableList(
        array  $conversations,
        int    $clubId,
        string $csrfToken
    ): string {
        if (empty($conversations)) {
            return '<p style="font-size:12px;color:var(--text-muted);padding:6px 8px;margin:0;">'
                 . 'No other conversations to join.</p>';
        }

        $html = '';
        foreach ($conversations as $conv) {
            $cid  = (int)$conv['id'];
            $name = e($conv['name']);
            $html .= '<div class="joinable-conv-row" style="display:flex;align-items:center;'
                   . 'justify-content:space-between;padding:6px 4px;border-bottom:1px solid var(--border);">'
                   . '<span style="font-size:13px;color:var(--text-primary);">'
                   . '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" '
                   . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" '
                   . 'stroke-linejoin="round" style="margin-right:4px;vertical-align:-1px;">'
                   . '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>'
                   . '</svg>' . $name . '</span>'
                   . '<form method="POST" action="/clubs/conversations/join_eval.php" '
                   . 'style="margin:0;">'
                   . '<input type="hidden" name="_csrf_token" value="' . e($csrfToken) . '">'
                   . '<input type="hidden" name="conversation_id" value="' . $cid . '">'
                   . '<input type="hidden" name="club_id" value="' . $clubId . '">'
                   . '<button type="submit" class="btn btn-primary" '
                   . 'style="font-size:11px;padding:3px 8px;">Join</button>'
                   . '</form>'
                   . '</div>';
        }
        return $html;
    }
}
