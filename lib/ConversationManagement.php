<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';

/**
 * All conversation/message database operations.
 *
 * Rules:
 *  - Every write method accepts a UserContext (for the ActivityLog).
 *  - SQL lives only in this class.
 *  - Errors are thrown as exceptions; callers decide how to handle them.
 */
final class ConversationManagement {

    // -------------------------------------------------------------------------
    // Reads
    // -------------------------------------------------------------------------

    /**
     * Return a single conversation by ID, or null.
     */
    public static function getConversationById(int $id): ?array {
        $st = pdo()->prepare(
            'SELECT * FROM conversations WHERE id = :id LIMIT 1'
        );
        $st->bindValue(':id', $id, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * Return whether a user is a member of a conversation.
     */
    public static function isUserMemberOfConversation(int $userId, int $conversationId): bool {
        $st = pdo()->prepare(
            'SELECT 1 FROM conversation_memberships
             WHERE conversation_id = :cid AND user_id = :uid LIMIT 1'
        );
        $st->bindValue(':cid', $conversationId, \PDO::PARAM_INT);
        $st->bindValue(':uid', $userId,         \PDO::PARAM_INT);
        $st->execute();
        return (bool)$st->fetchColumn();
    }

    /**
     * Return conversations that the user belongs to for a specific club,
     * ordered: General first, then Leadership, then custom alphabetically.
     *
     * @return array[]
     */
    public static function listConversationsForUserInClub(int $clubId, int $userId): array {
        $st = pdo()->prepare(
            'SELECT c.*,
                    (SELECT MAX(m.id) FROM messages m
                     WHERE m.conversation_id = c.id AND m.deleted_at IS NULL) AS last_message_id
             FROM conversations c
             INNER JOIN conversation_memberships cm
               ON cm.conversation_id = c.id AND cm.user_id = :uid
             WHERE c.club_id = :club_id
             ORDER BY
               CASE c.type WHEN \'general\' THEN 0 WHEN \'leadership\' THEN 1 ELSE 2 END ASC,
               c.name ASC'
        );
        $st->bindValue(':uid',     $userId, \PDO::PARAM_INT);
        $st->bindValue(':club_id', $clubId, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll() ?: [];
    }

    /**
     * Return all conversations the user belongs to, keyed by club_id.
     * Used to load all sidebar conversations in one query.
     *
     * @return array<int, array[]>  club_id => [ conversation rows ]
     */
    public static function listAllConversationsForUser(int $userId): array {
        $st = pdo()->prepare(
            'SELECT c.*
             FROM conversations c
             INNER JOIN conversation_memberships cm
               ON cm.conversation_id = c.id AND cm.user_id = :uid
             ORDER BY c.club_id,
               CASE c.type WHEN \'general\' THEN 0 WHEN \'leadership\' THEN 1 ELSE 2 END ASC,
               c.name ASC'
        );
        $st->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $st->execute();
        $rows   = $st->fetchAll() ?: [];
        $byClub = [];
        foreach ($rows as $row) {
            $byClub[(int)$row['club_id']][] = $row;
        }
        return $byClub;
    }

    /**
     * Return public (non-secret, custom) conversations in a club the user has NOT joined.
     *
     * @return array[]
     */
    public static function listJoinableConversations(int $clubId, int $userId): array {
        $st = pdo()->prepare(
            'SELECT c.*
             FROM conversations c
             WHERE c.club_id  = :club_id
               AND c.is_secret = 0
               AND c.type      = \'custom\'
               AND NOT EXISTS (
                 SELECT 1 FROM conversation_memberships cm
                 WHERE cm.conversation_id = c.id AND cm.user_id = :uid
               )
             ORDER BY c.name ASC'
        );
        $st->bindValue(':club_id', $clubId, \PDO::PARAM_INT);
        $st->bindValue(':uid',     $userId, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll() ?: [];
    }

    /**
     * Return messages for a conversation (oldest-first), joining sender info and heart counts.
     *
     * @param int $afterId  If > 0 only return messages with id > afterId (polling).
     * @param int $limit
     * @return array[]
     */
    public static function listMessages(int $conversationId, int $afterId = 0, int $limit = 60): array {
        $sql = 'SELECT m.*,
                       u.first_name, u.last_name, u.email AS user_email,
                       u.photo_public_file_id,
                       (SELECT COUNT(*) FROM message_reactions mr
                        WHERE mr.message_id = m.id AND mr.reaction = \'heart\') AS heart_count
                FROM messages m
                LEFT JOIN users u ON u.id = m.user_id
                WHERE m.conversation_id = :cid';
        if ($afterId > 0) {
            $sql .= ' AND m.id > :after_id';
        }
        $sql .= ' ORDER BY m.created_at ASC, m.id ASC LIMIT :lim';

        $st = pdo()->prepare($sql);
        $st->bindValue(':cid', $conversationId, \PDO::PARAM_INT);
        $st->bindValue(':lim', $limit,          \PDO::PARAM_INT);
        if ($afterId > 0) {
            $st->bindValue(':after_id', $afterId, \PDO::PARAM_INT);
        }
        $st->execute();
        return $st->fetchAll() ?: [];
    }

    /**
     * Return pinned, non-deleted messages for a conversation.
     *
     * @return array[]
     */
    public static function listPinnedMessages(int $conversationId): array {
        $st = pdo()->prepare(
            'SELECT m.*, u.first_name, u.last_name
             FROM messages m
             LEFT JOIN users u ON u.id = m.user_id
             WHERE m.conversation_id = :cid
               AND m.is_pinned = 1
               AND m.deleted_at IS NULL
             ORDER BY m.created_at ASC'
        );
        $st->bindValue(':cid', $conversationId, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll() ?: [];
    }

    /**
     * Return a single message by ID, or null.
     */
    public static function getMessageById(int $id): ?array {
        $st = pdo()->prepare('SELECT * FROM messages WHERE id = :id LIMIT 1');
        $st->bindValue(':id', $id, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * Return whether a user has reacted with a given reaction to a message.
     */
    public static function hasReacted(int $userId, int $messageId, string $reaction = 'heart'): bool {
        $st = pdo()->prepare(
            'SELECT 1 FROM message_reactions
             WHERE message_id = :mid AND user_id = :uid AND reaction = :r LIMIT 1'
        );
        $st->bindValue(':mid', $messageId, \PDO::PARAM_INT);
        $st->bindValue(':uid', $userId,    \PDO::PARAM_INT);
        $st->bindValue(':r',   $reaction);
        $st->execute();
        return (bool)$st->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Add a user to a conversation. Idempotent — silently ignores duplicates.
     */
    private static function addUserToConversation(int $conversationId, int $userId): void {
        $st = pdo()->prepare(
            'INSERT IGNORE INTO conversation_memberships (conversation_id, user_id)
             VALUES (:cid, :uid)'
        );
        $st->bindValue(':cid', $conversationId, \PDO::PARAM_INT);
        $st->bindValue(':uid', $userId,         \PDO::PARAM_INT);
        $st->execute();
    }

    /**
     * Remove a user from a specific conversation.
     */
    private static function removeUserFromConversation(int $conversationId, int $userId): void {
        $st = pdo()->prepare(
            'DELETE FROM conversation_memberships
             WHERE conversation_id = :cid AND user_id = :uid'
        );
        $st->bindValue(':cid', $conversationId, \PDO::PARAM_INT);
        $st->bindValue(':uid', $userId,         \PDO::PARAM_INT);
        $st->execute();
    }

    // -------------------------------------------------------------------------
    // System-level ensures (idempotent, no UserContext required)
    // -------------------------------------------------------------------------

    /**
     * Ensure a "General" conversation exists for the club. Returns its ID.
     */
    public static function ensureGeneralConversation(int $clubId): int {
        $st = pdo()->prepare(
            'SELECT id FROM conversations WHERE club_id = :cid AND type = \'general\' LIMIT 1'
        );
        $st->bindValue(':cid', $clubId, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        if ($row) {
            return (int)$row['id'];
        }
        $st = pdo()->prepare(
            'INSERT INTO conversations (club_id, name, is_secret, type)
             VALUES (:cid, \'General\', 0, \'general\')'
        );
        $st->bindValue(':cid', $clubId, \PDO::PARAM_INT);
        $st->execute();
        return (int)pdo()->lastInsertId();
    }

    /**
     * Ensure a "Leadership" (secret) conversation exists for the club. Returns its ID.
     */
    public static function ensureLeadershipConversation(int $clubId): int {
        $st = pdo()->prepare(
            'SELECT id FROM conversations WHERE club_id = :cid AND type = \'leadership\' LIMIT 1'
        );
        $st->bindValue(':cid', $clubId, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        if ($row) {
            return (int)$row['id'];
        }
        $st = pdo()->prepare(
            'INSERT INTO conversations (club_id, name, is_secret, type)
             VALUES (:cid, \'Leadership\', 1, \'leadership\')'
        );
        $st->bindValue(':cid', $clubId, \PDO::PARAM_INT);
        $st->execute();
        return (int)pdo()->lastInsertId();
    }

    // -------------------------------------------------------------------------
    // Membership lifecycle hooks — called from club membership events
    // -------------------------------------------------------------------------

    /**
     * Called when a user joins a club.
     * Ensures the General conversation exists and adds the user to it.
     */
    public static function onUserJoinedClub(UserContext $ctx, int $clubId, int $userId): void {
        $convId = self::ensureGeneralConversation($clubId);
        self::addUserToConversation($convId, $userId);
        ActivityLog::log($ctx, 'conversation_member_added', [
            'club_id'         => $clubId,
            'conversation_id' => $convId,
            'added_user_id'   => $userId,
            'type'            => 'general',
        ]);
    }

    /**
     * Called when a user is granted club-admin status.
     * Ensures the Leadership conversation exists and adds them to it.
     */
    public static function onUserBecameClubAdmin(UserContext $ctx, int $clubId, int $userId): void {
        $convId = self::ensureLeadershipConversation($clubId);
        self::addUserToConversation($convId, $userId);
        ActivityLog::log($ctx, 'conversation_member_added', [
            'club_id'         => $clubId,
            'conversation_id' => $convId,
            'added_user_id'   => $userId,
            'type'            => 'leadership',
        ]);
    }

    /**
     * Called when a user loses club-admin status.
     * Removes them from the Leadership conversation.
     */
    public static function onUserLostClubAdminStatus(UserContext $ctx, int $clubId, int $userId): void {
        $st = pdo()->prepare(
            'SELECT id FROM conversations
             WHERE club_id = :cid AND type = \'leadership\' LIMIT 1'
        );
        $st->bindValue(':cid', $clubId, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        if (!$row) {
            return; // no leadership conversation exists yet — nothing to do
        }
        $convId = (int)$row['id'];
        self::removeUserFromConversation($convId, $userId);
        ActivityLog::log($ctx, 'conversation_member_removed', [
            'club_id'          => $clubId,
            'conversation_id'  => $convId,
            'removed_user_id'  => $userId,
            'type'             => 'leadership',
        ]);
    }

    /**
     * Called when a user leaves a club or is removed from it.
     * Removes them from every conversation belonging to that club.
     */
    public static function onUserLeftClub(UserContext $ctx, int $clubId, int $userId): void {
        $st = pdo()->prepare(
            'DELETE cm FROM conversation_memberships cm
             INNER JOIN conversations c ON c.id = cm.conversation_id
             WHERE c.club_id = :club_id AND cm.user_id = :uid'
        );
        $st->bindValue(':club_id', $clubId, \PDO::PARAM_INT);
        $st->bindValue(':uid',     $userId, \PDO::PARAM_INT);
        $st->execute();
        ActivityLog::log($ctx, 'conversation_all_memberships_removed', [
            'club_id'         => $clubId,
            'removed_user_id' => $userId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Conversation CRUD
    // -------------------------------------------------------------------------

    /**
     * Create a custom conversation. Only club admins and app admins may do this.
     *
     * @param int[] $memberUserIds Club member user IDs to add initially (creator is always included).
     * @return int The new conversation ID.
     * @throws \RuntimeException
     */
    public static function createConversation(
        UserContext $ctx,
        int         $clubId,
        string      $name,
        bool        $isSecret,
        array       $memberUserIds
    ): int {
        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException('Conversation name cannot be blank.');
        }

        // Always include the creator; deduplicate
        $memberUserIds[] = $ctx->id;
        $memberUserIds   = array_values(array_unique(array_map('intval', $memberUserIds)));

        $st = pdo()->prepare(
            'INSERT INTO conversations (club_id, name, is_secret, type, created_by_user_id)
             VALUES (:cid, :name, :secret, \'custom\', :creator)'
        );
        $st->bindValue(':cid',     $clubId,       \PDO::PARAM_INT);
        $st->bindValue(':name',    $name);
        $st->bindValue(':secret',  (int)$isSecret, \PDO::PARAM_INT);
        $st->bindValue(':creator', $ctx->id,       \PDO::PARAM_INT);
        $st->execute();
        $convId = (int)pdo()->lastInsertId();

        foreach ($memberUserIds as $uid) {
            self::addUserToConversation($convId, $uid);
        }

        ActivityLog::log($ctx, 'conversation_created', [
            'club_id'         => $clubId,
            'conversation_id' => $convId,
            'name'            => $name,
            'is_secret'       => $isSecret,
            'member_count'    => count($memberUserIds),
        ]);

        return $convId;
    }

    /**
     * Delete a custom conversation. General and Leadership cannot be deleted.
     *
     * @throws \RuntimeException
     */
    public static function deleteConversation(UserContext $ctx, int $conversationId): void {
        $conv = self::getConversationById($conversationId);
        if (!$conv) {
            throw new \RuntimeException('Conversation not found.');
        }
        if ($conv['type'] !== 'custom') {
            throw new \RuntimeException(
                'The General and Leadership conversations cannot be deleted.'
            );
        }

        $st = pdo()->prepare('DELETE FROM conversations WHERE id = :id');
        $st->bindValue(':id', $conversationId, \PDO::PARAM_INT);
        $st->execute();

        ActivityLog::log($ctx, 'conversation_deleted', [
            'conversation_id' => $conversationId,
            'club_id'         => (int)$conv['club_id'],
            'name'            => $conv['name'],
        ]);
    }

    /**
     * Join a public (non-secret) conversation. User must be a club member.
     *
     * @throws \RuntimeException
     */
    public static function joinConversation(UserContext $ctx, int $conversationId): void {
        $conv = self::getConversationById($conversationId);
        if (!$conv) {
            throw new \RuntimeException('Conversation not found.');
        }
        if ($conv['is_secret']) {
            throw new \RuntimeException('This is a private conversation and cannot be joined directly.');
        }
        // Already a member — silently succeed
        if (self::isUserMemberOfConversation($ctx->id, $conversationId)) {
            return;
        }

        self::addUserToConversation($conversationId, $ctx->id);

        ActivityLog::log($ctx, 'conversation_joined', [
            'conversation_id' => $conversationId,
            'club_id'         => (int)$conv['club_id'],
            'name'            => $conv['name'],
        ]);
    }

    // -------------------------------------------------------------------------
    // Message operations
    // -------------------------------------------------------------------------

    /**
     * Post a message. User must be a member of the conversation.
     *
     * @return int The new message ID.
     * @throws \RuntimeException
     */
    public static function postMessage(UserContext $ctx, int $conversationId, string $body): int {
        $body = trim($body);
        if ($body === '') {
            throw new \RuntimeException('Message cannot be blank.');
        }
        if (!self::isUserMemberOfConversation($ctx->id, $conversationId)) {
            throw new \RuntimeException('You are not a member of this conversation.');
        }

        $st = pdo()->prepare(
            'INSERT INTO messages (conversation_id, user_id, body)
             VALUES (:cid, :uid, :body)'
        );
        $st->bindValue(':cid',  $conversationId, \PDO::PARAM_INT);
        $st->bindValue(':uid',  $ctx->id,        \PDO::PARAM_INT);
        $st->bindValue(':body', $body);
        $st->execute();
        $msgId = (int)pdo()->lastInsertId();

        ActivityLog::log($ctx, 'message_posted', [
            'conversation_id' => $conversationId,
            'message_id'      => $msgId,
        ]);

        return $msgId;
    }

    /**
     * Edit a message. Only the original poster may edit their own non-deleted messages.
     *
     * @throws \RuntimeException
     */
    public static function editMessage(UserContext $ctx, int $messageId, string $newBody): void {
        $newBody = trim($newBody);
        if ($newBody === '') {
            throw new \RuntimeException('Message cannot be blank.');
        }

        $msg = self::getMessageById($messageId);
        if (!$msg) {
            throw new \RuntimeException('Message not found.');
        }
        if ($msg['deleted_at'] !== null) {
            throw new \RuntimeException('Cannot edit a deleted message.');
        }
        if ((int)$msg['user_id'] !== $ctx->id) {
            throw new \RuntimeException('You can only edit your own messages.');
        }

        $st = pdo()->prepare('UPDATE messages SET body = :body WHERE id = :id');
        $st->bindValue(':body', $newBody);
        $st->bindValue(':id',   $messageId, \PDO::PARAM_INT);
        $st->execute();

        ActivityLog::log($ctx, 'message_edited', ['message_id' => $messageId]);
    }

    /**
     * Soft-delete a message. The original poster can delete their own;
     * club admins and app admins can delete any.
     *
     * @throws \RuntimeException
     */
    public static function deleteMessage(
        UserContext $ctx,
        int         $messageId,
        bool        $isClubAdmin = false
    ): void {
        $msg = self::getMessageById($messageId);
        if (!$msg) {
            throw new \RuntimeException('Message not found.');
        }
        if ($msg['deleted_at'] !== null) {
            return; // already deleted — silently succeed
        }

        $isOwner = ((int)$msg['user_id'] === $ctx->id);
        if (!$isOwner && !$isClubAdmin && !$ctx->admin) {
            throw new \RuntimeException('You do not have permission to delete this message.');
        }

        $st = pdo()->prepare('UPDATE messages SET deleted_at = NOW() WHERE id = :id');
        $st->bindValue(':id', $messageId, \PDO::PARAM_INT);
        $st->execute();

        ActivityLog::log($ctx, 'message_deleted', ['message_id' => $messageId]);
    }

    /**
     * Toggle the pinned state of a message. Requires club-admin or app-admin rights.
     *
     * @return bool New pinned state.
     * @throws \RuntimeException
     */
    public static function togglePinMessage(UserContext $ctx, int $messageId): bool {
        $msg = self::getMessageById($messageId);
        if (!$msg) {
            throw new \RuntimeException('Message not found.');
        }
        $newPinned = $msg['is_pinned'] ? 0 : 1;

        $st = pdo()->prepare('UPDATE messages SET is_pinned = :p WHERE id = :id');
        $st->bindValue(':p',  $newPinned, \PDO::PARAM_INT);
        $st->bindValue(':id', $messageId, \PDO::PARAM_INT);
        $st->execute();

        ActivityLog::log($ctx, $newPinned ? 'message_pinned' : 'message_unpinned', [
            'message_id' => $messageId,
        ]);

        return (bool)$newPinned;
    }

    /**
     * Toggle a heart reaction on a message for the current user.
     *
     * @return array{reacted: bool, count: int}
     * @throws \RuntimeException
     */
    public static function toggleReaction(
        UserContext $ctx,
        int         $messageId,
        string      $reaction = 'heart'
    ): array {
        $msg = self::getMessageById($messageId);
        if (!$msg) {
            throw new \RuntimeException('Message not found.');
        }
        if ($msg['deleted_at'] !== null) {
            throw new \RuntimeException('Cannot react to a deleted message.');
        }

        $alreadyReacted = self::hasReacted($ctx->id, $messageId, $reaction);

        if ($alreadyReacted) {
            $st = pdo()->prepare(
                'DELETE FROM message_reactions
                 WHERE message_id = :mid AND user_id = :uid AND reaction = :r'
            );
        } else {
            $st = pdo()->prepare(
                'INSERT INTO message_reactions (message_id, user_id, reaction)
                 VALUES (:mid, :uid, :r)'
            );
        }
        $st->bindValue(':mid', $messageId, \PDO::PARAM_INT);
        $st->bindValue(':uid', $ctx->id,   \PDO::PARAM_INT);
        $st->bindValue(':r',   $reaction);
        $st->execute();

        $st2 = pdo()->prepare(
            'SELECT COUNT(*) FROM message_reactions
             WHERE message_id = :mid AND reaction = :r'
        );
        $st2->bindValue(':mid', $messageId, \PDO::PARAM_INT);
        $st2->bindValue(':r',   $reaction);
        $st2->execute();

        return [
            'reacted' => !$alreadyReacted,
            'count'   => (int)$st2->fetchColumn(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Club lifecycle: seed default conversations
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Seed the default General and Leadership conversations for a newly created club.
     * Call this immediately after the club row is inserted.
     *
     * General    → all club members (at creation time this is just the creator).
     * Leadership → all club admins  (same: just the creator).
     *
     * @param UserContext $ctx    Actor (the admin/user who created the club).
     * @param int         $clubId The newly created club ID.
     */
    public static function onClubCreated(UserContext $ctx, int $clubId): void {
        // Seed empty General + Leadership shells.
        // Members are added via onUserJoinedClub / onUserBecameClubAdmin as
        // the creator gets their membership set up after the club is created.
        self::ensureGeneralConversation($clubId);
        self::ensureLeadershipConversation($clubId);
        ActivityLog::log($ctx, 'conversation_seeded_on_club_create', ['club_id' => $clubId]);
    }

    /**
     * Backfill General and Leadership conversations for every existing club
     * that is missing them, and sync current members/admins into them.
     * Idempotent — safe to run multiple times.
     *
     * Returns: [ 'clubs_processed' => N, 'general_created' => N, 'leadership_created' => N ]
     */
    public static function backfillClubConversations(UserContext $ctx): array {
        $clubs = pdo()->query('SELECT id FROM clubs ORDER BY id')->fetchAll();

        $generalCreated    = 0;
        $leadershipCreated = 0;

        foreach ($clubs as $row) {
            $clubId = (int)$row['id'];

            // ── General ───────────────────────────────────────────────────
            $check = pdo()->prepare(
                'SELECT id FROM conversations WHERE club_id = :cid AND type = \'general\' LIMIT 1'
            );
            $check->bindValue(':cid', $clubId, \PDO::PARAM_INT);
            $check->execute();
            $genId = $check->fetchColumn();
            if (!$genId) {
                $genId = self::ensureGeneralConversation($clubId);
                $generalCreated++;
            }
            // Sync all club members into General
            $mems = pdo()->prepare('SELECT user_id FROM club_memberships WHERE club_id = :cid');
            $mems->bindValue(':cid', $clubId, \PDO::PARAM_INT);
            $mems->execute();
            foreach ($mems->fetchAll() as $m) {
                self::addUserToConversation((int)$genId, (int)$m['user_id']);
            }

            // ── Leadership ────────────────────────────────────────────────
            $check2 = pdo()->prepare(
                'SELECT id FROM conversations WHERE club_id = :cid AND type = \'leadership\' LIMIT 1'
            );
            $check2->bindValue(':cid', $clubId, \PDO::PARAM_INT);
            $check2->execute();
            $ldId = $check2->fetchColumn();
            if (!$ldId) {
                $ldId = self::ensureLeadershipConversation($clubId);
                $leadershipCreated++;
            }
            // Sync all club admins into Leadership
            $admins = pdo()->prepare(
                'SELECT user_id FROM club_memberships WHERE club_id = :cid AND is_club_admin = 1'
            );
            $admins->bindValue(':cid', $clubId, \PDO::PARAM_INT);
            $admins->execute();
            foreach ($admins->fetchAll() as $m) {
                self::addUserToConversation((int)$ldId, (int)$m['user_id']);
            }
        }

        ActivityLog::log($ctx, 'backfill_conversations', [
            'clubs_processed'    => count($clubs),
            'general_created'    => $generalCreated,
            'leadership_created' => $leadershipCreated,
        ]);

        return [
            'clubs_processed'    => count($clubs),
            'general_created'    => $generalCreated,
            'leadership_created' => $leadershipCreated,
        ];
    }

    /**
     * Return all members of a conversation, ordered by first/last name.
     * Each row includes basic user profile columns.
     *
     * @return array[]
     */
    public static function listConversationMembers(int $conversationId): array {
        $st = pdo()->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.user_type,
                    u.photo_public_file_id
             FROM conversation_memberships cm
             INNER JOIN users u ON u.id = cm.user_id
             WHERE cm.conversation_id = :cid
             ORDER BY u.first_name, u.last_name, u.email'
        );
        $st->bindValue(':cid', $conversationId, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll();
    }
}
