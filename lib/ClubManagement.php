<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';

/**
 * All club-related database operations.
 *
 * Rules:
 *  - Every write method accepts a UserContext (for the ActivityLog).
 *  - SQL lives only in this class.
 *  - Errors are thrown as exceptions; callers decide how to handle them.
 */
final class ClubManagement {

    // -------------------------------------------------------------------------
    // Reads
    // -------------------------------------------------------------------------

    /**
     * Return all clubs sorted by name, with a member count attached.
     *
     * @return array[]
     */
    public static function listAllClubs(): array {
        $st = pdo()->query(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM club_memberships cm WHERE cm.club_id = c.id) AS member_count
             FROM clubs c
             ORDER BY c.name'
        );
        return $st->fetchAll() ?: [];
    }

    /**
     * Return the total number of clubs (for pagination, if needed later).
     */
    public static function countAllClubs(): int {
        $row = pdo()->query('SELECT COUNT(*) AS c FROM clubs')->fetch();
        return (int)($row['c'] ?? 0);
    }

    /**
     * Return a single club by ID (with member count), or null if not found.
     */
    public static function getClubById(int $id): ?array {
        $st = pdo()->prepare(
            'SELECT c.*,
                    (SELECT COUNT(*) FROM club_memberships cm WHERE cm.club_id = c.id) AS member_count
             FROM clubs c
             WHERE c.id = :id
             LIMIT 1'
        );
        $st->bindValue(':id', $id, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * Return a paginated, alphabetized list of all non-secret clubs.
     * Includes member count and whether the given user is already a member.
     *
     * @param  string   $keyword  Optional keyword filter (matches name or description).
     * @param  int[]    $days     Optional day filter; clubs must meet on at least one of these days.
     * @return array[]
     */
    public static function listPublicClubsPaginated(
        int    $limit,
        int    $offset,
        int    $forUserId = 0,
        string $keyword   = '',
        array  $days      = []
    ): array {
        [$where, $params] = self::buildPublicClubsWhere($keyword, $days);
        $params[':uid'] = $forUserId;
        $params[':lim'] = $limit;
        $params[':off'] = $offset;

        $sql = 'SELECT c.*,
                    (SELECT COUNT(*) FROM club_memberships cm  WHERE cm.club_id  = c.id) AS member_count,
                    (SELECT COUNT(*) FROM club_memberships cm2 WHERE cm2.club_id = c.id AND cm2.user_id = :uid) AS is_member
                FROM clubs c
                WHERE ' . $where . '
                ORDER BY c.name
                LIMIT :lim OFFSET :off';

        $st = pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $type = in_array($k, [':uid', ':lim', ':off'], true) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $st->bindValue($k, is_int($v) ? $v : $v, $type);
        }
        $st->execute();
        return $st->fetchAll() ?: [];
    }

    /**
     * Total count of public (non-secret) clubs, optionally filtered.
     *
     * @param  string $keyword  Optional keyword filter.
     * @param  int[]  $days     Optional day filter.
     */
    public static function countPublicClubs(string $keyword = '', array $days = []): int {
        [$where, $params] = self::buildPublicClubsWhere($keyword, $days);
        $sql = 'SELECT COUNT(*) AS c FROM clubs c WHERE ' . $where;
        $st  = pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, \PDO::PARAM_STR);
        }
        $st->execute();
        return (int)($st->fetch()['c'] ?? 0);
    }

    /**
     * Build the shared WHERE clause + params for public club listing/counting.
     *
     * @param  string $keyword
     * @param  int[]  $days
     * @return array{0: string, 1: array<string,string>}
     */
    private static function buildPublicClubsWhere(string $keyword, array $days): array {
        $conditions = ['c.is_secret = 0'];
        $params     = [];

        if ($keyword !== '') {
            $conditions[]        = '(c.name LIKE :kw_name OR c.description LIKE :kw_desc)';
            $params[':kw_name']  = '%' . $keyword . '%';
            $params[':kw_desc']  = '%' . $keyword . '%';
        }

        if (!empty($days)) {
            $dayParts = [];
            foreach (array_values($days) as $i => $day) {
                $key          = ':day' . $i;
                $dayParts[]   = 'FIND_IN_SET(' . $key . ', c.meeting_days) > 0';
                $params[$key] = (string)(int)$day;
            }
            $conditions[] = '(' . implode(' OR ', $dayParts) . ')';
        }

        return [implode(' AND ', $conditions), $params];
    }

    /**
     * Return true if the given user has club-admin privileges for this club.
     */
    public static function isUserClubAdmin(int $userId, int $clubId): bool {
        $st = pdo()->prepare(
            'SELECT COUNT(*) AS c FROM club_memberships
             WHERE user_id = :uid AND club_id = :cid AND is_club_admin = 1'
        );
        $st->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $st->bindValue(':cid', $clubId, \PDO::PARAM_INT);
        $st->execute();
        return (int)($st->fetch()['c'] ?? 0) > 0;
    }

    /**
     * Return true if the given user is a member of the given club.
     */
    public static function isUserMember(int $userId, int $clubId): bool {
        $st = pdo()->prepare(
            'SELECT COUNT(*) AS c FROM club_memberships
             WHERE user_id = :uid AND club_id = :cid'
        );
        $st->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $st->bindValue(':cid', $clubId, \PDO::PARAM_INT);
        $st->execute();
        return (int)($st->fetch()['c'] ?? 0) > 0;
    }

    /**
     * Return all members of a club, with user data joined, sorted by last name.
     *
     * Each row contains: id, first_name, last_name, email, phone,
     *   photo_public_file_id, user_type, is_club_admin, role.
     *
     * @return array[]
     */
    public static function listClubMembers(int $clubId): array {
        $st = pdo()->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
                    u.photo_public_file_id, u.user_type,
                    cm.is_club_admin, cm.role
             FROM users u
             JOIN club_memberships cm ON cm.user_id = u.id
             WHERE cm.club_id = :cid
             ORDER BY u.last_name, u.first_name'
        );
        $st->bindValue(':cid', $clubId, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll() ?: [];
    }

    /**
     * Return all clubs a user is a member of, alphabetized.
     *
     * @return array[]
     */
    public static function listUserMemberships(int $userId): array {
        $st = pdo()->prepare(
            'SELECT c.*, cm.is_club_admin, cm.role, cm.notification_setting
             FROM clubs c
             JOIN club_memberships cm ON cm.club_id = c.id
             WHERE cm.user_id = :uid
             ORDER BY c.name'
        );
        $st->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Writes
    // -------------------------------------------------------------------------

    /**
     * Create a new club and return its new ID.
     *
     * @throws \RuntimeException if the actor is not an app admin, or name is empty
     */
    public static function createClub(
        UserContext $ctx,
        string      $name,
        string      $description,
        string      $meetingDays,
        string      $meetingLocation,
        ?int        $photoFileId,
        ?int        $heroFileId,
        bool        $isSecret
    ): int {
        if (!$ctx->admin) {
            throw new \RuntimeException('Only app admins may create clubs.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException('Club name is required.');
        }

        $st = pdo()->prepare(
            'INSERT INTO clubs
               (name, description, meeting_days, meeting_location, photo_public_file_id, hero_public_file_id, is_secret, created_at)
             VALUES (:name, :desc, :days, :location, :photo, :hero, :secret, NOW())'
        );
        $st->bindValue(':name',     $name,                    \PDO::PARAM_STR);
        $st->bindValue(':desc',     trim($description),       \PDO::PARAM_STR);
        $st->bindValue(':days',     trim($meetingDays),       \PDO::PARAM_STR);
        $st->bindValue(':location', trim($meetingLocation),   \PDO::PARAM_STR);
        $st->bindValue(':photo',  $photoFileId,
            $photoFileId !== null ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $st->bindValue(':hero',   $heroFileId,
            $heroFileId  !== null ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $st->bindValue(':secret', $isSecret ? 1 : 0,       \PDO::PARAM_INT);
        $st->execute();

        $clubId = (int)pdo()->lastInsertId();
        ActivityLog::log($ctx, 'club.create', ['club_id' => $clubId, 'name' => $name]);
        return $clubId;
    }

    /**
     * Update an existing club's metadata.
     *
     * Pass a non-null $photoFileId / $heroFileId to replace the current photo.
     * Pass $clearPhoto / $clearHero = true to remove the current photo without
     * replacing it.  If both a new ID and clear=true are passed, the new ID wins.
     *
     * @throws \RuntimeException if the actor is not an app admin, or name is empty
     */
    public static function updateClub(
        UserContext $ctx,
        int         $clubId,
        string      $name,
        string      $description,
        string      $meetingDays,
        string      $meetingLocation,
        ?int        $photoFileId,
        ?int        $heroFileId,
        bool        $isSecret,
        bool        $clearPhoto = false,
        bool        $clearHero  = false
    ): void {
        if (!$ctx->admin) {
            throw new \RuntimeException('Only app admins may edit clubs.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException('Club name is required.');
        }

        $sets   = ['name = :name', 'description = :desc', 'meeting_days = :days', 'meeting_location = :location', 'is_secret = :secret'];
        $params = [
            ':name'     => $name,
            ':desc'     => trim($description),
            ':days'     => trim($meetingDays),
            ':location' => trim($meetingLocation),
            ':secret'   => $isSecret ? 1 : 0,
        ];

        // Profile photo
        if ($photoFileId !== null) {
            $sets[]           = 'photo_public_file_id = :photo';
            $params[':photo'] = $photoFileId;
        } elseif ($clearPhoto) {
            $sets[] = 'photo_public_file_id = NULL';
        }

        // Hero photo
        if ($heroFileId !== null) {
            $sets[]          = 'hero_public_file_id = :hero';
            $params[':hero'] = $heroFileId;
        } elseif ($clearHero) {
            $sets[] = 'hero_public_file_id = NULL';
        }

        $params[':id'] = $clubId;
        $sql = 'UPDATE clubs SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $st  = pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $st->execute();

        ActivityLog::log($ctx, 'club.update', ['club_id' => $clubId, 'name' => $name]);
    }

    /**
     * Update club settings. Allowed for app admins AND club admins.
     *
     * Accepts the same photo/hero parameters as updateClub().
     *
     * @throws \RuntimeException if the actor lacks permission or name is empty
     */
    public static function updateClubSettings(
        UserContext $ctx,
        int         $clubId,
        string      $name,
        string      $description,
        string      $meetingDays,
        string      $meetingLocation,
        ?int        $photoFileId,
        ?int        $heroFileId,
        bool        $isSecret,
        bool        $clearPhoto = false,
        bool        $clearHero  = false
    ): void {
        if (!$ctx->admin && !self::isUserClubAdmin($ctx->id, $clubId)) {
            throw new \RuntimeException('You must be a club admin to update these settings.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException('Club name is required.');
        }

        $sets   = ['name = :name', 'description = :desc', 'meeting_days = :days',
                   'meeting_location = :location', 'is_secret = :secret'];
        $params = [
            ':name'     => $name,
            ':desc'     => trim($description),
            ':days'     => trim($meetingDays),
            ':location' => trim($meetingLocation),
            ':secret'   => $isSecret ? 1 : 0,
        ];

        if ($photoFileId !== null) {
            $sets[]           = 'photo_public_file_id = :photo';
            $params[':photo'] = $photoFileId;
        } elseif ($clearPhoto) {
            $sets[] = 'photo_public_file_id = NULL';
        }

        if ($heroFileId !== null) {
            $sets[]          = 'hero_public_file_id = :hero';
            $params[':hero'] = $heroFileId;
        } elseif ($clearHero) {
            $sets[] = 'hero_public_file_id = NULL';
        }

        $params[':id'] = $clubId;
        $sql = 'UPDATE clubs SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $st  = pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $st->execute();

        ActivityLog::log($ctx, 'club.settings_update', ['club_id' => $clubId, 'name' => $name]);
    }

    /**
     * Add the current user as a regular member of a club.
     *
     * @throws \RuntimeException if the user is already a member
     */
    public static function joinClub(UserContext $ctx, int $clubId): void {
        if ($ctx->id === 0) {
            throw new \RuntimeException('You must be logged in to join a club.');
        }

        if (self::isUserMember($ctx->id, $clubId)) {
            throw new \RuntimeException('You are already a member of this club.');
        }

        $st = pdo()->prepare(
            'INSERT INTO club_memberships (user_id, club_id, is_club_admin, role, notification_setting)
             VALUES (:uid, :cid, 0, \'\', \'everything\')'
        );
        $st->bindValue(':uid', $ctx->id, \PDO::PARAM_INT);
        $st->bindValue(':cid', $clubId,  \PDO::PARAM_INT);
        $st->execute();

        ActivityLog::log($ctx, 'club.join', ['club_id' => $clubId]);
    }

    /**
     * Remove the current user's membership from a club.
     *
     * @throws \RuntimeException if the user is not a member
     */
    public static function leaveClub(UserContext $ctx, int $clubId): void {
        if ($ctx->id === 0) {
            throw new \RuntimeException('You must be logged in to leave a club.');
        }

        if (!self::isUserMember($ctx->id, $clubId)) {
            throw new \RuntimeException('You are not a member of this club.');
        }

        $st = pdo()->prepare(
            'DELETE FROM club_memberships WHERE user_id = :uid AND club_id = :cid'
        );
        $st->bindValue(':uid', $ctx->id, \PDO::PARAM_INT);
        $st->bindValue(':cid', $clubId,  \PDO::PARAM_INT);
        $st->execute();

        ActivityLog::log($ctx, 'club.leave', ['club_id' => $clubId]);
    }

    /**
     * Permanently delete a club and all its child data (cascades via FK).
     *
     * @throws \RuntimeException if the actor is not an app admin, or club not found
     */
    public static function deleteClub(UserContext $ctx, int $clubId): void {
        if (!$ctx->admin) {
            throw new \RuntimeException('Only app admins may delete clubs.');
        }

        $club = self::getClubById($clubId);
        if (!$club) {
            throw new \RuntimeException('Club not found.');
        }

        $st = pdo()->prepare('DELETE FROM clubs WHERE id = :id');
        $st->bindValue(':id', $clubId, \PDO::PARAM_INT);
        $st->execute();

        ActivityLog::log($ctx, 'club.delete', [
            'club_id' => $clubId,
            'name'    => $club['name'] ?? '',
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Save an image from a base64 data URL (as produced by <canvas>.toDataURL).
     * Returns the new public_file ID, or null if the data URL is empty/invalid.
     */
    public static function savePhotoFromDataUrl(
        string $dataUrl,
        string $filename,
        ?int   $uploadedByUserId
    ): ?int {
        $dataUrl = trim($dataUrl);
        if ($dataUrl === '') return null;

        if (!preg_match('/^data:(image\/[a-z+\-]+);base64,(.+)$/i', $dataUrl, $m)) {
            return null;
        }

        $mime = strtolower($m[1]);
        $bin  = base64_decode($m[2], true);
        if ($bin === false || strlen($bin) === 0) return null;

        return \Files::insertPublicFile($bin, $mime, $filename, $uploadedByUserId);
    }

    /**
     * Save an image from a $_FILES upload slot.
     * Returns the new public_file ID, or null if nothing was uploaded.
     *
     * @throws \RuntimeException on disallowed mime type
     */
    public static function savePhotoFromUpload(
        array  $fileSlot,
        string $filename,
        ?int   $uploadedByUserId
    ): ?int {
        if (empty($fileSlot['tmp_name']) || $fileSlot['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mime    = (string)mime_content_type($fileSlot['tmp_name']);
        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Only JPEG, PNG, WebP, or GIF images are allowed.');
        }

        $bin = file_get_contents($fileSlot['tmp_name']);
        if ($bin === false || strlen($bin) === 0) {
            throw new \RuntimeException('Could not read uploaded file.');
        }

        return \Files::insertPublicFile($bin, $mime, $filename, $uploadedByUserId);
    }
}
