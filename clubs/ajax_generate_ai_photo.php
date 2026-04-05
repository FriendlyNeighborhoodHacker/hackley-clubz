<?php
declare(strict_types=1);
/**
 * Ajax endpoint: generate a club profile photo via DALL-E 3.
 *
 * POST params:
 *   prompt      — text prompt for the image
 *   _csrf_token — CSRF token
 *
 * Returns JSON:
 *   { success: true,  data_url: "data:image/png;base64,..." }
 *   { success: false, error: "..." }
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';

Application::init();

// ── Feature flag ──────────────────────────────────────────────────────────
if (!defined('ENABLE_AI_PHOTO_GENERATION') || !ENABLE_AI_PHOTO_GENERATION) {
    echo json_encode(['success' => false, 'error' => 'AI photo generation is not enabled.']);
    exit;
}

// ── Auth ──────────────────────────────────────────────────────────────────
if (empty($_SESSION['uid'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
    exit;
}

// ── Method ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────────────────
try {
    csrf_verify();
} catch (\RuntimeException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

// ── Input ─────────────────────────────────────────────────────────────────
$prompt = trim($_POST['prompt'] ?? '');
if ($prompt === '') {
    echo json_encode(['success' => false, 'error' => 'Prompt is required.']);
    exit;
}
if (strlen($prompt) > 4000) {
    echo json_encode(['success' => false, 'error' => 'Prompt is too long (max 4000 characters).']);
    exit;
}

// ── OpenAI API key ────────────────────────────────────────────────────────
$apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
if ($apiKey === '') {
    echo json_encode(['success' => false, 'error' => 'OpenAI API key is not configured.']);
    exit;
}

// ── Call gpt-image-1-mini ─────────────────────────────────────────────────
set_time_limit(120); // image generation can take 30-60s; raise limit right before the call

$payload = json_encode([
    //'model'  => 'gpt-image-1-mini',
    'model'  => 'gpt-image-1.5',
    'prompt' => $prompt,
    'n'      => 1,
    'size'   => '1024x1024',
]);

$opts = [
    'http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]),
        'content'       => $payload,
        'ignore_errors' => true,  // read non-200 responses instead of returning false
        'timeout'       => 110,
    ],
];

$raw = @file_get_contents(
    'https://api.openai.com/v1/images/generations',
    false,
    stream_context_create($opts)
);

if ($raw === false) {
    echo json_encode(['success' => false, 'error' => 'Could not reach the OpenAI API. Please try again.']);
    exit;
}

$decoded = json_decode($raw, true);

if (!is_array($decoded)) {
    echo json_encode(['success' => false, 'error' => 'Unexpected response from OpenAI.']);
    exit;
}

// OpenAI surfaces errors in decoded['error']['message']
if (isset($decoded['error'])) {
    $msg = $decoded['error']['message'] ?? 'OpenAI returned an error.';
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

$b64 = $decoded['data'][0]['b64_json'] ?? null;
if (!$b64) {
    echo json_encode(['success' => false, 'error' => 'No image data returned by OpenAI.']);
    exit;
}

echo json_encode([
    'success'  => true,
    'data_url' => 'data:image/png;base64,' . $b64,
]);
exit;
