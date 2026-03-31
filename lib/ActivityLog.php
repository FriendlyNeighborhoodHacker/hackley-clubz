<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';

/**
 * Writes application activity to the database and to a line-delimited JSON log file.
 *
 * Design notes:
 * - Do NOT perform extra DB queries here (e.g., to look up names). Callers pass
 *   only metadata they already have in memory.
 * - Errors are swallowed so that logging failures never break the main request flow.
 */
final class ActivityLog {

    private static function pdo(): \PDO {
        return pdo();
    }

    // -------------------------------------------------------------------------
    // Action type constants — extend as new features are added
    // -------------------------------------------------------------------------
    const ACTION_USER_REGISTER          = 'user.register';
    const ACTION_USER_VERIFY_EMAIL      = 'user.verify_email';
    const ACTION_USER_LOGIN             = 'user.login';
    const ACTION_USER_LOGIN_FAILED      = 'user.login_failed';
    const ACTION_USER_LOGOUT            = 'user.logout';
    const ACTION_USER_UPDATE_PROFILE    = 'user.update_profile';
    const ACTION_USER_UPDATE_PHOTO      = 'user.update_photo';
    const ACTION_USER_PASSWORD_RESET_REQUEST = 'user.password_reset_request';
    const ACTION_USER_PASSWORD_RESET    = 'user.password_reset';

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Record an activity entry.
     *
     * @param ?UserContext $ctx        The acting user (null for system/anonymous actions)
     * @param string       $actionType Application-defined action identifier (use class constants above)
     * @param array        $metadata   Arbitrary key→value pairs serialised to JSON
     */
    public static function log(?UserContext $ctx, string $actionType, array $metadata = []): void {
        $action = trim($actionType);
        if ($action === '') return;

        $userId = $ctx ? (int)$ctx->id : null;

        // Write to DB
        try {
            $st = self::pdo()->prepare(
                "INSERT INTO activity_log (user_id, action_type, json_metadata, created_at)
                 VALUES (:user_id, :action_type, :json_metadata, NOW())"
            );
            $st->bindValue(':user_id',       $userId,                                       $userId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $st->bindValue(':action_type',   $action,                                       \PDO::PARAM_STR);
            $st->bindValue(':json_metadata', json_encode($metadata, JSON_UNESCAPED_SLASHES), \PDO::PARAM_STR);
            $st->execute();
        } catch (\Throwable $e) {
            // Swallow — logging must not break request flow
        }

        // Append to file (best-effort)
        try {
            $logDir = dirname(__DIR__) . '/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
            $payload = [
                'ts'      => gmdate('c'),
                'user_id' => $userId,
                'action'  => $action,
                'meta'    => $metadata,
            ];
            @file_put_contents(
                $logDir . '/activity.log',
                json_encode($payload, JSON_UNESCAPED_SLASHES) . PHP_EOL,
                FILE_APPEND | LOCK_EX
            );
        } catch (\Throwable $e) {
            // Best-effort only
        }
    }

    // -------------------------------------------------------------------------
    // Read
    // -------------------------------------------------------------------------

    /**
     * Retrieve recent activity entries, newest first.
     *
     * Supported filters: user_id, action_type, since (datetime string), until (datetime string).
     */
    public static function list(array $filters = [], int $limit = 50, int $offset = 0): array {
        $limit  = max(1, min(500, $limit));
        $offset = max(0, $offset);

        [$where, $params] = self::buildWhereClause($filters);

        $sql = 'SELECT id, created_at, user_id, action_type, json_metadata FROM activity_log';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        $st = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $st->execute();
        $rows = $st->fetchAll() ?: [];

        foreach ($rows as &$r) {
            $raw = (string)($r['json_metadata'] ?? '');
            $r['metadata'] = $raw !== '' ? json_decode($raw, true) : null;
        }
        return $rows;
    }

    /**
     * Count activity entries matching filters (for pagination).
     */
    public static function count(array $filters = []): int {
        [$where, $params] = self::buildWhereClause($filters);

        $sql = 'SELECT COUNT(*) AS c FROM activity_log';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);

        $st = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $st->execute();
        $row = $st->fetch();
        return (int)($row['c'] ?? 0);
    }

    /**
     * Return the distinct action types present in the log (for UI filter dropdowns).
     */
    public static function distinctActionTypes(): array {
        $st = self::pdo()->query('SELECT DISTINCT action_type FROM activity_log ORDER BY action_type ASC');
        return array_column($st->fetchAll() ?: [], 'action_type');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function buildWhereClause(array $filters): array {
        $where  = [];
        $params = [];

        if (!empty($filters['user_id'])) {
            $where[]           = 'user_id = :user_id';
            $params[':user_id'] = (int)$filters['user_id'];
        }
        if (!empty($filters['action_type'])) {
            $where[]               = 'action_type = :action_type';
            $params[':action_type'] = trim((string)$filters['action_type']);
        }
        if (!empty($filters['since'])) {
            $where[]        = 'created_at >= :since';
            $params[':since'] = trim((string)$filters['since']);
        }
        if (!empty($filters['until'])) {
            $where[]        = 'created_at <= :until';
            $params[':until'] = trim((string)$filters['until']);
        }

        return [$where, $params];
    }
}
