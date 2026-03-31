<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/**
 * Manages file storage in the `public_files` table and the on-disk cache.
 *
 * Image tags should reference the URL returned by publicFileUrl() rather than
 * render_image.php directly — the method handles caching transparently.
 *
 * Cache layout: cache/public/{firstHexChar}/{md5(id)}.{ext}
 */
final class Files {

    private static function pdo(): \PDO {
        return pdo();
    }

    // -------------------------------------------------------------------------
    // URL resolution (with caching)
    // -------------------------------------------------------------------------

    /**
     * Return the best URL for a public file: a cached static path when available,
     * otherwise the dynamic render_image.php endpoint.
     *
     * Returns empty string for invalid/missing IDs.
     */
    public static function publicFileUrl(int $fileId): string {
        if ($fileId <= 0) return '';

        try {
            // Fetch metadata
            $st = self::pdo()->prepare(
                'SELECT content_type, original_filename FROM public_files WHERE id = ? LIMIT 1'
            );
            $st->execute([$fileId]);
            $meta = $st->fetch();
            if (!$meta) return '';

            $ext = self::cacheableExtension(
                (string)($meta['content_type']     ?? ''),
                (string)($meta['original_filename'] ?? '')
            );

            // Not a cacheable type — serve dynamically
            if ($ext === null) {
                return '/render_image.php?id=' . $fileId;
            }

            $hash      = md5((string)$fileId);
            $dirKey    = $hash[0];
            $filename  = $hash . $ext;
            $cacheDir  = self::cacheBaseDir() . '/' . $dirKey;
            $cachePath = $cacheDir . '/' . $filename;
            $cacheUrl  = self::cacheBaseUrl() . '/' . $dirKey . '/' . $filename;

            if (is_file($cachePath)) {
                return $cacheUrl;
            }

            // Write the file to cache
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);

            $st2 = self::pdo()->prepare('SELECT data FROM public_files WHERE id = ? LIMIT 1');
            $st2->execute([$fileId]);
            $row = $st2->fetch();
            if (!$row) return '/render_image.php?id=' . $fileId;

            $tmp = $cachePath . '.tmp' . bin2hex(random_bytes(4));
            if (@file_put_contents($tmp, $row['data'], LOCK_EX) === false) {
                @unlink($tmp);
                return '/render_image.php?id=' . $fileId;
            }
            @chmod($tmp, 0644);
            if (!@rename($tmp, $cachePath)) {
                @unlink($tmp);
                return '/render_image.php?id=' . $fileId;
            }

            return $cacheUrl;

        } catch (\Throwable $e) {
            return '/render_image.php?id=' . $fileId;
        }
    }

    /**
     * Convenience wrapper: returns URL for a profile photo, or empty string if none.
     */
    public static function profilePhotoUrl(?int $fileId): string {
        return ($fileId && $fileId > 0) ? self::publicFileUrl($fileId) : '';
    }

    // -------------------------------------------------------------------------
    // Storage
    // -------------------------------------------------------------------------

    /**
     * Insert binary data into public_files and return the new ID.
     *
     * @param string $data             Raw binary file content
     * @param string $contentType      MIME type (e.g. 'image/jpeg')
     * @param string $originalFilename Original upload filename
     * @param ?int   $createdByUserId  User who uploaded (null for system)
     */
    public static function insertPublicFile(
        string $data,
        string $contentType,
        string $originalFilename,
        ?int $createdByUserId
    ): int {
        $sha = hash('sha256', $data);
        $len = strlen($data);
        $st  = self::pdo()->prepare(
            "INSERT INTO public_files
               (data, content_type, original_filename, byte_length, sha256, created_by_user_id, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $st->execute([$data, $contentType, $originalFilename, $len, $sha, $createdByUserId]);
        return (int)self::pdo()->lastInsertId();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function cacheBaseDir(): string {
        return dirname(__DIR__) . '/cache/public';
    }

    private static function cacheBaseUrl(): string {
        return '/cache/public';
    }

    /**
     * Determine the file extension to use for caching.
     * Returns null if the content type is not a known cacheable image/pdf.
     */
    private static function cacheableExtension(string $contentType, string $originalFilename): ?string {
        $ct = strtolower(trim($contentType));
        $ctMap = [
            'image/jpeg'      => '.jpg',
            'image/png'       => '.png',
            'image/webp'      => '.webp',
            'image/gif'       => '.gif',
            'application/pdf' => '.pdf',
        ];
        if (isset($ctMap[$ct])) return $ctMap[$ct];

        // Fall back to extension from original filename
        $ext = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $extMap = [
            'jpg'  => '.jpg',
            'jpeg' => '.jpg',
            'png'  => '.png',
            'webp' => '.webp',
            'gif'  => '.gif',
            'pdf'  => '.pdf',
        ];
        return $extMap[$ext] ?? null;
    }
}
