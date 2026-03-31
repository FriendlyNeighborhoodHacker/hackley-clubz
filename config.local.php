<?php
// Local configuration for Hackley Clubz (do not commit the real config.local.php)
//
// Copy this file to config.local.php and fill in your environment-specific values.

// ===== Database =====
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'hackleyclubz');
define('DB_USER', 'root');
define('DB_PASS', '');

// ===== Application =====
// APP_NAME is used as a fallback; UI will prefer the 'site_title' setting from the database.
define('APP_NAME', 'Hackley Clubz');

// Base URL of the application (no trailing slash). Used for email links.
// Example: 'https://clubz.hackleyschool.org'
define('APP_URL', 'http://localhost');

// ===== Security =====
// Optional super password for testing — allows logging in as any user.
// IMPORTANT: Leave blank or remove entirely in production.
define('SUPER_PASSWORD', 'super');

// HMAC key for password reset tokens (REQUIRED).
// Generate with: php -r "echo bin2hex(random_bytes(32));"
define('RESET_TOKEN_HMAC_KEY', '033c99d89ff8962470a9cbe924303e0fffd786674bfb54cc516c4280455dcb7d');

// SMTP configuration (DreamHost/Gmail)
define('SMTP_HOST', 'smtp.gmail.com');     // e.g., smtp.gmail.com
define('SMTP_PORT', 587);                  // 587 for STARTTLS, 465 for SSL
define('SMTP_USER', 'hackleydebatewebsite@gmail.com');  // your Gmail address
define('SMTP_PASS', 'nsqlwngqoomyxszm');                   // app password (recommended), not your normal password
define('SMTP_SECURE', 'tls');              // 'tls' or 'ssl'
define('SMTP_FROM_EMAIL', 'hackleydebatewebsite@gmail.com');             // from email (often same as SMTP_USER)
define('SMTP_FROM_NAME',  'Hackley Clubz');

// Email debug mode — when true, emails are simulated (not actually sent) and logged.
// IMPORTANT: Set to false in production.
define('EMAIL_DEBUG_MODE', false);
