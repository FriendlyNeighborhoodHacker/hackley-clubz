<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';

/**
 * Key/value application settings stored in the `settings` table.
 *
 * Values are cached in-process for the lifetime of the request.
 */
final class Settings {

    /** @var array<string,string|null> In-process cache */
    private static array $cache = [];
    private static bool  $loaded = false;

    private static function pdo(): \PDO {
        return pdo();
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * Get a setting value by key. Returns $default if the key does not exist.
     */
    public static function get(string $key, mixed $default = null): mixed {
        self::ensureLoaded();
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key] ?? $default;
        }
        return $default;
    }

    /**
     * Return all settings as an associative array.
     */
    public static function all(): array {
        self::ensureLoaded();
        return self::$cache;
    }

    /**
     * Return the list of student email domains (comma-separated in settings).
     * Users registering with one of these domains get user_type = 'student'.
     *
     * @return string[]  e.g. ['students.hackleyschool.org']
     */
    public static function getStudentEmailDomains(): array {
        return self::parseDomainList((string)(self::get('student_email_domains', '') ?? ''));
    }

    /**
     * Return the list of adult/faculty email domains (comma-separated in settings).
     * Users registering with one of these domains get user_type = 'adult'.
     *
     * @return string[]  e.g. ['hackleyschool.org']
     */
    public static function getAdultEmailDomains(): array {
        return self::parseDomainList((string)(self::get('adult_email_domains', '') ?? ''));
    }

    /**
     * Return the combined list of all allowed registration domains
     * (union of student + adult domains).
     *
     * @return string[]
     */
    public static function getAllowedEmailDomains(): array {
        return array_values(array_unique(array_merge(
            self::getStudentEmailDomains(),
            self::getAdultEmailDomains()
        )));
    }

    /**
     * Return the site title.
     */
    public static function siteTitle(): string {
        return (string)(self::get('site_title') ?: (defined('APP_NAME') ? APP_NAME : 'Hackley Clubz'));
    }

    /**
     * Return the site logo public_file_id, or null if none is set.
     */
    public static function siteLogoFileId(): ?int {
        $v = self::get('site_logo_file_id');
        return ($v !== null && $v !== '') ? (int)$v : null;
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Set a setting value. Only app admins should be permitted to call this.
     *
     * @throws \RuntimeException if the actor is not an app admin
     */
    public static function set(UserContext $ctx, string $key, ?string $value): void {
        if (!$ctx->admin) {
            throw new \RuntimeException('Only application admins may change settings.');
        }

        $key = trim($key);
        if ($key === '') throw new \RuntimeException('Setting key may not be empty.');

        $st = self::pdo()->prepare(
            "INSERT INTO settings (key_name, value) VALUES (:key, :value)
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()"
        );
        $st->bindValue(':key',   $key,   \PDO::PARAM_STR);
        $st->bindValue(':value', $value, \PDO::PARAM_STR);
        $st->execute();

        // Invalidate cache
        self::$cache[$key] = $value;

        ActivityLog::log($ctx, 'settings.update', ['key' => $key]);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Parse a comma-separated domain list into a trimmed, filtered array.
     *
     * @return string[]
     */
    private static function parseDomainList(string $raw): array {
        if ($raw === '') return [];
        $domains = array_map('trim', explode(',', $raw));
        return array_values(array_filter($domains));
    }

    /**
     * Bulk-load all settings from the DB into the in-process cache (once per request).
     *
     * Throws on PDO / connection errors so callers see a real error rather than
     * silently working with an empty settings cache (which would, for example,
     * make every email domain appear "not allowed").
     *
     * The only exception swallowed is a missing `settings` table (SQLSTATE 42S02),
     * which can legitimately happen during initial schema setup.
     */
    private static function ensureLoaded(): void {
        if (self::$loaded) return;
        try {
            $st = self::pdo()->query('SELECT key_name, value FROM settings');
            foreach ($st->fetchAll() ?: [] as $row) {
                self::$cache[$row['key_name']] = $row['value'];
            }
        } catch (\PDOException $e) {
            // Only swallow "table doesn't exist" (happens on a fresh install before
            // the schema has been run).  All other PDO errors (wrong credentials,
            // host unreachable, etc.) are re-thrown so they surface immediately.
            if ($e->getCode() !== '42S02') {
                throw $e;
            }
        }
        self::$loaded = true;
    }
}
