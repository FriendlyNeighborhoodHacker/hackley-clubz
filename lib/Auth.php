<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/UserManagement.php';

/**
 * Authentication helpers.
 *
 * All methods are static. Typical usage:
 *
 *   Auth::requireLogin();               // redirect to login if not authenticated
 *   $user = Auth::currentUser();        // full DB row or null
 *   Auth::loginUser($userRow);          // populate session after successful login
 *   Auth::logoutUser();                 // destroy session
 *   $url = Auth::postLoginRedirectUrl();// safe redirect target after login
 */
final class Auth {

    // -------------------------------------------------------------------------
    // Current user
    // -------------------------------------------------------------------------

    /**
     * Return the full DB row for the currently logged-in user, or null.
     *
     * The result is memoised for the lifetime of the request so the DB is
     * only queried once.
     */
    public static function currentUser(): ?array {
        static $user = false; // false = not yet fetched; null = not logged in
        if ($user !== false) return $user;

        if (empty($_SESSION['uid'])) {
            $user = null;
            return null;
        }

        $user = UserManagement::findUserById((int)$_SESSION['uid']);
        return $user ?: null;
    }

    // -------------------------------------------------------------------------
    // Require login
    // -------------------------------------------------------------------------

    /**
     * Redirect unauthenticated visitors to the login page.
     *
     * If $redirectBack is true (the default), the current request URL is
     * appended as a `?redirect=` query parameter so that after a successful
     * login the user is returned to the page they originally requested.
     *
     * Call this near the top of any page that requires authentication.
     */
    public static function requireLogin(bool $redirectBack = true): void {
        if (!empty($_SESSION['uid'])) return;

        $loginUrl = '/login.php';
        if ($redirectBack) {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            if ($requestUri !== '' && $requestUri !== '/login.php') {
                $loginUrl .= '?redirect=' . urlencode($requestUri);
            }
        }
        redirect($loginUrl);
    }

    // -------------------------------------------------------------------------
    // Session management
    // -------------------------------------------------------------------------

    /**
     * Populate the session after a successful login.
     *
     * Call this immediately after UserManagement::attemptLogin() returns a
     * non-null user row.
     */
    public static function loginUser(array $user): void {
        // Rotate session ID to prevent session-fixation attacks
        session_regenerate_id(true);

        $_SESSION['uid']      = (int)$user['id'];
        $_SESSION['is_admin'] = !empty($user['is_admin']) ? 1 : 0;

        // Seed the request-scoped UserContext so it is available immediately
        UserContext::set(new UserContext((int)$user['id'], (bool)$user['is_admin']));
    }

    /**
     * Destroy the session and clear the cookie (logout).
     */
    public static function logoutUser(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(), '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    // -------------------------------------------------------------------------
    // Admin gate
    // -------------------------------------------------------------------------

    /**
     * Redirect non-admin users away with a flash error.
     *
     * Calls requireLogin() first, so unauthenticated visitors are sent to the
     * login page rather than seeing a generic "no permission" message.
     */
    public static function requireAdmin(): void {
        self::requireLogin();
        $user = self::currentUser();
        if (!$user || empty($user['is_admin'])) {
            Flash::set('error', 'You do not have permission to access that page.');
            redirect('/index.php');
        }
    }

    // -------------------------------------------------------------------------
    // Deep-link redirect helper
    // -------------------------------------------------------------------------

    /**
     * Return the URL to redirect to after a successful login.
     *
     * Reads the `redirect` parameter from GET or POST but validates it to
     * prevent open-redirect attacks: only relative paths on the same host
     * are permitted.
     */
    public static function postLoginRedirectUrl(): string {
        $redirect = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
        if ($redirect === '') return '/index.php';

        // Allow only relative paths (no scheme, no host, no newlines)
        if (
            str_starts_with($redirect, '/')
            && !str_starts_with($redirect, '//')
            && !preg_match('/[\r\n]/', $redirect)
        ) {
            return $redirect;
        }

        return '/index.php';
    }
}
