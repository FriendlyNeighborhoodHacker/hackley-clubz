<?php
declare(strict_types=1);

/**
 * Carries the identity and permissions of the currently-acting user for a single request.
 *
 * All write operations in management classes accept a UserContext so they can record
 * the actor in the ActivityLog without needing an extra DB query.
 *
 * Usage:
 *   // Seed once per request (done by Application::init()):
 *   UserContext::set(new UserContext($uid, $isAdmin));
 *
 *   // Retrieve anywhere:
 *   $ctx = UserContext::getLoggedInUserContext();
 */
class UserContext {
    public int  $id;
    public bool $admin;

    private static ?UserContext $current = null;

    public function __construct(int $id, bool $admin) {
        $this->id    = $id;
        $this->admin = $admin;
    }

    /**
     * Store the logged-in user context for this request.
     */
    public static function set(UserContext $ctx): void {
        self::$current = $ctx;
    }

    /**
     * Return the logged-in user context for this request, or null if not logged in.
     */
    public static function getLoggedInUserContext(): ?UserContext {
        return self::$current;
    }

    /**
     * Seed from the session without hitting the DB.
     * Called early in Application::init() so that the context is available
     * even before current_user() fetches the full user row.
     */
    public static function bootstrapFromSession(): void {
        if (self::$current !== null) return;
        if (!empty($_SESSION['uid'])) {
            $uid     = (int)$_SESSION['uid'];
            $isAdmin = !empty($_SESSION['is_admin']);
            self::$current = new UserContext($uid, $isAdmin);
        }
    }
}
