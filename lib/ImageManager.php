<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Files.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';

/**
 * Handles image uploads and retrieval for Hackley Clubz.
 *
 * Supports:
 *  - Storing raw binary image data (from file uploads).
 *  - Decoding base64-encoded image data (from canvas/cropper in the browser).
 *  - Returning display URLs via Files::publicFileUrl() (which applies caching).
 */
final class ImageManager {

    /** Maximum allowed upload size in bytes (default: 8 MB). */
    const MAX_BYTES = 8 * 1024 * 1024;

    /** Allowed MIME types for image uploads. */
    const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    // -------------------------------------------------------------------------
    // Store
    // -------------------------------------------------------------------------

    /**
     * Store raw binary image data and return the new public_file ID.
     *
     * @param UserContext $ctx            Actor (for activity log)
     * @param string      $binaryData     Raw binary image content
     * @param string      $contentType    MIME type (e.g. 'image/jpeg')
     * @param string      $originalName   Original filename
     * @throws \RuntimeException on validation failure
     */
    public static function storeImage(
        UserContext $ctx,
        string $binaryData,
        string $contentType,
        string $originalName = 'upload'
    ): int {
        if (strlen($binaryData) > self::MAX_BYTES) {
            throw new \RuntimeException('Image file is too large. Maximum size is 8 MB.');
        }
        if (!in_array(strtolower(trim($contentType)), self::ALLOWED_TYPES, true)) {
            throw new \RuntimeException('Unsupported image type. Please upload a JPEG, PNG, WebP, or GIF.');
        }

        $fileId = Files::insertPublicFile($binaryData, $contentType, $originalName, $ctx->id);
        ActivityLog::log($ctx, ActivityLog::ACTION_USER_UPDATE_PHOTO, ['file_id' => $fileId]);
        return $fileId;
    }

    /**
     * Store a base64-encoded image (e.g., from a canvas/cropper element) and return the new ID.
     *
     * Accepts both bare base64 and data-URL format:
     *   data:image/jpeg;base64,/9j/4AAQ...
     *
     * @throws \RuntimeException on decode failure or validation failure
     */
    public static function storeBase64Image(
        UserContext $ctx,
        string $base64Data,
        string $originalName = 'upload'
    ): int {
        // Strip optional data-URL prefix and extract MIME type
        $contentType = 'image/jpeg'; // default
        if (preg_match('/^data:([^;]+);base64,(.+)$/s', $base64Data, $m)) {
            $contentType = strtolower(trim($m[1]));
            $base64Data  = $m[2];
        }

        $binary = base64_decode($base64Data, strict: true);
        if ($binary === false) {
            throw new \RuntimeException('Invalid base64 image data.');
        }

        return self::storeImage($ctx, $binary, $contentType, $originalName);
    }

    // -------------------------------------------------------------------------
    // Retrieve
    // -------------------------------------------------------------------------

    /**
     * Return the display URL for a stored image.
     * Returns empty string if no file ID is provided.
     */
    public static function imageUrl(?int $fileId): string {
        if ($fileId === null || $fileId <= 0) return '';
        return Files::publicFileUrl($fileId);
    }
}
