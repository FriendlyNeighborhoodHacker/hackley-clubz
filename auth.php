<?php
declare(strict_types=1);

/**
 * Compatibility shim — all auth logic has moved to lib/Auth.php.
 * Files that require_once this file still work; new files should
 * require lib/Auth.php directly.
 */
require_once __DIR__ . '/lib/Auth.php';
