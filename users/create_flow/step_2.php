<?php
declare(strict_types=1);

/**
 * Account creation wizard — Step 2: Choose a password.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Settings.php';
require_once __DIR__ . '/../../lib/Files.php';
require_once __DIR__ . '/../../auth.php';

function eyeIconSvg(): string {
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
      <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
      <circle cx="12" cy="12" r="3"/>
    </svg>';
}

Application::init();

if (!empty($_SESSION['uid'])) {
    redirect('/index.php');
}

// Must have completed step 1
if (empty($_SESSION['create_email'])) {
    redirect('/users/create_flow/step_1.php');
}

$siteTitle  = Settings::siteTitle();
$logoFileId = Settings::siteLogoFileId();
$logoUrl    = $logoFileId ? Files::publicFileUrl($logoFileId) : '';
$email      = $_SESSION['create_email'];

$errorMsg = Flash::get('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — <?= e($siteTitle) ?></title>
  <link rel="stylesheet" href="/public/css/app.css">
</head>
<body>

<div class="auth-page">
  <div class="auth-card">

    <!-- Logo -->
    <div class="auth-logo">
      <?php if ($logoUrl !== ''): ?>
        <img src="<?= e($logoUrl) ?>" alt="<?= e($siteTitle) ?> logo">
      <?php else: ?>
        <span class="app-name"><?= e($siteTitle) ?></span>
      <?php endif; ?>
    </div>

    <!-- Step indicator -->
    <div class="wizard-steps" aria-label="Step 2 of 4">
      <div class="wizard-step done"   title="Step 1: Email"></div>
      <div class="wizard-step active" title="Step 2: Password"></div>
      <div class="wizard-step"        title="Step 3: Your name"></div>
      <div class="wizard-step"        title="Step 4: Profile photo"></div>
    </div>

    <p class="prompt">Enter a <em>password:</em></p>
    <p class="text-muted mt-4" style="margin-bottom:20px;">Setting up account for <strong><?= e($email) ?></strong></p>

    <?php if ($errorMsg): ?>
      <div class="flash flash--error"><?= e($errorMsg) ?></div>
    <?php endif; ?>

    <form method="POST" action="/users/create_flow/step_2_eval.php" novalidate>
      <?= csrf_input() ?>

      <div class="form-group">
        <div class="input-wrapper">
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter password here."
            autocomplete="new-password"
            autofocus
            required>
          <button type="button" class="input-eye-btn" aria-label="Show/hide password"
                  onclick="togglePw('password', this)">
            <?= eyeIconSvg() ?>
          </button>
        </div>
      </div>

      <div class="form-group">
        <div class="input-wrapper">
          <input
            type="password"
            id="password_confirm"
            name="password_confirm"
            placeholder="Confirm Password"
            autocomplete="new-password"
            required>
          <button type="button" class="input-eye-btn" aria-label="Show/hide confirm password"
                  onclick="togglePw('password_confirm', this)">
            <?= eyeIconSvg() ?>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Continue</button>
    </form>

    <div class="auth-footer-links">
      <a href="/users/create_flow/step_1.php">← Back</a>
    </div>

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
