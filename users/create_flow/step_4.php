<?php
declare(strict_types=1);

/**
 * Account creation wizard — Step 4: Enter your name.
 * Only reached after email verification (user is now logged in).
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Settings.php';
require_once __DIR__ . '/../../lib/Files.php';
require_once __DIR__ . '/../../auth.php';

Application::init();
Auth::requireLogin();

$siteTitle  = Settings::siteTitle();
$logoFileId = Settings::siteLogoFileId();
$logoUrl    = $logoFileId ? Files::publicFileUrl($logoFileId) : '';

$errorMsg = Flash::get('error');
$user     = Auth::currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>What's Your Name? — <?= e($siteTitle) ?></title>
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

    <!-- Step indicator (step 3 of 5) -->
    <div class="wizard-steps" aria-label="Step 3 of 5">
      <div class="wizard-step done"   title="Step 1: Email"></div>
      <div class="wizard-step done"   title="Step 2: Password"></div>
      <div class="wizard-step active" title="Step 3: Your name"></div>
      <div class="wizard-step"        title="Step 4: Profile photo"></div>
      <div class="wizard-step"        title="Step 5: Phone number"></div>
    </div>

    <p class="prompt">What is your <em>name?</em></p>
    <p class="text-muted mt-4" style="margin-bottom:20px;">Be yourself – we use real names on <?= e($siteTitle) ?>.</p>

    <?php if ($errorMsg): ?>
      <div class="flash flash--error"><?= e($errorMsg) ?></div>
    <?php endif; ?>

    <form method="POST" action="/users/create_flow/step_4_eval.php" novalidate>
      <?= csrf_input() ?>

      <div class="form-group">
        <input
          type="text"
          id="first_name"
          name="first_name"
          value="<?= e($user['first_name'] ?? '') ?>"
          placeholder="First Name"
          autocomplete="given-name"
          autofocus
          required>
      </div>

      <div class="form-group">
        <input
          type="text"
          id="last_name"
          name="last_name"
          value="<?= e($user['last_name'] ?? '') ?>"
          placeholder="Last Name"
          autocomplete="family-name"
          required>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Continue</button>
    </form>

  </div>
</div>

</body>
</html>
