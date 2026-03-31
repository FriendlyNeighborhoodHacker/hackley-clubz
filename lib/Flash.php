<?php
declare(strict_types=1);

/**
 * Flash message helpers.
 *
 * Flash messages are stored in the session for exactly one request — they
 * survive a redirect and are consumed (cleared) when read or rendered.
 *
 * Typical types: 'success', 'error', 'info'.
 *
 * Usage:
 *   Flash::set('error', 'Something went wrong.');
 *   Flash::get('error');    // returns the message and clears it
 *   Flash::render();        // returns HTML for all pending messages and clears them
 */
final class Flash {

    /**
     * Store a flash message in the session.
     */
    public static function set(string $type, string $message): void {
        $_SESSION['flash'][$type] = $message;
    }

    /**
     * Retrieve and clear a flash message of the given type.
     * Returns null if no message of that type is pending.
     */
    public static function get(string $type): ?string {
        $msg = $_SESSION['flash'][$type] ?? null;
        unset($_SESSION['flash'][$type]);
        return $msg;
    }

    /**
     * Render all pending flash messages as HTML and clear them.
     * Returns an empty string if there are no pending messages.
     *
     * Outputs divs with classes:  flash flash--success / flash--error / flash--info
     */
    public static function render(): string {
        $types = ['success', 'error', 'info'];
        $html  = '';
        foreach ($types as $type) {
            $msg = self::get($type);
            if ($msg !== null) {
                $html .= '<div class="flash flash--' . $type . '">'
                       . htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                       . '</div>';
            }
        }
        return $html;
    }
}
