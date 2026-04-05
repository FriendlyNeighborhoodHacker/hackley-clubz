<?php
declare(strict_types=1);

require_once __DIR__ . '/ApplicationUI.php';

/**
 * ClubUI
 *
 * Shared UI helpers for club pages.
 */
class ClubUI
{
    // ─────────────────────────────────────────────────────────────────────────
    // Menu helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build the standard club navigation menu items array.
     *
     * Returns items for 🏠 Info Page (always), 👑 Members and ⚙️ Settings
     * (only when $canManage is true), with the active flag set on $activePage.
     *
     * @param int    $clubId     Club ID.
     * @param string $activePage 'info' | 'events' | 'members' | 'settings' | ''
     * @param bool   $canManage  Whether the viewer can manage this club.
     * @return array             Array of ['label','href','active'] maps.
     */
    public static function buildClubMenuItems(
        int    $clubId,
        string $activePage = '',
        bool   $canManage  = true,
        bool   $isMember   = false,
        string $clubName   = ''
    ): array {
        // Trusted, code-generated SVG icon HTML — not from user input.
        $icons = [
            'info'     => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#038BFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
            'events'   => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#FF6B47" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
            'members'  => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#7C3AED" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
            'settings' => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6B7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>',
        ];

        $all = [
            'info'     => ['label' => 'Club Info', 'icon_html' => $icons['info'],     'href' => '/clubs/view.php?id='          . $clubId],
            'members'  => ['label' => 'Members',   'icon_html' => $icons['members'],  'href' => '/clubs/members.php?id='       . $clubId],
            'events'   => ['label' => 'Events',    'icon_html' => $icons['events'],   'href' => '/clubs/events/index.php?id='  . $clubId],
            'settings' => ['label' => 'Settings',  'icon_html' => $icons['settings'], 'href' => '/clubs/settings.php?id='      . $clubId],
        ];

        $items = [];
        foreach ($all as $key => $item) {
            // Settings: club/app admins only
            if ($key === 'settings' && !$canManage) {
                continue;
            }
            // Members/Events: any club member (or admin)
            if (in_array($key, ['members', 'events'], true) && !$isMember && !$canManage) {
                continue;
            }
            $items[] = [
                'label'     => $item['label'],
                'icon_html' => $item['icon_html'] ?? '',
                'href'      => $item['href'],
                'active'    => ($key === $activePage),
            ];
        }

        if ($isMember) {
            $items[] = [
                'label'            => 'Leave Club',
                'icon_html'        => '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>',
                'href'             => '#',
                'active'           => false,
                'separator_before' => true,
                'onclick'          => "if(confirm('Leave " . addslashes($clubName) . "?')) document.getElementById('club-leave-form').submit(); return false;",
            ];
        }

        return $items;
    }

    /**
     * Render the hidden POST form submitted by the Leave Club menu button.
     *
     * Place this in the page (outside visible structure) whenever
     * buildClubMenuItems() is called with $isMember = true, so the
     * ⋯ menu's Leave Club button has a form to submit.
     *
     * @param int    $clubId   Club ID.
     * @param string $returnTo URL to redirect to after leaving.
     * @return string          Ready-to-echo HTML.
     */
    public static function leaveClubForm(int $clubId, string $returnTo = '/clubs/browse.php'): string
    {
        $h = static fn(string $s): string =>
            htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<form id="club-leave-form" method="POST" action="/clubs/leave_eval.php" style="display:none;">'
             . csrf_input()
             . '<input type="hidden" name="club_id" value="' . (int)$clubId . '">'
             . '<input type="hidden" name="return_to" value="' . $h($returnTo) . '">'
             . '</form>';
    }

    /**
     * Format a "Meets" subtext line for the title block from a club row.
     *
     * Returns a human-readable string describing when (and optionally where)
     * the club meets, or '' if no meeting days are set.
     *
     * Examples:
     *   "Day 4"                        (1 day, no location)
     *   "Day 4, Shep's Room"           (1 day, with location)
     *   "Days 4 and 7, Room 104"       (2 days, with location)
     *   "Days 1, 3, and 4, Room 104"   (3+ days, Oxford comma, with location)
     *
     * @param array $club  Club row containing 'meeting_days' and 'meeting_location'.
     * @return string      Formatted string, or '' if no days are set.
     */
    public static function formatMeetingSubtext(array $club): string
    {
        $rawDays = trim((string)($club['meeting_days'] ?? ''));
        if ($rawDays === '') {
            return '';
        }

        $days = array_values(array_unique(
            array_filter(array_map('intval', explode(',', $rawDays)))
        ));

        if (empty($days)) {
            return '';
        }

        sort($days);
        $nums  = array_map('strval', $days);
        $count = count($nums);

        if ($count === 1) {
            $daysStr = 'Day ' . $nums[0];
        } elseif ($count === 2) {
            $daysStr = 'Days ' . $nums[0] . ' and ' . $nums[1];
        } else {
            $last    = array_pop($nums);
            $daysStr = 'Days ' . implode(', ', $nums) . ', and ' . $last;
        }

        $loc = trim((string)($club['meeting_location'] ?? ''));
        return $loc !== '' ? $daysStr . ', ' . $loc : $daysStr;
    }

    /**
     * Render a club description string as safe HTML using Markdown.
     *
     * Supports the full useful subset of Markdown: bold, italic, links,
     * images, headings, lists, blockquotes, code (inline + fenced), tables,
     * and horizontal rules.  Raw HTML typed by the author is escaped (safe
     * mode) to prevent XSS.
     *
     * @param string $text  Raw description text (may contain Markdown).
     * @return string       HTML wrapped in <div class="club-description">,
     *                      or '' when $text is blank.
     */
    public static function renderDescription(string $text): string
    {
        if (trim($text) === '') {
            return '';
        }

        require_once __DIR__ . '/Parsedown.php';

        $pd = new Parsedown();
        $pd->setSafeMode(true);     // escape raw HTML → prevents XSS
        $pd->setBreaksEnabled(true); // single newlines → <br>

        return '<div class="club-description">' . $pd->text($text) . '</div>';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Legacy / previously-here: titleBlock() — moved to ApplicationUI::titleBlock()
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @deprecated Use ApplicationUI::titleBlock() directly.
     * Kept temporarily so any callers outside these four pages still work.
     * Remove once all callers are updated.
     *
     * Render the standard club page title block.
     *
     * Outputs (in order):
     *   1. Optional 3:1 hero banner ($heroUrl).
     *   2. A flex row containing:
     *        • Club profile photo (or an initial-avatar placeholder).
     *        • Title heading with optional "› Subtitle" and subtext lines.
     *        • Any extra action HTML in the flex row ($extraActionHtml, e.g. a Join button).
     *        • ⋯ dropdown menu (only when $menuItems is non-empty).
     *   3. Inline JS for the menu toggle (guarded by a typeof check).
     *
     * @param string $title            Primary heading text (e.g. club name).
     * @param string $subtitle         Section label after "›" (e.g. "Settings"). '' = omit.
     * @param string $photoUrl         Circle photo URL; '' shows the initial-avatar fallback.
     * @param string $photoInitial     Single character shown when $photoUrl is ''.
     * @param array  $subtextLines     Lines rendered below the heading (e.g. ['5 members']).
     * @param array  $menuItems        Items for the ⋯ dropdown menu.
     *                                 Each entry: ['label' => '…', 'href' => '…', 'active' => false]
     * @param string $heroUrl          Optional 3:1 hero banner image URL.
     * @param string $extraMenuHtml    Raw HTML appended inside the ⋯ dropdown
     *                                 (e.g. a separator + Leave Club button).
     * @param string $extraActionHtml  Raw HTML placed in the flex row before the ⋯ button
     *                                 (e.g. a Join Club button for non-members).
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
        string $extraActionHtml = ''
    ): string {
        return ApplicationUI::titleBlock(
            $title, $subtitle, $photoUrl, $photoInitial,
            $subtextLines, $menuItems, $heroUrl, $extraMenuHtml, $extraActionHtml
        );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Standalone menu widget
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Render just the ⋯ button + dropdown widget (no surrounding title block).
     *
     * Useful when only the navigation widget is needed in isolation.
     * For new code, prefer composing titleBlock() + buildClubMenuItems().
     *
     * @param int    $clubId     Club ID.
     * @param string $activePage 'info' | 'members' | 'settings' | ''
     * @param bool   $canManage  Whether the current user can manage this club.
     * @param string $extraHtml  Optional raw HTML appended inside the dropdown.
     * @return string            Ready-to-echo HTML.
     */
    public static function renderClubMenu(
        int    $clubId,
        string $activePage = '',
        bool   $canManage  = true,
        string $extraHtml  = ''
    ): string {
        $h     = static fn(string $s): string =>
            htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $items = self::buildClubMenuItems($clubId, $activePage, $canManage);

        ob_start();
        ?>
<div style="flex-shrink:0; position:relative;" id="club-admin-menu-wrap">
  <button type="button" id="club-admin-menu-btn"
          style="background:none; border:1.5px solid var(--border); border-radius:var(--radius-sm);
                 padding:7px 13px; cursor:pointer; font-size:18px; color:var(--text-secondary);
                 line-height:1; transition:background .15s, color .15s;"
          onmouseenter="this.style.background='var(--border-light)';this.style.color='var(--purple-dark)'"
          onmouseleave="this.style.background='none';this.style.color='var(--text-secondary)'"
          onclick="toggleClubAdminMenu(event)"
          title="Club menu"
          aria-label="Club options">⋯</button>
  <div id="club-admin-menu"
       style="display:none; position:absolute; right:0; top:100%; margin-top:4px;
              background:var(--surface); border:1px solid var(--border);
              border-radius:var(--radius-sm); box-shadow:var(--shadow-md);
              min-width:180px; z-index:50; overflow:hidden;">
    <?php foreach ($items as $item): ?>
      <a href="<?= $h((string)($item['href'] ?? '')) ?>"
         class="admin-panel-link<?= !empty($item['active']) ? ' active' : '' ?>"
         style="display:flex; align-items:center; gap:7px;">
        <?php if (!empty($item['icon_html'])): ?><span style="display:inline-flex;flex-shrink:0;"><?= $item['icon_html'] ?></span><?php endif; ?>
        <?= $h((string)($item['label'] ?? '')) ?>
      </a>
    <?php endforeach; ?>
    <?= $extraHtml ?>
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
<?php
        return ob_get_clean();
    }
}
