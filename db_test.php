<?php
/**
 * db_test.php — Standalone MySQL connection test.
 *
 * Does NOT depend on any other project files except config.local.php.
 * Run from the command line:  php db_test.php
 * Or visit it in a browser:  http://localhost/db_test.php
 *
 * DELETE OR PROTECT THIS FILE before deploying to production.
 */

// Override the 30-second max_execution_time so a hung TCP connect
// doesn't kill the script before we can print a useful error.
set_time_limit(10);

$localConfig = __DIR__ . '/config.local.php';
if (!file_exists($localConfig)) {
    die("ERROR: config.local.php not found.\n"
      . "Copy config.local.php.example to config.local.php and fill in your values.\n");
}
require_once $localConfig;

$host = defined('DB_HOST') ? DB_HOST : null;
$name = defined('DB_NAME') ? DB_NAME : null;
$user = defined('DB_USER') ? DB_USER : null;
$pass = defined('DB_PASS') ? DB_PASS : null;

echo "=== Hackley Clubz — MySQL connection test ===\n\n";
echo "  DB_HOST : " . ($host ?? '(not defined)') . "\n";
echo "  DB_NAME : " . ($name ?? '(not defined)') . "\n";
echo "  DB_USER : " . ($user ?? '(not defined)') . "\n";
echo "  DB_PASS : " . ($pass !== null ? str_repeat('*', strlen($pass)) : '(not defined)') . "\n\n";

if (!$host || !$name || !$user || $pass === null) {
    die("ERROR: One or more DB_* constants are missing from config.local.php.\n");
}

echo "Attempting connection (5-second timeout)...\n";

try {
    $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "SUCCESS: Connected to MySQL server.\n\n";

    // Check schema version
    $tables = [];
    $st = $pdo->query("SHOW TABLES");
    foreach ($st->fetchAll(PDO::FETCH_NUM) as $row) {
        $tables[] = $row[0];
    }
    echo "Tables found (" . count($tables) . "): " . implode(', ', $tables) . "\n\n";

    // Check settings table
    if (in_array('settings', $tables)) {
        echo "Settings table contents:\n";
        $rows = $pdo->query("SELECT key_name, value FROM settings ORDER BY key_name")->fetchAll();
        foreach ($rows as $r) {
            echo "  " . str_pad($r['key_name'], 30) . " = " . $r['value'] . "\n";
        }
    } else {
        echo "WARNING: 'settings' table does not exist.\n"
           . "         Run schema.sql against the database to set up the schema.\n";
    }

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n\n";
    echo "Common causes:\n";
    echo "  - MySQL server is not running  (macOS: mysql.server start)\n";
    echo "  - DB_HOST is wrong             (check config.local.php)\n";
    echo "  - DB_USER / DB_PASS is wrong   (check config.local.php)\n";
    echo "  - DB_NAME database doesn't exist\n\n";
    echo "If DB_HOST is 'localhost', PHP uses a Unix socket instead of TCP.\n";
    echo "Try changing DB_HOST to '127.0.0.1' in config.local.php to force TCP.\n";
} catch (Throwable $e) {
    echo "FATAL: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
