<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/Application.php';
require_once __DIR__ . '/../lib/Auth.php';
require_once __DIR__ . '/../lib/Settings.php';

Application::init();
Auth::requireAdmin();

// Current values from DB
$current = Settings::all();

// Settings field definitions for Hackley Clubz
$settingsDef = [
    'site_title' => [
        'label' => 'Site Title',
        'type'  => 'text',
        'hint'  => 'Shown in the browser title and header. Defaults to "Hackley Clubz" if empty.',
    ],
    'announcement' => [
        'label' => 'Announcement',
        'type'  => 'textarea',
        'hint'  => 'Banner message shown on the home page when non-empty.',
    ],
    'timezone' => [
        'label' => 'Time Zone',
        'type'  => 'timezone',
        'hint'  => 'All event times are displayed in this time zone.',
    ],
    'student_email_domains' => [
        'label' => 'Student Email Domains',
        'type'  => 'text',
        'hint'  => 'Comma-separated list of domains whose users register as students. E.g. students.hackleyschool.org',
    ],
    'adult_email_domains' => [
        'label' => 'Adult / Faculty Email Domains',
        'type'  => 'text',
        'hint'  => 'Comma-separated list of domains whose users register as adults / faculty. E.g. hackleyschool.org',
    ],
];

$pageTitle     = 'App Settings';
$activeSidebar = 'admin';

ob_start();
?>
<div class="admin-page">

  <div class="admin-subnav">
    <a href="/admin/settings.php" class="active">Settings</a>
    <a href="/admin/activity_log.php">Activity Log</a>
    <a href="/admin/email_log.php">Email Log</a>
  </div>

  <h2>App Settings</h2>

  <div class="card" style="max-width:640px;padding:32px;">
    <form method="post" action="/admin/settings_eval.php" novalidate>
      <?= csrf_input() ?>

      <?php foreach ($settingsDef as $key => $meta): ?>
        <?php $val = (string)($current[$key] ?? ''); ?>
        <div class="form-group">
          <label for="s-<?= e($key) ?>"><?= e($meta['label']) ?></label>

          <?php if ($meta['type'] === 'textarea'): ?>
            <textarea id="s-<?= e($key) ?>" name="s[<?= e($key) ?>]"
                      rows="4"><?= e($val) ?></textarea>

          <?php elseif ($meta['type'] === 'timezone'): ?>
            <select id="s-<?= e($key) ?>" name="s[<?= e($key) ?>]">
              <?php foreach (DateTimeZone::listIdentifiers() as $tz): ?>
                <option value="<?= e($tz) ?>"<?= $val === $tz ? ' selected' : '' ?>><?= e($tz) ?></option>
              <?php endforeach; ?>
            </select>

          <?php else: ?>
            <input type="text" id="s-<?= e($key) ?>" name="s[<?= e($key) ?>]"
                   value="<?= e($val) ?>">
          <?php endif; ?>

          <?php if (!empty($meta['hint'])): ?>
            <small class="text-muted" style="display:block;margin-top:4px;"><?= e($meta['hint']) ?></small>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>

      <div style="display:flex;gap:12px;margin-top:8px;">
        <button type="submit" class="btn btn-primary">Save Settings</button>
        <a href="/index.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
  </div>

</div><!-- .admin-page -->
<?php
$content = ob_get_clean();
include __DIR__ . '/../templates/layout.php';
