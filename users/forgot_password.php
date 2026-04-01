<?php
declare(strict_types=1);

/**
 * Forgot password — step 1: enter your email address.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Files.php';
require_once __DIR__ . '/../auth.php';

Application::init();

if (!empty($_SESSION['uid'])) {
    redirect('/index.php');
}

$siteTitle  = Settings::siteTitle();
$logoFileId = Settings::siteLogoFileId();
$logoUrl    = $logoFileId ? Files::publicFileUrl($logoFileId) : '';

$errorMsg   = Flash::get('error');
$successMsg = Flash::get('success');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password — <?= e($siteTitle) ?></title>
  <link rel="stylesheet" href="<?= Application::css_url() ?>">
</head>
<body>

<div class="auth-page">
  <div class="auth-card">

    <div class="auth-logo">
      <?php if ($logoUrl !== ''): ?>
        <img src="<?= e($logoUrl) ?>" alt="<?= e($siteTitle) ?> logo">
      <?php else: ?>
        <span class="app-name"><?= e($siteTitle) ?></span>
      <?php endif; ?>
    </div>

    <p class="prompt" style="text-align:center; margin-bottom:24px;">Reset your <em>password.</em></p>

    <?php if ($errorMsg): ?>
      <div class="flash flash--error"><?= e($errorMsg) ?></div>
    <?php endif; ?>
    <?php if ($successMsg): ?>
      <div class="flash flash--success"><?= e($successMsg) ?></div>
    <?php endif; ?>

    <p class="text-muted mb-6">Enter your school email address and we'll send you a link to reset your password.</p>

    <form method="POST" action="/users/forgot_password_eval.php" novalidate>
      <?= csrf_input() ?>

      <div class="form-group">
        <label for="email">School email</label>
        <input
          type="email"
          id="email"
          name="email"
          placeholder="yourname@hackleyschool.org"
          autocomplete="email"
          autofocus
          required>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Send Reset Link</button>
    </form>

    <div class="auth-footer-links">
      <a href="/login.php">← Back to login</a>
    </div>

  </div>
</div>

</body>
</html>
