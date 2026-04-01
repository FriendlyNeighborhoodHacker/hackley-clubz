<?php
declare(strict_types=1);

/**
 * Account creation wizard — Step 6: Add your phone number.
 * Optional — user may skip via "Do this later" link.
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

$errorMsg  = Flash::get('error');
$user      = Auth::currentUser();
$firstName = $user['first_name'] ?? '';

// Country codes: emoji flag, name, dial code
// US is first (default); rest are alphabetical
$countries = [
    ['flag' => '🇺🇸', 'name' => 'United States',    'code' => '+1'],
    ['flag' => '🇦🇺', 'name' => 'Australia',          'code' => '+61'],
    ['flag' => '🇦🇹', 'name' => 'Austria',             'code' => '+43'],
    ['flag' => '🇧🇪', 'name' => 'Belgium',             'code' => '+32'],
    ['flag' => '🇧🇷', 'name' => 'Brazil',              'code' => '+55'],
    ['flag' => '🇨🇦', 'name' => 'Canada',              'code' => '+1'],
    ['flag' => '🇨🇱', 'name' => 'Chile',               'code' => '+56'],
    ['flag' => '🇨🇳', 'name' => 'China',               'code' => '+86'],
    ['flag' => '🇨🇴', 'name' => 'Colombia',            'code' => '+57'],
    ['flag' => '🇭🇷', 'name' => 'Croatia',             'code' => '+385'],
    ['flag' => '🇩🇰', 'name' => 'Denmark',             'code' => '+45'],
    ['flag' => '🇫🇮', 'name' => 'Finland',             'code' => '+358'],
    ['flag' => '🇫🇷', 'name' => 'France',              'code' => '+33'],
    ['flag' => '🇩🇪', 'name' => 'Germany',             'code' => '+49'],
    ['flag' => '🇬🇷', 'name' => 'Greece',              'code' => '+30'],
    ['flag' => '🇭🇰', 'name' => 'Hong Kong',           'code' => '+852'],
    ['flag' => '🇭🇺', 'name' => 'Hungary',             'code' => '+36'],
    ['flag' => '🇮🇳', 'name' => 'India',               'code' => '+91'],
    ['flag' => '🇮🇩', 'name' => 'Indonesia',           'code' => '+62'],
    ['flag' => '🇮🇪', 'name' => 'Ireland',             'code' => '+353'],
    ['flag' => '🇮🇱', 'name' => 'Israel',              'code' => '+972'],
    ['flag' => '🇮🇹', 'name' => 'Italy',               'code' => '+39'],
    ['flag' => '🇯🇵', 'name' => 'Japan',               'code' => '+81'],
    ['flag' => '🇲🇾', 'name' => 'Malaysia',            'code' => '+60'],
    ['flag' => '🇲🇽', 'name' => 'Mexico',              'code' => '+52'],
    ['flag' => '🇳🇱', 'name' => 'Netherlands',         'code' => '+31'],
    ['flag' => '🇳🇿', 'name' => 'New Zealand',         'code' => '+64'],
    ['flag' => '🇳🇬', 'name' => 'Nigeria',             'code' => '+234'],
    ['flag' => '🇳🇴', 'name' => 'Norway',              'code' => '+47'],
    ['flag' => '🇵🇰', 'name' => 'Pakistan',            'code' => '+92'],
    ['flag' => '🇵🇭', 'name' => 'Philippines',         'code' => '+63'],
    ['flag' => '🇵🇱', 'name' => 'Poland',              'code' => '+48'],
    ['flag' => '🇵🇹', 'name' => 'Portugal',            'code' => '+351'],
    ['flag' => '🇷🇴', 'name' => 'Romania',             'code' => '+40'],
    ['flag' => '🇷🇺', 'name' => 'Russia',              'code' => '+7'],
    ['flag' => '🇸🇦', 'name' => 'Saudi Arabia',        'code' => '+966'],
    ['flag' => '🇸🇬', 'name' => 'Singapore',           'code' => '+65'],
    ['flag' => '🇿🇦', 'name' => 'South Africa',        'code' => '+27'],
    ['flag' => '🇰🇷', 'name' => 'South Korea',         'code' => '+82'],
    ['flag' => '🇪🇸', 'name' => 'Spain',               'code' => '+34'],
    ['flag' => '🇸🇪', 'name' => 'Sweden',              'code' => '+46'],
    ['flag' => '🇨🇭', 'name' => 'Switzerland',         'code' => '+41'],
    ['flag' => '🇹🇼', 'name' => 'Taiwan',              'code' => '+886'],
    ['flag' => '🇹🇭', 'name' => 'Thailand',            'code' => '+66'],
    ['flag' => '🇹🇷', 'name' => 'Turkey',              'code' => '+90'],
    ['flag' => '🇬🇧', 'name' => 'United Kingdom',      'code' => '+44'],
    ['flag' => '🇦🇪', 'name' => 'UAE',                 'code' => '+971'],
    ['flag' => '🇺🇦', 'name' => 'Ukraine',             'code' => '+380'],
    ['flag' => '🇻🇳', 'name' => 'Vietnam',             'code' => '+84'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>What's Your Number? — <?= e($siteTitle) ?></title>
  <link rel="stylesheet" href="/public/css/app.css">
  <style>
    .phone-row { display:flex; gap:8px; align-items:stretch; }
    .phone-row input { flex:1; min-width:0; }

    /* Custom compact country-code picker */
    .cc-picker { position:relative; flex:0 0 auto; }
    .cc-trigger {
      display:flex; align-items:center; gap:6px;
      padding:0 12px; height:100%;
      border:1px solid var(--border); border-radius:var(--radius);
      background:var(--surface); cursor:pointer;
      font-size:15px; white-space:nowrap; line-height:1;
    }
    .cc-trigger:hover, .cc-trigger:focus { border-color:var(--accent-blue); outline:none; }
    .cc-arrow { font-size:10px; color:var(--text-muted); margin-left:2px; }
    .cc-dropdown {
      display:none; position:absolute; top:calc(100% + 4px); left:0;
      min-width:250px; max-height:260px; overflow-y:auto;
      background:var(--surface); border:1px solid var(--border);
      border-radius:var(--radius); box-shadow:0 4px 20px rgba(0,0,0,.12);
      list-style:none; padding:4px 0; margin:0; z-index:200;
    }
    .cc-dropdown.open { display:block; }
    .cc-option { padding:9px 14px; cursor:pointer; font-size:14px; white-space:nowrap; }
    .cc-option:hover { background:var(--bg); }
    .cc-option.selected { font-weight:600; }
  </style>
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

    <!-- Step indicator (step 5 of 5) -->
    <div class="wizard-steps" aria-label="Step 5 of 5">
      <div class="wizard-step done"   title="Step 1: Email"></div>
      <div class="wizard-step done"   title="Step 2: Password"></div>
      <div class="wizard-step done"   title="Step 3: Your name"></div>
      <div class="wizard-step done"   title="Step 4: Profile photo"></div>
      <div class="wizard-step active" title="Step 5: Phone number"></div>
    </div>

    <p class="prompt">
      <?php if ($firstName !== ''): ?>
        What's your <em>number,</em> <?= e($firstName) ?>?
      <?php else: ?>
        <em>What's your number?</em>
      <?php endif; ?>
    </p>

    <?php if ($errorMsg): ?>
      <div class="flash flash--error mt-4"><?= e($errorMsg) ?></div>
    <?php endif; ?>

    <form method="POST" action="/users/create_flow/step_6_eval.php" novalidate id="phoneForm">
      <?= csrf_input() ?>

      <div class="form-group">
        <div class="phone-row">

          <!-- Compact country-code picker -->
          <div class="cc-picker" id="ccPicker">
            <button type="button" class="cc-trigger" id="ccTrigger" aria-haspopup="listbox" aria-expanded="false">
              <span id="ccFlag">🇺🇸</span>
              <span id="ccCode">+1</span>
              <span class="cc-arrow">▾</span>
            </button>
            <ul class="cc-dropdown" id="ccDropdown" role="listbox">
              <?php foreach ($countries as $c): ?>
                <li class="cc-option<?= $c['name'] === 'United States' ? ' selected' : '' ?>"
                    role="option"
                    data-code="<?= e($c['code']) ?>"
                    data-flag="<?= e($c['flag']) ?>">
                  <?= e($c['flag'] . ' ' . $c['name'] . ' (' . $c['code'] . ')') ?>
                </li>
              <?php endforeach; ?>
            </ul>
            <input type="hidden" name="country_code" id="countryCodeHidden" value="+1">
          </div>

          <input
            type="tel"
            id="phoneLocal"
            name="phone_local"
            placeholder="(555) 555-5555"
            autocomplete="tel-national"
            autofocus
            inputmode="tel">
        </div>
      </div>

      <button type="submit" class="btn btn-primary btn-block">Continue</button>
    </form>

    <a href="/index.php" class="skip-link mt-4">Do this later →</a>

  </div>
</div>

<script>
(function () {
  const trigger   = document.getElementById('ccTrigger');
  const dropdown  = document.getElementById('ccDropdown');
  const flagEl    = document.getElementById('ccFlag');
  const codeEl    = document.getElementById('ccCode');
  const hidden    = document.getElementById('countryCodeHidden');
  const phoneInput = document.getElementById('phoneLocal');

  // Toggle dropdown open/closed
  trigger.addEventListener('click', function (e) {
    e.stopPropagation();
    const isOpen = dropdown.classList.toggle('open');
    this.setAttribute('aria-expanded', isOpen);
  });

  // Select a country
  dropdown.addEventListener('click', function (e) {
    const item = e.target.closest('.cc-option');
    if (!item) return;
    const code = item.dataset.code;
    const flag = item.dataset.flag;
    flagEl.textContent = flag;
    codeEl.textContent = code;
    hidden.value = code;
    dropdown.querySelectorAll('.cc-option').forEach(el => el.classList.remove('selected'));
    item.classList.add('selected');
    dropdown.classList.remove('open');
    trigger.setAttribute('aria-expanded', 'false');
    phoneInput.placeholder = code === '+1' ? '(555) 555-5555' : 'Phone number';
  });

  // Close when clicking outside
  document.addEventListener('click', function () {
    dropdown.classList.remove('open');
    trigger.setAttribute('aria-expanded', 'false');
  });

  // Auto-format phone as user types
  phoneInput.addEventListener('input', function () {
    const raw = this.value.replace(/\D/g, '');
    this.value = formatNumber(raw, hidden.value);
  });

  function formatNumber(digits, code) {
    if (!digits) return '';
    if (code === '+1') {
      if (digits.length <= 3)  return '(' + digits;
      if (digits.length <= 6)  return '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
      return '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6, 10);
    }
    return digits.slice(0, 15).replace(/(\d{3})(?=\d)/g, '$1 ').trim();
  }
})();
</script>

</body>
</html>
