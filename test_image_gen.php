<?php
declare(strict_types=1);
/**
 * DEV-ONLY: Test page for OpenAI image generation.
 * Delete or gate behind a flag before going to production.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Application.php';
require_once __DIR__ . '/lib/Auth.php';

set_time_limit(0);   // Image generation can take 30-60s — remove the default limit

Application::init();
Auth::requireLogin();

// Admin-only
$user = Auth::currentUser();
if (empty($user['is_admin'])) {
    http_response_code(403);
    echo '<h2>403 — Admins only.</h2>';
    exit;
}

$DEFAULT_PROMPT = 'Please generate an image for a small icon for a high school club at Hackley High School.  It should be simple, because it needs to appear in a small icon, and clean. No words. No complex background.  Here is the club name: "Chess Club".';

$MODELS = ['gpt-image-1.5', 'gpt-image-1', 'gpt-image-1-mini', 'dall-e-3'];

$apiKey      = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
$results     = [];
$activePrompt = trim($_GET['prompt'] ?? '') !== '' ? trim($_GET['prompt']) : $DEFAULT_PROMPT;

if (isset($_GET['run']) && $apiKey !== '') {
    $model = $_GET['model'] ?? 'gpt-image-1.5';

    $payload = ['model' => $model, 'prompt' => $activePrompt, 'n' => 1, 'size' => '1024x1024'];
    // dall-e-3 needs response_format; gpt-image models return b64_json natively
    if ($model === 'dall-e-3') {
        $payload['response_format'] = 'b64_json';
    }

    $opts = [
        'http' => [
            'method'        => 'POST',
            'header'        => implode("\r\n", [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ]),
            'content'       => json_encode($payload),
            'ignore_errors' => true,
            'timeout'       => 90,
        ],
    ];

    $start  = microtime(true);
    $raw    = @file_get_contents('https://api.openai.com/v1/images/generations', false,
                                 stream_context_create($opts));
    $elapsed = round(microtime(true) - $start, 2);

    $decoded = $raw !== false ? json_decode($raw, true) : null;

    $results = [
        'model'   => $model,
        'elapsed' => $elapsed,
        'raw'     => $raw,
        'decoded' => $decoded,
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Image Gen Test</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width:900px; margin:40px auto; padding:0 20px; }
    h1   { font-size:1.4rem; margin-bottom:6px; }
    pre  { background:#f4f4f4; border:1px solid #ddd; border-radius:6px;
           padding:14px; overflow:auto; font-size:12px; white-space:pre-wrap; word-break:break-all; }
    .btn { display:inline-block; padding:8px 18px; border-radius:6px; border:none; cursor:pointer;
           font-size:14px; text-decoration:none; background:#038BFF; color:#fff; margin-right:6px; }
    .btn-secondary { background:#eee; color:#333; }
    .tag  { display:inline-block; font-size:11px; background:#e0f0ff; color:#0066cc;
            border-radius:4px; padding:2px 7px; margin-right:4px; }
    .error { color:#dc2626; }
    .ok    { color:#16a34a; }
    img.result { max-width:300px; border-radius:50%; border:3px solid #ddd; display:block; margin:12px 0; }
    .section { border:1px solid #ddd; border-radius:8px; padding:20px; margin-bottom:24px; }
  </style>
</head>
<body>

<h1>🧪 OpenAI Image Generation Test</h1>
<p style="color:#666; font-size:13px;">Admin-only dev page. Delete before production.</p>

<?php if ($apiKey === ''): ?>
  <p class="error">⚠️ <strong>OPENAI_API_KEY</strong> is not set in config.local.php.</p>
<?php else: ?>
  <p class="ok">✓ API key is configured.</p>
<?php endif; ?>

<div class="section">
  <h2 style="margin:0 0 10px; font-size:1rem;">Test prompt</h2>
  <form method="GET" action="">
    <input type="hidden" name="run" value="1">
    <textarea name="prompt" rows="5"
              style="width:100%; box-sizing:border-box; font-size:13px; padding:10px 12px;
                     border:1px solid #ccc; border-radius:6px; font-family:inherit;
                     resize:vertical; margin-bottom:12px;"><?= htmlspecialchars($activePrompt, ENT_QUOTES) ?></textarea>

    <p style="margin:0 0 8px; font-size:13px; color:#555;">Choose a model and click Run:</p>
    <div>
      <?php foreach ($MODELS as $m): ?>
        <button type="submit" name="model" value="<?= htmlspecialchars($m) ?>"
                class="btn <?= ($m !== 'gpt-image-1.5') ? 'btn-secondary' : '' ?>"
                style="margin-bottom:8px;">
          Run: <?= htmlspecialchars($m) ?>
        </button>
      <?php endforeach; ?>
    </div>
  </form>
</div>

<?php if (!empty($results)): ?>
<div class="section">
  <h2 style="margin:0 0 10px; font-size:1rem;">
    Results — <span class="tag"><?= htmlspecialchars($results['model']) ?></span>
    <span style="color:#999; font-size:12px;"><?= $results['elapsed'] ?>s</span>
  </h2>

  <?php if ($results['raw'] === false): ?>
    <p class="error">⚠️ Could not reach OpenAI API (file_get_contents returned false).</p>

  <?php elseif (isset($results['decoded']['error'])): ?>
    <p class="error">⚠️ API error: <?= htmlspecialchars($results['decoded']['error']['message'] ?? 'unknown') ?></p>
    <pre><?= htmlspecialchars(json_encode($results['decoded'], JSON_PRETTY_PRINT)) ?></pre>

  <?php else: ?>
    <?php
      // Try to find the image data — check b64_json and url in data[0]
      $item   = $results['decoded']['data'][0] ?? null;
      $b64    = $item['b64_json'] ?? null;
      $imgUrl = $item['url']      ?? null;
    ?>

    <?php if ($b64): ?>
      <p class="ok">✓ Got <strong>b64_json</strong> — <?= number_format(strlen($b64) / 1024, 1) ?> KB</p>
      <img src="data:image/png;base64,<?= $b64 ?>" class="result" alt="Generated image">
      <img src="data:image/png;base64,<?= $b64 ?>" style="width:64px;height:64px;border-radius:50%;border:2px solid #ddd;display:inline-block;vertical-align:middle;" alt="64px preview">
      <small style="color:#666; font-size:12px; vertical-align:middle; margin-left:8px;">64px circle preview</small>

    <?php elseif ($imgUrl): ?>
      <p class="ok">✓ Got <strong>url</strong> (no b64_json in response):</p>
      <img src="<?= htmlspecialchars($imgUrl) ?>" class="result" alt="Generated image">
      <p style="font-size:12px; color:#666;">Note: if the endpoint returns URLs instead of b64_json, the main endpoint needs updating.</p>

    <?php else: ?>
      <p class="error">⚠️ Response had neither b64_json nor url in data[0].</p>
    <?php endif; ?>

    <h3 style="font-size:0.85rem; color:#666; margin-top:16px;">Full response keys in data[0]:</h3>
    <pre><?= htmlspecialchars(json_encode(array_keys($item ?? []), JSON_PRETTY_PRINT)) ?></pre>

    <h3 style="font-size:0.85rem; color:#666; margin-top:8px;">Top-level response keys:</h3>
    <pre><?= htmlspecialchars(json_encode(array_keys($results['decoded'] ?? []), JSON_PRETTY_PRINT)) ?></pre>
  <?php endif; ?>
</div>
<?php endif; ?>

</body>
</html>
