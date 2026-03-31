<?php
declare(strict_types=1);

/**
 * Logout — destroys the session and redirects to the login page.
 * Accessible via GET or POST; no CSRF token required since logging out
 * is not a destructive data-modifying action.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/Application.php';
require_once __DIR__ . '/lib/ActivityLog.php';
require_once __DIR__ . '/auth.php';

Application::init();

// Log the logout before destroying the session
$ctx = \UserContext::getLoggedInUserContext();
if ($ctx) {
    ActivityLog::log($ctx, ActivityLog::ACTION_USER_LOGOUT, []);
}

Auth::logoutUser();

Flash::set('info', 'You have been logged out.');
redirect('/login.php');
