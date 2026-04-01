<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';
require_once __DIR__ . '/Settings.php';

/**
 * All user-related database operations.
 *
 * Rules:
 *  - Every method that writes to the DB accepts a UserContext for ActivityLog.
 *  - SQL lives only in this class — never in .php page files.
 *  - Errors are thrown as exceptions; callers decide how to handle/display them.
 */
final class UserManagement {

    // -------------------------------------------------------------------------
    // Password constraints
    // -------------------------------------------------------------------------

    /** Minimum password length. */
    const MIN_PASSWORD_LENGTH = 4;

    /** Password reset token TTL (in seconds). */
    const RESET_TOKEN_TTL = 3600; // 1 hour

    // -------------------------------------------------------------------------
    // Registration
    // -------------------------------------------------------------------------

    /**
     * Validate an email address for registration.
     * Returns true if the domain is allowed, false otherwise.
     */
    public static function isEmailDomainAllowed(string $email): bool {
        $email = strtolower(trim($email));
        $at    = strrpos($email, '@');
        if ($at === false) return false;
        $domain  = substr($email, $at + 1);
        $allowed = Settings::getAllowedEmailDomains();
        return in_array($domain, $allowed, true);
    }

    /**
     * Determine user_type from email domain.
     * Checks the domain against the configured student and adult domain lists.
     * Defaults to 'adult' if the domain matches neither list (shouldn't
     * normally happen since isEmailDomainAllowed() is called first).
     */
    private static function userTypeFromEmail(string $email): string {
        $email  = strtolower(trim($email));
        $at     = strrpos($email, '@');
        $domain = $at !== false ? substr($email, $at + 1) : '';

        if (in_array($domain, Settings::getStudentEmailDomains(), true)) {
            return 'student';
        }
        return 'adult';
    }

    /**
     * Create a new (unverified) user record and return the new user ID.
     *
     * Generates an email verification token, but does NOT send the email —
     * the caller is responsible for sending the verification email.
     *
     * If an unverified user already exists with this email, the existing record
     * is reset (new token, new password hash) so the user can retry.
     *
     * @throws \RuntimeException if the email domain is not allowed, or if a
     *                           verified account already exists for this email.
     */
    public static function createPendingUser(string $email, string $passwordHash): int {
        $email = strtolower(trim($email));

        if (!self::isEmailDomainAllowed($email)) {
            throw new \RuntimeException('This email address is not from an allowed domain.');
        }

        // Check for an existing account
        $existing = self::findUserByEmail($email);
        if ($existing) {
            if ($existing['email_verified_at'] !== null) {
                throw new \RuntimeException('An account with this email address already exists.');
            }
            // Unverified account — reset it so they can retry
            $token = bin2hex(random_bytes(32));
            $st = pdo()->prepare(
                "UPDATE users
                 SET password_hash = :pw, email_verify_token = :token, created_at = NOW()
                 WHERE id = :id"
            );
            $st->bindValue(':pw',    $passwordHash, \PDO::PARAM_STR);
            $st->bindValue(':token', $token,        \PDO::PARAM_STR);
            $st->bindValue(':id',    (int)$existing['id'], \PDO::PARAM_INT);
            $st->execute();

            ActivityLog::log(null, ActivityLog::ACTION_USER_REGISTER, ['email' => $email, 'retry' => true]);
            return (int)$existing['id'];
        }

        $token    = bin2hex(random_bytes(32));
        $userType = self::userTypeFromEmail($email);

        $st = pdo()->prepare(
            "INSERT INTO users (email, password_hash, user_type, email_verify_token, created_at)
             VALUES (:email, :pw, :user_type, :token, NOW())"
        );
        $st->bindValue(':email',     $email,        \PDO::PARAM_STR);
        $st->bindValue(':pw',        $passwordHash, \PDO::PARAM_STR);
        $st->bindValue(':user_type', $userType,     \PDO::PARAM_STR);
        $st->bindValue(':token',     $token,        \PDO::PARAM_STR);
        $st->execute();

        $userId = (int)pdo()->lastInsertId();
        ActivityLog::log(null, ActivityLog::ACTION_USER_REGISTER, ['email' => $email, 'user_id' => $userId]);
        return $userId;
    }

    /**
     * Return the email_verify_token for a given user ID, or null if none.
     * Used when constructing the verification email link.
     */
    public static function getEmailVerifyToken(int $userId): ?string {
        $st = pdo()->prepare('SELECT email_verify_token FROM users WHERE id = :id LIMIT 1');
        $st->bindValue(':id', $userId, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return $row ? ($row['email_verify_token'] ?? null) : null;
    }

    /**
     * Verify an email using the token sent to the user.
     * Marks email_verified_at, clears the token, and returns the user row.
     *
     * @throws \RuntimeException if the token is invalid or already used.
     */
    public static function verifyEmailToken(string $token): array {
        $token = trim($token);
        if ($token === '') throw new \RuntimeException('Invalid verification link.');

        $st = pdo()->prepare(
            'SELECT * FROM users WHERE email_verify_token = :token LIMIT 1'
        );
        $st->bindValue(':token', $token, \PDO::PARAM_STR);
        $st->execute();
        $user = $st->fetch();

        if (!$user) {
            throw new \RuntimeException('This verification link is invalid or has already been used.');
        }

        // Mark verified
        $st2 = pdo()->prepare(
            'UPDATE users SET email_verified_at = NOW(), email_verify_token = NULL WHERE id = :id'
        );
        $st2->bindValue(':id', (int)$user['id'], \PDO::PARAM_INT);
        $st2->execute();

        $ctx = new UserContext((int)$user['id'], (bool)$user['is_admin']);
        ActivityLog::log($ctx, ActivityLog::ACTION_USER_VERIFY_EMAIL, ['email' => $user['email']]);

        // Return fresh row
        return self::findUserById((int)$user['id']) ?? $user;
    }

    // -------------------------------------------------------------------------
    // Profile completion (wizard steps after email verification)
    // -------------------------------------------------------------------------

    /**
     * Save the user's first and last name after email verification.
     *
     * @throws \RuntimeException on empty names
     */
    public static function completeName(UserContext $ctx, string $firstName, string $lastName): void {
        $firstName = trim($firstName);
        $lastName  = trim($lastName);

        if ($firstName === '') throw new \RuntimeException('First name is required.');
        if ($lastName  === '') throw new \RuntimeException('Last name is required.');

        $st = pdo()->prepare(
            'UPDATE users SET first_name = :fn, last_name = :ln WHERE id = :id'
        );
        $st->bindValue(':fn',  $firstName, \PDO::PARAM_STR);
        $st->bindValue(':ln',  $lastName,  \PDO::PARAM_STR);
        $st->bindValue(':id',  $ctx->id,   \PDO::PARAM_INT);
        $st->execute();

        ActivityLog::log($ctx, ActivityLog::ACTION_USER_UPDATE_PROFILE, ['updated' => ['first_name', 'last_name']]);
    }

    /**
     * Set (or replace) a user's profile photo.
     *
     * An admin may update any user's photo by supplying $targetUserId.
     * A non-admin may only update their own photo ($targetUserId must be null or equal to $ctx->id).
     *
     * @throws \RuntimeException on invalid file ID or authorisation failure
     */
    public static function setProfilePhoto(UserContext $ctx, int $publicFileId, ?int $targetUserId = null): void {
        if ($publicFileId <= 0) throw new \RuntimeException('Invalid photo file ID.');

        $targetUserId = $targetUserId ?? $ctx->id;

        if ($targetUserId !== $ctx->id && !$ctx->admin) {
            throw new \RuntimeException('You are not authorised to update this user\'s photo.');
        }

        $st = pdo()->prepare('UPDATE users SET photo_public_file_id = :fid WHERE id = :id');
        $st->bindValue(':fid', $publicFileId,  \PDO::PARAM_INT);
        $st->bindValue(':id',  $targetUserId,  \PDO::PARAM_INT);
        $st->execute();

        ActivityLog::log($ctx, ActivityLog::ACTION_USER_UPDATE_PHOTO, [
            'photo_file_id'  => $publicFileId,
            'target_user_id' => $targetUserId,
        ]);
    }

    // -------------------------------------------------------------------------
    // Profile editing
    // -------------------------------------------------------------------------

    /**
     * Update editable profile fields for the given user.
     *
     * $fields may contain any subset of: first_name, last_name, phone.
     * An admin may also update any user's profile by passing the target user_id.
     *
     * @throws \RuntimeException on validation failure or authorisation error
     */
    public static function updateProfile(UserContext $ctx, array $fields, ?int $targetUserId = null): void {
        $targetUserId = $targetUserId ?? $ctx->id;

        if ($targetUserId !== $ctx->id && !$ctx->admin) {
            throw new \RuntimeException('You are not authorised to edit this profile.');
        }

        $allowed = ['first_name', 'last_name', 'phone'];
        $sets    = [];
        $params  = [];

        foreach ($allowed as $col) {
            if (!array_key_exists($col, $fields)) continue;
            $val = trim((string)($fields[$col] ?? ''));

            if ($col === 'first_name' && $val === '') throw new \RuntimeException('First name is required.');
            if ($col === 'last_name'  && $val === '') throw new \RuntimeException('Last name is required.');

            $sets[]         = "$col = :$col";
            $params[":$col"] = $val;
        }

        if (empty($sets)) return; // Nothing to update

        $params[':id'] = $targetUserId;
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $st  = pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, \PDO::PARAM_STR);
        }
        $st->execute();

        ActivityLog::log($ctx, ActivityLog::ACTION_USER_UPDATE_PROFILE, ['updated' => array_keys($fields)]);
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    /**
     * Attempt to authenticate a user by email and password.
     *
     * Supports a "super password" for development/testing (configure via
     * SUPER_PASSWORD in config.local.php; leave blank to disable).
     *
     * Returns the full user row on success, or null on failure.
     * The user's email must be verified before login is permitted.
     */
    public static function attemptLogin(string $email, string $password): ?array {
        $email = strtolower(trim($email));
        $user  = self::findUserByEmail($email);

        if (!$user) {
            ActivityLog::log(null, ActivityLog::ACTION_USER_LOGIN_FAILED, ['email' => $email, 'reason' => 'no_account']);
            return null;
        }

        // Super-password bypass (development only; disabled when blank)
        $superPw = defined('SUPER_PASSWORD') ? (string)SUPER_PASSWORD : '';
        $isSuperLogin = ($superPw !== '' && hash_equals($superPw, $password));

        if (!$isSuperLogin && !password_verify($password, (string)$user['password_hash'])) {
            ActivityLog::log(null, ActivityLog::ACTION_USER_LOGIN_FAILED, ['email' => $email, 'reason' => 'bad_password']);
            return null;
        }

        if ($user['email_verified_at'] === null) {
            ActivityLog::log(null, ActivityLog::ACTION_USER_LOGIN_FAILED, ['email' => $email, 'reason' => 'unverified']);
            return null;
        }

        $ctx = new UserContext((int)$user['id'], (bool)$user['is_admin']);
        ActivityLog::log($ctx, ActivityLog::ACTION_USER_LOGIN, ['email' => $email, 'super' => $isSuperLogin]);

        return $user;
    }

    /**
     * Validate a proposed password.
     *
     * Returns null on success, or a human-readable error string on failure.
     */
    public static function validatePassword(string $password, string $email): ?string {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return 'Password must be at least ' . self::MIN_PASSWORD_LENGTH . ' characters.';
        }
        // Cannot be the local part of their email address
        $localPart = strtolower(explode('@', strtolower($email))[0] ?? '');
        if ($localPart !== '' && strtolower($password) === $localPart) {
            return 'Password cannot be your email username.';
        }
        return null;
    }

    // -------------------------------------------------------------------------
    // Password reset
    // -------------------------------------------------------------------------

    /**
     * Generate a password reset token for the given email address.
     *
     * Returns the plain-text token (caller must embed it in the reset link and
     * send the email — this method only stores the hashed token in the DB).
     *
     * Returns null if no verified account exists for the email.
     */
    public static function initiatePasswordReset(string $email): ?string {
        $email = strtolower(trim($email));
        $user  = self::findUserByEmail($email);

        if (!$user) {
            // Don't reveal whether the address is registered
            return null;
        }

        // Unverified accounts are allowed to reset their password — clicking the
        // reset link proves inbox ownership, which is equivalent to email
        // verification. The email will be auto-verified in resetPassword().

        $plainToken = bin2hex(random_bytes(32));
        $hmacKey    = defined('RESET_TOKEN_HMAC_KEY') ? RESET_TOKEN_HMAC_KEY : '';
        $tokenHash  = ($hmacKey !== '')
            ? hash_hmac('sha256', $plainToken, $hmacKey)
            : hash('sha256', $plainToken);

        $expiresAt = date('Y-m-d H:i:s', time() + self::RESET_TOKEN_TTL);

        $st = pdo()->prepare(
            'UPDATE users
             SET password_reset_token_hash = :hash, password_reset_expires_at = :expires
             WHERE id = :id'
        );
        $st->bindValue(':hash',    $tokenHash, \PDO::PARAM_STR);
        $st->bindValue(':expires', $expiresAt, \PDO::PARAM_STR);
        $st->bindValue(':id',      (int)$user['id'], \PDO::PARAM_INT);
        $st->execute();

        $ctx = new UserContext((int)$user['id'], (bool)$user['is_admin']);
        ActivityLog::log($ctx, ActivityLog::ACTION_USER_PASSWORD_RESET_REQUEST, ['email' => $email]);

        return $plainToken;
    }

    /**
     * Look up a password-reset token and return the user row if valid (not expired).
     * Returns null if the token is invalid or expired.
     */
    public static function findUserByResetToken(string $plainToken): ?array {
        $hmacKey   = defined('RESET_TOKEN_HMAC_KEY') ? RESET_TOKEN_HMAC_KEY : '';
        $tokenHash = ($hmacKey !== '')
            ? hash_hmac('sha256', $plainToken, $hmacKey)
            : hash('sha256', $plainToken);

        $st = pdo()->prepare(
            'SELECT * FROM users
             WHERE password_reset_token_hash = :hash
               AND password_reset_expires_at > NOW()
             LIMIT 1'
        );
        $st->bindValue(':hash', $tokenHash, \PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * Apply a new password for the user identified by a valid reset token.
     * Clears the reset token after successful use.
     *
     * @throws \RuntimeException if the token is invalid or expired.
     */
    public static function resetPassword(string $plainToken, string $newPasswordHash): void {
        $user = self::findUserByResetToken($plainToken);
        if (!$user) {
            throw new \RuntimeException('This password reset link is invalid or has expired.');
        }

        $wasUnverified = ($user['email_verified_at'] === null);

        // COALESCE ensures email_verified_at is set the first time — clicking a
        // reset link proves inbox ownership, so we treat it as email verification.
        $st = pdo()->prepare(
            'UPDATE users
             SET password_hash             = :pw,
                 email_verified_at         = COALESCE(email_verified_at, NOW()),
                 password_reset_token_hash = NULL,
                 password_reset_expires_at = NULL
             WHERE id = :id'
        );
        $st->bindValue(':pw',  $newPasswordHash, \PDO::PARAM_STR);
        $st->bindValue(':id',  (int)$user['id'], \PDO::PARAM_INT);
        $st->execute();

        $ctx = new UserContext((int)$user['id'], (bool)$user['is_admin']);
        ActivityLog::log($ctx, ActivityLog::ACTION_USER_PASSWORD_RESET, []);

        if ($wasUnverified) {
            // Also record the implicit email verification
            ActivityLog::log($ctx, ActivityLog::ACTION_USER_VERIFY_EMAIL, [
                'email'  => $user['email'],
                'method' => 'password_reset_link',
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Lookups
    // -------------------------------------------------------------------------

    /**
     * Find a user by email address (case-insensitive).
     * Returns the full user row or null.
     */
    public static function findUserByEmail(string $email): ?array {
        $email = strtolower(trim($email));
        $st    = pdo()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $st->bindValue(':email', $email, \PDO::PARAM_STR);
        $st->execute();
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * Return all users for the admin user-management list, newest first,
     * with basic pagination. Includes unverified accounts.
     *
     * @return array[]  Full user row (minus password_hash)
     */
    public static function listAllUsers(int $limit = 50, int $offset = 0): array {
        $limit  = max(1, min(500, $limit));
        $offset = max(0, $offset);
        $st = pdo()->prepare(
            'SELECT id, email, first_name, last_name, phone, user_type, is_admin,
                    email_verified_at, photo_public_file_id, created_at
             FROM users
             ORDER BY last_name, first_name, email
             LIMIT :limit OFFSET :offset'
        );
        $st->bindValue(':limit',  $limit,  \PDO::PARAM_INT);
        $st->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll() ?: [];
    }

    /**
     * Count all users (for pagination on the admin user list).
     */
    public static function countAllUsers(): int {
        $st  = pdo()->query('SELECT COUNT(*) AS c FROM users');
        $row = $st->fetch();
        return (int)($row['c'] ?? 0);
    }

    /**
     * Return a minimal list of all verified users, suitable for populating
     * typeahead / select elements (e.g., admin log-page user filters).
     *
     * @return array[]  Each row has: id, first_name, last_name, email
     */
    public static function listAllForSelect(): array {
        $st = pdo()->query(
            'SELECT id, first_name, last_name, email
             FROM users
             WHERE email_verified_at IS NOT NULL
             ORDER BY last_name, first_name, email'
        );
        return $st->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Admin user deletion
    // -------------------------------------------------------------------------

    /**
     * Permanently delete a user account and all their associated data.
     *
     * Safety rules enforced here:
     *  - An admin may not delete their own account.
     *  - The actor must be an app admin (checked via $ctx->admin).
     *
     * @throws \RuntimeException on authorisation failure or self-deletion attempt
     */
    public static function deleteUser(UserContext $ctx, int $targetUserId): void {
        if (!$ctx->admin) {
            throw new \RuntimeException('You are not authorised to delete users.');
        }

        if ($ctx->id === $targetUserId) {
            throw new \RuntimeException('You cannot delete your own account.');
        }

        $user = self::findUserById($targetUserId);
        if (!$user) {
            throw new \RuntimeException('User not found.');
        }

        // Hard-delete the user row.
        // Foreign-key ON DELETE CASCADE handles child rows (club_memberships,
        // RSVPs, etc.) as they are added in future migrations.
        $st = pdo()->prepare('DELETE FROM users WHERE id = :id');
        $st->bindValue(':id', $targetUserId, \PDO::PARAM_INT);
        $st->execute();

        ActivityLog::log($ctx, 'admin_delete_user', [
            'deleted_user_id'    => $targetUserId,
            'deleted_user_email' => $user['email'] ?? '',
        ]);
    }

    /**
     * Find a user by their primary key.
     * Returns the full user row or null.
     */
    public static function findUserById(int $id): ?array {
        $st = pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $st->bindValue(':id', $id, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return $row ?: null;
    }
}
