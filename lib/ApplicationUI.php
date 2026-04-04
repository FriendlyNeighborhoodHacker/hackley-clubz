<?php
declare(strict_types=1);

/**
 * ApplicationUI
 *
 * Generic, reusable UI helpers that are not tied to any specific
 * domain (clubs, admin, etc.).
 */
class ApplicationUI
{
    /**
     * Render a standard entity title block.
     *
     * Outputs (in order):
     *   1. Optional 3:1 hero banner ($heroUrl).
     *   2. A flex row containing:
     *        • Circle photo (or initial-avatar placeholder).
     *        • Title heading with optional "› Subtitle" and subtext lines.
     *        • Any extra action HTML placed in the flex row ($extraActionHtml).
     *        • ⋯ dropdown menu (only when $menuItems is non-empty or
     *          $extraMenuHtml is non-empty).
     *   3. Inline JS for the menu toggle (guarded by a typeof check so it is
     *      safe even when called more than once per page).
     *
     * Menu item format (each element of $menuItems):
     *   [
     *     'label'          => '🏠 Info Page',   // displayed text (HTML-escaped)
     *     'href'           => '/clubs/...',      // link target (omit or '' for buttons)
     *     'active'         => false,             // highlight as current page
     *     'separator_before' => false,           // draw a thin divider above this item
     *     'onclick'        => 'js code...',      // if present, renders as <button>
     *                                            // rather than <a>; value is used
     *                                            // verbatim as the onclick attribute
     *                                            // (HTML-escaped with ENT_COMPAT).
     *   ]
     *
     * @param string $title            Primary heading (e.g. entity name).
     * @param string $subtitle         Section label appended after "›". '' = omit.
     * @param string $photoUrl         Circle photo URL; '' shows the initial avatar.
     * @param string $photoInitial     Single character shown when $photoUrl is ''.
     * @param array  $subtextLines     Lines rendered below the heading.
     * @param array  $menuItems        Items for the ⋯ dropdown (see format above).
     * @param string $heroUrl          Optional 3:1 hero banner image URL.
     * @param string $extraMenuHtml    Raw HTML appended inside the ⋯ dropdown.
     * @param string $extraActionHtml  Raw HTML placed in the flex row before ⋯.
     * @param array  $breadcrumbs      Optional breadcrumb trail rendered between the
     *                                 hero image and the title row.  Each entry is:
     *                                 ['label' => 'Back to clubs', 'href' => '/clubs/browse.php']
     *                                 The first item gets a ← prefix; subsequent items
     *                                 are separated by ›.  Defaults to [] (none shown).
     * @return string                  Ready-to-echo HTML.
     */
    public static function titleBlock(
        string $title,
        string $subtitle,
        string $photoUrl,
        string $photoInitial,
        array  $subtextLines,
        array  $menuItems,
        string $heroUrl         = '',
        string $extraMenuHtml   = '',
        string $extraActionHtml = '',
        array  $breadcrumbs     = []
    ): string {
        $h = static fn(string $s): string =>
            htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $showMenu = !empty($menuItems) || $extraMenuHtml !== '';

        ob_start();
        ?>
  <?php if (!empty($breadcrumbs)): ?>
  <div style="font-size:14px; color:var(--text-secondary); margin-bottom:12px;">
    <?php foreach ($breadcrumbs as $i => $crumb): ?>
      <?php if ($i > 0): ?>
        <span style="margin:0 4px; color:var(--border);">›</span>
      <?php endif; ?>
      <a href="<?= $h((string)($crumb['href'] ?? '')) ?>"
         style="color:var(--text-secondary); text-decoration:none;">
        <?= $i === 0 ? '← ' : '' ?><?= $h((string)($crumb['label'] ?? '')) ?>
      </a>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <?php if ($heroUrl !== ''): ?>
  <div style="border-radius:var(--radius); overflow:hidden; aspect-ratio:3/1;
              background:var(--border); margin-bottom:0;">
    <img src="<?= $h($heroUrl) ?>" alt="<?= $h($title) ?>"
         style="width:100%; height:100%; object-fit:cover; display:block;">
  </div>
  <?php endif; ?>

  <div style="display:flex; align-items:flex-start; gap:16px; margin:16px 0 24px; flex-wrap:wrap;">

    <?php if ($photoUrl !== ''): ?>
      <img src="<?= $h($photoUrl) ?>" class="avatar"
           style="width:72px; height:72px; flex-shrink:0;" alt="">
    <?php else: ?>
      <div class="avatar-placeholder"
           style="width:72px; height:72px; font-size:28px; flex-shrink:0;
                  background:var(--gradient-brand);">
        <?= $h(strtoupper(substr($photoInitial, 0, 1))) ?>
      </div>
    <?php endif; ?>

    <div style="flex:1; min-width:0;">
      <h1 style="font-family:var(--font-title); font-weight:200; font-size:1.8rem;
                 margin:0 0 4px; line-height:1.2;">
        <?= $h($title) ?><?php if ($subtitle !== ''): ?><span style="margin:0 6px;">›</span><?= $h($subtitle) ?><?php endif; ?>
      </h1>
      <?php foreach ($subtextLines as $line): ?>
        <div style="font-size:0.85rem; color:var(--text-muted);"><?= $h((string)$line) ?></div>
      <?php endforeach; ?>
    </div>

    <?php if ($extraActionHtml !== ''): ?>
      <div style="flex-shrink:0;"><?= $extraActionHtml ?></div>
    <?php endif; ?>

    <?php if ($showMenu): ?>
    <div style="flex-shrink:0; position:relative;" id="club-admin-menu-wrap">
      <button type="button" id="club-admin-menu-btn"
              style="background:none; border:1.5px solid var(--border); border-radius:var(--radius-sm);
                     padding:7px 13px; cursor:pointer; font-size:18px; color:var(--text-secondary);
                     line-height:1; transition:background .15s, color .15s;"
              onmouseenter="this.style.background='var(--border-light)';this.style.color='var(--purple-dark)'"
              onmouseleave="this.style.background='none';this.style.color='var(--text-secondary)'"
              onclick="toggleClubAdminMenu(event)"
              title="Menu"
              aria-label="Options">⋯</button>
      <div id="club-admin-menu"
           style="display:none; position:absolute; right:0; top:100%; margin-top:4px;
                  background:var(--surface); border:1px solid var(--border);
                  border-radius:var(--radius-sm); box-shadow:var(--shadow-md);
                  min-width:180px; z-index:50; overflow:hidden;">
        <?php foreach ($menuItems as $item): ?>
          <?php if (!empty($item['separator_before'])): ?>
            <div style="border-top:1px solid var(--border-light);"></div>
          <?php endif; ?>
          <?php if (isset($item['onclick'])): ?>
            <button type="button"
                    class="admin-panel-link<?= !empty($item['active']) ? ' active' : '' ?>"
                    style="width:100%; text-align:left; background:none; border:none; cursor:pointer;"
                    onclick="<?= htmlspecialchars((string)$item['onclick'], ENT_COMPAT, 'UTF-8') ?>">
              <?= $h((string)($item['label'] ?? '')) ?>
            </button>
          <?php else: ?>
            <a href="<?= $h((string)($item['href'] ?? '')) ?>"
               class="admin-panel-link<?= !empty($item['active']) ? ' active' : '' ?>">
              <?= $h((string)($item['label'] ?? '')) ?>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
        <?= $extraMenuHtml ?>
      </div>
    </div>
    <script>
    if (typeof toggleClubAdminMenu === 'undefined') {
      function toggleClubAdminMenu(e) {
        e.stopPropagation();
        const m = document.getElementById('club-admin-menu');
        m.style.display = m.style.display === 'block' ? 'none' : 'block';
      }
      document.addEventListener('click', function() {
        const m = document.getElementById('club-admin-menu');
        if (m) m.style.display = 'none';
      });
    }
    </script>
    <?php endif; ?>

  </div>
<?php
        return ob_get_clean();
    }
}
