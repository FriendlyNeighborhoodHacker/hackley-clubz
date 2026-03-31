<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';

/**
 * Logs all email-sending attempts to the database and to a line-delimited JSON file.
 *
 * Errors are swallowed to prevent logging failures from disrupting email delivery.
 */
final class EmailLog {

    private static function pdo(): \PDO {
        return pdo();
    }

    // -------------------------------------------------------------------------
    // Write
    // -------------------------------------------------------------------------

    /**
     * Log an email sending attempt.
     *
     * @param ?UserContext $ctx          Acting user (null for system-generated emails)
     * @param string       $toEmail      Recipient email address
     * @param string       $toName       Recipient display name
     * @param string       $subject      Email subject line
     * @param string       $bodyHtml     Email body (HTML)
     * @param bool         $success      Whether the email was delivered successfully
     * @param ?string      $errorMessage Error detail if delivery failed
     */
    public static function log(
        ?UserContext $ctx,
        string $toEmail,
        string $toName,
        string $subject,
        string $bodyHtml,
        bool $success,
        ?string $errorMessage = null
    ): void {
        $toEmail = trim($toEmail);
        $toName  = trim($toName);
        $subject = trim($subject);

        if ($toEmail === '' || $subject === '') return;

        $userId = $ctx ? (int)$ctx->id : null;

        // Write to DB
        try {
            $st = self::pdo()->prepare(
                "INSERT INTO emails_sent
                   (sent_by_user_id, to_email, to_name, subject, body_html, success, error_message, created_at)
                 VALUES
                   (:sent_by_user_id, :to_email, :to_name, :subject, :body_html, :success, :error_message, NOW())"
            );
            $st->bindValue(':sent_by_user_id', $userId,        $userId === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $st->bindValue(':to_email',        $toEmail,       \PDO::PARAM_STR);
            $st->bindValue(':to_name',         $toName ?: null, \PDO::PARAM_STR);
            $st->bindValue(':subject',         $subject,       \PDO::PARAM_STR);
            $st->bindValue(':body_html',       $bodyHtml,      \PDO::PARAM_STR);
            $st->bindValue(':success',         $success ? 1 : 0, \PDO::PARAM_INT);
            $st->bindValue(':error_message',   $errorMessage,  \PDO::PARAM_STR);
            $st->execute();
        } catch (\Throwable $e) {
            // Swallow — logging must not break email flow
        }

        // Append to file (best-effort)
        try {
            $logDir = dirname(__DIR__) . '/logs';
            if (!is_dir($logDir)) @mkdir($logDir, 0775, true);
            $payload = [
                'ts'                => gmdate('c'),
                'sent_by_user_id'   => $userId,
                'to_email'          => $toEmail,
                'to_name'           => $toName ?: null,
                'subject'           => $subject,
                'body_length'       => strlen($bodyHtml),
                'success'           => $success,
                'error_message'     => $errorMessage,
            ];
            @file_put_contents(
                $logDir . '/emails.log',
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
     * Retrieve recent email log entries, newest first.
     *
     * Supported filters: sent_by_user_id, to_email, success (bool), since, until.
     */
    public static function list(array $filters = [], int $limit = 50, int $offset = 0): array {
        $limit  = max(1, min(500, $limit));
        $offset = max(0, $offset);

        [$where, $params] = self::buildWhereClause($filters);

        $sql = 'SELECT id, created_at, sent_by_user_id, to_email, to_name, subject, success, error_message FROM emails_sent';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;

        $st = self::pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
        }
        $st->execute();
        return $st->fetchAll() ?: [];
    }

    /**
     * Count email log entries matching filters (for pagination).
     */
    public static function count(array $filters = []): int {
        [$where, $params] = self::buildWhereClause($filters);

        $sql = 'SELECT COUNT(*) AS c FROM emails_sent';
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
     * Retrieve a single email record by ID.
     * Optionally validates that it was sent to the given address (security guard).
     */
    public static function findById(int $id, ?string $validateToEmail = null): ?array {
        $st = self::pdo()->prepare('SELECT * FROM emails_sent WHERE id = :id LIMIT 1');
        $st->bindValue(':id', $id, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        if (!$row) return null;

        if ($validateToEmail !== null && strtolower(trim($row['to_email'])) !== strtolower(trim($validateToEmail))) {
            return null;
        }
        return $row;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private static function buildWhereClause(array $filters): array {
        $where  = [];
        $params = [];

        if (!empty($filters['sent_by_user_id'])) {
            $where[]                    = 'sent_by_user_id = :sent_by_user_id';
            $params[':sent_by_user_id'] = (int)$filters['sent_by_user_id'];
        }
        if (!empty($filters['to_email'])) {
            $where[]           = 'to_email = :to_email';
            $params[':to_email'] = trim((string)$filters['to_email']);
        }
        if (isset($filters['success']) && $filters['success'] !== null) {
            $where[]           = 'success = :success';
            $params[':success'] = $filters['success'] ? 1 : 0;
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
