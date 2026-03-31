<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/Settings.php';

/**
 * Bootstrap helper called once per request at the top of each page.
 *
 * Responsibilities:
 *   1. Ensure the session is active.
 *   2. Seed UserContext from the session (fast, no DB query needed).
 *   3. Apply the timezone from Settings (one DB query if not yet cached).
 */
class Application {

    public static function init(): void {
        // Session is already started by config.php, but be defensive.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }


        // Seed UserContext from session data so the rest of the request has
        // access to the current user's id/admin flag without hitting the DB.
        UserContext::bootstrapFromSession();

        // Apply timezone from settings (falls back to APP_TIMEZONE constant).
        try {
            $tz = Settings::get('timezone', '');
            if ($tz && $tz !== '') {
                date_default_timezone_set($tz);
            }
        } catch (\Throwable $e) {
            // Settings table may not exist yet on fresh installs — ignore.
        }
    }
}
