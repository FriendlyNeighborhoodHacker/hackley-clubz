<?php
declare(strict_types=1);

/**
 * Account creation wizard — Step 3: "Check your email" confirmation.
 * This is a static confirmation page shown after the verification email is sent.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Settings.php';
require_once __DIR__ . '/../../lib/Files.php';
require_once __DIR__ . '/../../auth.php';

Application::init();

if (!empty($_SESSION['uid'])) {
    redirect('/index.php');
}

$siteTitle  = Settings::siteTitle();
$logoFileId = Settings::siteLogoFileId();
$logoUrl    = $logoFileId ? Files::publicFileUrl($logoFileId) : '';
$email      = $_SESSION['create_email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Check Your Email — <?= e($siteTitle) ?></title>
  <link rel="stylesheet" href="<?= Application::css_url() ?>">
</head>
<body>

<div class="auth-page">
  <div class="auth-card" style="text-align:center;">

    <!-- Logo -->
    <div class="auth-logo">
      <?php if ($logoUrl !== ''): ?>
        <img src="<?= e($logoUrl) ?>" alt="<?= e($siteTitle) ?> logo">
      <?php else: ?>
        <span class="app-name"><?= e($siteTitle) ?></span>
      <?php endif; ?>
    </div>

    <!-- Email icon -->
    <div style="margin: 24px auto 20px; width: 72px; height: 72px; border-radius: 50%;
                background: linear-gradient(135deg, #FF6B47, #2D1B69);
                display: flex; align-items: center; justify-content: center;">
      <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="white"
           stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
        <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
        <polyline points="22,6 12,13 2,6"/>
      </svg>
    </div>

    <p class="prompt" style="margin-bottom:16px;"><em>You're almost done!</em></p>

    <p style="color: var(--text-secondary); margin-bottom: 8px;">
      Please check your email and click on the verification link.
    </p>
    <?php if ($email !== ''): ?>
      <p style="color: var(--text-muted); font-size: 0.875rem;">
        We sent a link to <strong><?= e($email) ?></strong>
      </p>
    <?php endif; ?>

    <hr class="divider">

    <p class="text-muted">
      Didn't receive the email?
      <a href="/users/create_flow/step_2.php">Resend verification email</a>
    </p>

    <div class="auth-footer-links" style="margin-top: 12px;">
      <a href="/login.php">Back to login</a>
    </div>

  </div>
</div>

</body>
</html>
