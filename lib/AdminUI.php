<?php
declare(strict_types=1);

/**
 * AdminUI
 *
 * Shared UI helpers for admin pages.
 * Centralises recurring UI elements so that adding or renaming a section
 * only requires a change in one place.
 */
class AdminUI
{
    /**
     * Render the "Admin › Section" breadcrumb context line shown at the top
     * of every admin page in place of the former tab bar.
     *
     * @param string $label  Human-readable section name, e.g. 'Users'.
     * @return string        Ready-to-echo HTML.
     */
    public static function adminBreadcrumb(string $label): string
    {
        $h = fn(string $s): string =>
            htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<p style="font-size:0.85rem; color:var(--text-secondary); margin-bottom:20px;">'
             . '<button type="button" onclick="document.getElementById(\'admin-panel-btn\')?.click()"'
             . ' style="background:none; border:none; padding:0; cursor:pointer;'
             . ' color:var(--text-secondary); font-size:inherit; font-family:inherit;">Admin</button>'
             . ' &rsaquo; '
             . $h($label)
             . '</p>' . "\n";
    }
}
