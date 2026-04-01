<?php
declare(strict_types=1);

/**
 * Account creation wizard — Step 1: Enter your school email.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Settings.php';
require_once __DIR__ . '/../../lib/Files.php';
require_once __DIR__ . '/../../auth.php';

Application::init();

// Already logged in — skip to homepage
if (!empty($_SESSION['uid'])) {
    redirect('/index.php');
}

$siteTitle = Settings::siteTitle();
$logoFileId = Settings::siteLogoFileId();
$logoUrl    = $logoFileId ? Files::publicFileUrl($logoFileId) : '';

$errorMsg = Flash::get('error');
// Pre-fill email if coming back from an error
$prefillEmail = e($_SESSION['create_email'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account — <?= e($siteTitle) ?></title>
  <link rel="stylesheet" href="<?= Application::css_url() ?>">
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

    <!-- Step indicator: 4 steps (1=email, 2=password, 3=name, 4=photo) -->
    <div class="wizard-steps" aria-label="Step 1 of 4">
      <div class="wizard-step active" title="Step 1: Email"></div>
      <div class="wizard-step" title="Step 2: Password"></div>
      <div class="wizard-step" title="Step 3: Your name"></div>
      <div class="wizard-step" title="Step 4: Profile photo"></div>
    </div>

    <p class="prompt">What is your <em>school email?</em></p>

    <?php if ($errorMsg): ?>
      <div class="flash flash--error mt-4"><?= e($errorMsg) ?></div>
    <?php endif; ?>

    <form method="POST" action="/users/create_flow/step_1_eval.php" novalidate style="margin-top:24px;">
      <?= csrf_input() ?>

      <div class="form-group">
        <input
          type="email"
          id="email"
          name="email"
          value="<?= $prefillEmail ?>"
          placeholder="Enter email here."
          autocomplete="email"
          autofocus
          required>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Continue</button>
    </form>

    <div class="auth-footer-links">
      Already have an account? <a href="/login.php">Log in</a>
    </div>

  </div>
</div>

</body>
</html>
