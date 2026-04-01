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
        string      $meets,
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
               (name, description, meets, photo_public_file_id, hero_public_file_id, is_secret, created_at)
             VALUES (:name, :desc, :meets, :photo, :hero, :secret, NOW())'
        );
        $st->bindValue(':name',   $name,                    \PDO::PARAM_STR);
        $st->bindValue(':desc',   trim($description),       \PDO::PARAM_STR);
        $st->bindValue(':meets',  trim($meets),             \PDO::PARAM_STR);
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
        string      $meets,
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

        $sets   = ['name = :name', 'description = :desc', 'meets = :meets', 'is_secret = :secret'];
        $params = [
            ':name'   => $name,
            ':desc'   => trim($description),
            ':meets'  => trim($meets),
            ':secret' => $isSecret ? 1 : 0,
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
