<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Application.php';
require_once __DIR__ . '/lib/Settings.php';
require_once __DIR__ . '/lib/Files.php';
require_once __DIR__ . '/auth.php';

Application::init();

// Already logged in — go to homepage
if (!empty($_SESSION['uid'])) {
    redirect('/index.php');
}

$siteTitle  = Settings::siteTitle();
$logoFileId = Settings::siteLogoFileId();
$logoUrl    = $logoFileId ? Files::publicFileUrl($logoFileId) : '';

// Preserve ?redirect= for deep-link support
$redirectParam = trim($_GET['redirect'] ?? '');
$redirectInput = ($redirectParam !== '') ? '<input type="hidden" name="redirect" value="' . e($redirectParam) . '">' : '';

$errorMsg = Flash::get('error');
$infoMsg  = Flash::get('info');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login — <?= e($siteTitle) ?></title>
  <link rel="stylesheet" href="/public/css/app.css">
  <style>
    .splash-logo {
      max-width: 220px;
      width: 60%;
      margin-bottom: 28px;
      opacity: 0.92;
    }
  </style>
</head>
<body>

<!-- ===== Splash quote screen (shown before login card fades in) ===== -->
<div class="splash-screen" id="splashScreen" aria-hidden="true">
  <img src="/images/logo.png" alt="<?= e($siteTitle) ?>" class="splash-logo">
  <div class="splash-quote" id="splashQuote"></div>
</div>

<!-- ===== Login Card (hidden until splash finishes) ===== -->
<div class="auth-page" id="authPage" style="display:none;">
  <div class="auth-card">

    <!-- Logo / App Name -->
    <div class="auth-logo">
      <?php if ($logoUrl !== ''): ?>
        <img src="<?= e($logoUrl) ?>" alt="<?= e($siteTitle) ?> logo">
      <?php else: ?>
        <span class="app-name"><?= e($siteTitle) ?></span>
      <?php endif; ?>
    </div>

    <h1 class="auth-title">Welcome back</h1>

    <?php if ($errorMsg): ?>
      <div class="flash flash--error"><?= e($errorMsg) ?></div>
    <?php endif; ?>
    <?php if ($infoMsg): ?>
      <div class="flash flash--info"><?= e($infoMsg) ?></div>
    <?php endif; ?>

    <form method="POST" action="/login_eval.php" novalidate>
      <?= csrf_input() ?>
      <?= $redirectInput ?>

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

      <div class="form-group">
        <label for="password">Password</label>
        <div class="input-wrapper">
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter password"
            autocomplete="current-password"
            required>
          <button type="button" class="input-eye-btn" aria-label="Show/hide password"
                  onclick="togglePasswordVisibility('password', this)">
            <svg id="eye-icon-password" width="18" height="18" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Login</button>
    </form>

    <div class="auth-footer-links">
      <a href="/users/forgot_password.php">Forgot password?</a>
      &nbsp;·&nbsp;
      <a href="/users/create_flow/step_1.php">Create account</a>
    </div>

  </div>
</div>

<script>
// ─── Splash quote (one random quote, then transition to login) ────────────
const QUOTES = [
  "Go forth and spread beauty and light.",
  "United, we help one another.",
  "Character is higher than intellect.",
  "Enter here to be and find a friend."
];

const splashEl = document.getElementById('splashScreen');
const quoteEl  = document.getElementById('splashQuote');
const authEl   = document.getElementById('authPage');

const SHOW_MS = 2200; // how long the quote is fully visible
const FADE_MS =  600; // CSS fade-in/out duration (must match CSS transition)

// Pick one quote at random and show it
const quote = QUOTES[Math.floor(Math.random() * QUOTES.length)];
quoteEl.textContent = quote;

// Small tick so the element is in the DOM before we trigger the CSS transition
setTimeout(() => quoteEl.classList.add('visible'), 50);

// After FADE_MS (fade in) + SHOW_MS (visible), fade the splash out and reveal login
setTimeout(() => {
  splashEl.style.transition = 'opacity .6s ease';
  splashEl.style.opacity    = '0';
  authEl.style.display      = 'flex';
  setTimeout(() => { splashEl.style.display = 'none'; }, 700);
}, FADE_MS + SHOW_MS);

// ─── Password visibility toggle ───────────────────────────────────────────
function togglePasswordVisibility(inputId, btn) {
  const input = document.getElementById(inputId);
  const isHidden = input.type === 'password';
  input.type = isHidden ? 'text' : 'password';
  // Swap icon: open/closed eye
  btn.querySelector('svg').innerHTML = isHidden
    ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'
    : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
}
</script>

</body>
</html>
