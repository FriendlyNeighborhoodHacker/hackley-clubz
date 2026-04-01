<?php
declare(strict_types=1);

/**
 * Email verification handler.
 * Receives the ?token= link from the verification email.
 * On success: logs the user in and redirects to step 4 (name entry).
 * On failure: shows an error and links back to login.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/UserManagement.php';
require_once __DIR__ . '/../../lib/Settings.php';
require_once __DIR__ . '/../../lib/Files.php';
require_once __DIR__ . '/../../auth.php';

Application::init();

$token = trim($_GET['token'] ?? '');

if ($token === '') {
    Flash::set('error', 'Invalid verification link.');
    redirect('/login.php');
}

try {
    $user = UserManagement::verifyEmailToken($token);
} catch (\RuntimeException $e) {
    $siteTitle  = Settings::siteTitle();
    $logoFileId = Settings::siteLogoFileId();
    $logoUrl    = $logoFileId ? Files::publicFileUrl($logoFileId) : '';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <meta name="viewport" content="width=device-width, initial-scale=1.0">
      <title>Verification Failed — <?= e($siteTitle) ?></title>
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
        <div class="flash flash--error"><?= e($e->getMessage()) ?></div>
        <p class="text-muted mt-4">
          The link may have already been used or has expired.
        </p>
        <div class="auth-footer-links mt-6">
          <a href="/login.php">Go to login</a>
          &nbsp;·&nbsp;
          <a href="/users/create_flow/step_1.php">Create a new account</a>
        </div>
      </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Email is verified — log the user in and send to step 4 (name)
Auth::loginUser($user);

// Clean up step 1 session state
unset($_SESSION['create_email']);

redirect('/users/create_flow/step_4.php');
