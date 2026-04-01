<?php
declare(strict_types=1);

/**
 * Edit profile page — update name and profile photo.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Settings.php';
require_once __DIR__ . '/../lib/Files.php';
require_once __DIR__ . '/../auth.php';

Application::init();
Auth::requireLogin();

$user       = Auth::currentUser();
$photoUrl   = Files::profilePhotoUrl($user['photo_public_file_id'] ?? null);

$initials = strtoupper(
    substr($user['first_name'] ?? '', 0, 1) .
    substr($user['last_name']  ?? '', 0, 1)
);
if ($initials === '') $initials = strtoupper(substr($user['email'] ?? '', 0, 1));

$errorMsg   = Flash::get('error');
$successMsg = Flash::get('success');
$pageTitle  = 'Edit Profile';

// Parse stored phone into country code + local number for pre-filling the form
$storedPhone      = trim($user['phone'] ?? '');
$phoneCountryCode = '+1';
$phoneLocal       = '';
if ($storedPhone !== '') {
    if (str_starts_with($storedPhone, '+')) {
        $spacePos = strpos($storedPhone, ' ');
        if ($spacePos !== false) {
            $phoneCountryCode = substr($storedPhone, 0, $spacePos);
            $phoneLocal       = substr($storedPhone, $spacePos + 1);
        } else {
            $phoneCountryCode = $storedPhone;
        }
    } else {
        // Value stored without a country code prefix — treat as local number
        $phoneLocal = $storedPhone;
    }
}

$phoneCountries = [
    ['flag' => '🇺🇸', 'name' => 'United States',   'code' => '+1'],
    ['flag' => '🇦🇺', 'name' => 'Australia',         'code' => '+61'],
    ['flag' => '🇦🇹', 'name' => 'Austria',            'code' => '+43'],
    ['flag' => '🇧🇪', 'name' => 'Belgium',            'code' => '+32'],
    ['flag' => '🇧🇷', 'name' => 'Brazil',             'code' => '+55'],
    ['flag' => '🇨🇦', 'name' => 'Canada',             'code' => '+1'],
    ['flag' => '🇨🇱', 'name' => 'Chile',              'code' => '+56'],
    ['flag' => '🇨🇳', 'name' => 'China',              'code' => '+86'],
    ['flag' => '🇨🇴', 'name' => 'Colombia',           'code' => '+57'],
    ['flag' => '🇭🇷', 'name' => 'Croatia',            'code' => '+385'],
    ['flag' => '🇩🇰', 'name' => 'Denmark',            'code' => '+45'],
    ['flag' => '🇫🇮', 'name' => 'Finland',            'code' => '+358'],
    ['flag' => '🇫🇷', 'name' => 'France',             'code' => '+33'],
    ['flag' => '🇩🇪', 'name' => 'Germany',            'code' => '+49'],
    ['flag' => '🇬🇷', 'name' => 'Greece',             'code' => '+30'],
    ['flag' => '🇭🇰', 'name' => 'Hong Kong',          'code' => '+852'],
    ['flag' => '🇭🇺', 'name' => 'Hungary',            'code' => '+36'],
    ['flag' => '🇮🇳', 'name' => 'India',              'code' => '+91'],
    ['flag' => '🇮🇩', 'name' => 'Indonesia',          'code' => '+62'],
    ['flag' => '🇮🇪', 'name' => 'Ireland',            'code' => '+353'],
    ['flag' => '🇮🇱', 'name' => 'Israel',             'code' => '+972'],
    ['flag' => '🇮🇹', 'name' => 'Italy',              'code' => '+39'],
    ['flag' => '🇯🇵', 'name' => 'Japan',              'code' => '+81'],
    ['flag' => '🇲🇾', 'name' => 'Malaysia',           'code' => '+60'],
    ['flag' => '🇲🇽', 'name' => 'Mexico',             'code' => '+52'],
    ['flag' => '🇳🇱', 'name' => 'Netherlands',        'code' => '+31'],
    ['flag' => '🇳🇿', 'name' => 'New Zealand',        'code' => '+64'],
    ['flag' => '🇳🇬', 'name' => 'Nigeria',            'code' => '+234'],
    ['flag' => '🇳🇴', 'name' => 'Norway',             'code' => '+47'],
    ['flag' => '🇵🇰', 'name' => 'Pakistan',           'code' => '+92'],
    ['flag' => '🇵🇭', 'name' => 'Philippines',        'code' => '+63'],
    ['flag' => '🇵🇱', 'name' => 'Poland',             'code' => '+48'],
    ['flag' => '🇵🇹', 'name' => 'Portugal',           'code' => '+351'],
    ['flag' => '🇷🇴', 'name' => 'Romania',            'code' => '+40'],
    ['flag' => '🇷🇺', 'name' => 'Russia',             'code' => '+7'],
    ['flag' => '🇸🇦', 'name' => 'Saudi Arabia',       'code' => '+966'],
    ['flag' => '🇸🇬', 'name' => 'Singapore',          'code' => '+65'],
    ['flag' => '🇿🇦', 'name' => 'South Africa',       'code' => '+27'],
    ['flag' => '🇰🇷', 'name' => 'South Korea',        'code' => '+82'],
    ['flag' => '🇪🇸', 'name' => 'Spain',              'code' => '+34'],
    ['flag' => '🇸🇪', 'name' => 'Sweden',             'code' => '+46'],
    ['flag' => '🇨🇭', 'name' => 'Switzerland',        'code' => '+41'],
    ['flag' => '🇹🇼', 'name' => 'Taiwan',             'code' => '+886'],
    ['flag' => '🇹🇭', 'name' => 'Thailand',           'code' => '+66'],
    ['flag' => '🇹🇷', 'name' => 'Turkey',             'code' => '+90'],
    ['flag' => '🇬🇧', 'name' => 'United Kingdom',     'code' => '+44'],
    ['flag' => '🇦🇪', 'name' => 'UAE',                'code' => '+971'],
    ['flag' => '🇺🇦', 'name' => 'Ukraine',            'code' => '+380'],
    ['flag' => '🇻🇳', 'name' => 'Vietnam',            'code' => '+84'],
];

// Find the emoji flag for the initially-selected country code
$selectedFlag = '🇺🇸';
foreach ($phoneCountries as $c) {
    if ($c['code'] === $phoneCountryCode) {
        $selectedFlag = $c['flag'];
        break; // first match wins (e.g. +1 → United States, not Canada)
    }
}

ob_start();
?>
<style>
  /* Custom compact country-code picker — profile edit page */
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

<div style="max-width:540px; margin:0 auto;">

  <div style="display:flex; align-items:center; gap:12px; margin-bottom:28px;">
    <a href="/profile/index.php" style="color:var(--text-secondary); font-size:14px;">← My Profile</a>
    <h1 style="font-family:var(--font-title); font-weight:200; font-size:1.5rem;">Edit Profile</h1>
  </div>

  <?php if ($errorMsg): ?><div class="flash flash--error"><?= e($errorMsg) ?></div><?php endif; ?>
  <?php if ($successMsg): ?><div class="flash flash--success"><?= e($successMsg) ?></div><?php endif; ?>

  <!-- Photo section -->
  <div style="background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:24px; margin-bottom:20px;">
    <h2 style="font-size:0.95rem; color:var(--text-secondary); margin-bottom:20px; font-weight:400;">Profile photo</h2>

    <div style="display:flex; align-items:center; gap:20px; margin-bottom:20px;">
      <?php if ($photoUrl !== ''): ?>
        <img src="<?= e($photoUrl) ?>" alt="Current profile photo" class="avatar avatar-lg">
      <?php else: ?>
        <div class="avatar-placeholder avatar-lg"><?= e($initials) ?></div>
      <?php endif; ?>
      <div>
        <p style="font-size:0.875rem; color:var(--text-secondary); margin-bottom:8px;">
          <?= $photoUrl !== '' ? 'Your current photo' : 'No photo set yet.' ?>
        </p>
        <button type="button" class="btn btn-secondary" style="font-size:13px; padding:8px 16px;"
                onclick="document.getElementById('photoFileInput').click()">
          <?= $photoUrl !== '' ? 'Change photo' : 'Upload photo' ?>
        </button>
      </div>
    </div>

    <!-- Photo crop UI (same pattern as wizard step 5) -->
    <div id="photoCropArea" style="display:none; text-align:center;">
      <div style="position:relative; width:200px; height:200px; margin:0 auto 16px;
                  border-radius:50%; overflow:hidden; background:var(--border); border:2px solid var(--border);">
        <canvas id="editCropCanvas" style="position:absolute; top:0; left:0; cursor:grab; touch-action:none;"></canvas>
      </div>
      <div style="display:flex; align-items:center; gap:10px; max-width:200px; margin:0 auto 16px;">
        <label style="font-size:12px; color:var(--text-muted); white-space:nowrap;">Zoom</label>
        <input type="range" id="editZoom" min="0.5" max="3" step="0.01" value="1"
               style="flex:1; padding:0; border:none; box-shadow:none; height:4px; accent-color:var(--accent-blue);">
      </div>
      <button type="button" class="btn btn-secondary" style="font-size:13px; margin-bottom:12px;"
              onclick="document.getElementById('photoFileInput').click()">Choose different photo</button>
    </div>

    <input type="file" id="photoFileInput" accept="image/jpeg,image/png,image/webp,image/gif" style="display:none;"
           onchange="handleEditPhoto(this)">

    <form method="POST" action="/profile/edit_photo_eval.php" id="editPhotoForm" style="display:none;">
      <?= csrf_input() ?>
      <input type="hidden" name="photo_data" id="editPhotoData">
      <button type="submit" class="btn btn-primary" style="font-size:14px;">Save Photo</button>
    </form>
  </div>

  <!-- Profile fields -->
  <div style="background:var(--surface); border:1px solid var(--border); border-radius:var(--radius); padding:24px;">
    <h2 style="font-size:0.95rem; color:var(--text-secondary); margin-bottom:20px; font-weight:400;">Personal information</h2>

    <form method="POST" action="/profile/edit_eval.php" novalidate>
      <?= csrf_input() ?>

      <div class="form-group">
        <label for="first_name">First name</label>
        <input type="text" id="first_name" name="first_name"
               value="<?= e($user['first_name'] ?? '') ?>"
               placeholder="First name" autocomplete="given-name" required>
      </div>

      <div class="form-group">
        <label for="last_name">Last name</label>
        <input type="text" id="last_name" name="last_name"
               value="<?= e($user['last_name'] ?? '') ?>"
               placeholder="Last name" autocomplete="family-name" required>
      </div>

      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" value="<?= e($user['email'] ?? '') ?>"
               disabled style="background:var(--bg); color:var(--text-muted); cursor:not-allowed;">
        <small style="color:var(--text-muted); font-size:12px;">Email address cannot be changed.</small>
      </div>

      <div class="form-group">
        <label for="phone_local">Phone number</label>
        <div style="display:flex; gap:8px; align-items:stretch;">

          <!-- Compact country-code picker -->
          <div class="cc-picker" id="ccPicker">
            <button type="button" class="cc-trigger" id="ccTrigger" aria-haspopup="listbox" aria-expanded="false">
              <span id="ccFlag"><?= e($selectedFlag) ?></span>
              <span id="ccCode"><?= e($phoneCountryCode) ?></span>
              <span class="cc-arrow">▾</span>
            </button>
            <ul class="cc-dropdown" id="ccDropdown" role="listbox">
              <?php
                $firstMatch = true;
                foreach ($phoneCountries as $c):
                  $isSelected = ($firstMatch && $c['code'] === $phoneCountryCode);
                  if ($isSelected) $firstMatch = false;
              ?>
                <li class="cc-option<?= $isSelected ? ' selected' : '' ?>"
                    role="option"
                    data-code="<?= e($c['code']) ?>"
                    data-flag="<?= e($c['flag']) ?>">
                  <?= e($c['flag'] . ' ' . $c['name'] . ' (' . $c['code'] . ')') ?>
                </li>
              <?php endforeach; ?>
            </ul>
            <input type="hidden" name="country_code" id="countryCodeHidden" value="<?= e($phoneCountryCode) ?>">
          </div>

          <input type="tel" id="phone_local" name="phone_local"
                 value="<?= e($phoneLocal) ?>"
                 placeholder="<?= $phoneCountryCode === '+1' ? '(555) 555-5555' : 'Phone number' ?>"
                 autocomplete="tel-national"
                 inputmode="tel"
                 style="flex:1; min-width:0;">
        </div>
        <small style="color:var(--text-muted); font-size:12px; margin-top:4px; display:block;">Leave blank to remove your phone number.</small>
      </div>

      <button type="submit" class="btn btn-primary">Save Changes</button>
    </form>
  </div>

</div>

<script>
// ─── Photo crop (same logic as step 5, 200px canvas) ─────────────────────
let eImg = null, eScale = 1, eOffX = 0, eOffY = 0, eDrag = false, eLX = 0, eLY = 0;
const EC = 200;
const eCanvas = document.getElementById('editCropCanvas');
const eCtx    = eCanvas.getContext('2d');
const eZoom   = document.getElementById('editZoom');
eCanvas.width = eCanvas.height = EC;

function handleEditPhoto(input) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = (ev) => {
    eImg = new Image();
    eImg.onload = () => {
      eScale = Math.max(EC / eImg.width, EC / eImg.height);
      eOffX = (EC - eImg.width  * eScale) / 2;
      eOffY = (EC - eImg.height * eScale) / 2;
      eZoom.value = eScale;
      eZoom.min   = Math.max(0.1, eScale * 0.5);
      eZoom.max   = eScale * 4;
      document.getElementById('photoCropArea').style.display = 'block';
      document.getElementById('editPhotoForm').style.display = 'block';
      drawEdit();
    };
    eImg.src = ev.target.result;
  };
  reader.readAsDataURL(file);
}

function drawEdit() {
  eCtx.clearRect(0, 0, EC, EC);
  if (!eImg) return;
  eCtx.drawImage(eImg, eOffX, eOffY, eImg.width * eScale, eImg.height * eScale);
}

eZoom.addEventListener('input', () => {
  const ns = parseFloat(eZoom.value);
  eOffX = EC/2 - (EC/2 - eOffX) * (ns / eScale);
  eOffY = EC/2 - (EC/2 - eOffY) * (ns / eScale);
  eScale = ns; clampE(); drawEdit();
});

eCanvas.addEventListener('mousedown',  (e) => { eDrag=true; eLX=e.clientX; eLY=e.clientY; });
eCanvas.addEventListener('mousemove',  (e) => {
  if (!eDrag) return;
  eOffX += e.clientX - eLX; eOffY += e.clientY - eLY;
  eLX = e.clientX; eLY = e.clientY; clampE(); drawEdit();
});
eCanvas.addEventListener('mouseup',    () => eDrag=false);
eCanvas.addEventListener('mouseleave', () => eDrag=false);

function clampE() {
  if (!eImg) return;
  eOffX = Math.min(0, Math.max(eOffX, EC - eImg.width  * eScale));
  eOffY = Math.min(0, Math.max(eOffY, EC - eImg.height * eScale));
}

document.getElementById('editPhotoForm').addEventListener('submit', (ev) => {
  if (!eImg) { ev.preventDefault(); return; }
  document.getElementById('editPhotoData').value = eCanvas.toDataURL('image/jpeg', 0.9);
});

// ─── Compact country-code picker + phone auto-format ─────────────────────
(function () {
  const trigger    = document.getElementById('ccTrigger');
  const dropdown   = document.getElementById('ccDropdown');
  const flagEl     = document.getElementById('ccFlag');
  const codeEl     = document.getElementById('ccCode');
  const hidden     = document.getElementById('countryCodeHidden');
  const phoneInput = document.getElementById('phone_local');
  if (!trigger || !phoneInput) return;

  trigger.addEventListener('click', function (e) {
    e.stopPropagation();
    const isOpen = dropdown.classList.toggle('open');
    this.setAttribute('aria-expanded', isOpen);
  });

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

  document.addEventListener('click', function () {
    dropdown.classList.remove('open');
    trigger.setAttribute('aria-expanded', 'false');
  });

  phoneInput.addEventListener('input', function () {
    const raw = this.value.replace(/\D/g, '');
    this.value = formatPhoneNumber(raw, hidden.value);
  });

  function formatPhoneNumber(digits, code) {
    if (!digits) return '';
    if (code === '+1') {
      if (digits.length <= 3) return '(' + digits;
      if (digits.length <= 6) return '(' + digits.slice(0,3) + ') ' + digits.slice(3);
      return '(' + digits.slice(0,3) + ') ' + digits.slice(3,6) + '-' + digits.slice(6,10);
    }
    return digits.slice(0, 15).replace(/(\d{3})(?=\d)/g, '$1 ').trim();
  }
})();
</script>
<?php
$content = ob_get_clean();
$activeSidebar = 'profile';
include __DIR__ . '/../templates/layout.php';
