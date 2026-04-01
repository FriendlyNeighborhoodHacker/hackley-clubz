<?php
declare(strict_types=1);

/**
 * Password reset form — enter new password (via token link from email).
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/UserManagement.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Files.php';
require_once __DIR__ . '/../auth.php';

Application::init();

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    Flash::set('error', 'Invalid or missing reset token.');
    redirect('/users/forgot_password.php');
}

// Validate the token up-front so we show a clear error rather than a useless form
$user = UserManagement::findUserByResetToken($token);
if (!$user) {
    $siteTitle  = Settings::siteTitle();
    $logoFileId = Settings::siteLogoFileId();
    $logoUrl    = $logoFileId ? Files::publicFileUrl($logoFileId) : '';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Reset Link Expired — <?= e($siteTitle) ?></title>
      <link rel="stylesheet" href="<?= Application::css_url() ?>">
    </head>
    <body>
    <div class="auth-page">
      <div class="auth-card" style="text-align:center;">
        <div class="auth-logo">
          <?php if ($logoUrl !== ''): ?>
            <img src="<?= e($logoUrl) ?>" alt="<?= e($siteTitle) ?> logo">
          <?php else: ?>
            <span class="app-name"><?= e($siteTitle) ?></span>
          <?php endif; ?>
        </div>
        <div class="flash flash--error">This password reset link is invalid or has expired.</div>
        <p class="text-muted mt-4">Please request a new reset link.</p>
        <div class="auth-footer-links mt-6">
          <a href="/users/forgot_password.php">Request new link</a>
          &nbsp;·&nbsp;
          <a href="/login.php">Back to login</a>
        </div>
      </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

$siteTitle  = Settings::siteTitle();
$logoFileId = Settings::siteLogoFileId();
$logoUrl    = $logoFileId ? Files::publicFileUrl($logoFileId) : '';
$errorMsg   = Flash::get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password — <?= e($siteTitle) ?></title>
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

    <p class="prompt">Choose a new <em>password.</em></p>

    <?php if ($errorMsg): ?>
      <div class="flash flash--error mt-4"><?= e($errorMsg) ?></div>
    <?php endif; ?>

    <form method="POST" action="/users/reset_password_eval.php" novalidate style="margin-top:24px;">
      <?= csrf_input() ?>
      <input type="hidden" name="token" value="<?= e($token) ?>">

      <div class="form-group">
        <div class="input-wrapper">
          <input type="password" id="password" name="password"
                 placeholder="New password" autocomplete="new-password" autofocus required>
          <button type="button" class="input-eye-btn" aria-label="Show/hide password"
                  onclick="togglePw('password', this)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <div class="form-group">
        <div class="input-wrapper">
          <input type="password" id="password_confirm" name="password_confirm"
                 placeholder="Confirm new password" autocomplete="new-password" required>
          <button type="button" class="input-eye-btn" aria-label="Show/hide confirm password"
                  onclick="togglePw('password_confirm', this)">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Set New Password</button>
    </form>

  </div>
</div>

<script>
function togglePw(inputId, btn) {
  const input = document.getElementById(inputId);
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  btn.querySelector('svg').innerHTML = isHidden
    ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
    : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}
</script>

</body>
</html>
