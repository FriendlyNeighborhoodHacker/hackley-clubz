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
     * Render the top admin sub-navigation bar.
     *
     * @param string $active  Key of the currently active tab.
     *                        Valid values: 'settings', 'activity_log', 'email_log', 'clubs', 'users'
     * @return string         Ready-to-echo HTML.
     */
    public static function adminSubnav(string $active): string
    {
        $links = [
            'settings'     => ['label' => 'Settings',      'href' => '/admin/settings.php'],
            'activity_log' => ['label' => 'Activity Log',  'href' => '/admin/activity_log.php'],
            'email_log'    => ['label' => 'Email Log',     'href' => '/admin/email_log.php'],
            'clubs'        => ['label' => 'Clubs',         'href' => '/admin/clubs/index.php'],
            'users'        => ['label' => 'Users',         'href' => '/admin/users/index.php'],
        ];

        $html = '<div class="admin-subnav">' . "\n";
        foreach ($links as $key => $link) {
            $isActive  = ($key === $active);
            $class     = $isActive ? ' class="active"' : '';
            $html     .= '  <a href="' . htmlspecialchars($link['href'], ENT_QUOTES, 'UTF-8') . '"' . $class . '>'
                       . htmlspecialchars($link['label'], ENT_QUOTES, 'UTF-8')
                       . '</a>' . "\n";
        }
        $html .= '</div>' . "\n";

        return $html;
    }
}
