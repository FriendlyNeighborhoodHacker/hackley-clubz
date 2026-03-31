<?php
declare(strict_types=1);

// ===== Load local configuration =====
$localConfig = __DIR__ . '/config.local.php';
if (!file_exists($localConfig)) {
    die('config.local.php not found. Copy config.local.php.example to config.local.php and fill in your values.');
}
require_once $localConfig;

// ===== Session =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== Timezone =====
// Will be overridden by Settings::get('timezone') once the DB is available,
// but set a sane default now for any pre-DB error messages.
if (!defined('APP_TIMEZONE')) {
    define('APP_TIMEZONE', 'America/New_York');
}
date_default_timezone_set(APP_TIMEZONE);

// ===== PDO Singleton =====
/**
 * Returns the shared PDO instance, creating it on first call.
 */
function pdo(): \PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new \PDO($dsn, DB_USER, DB_PASS, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

// ===== CSRF Helpers =====
/**
 * Return the current request's CSRF token, generating one if needed.
 */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify the CSRF token submitted in a POST request.
 * Throws RuntimeException on mismatch or missing token.
 */
function csrf_verify(): void {
    $submitted = $_POST['_csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $submitted)) {
        throw new \RuntimeException('Invalid or missing CSRF token. Please reload the page and try again.');
    }
}

/**
 * Return an HTML hidden input containing the CSRF token.
 */
function csrf_input(): string {
    return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// ===== HTML Escaping Helper =====
/**
 * Escape a value for safe HTML output.
 */
function e(mixed $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ===== Redirect Helper =====
/**
 * Redirect to a URL and exit. Relative URLs are fine.
 */
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

// Flash message helpers are in lib/Flash.php (Flash::set / Flash::get / Flash::render).
require_once __DIR__ . '/lib/Flash.php';
