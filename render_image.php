<?php
declare(strict_types=1);

/**
 * Dynamically serve an image from the public_files table.
 *
 * URL: /render_image.php?id={int}
 *
 * This endpoint is the fallback when the on-disk cache has not yet been
 * written by Files::publicFileUrl(). Files::publicFileUrl() writes the cache
 * and returns a static URL; this file is only reached when the cache is cold
 * or for non-cacheable types.
 *
 * No authentication is required — public_files are public by design.
 */

require_once __DIR__ . '/config.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid image ID.');
}

try {
    $st = pdo()->prepare(
        'SELECT content_type, original_filename, data FROM public_files WHERE id = :id LIMIT 1'
    );
    $st->bindValue(':id', $id, \PDO::PARAM_INT);
    $st->execute();
    $row = $st->fetch();
} catch (\Throwable $e) {
    http_response_code(500);
    exit('Database error.');
}

if (!$row) {
    http_response_code(404);
    exit('Image not found.');
}

$contentType = trim((string)($row['content_type'] ?? 'application/octet-stream'));
$data        = (string)($row['data'] ?? '');

// Send caching headers (public, 30 days — these files are immutable once stored)
header('Content-Type: '   . $contentType);
header('Content-Length: ' . strlen($data));
header('Cache-Control: public, max-age=2592000, immutable');
header('X-Content-Type-Options: nosniff');

echo $data;
