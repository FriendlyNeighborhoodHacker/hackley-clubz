<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/EmailLog.php';
require_once __DIR__ . '/UserContext.php';

/**
 * SMTP email sending for Hackley Clubz.
 *
 * All sending goes through send_email() (or send_email_detailed() when you
 * need error details). Both functions log the attempt via EmailLog.
 *
 * Configuration constants (defined in config.local.php):
 *   SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS,
 *   SMTP_SECURE ('tls' | 'ssl'), SMTP_FROM_EMAIL, SMTP_FROM_NAME
 *
 * When EMAIL_DEBUG_MODE is true, emails are simulated and not actually sent.
 */

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Send an HTML email.
 *
 * @return bool  true on success, false on failure
 */
function send_email(string $toEmail, string $subject, string $html, string $toName = ''): bool {
    if ($toName === '') $toName = $toEmail;

    if (defined('EMAIL_DEBUG_MODE') && EMAIL_DEBUG_MODE === true) {
        return _mailer_debug_send($toEmail, $toName, $subject, $html);
    }

    $success = _mailer_smtp_send($toEmail, $toName, $subject, $html, $errorMsg);

    try {
        $ctx = UserContext::getLoggedInUserContext();
        EmailLog::log($ctx, $toEmail, $toName, $subject, $html, $success, $success ? null : $errorMsg);
    } catch (\Throwable $e) { /* don't let logging break sending */ }

    return $success;
}

/**
 * Send an HTML email and return detailed result.
 *
 * @return array{success: bool, error: string|null}
 */
function send_email_detailed(string $toEmail, string $subject, string $html, string $toName = ''): array {
    if ($toName === '') $toName = $toEmail;

    if (defined('EMAIL_DEBUG_MODE') && EMAIL_DEBUG_MODE === true) {
        $success = _mailer_debug_send($toEmail, $toName, $subject, $html);
        return ['success' => $success, 'error' => $success ? null : 'Debug mode: simulated failure'];
    }

    $success = _mailer_smtp_send($toEmail, $toName, $subject, $html, $errorMsg);

    try {
        $ctx = UserContext::getLoggedInUserContext();
        EmailLog::log($ctx, $toEmail, $toName, $subject, $html, $success, $success ? null : $errorMsg);
    } catch (\Throwable $e) { /* don't let logging break sending */ }

    return ['success' => $success, 'error' => $success ? null : $errorMsg];
}

// ---------------------------------------------------------------------------
// Internal: debug mode simulation
// ---------------------------------------------------------------------------

function _mailer_debug_send(string $toEmail, string $toName, string $subject, string $html): bool {
    $success = (random_int(1, 10) > 1); // 90 % success rate for testing
    $logBody = $html . "\n\n[DEBUG MODE — NOT ACTUALLY SENT]";
    try {
        $ctx = UserContext::getLoggedInUserContext();
        EmailLog::log($ctx, $toEmail, $toName, $subject, $logBody, $success, $success ? null : 'Debug: simulated SMTP failure');
    } catch (\Throwable $e) {}
    return $success;
}

// ---------------------------------------------------------------------------
// Internal: real SMTP send
// ---------------------------------------------------------------------------

/**
 * Low-level SMTP send. Populates $errorMsg on failure.
 * Supports STARTTLS (port 587) and SSL (port 465).
 *
 * @param string|null &$errorMsg  Set to a description of the failure on false return.
 */
function _mailer_smtp_send(
    string $toEmail,
    string $toName,
    string $subject,
    string $html,
    ?string &$errorMsg = null
): bool {
    if (!defined('SMTP_HOST') || !defined('SMTP_PORT') || !defined('SMTP_USER') || !defined('SMTP_PASS')) {
        $errorMsg = 'SMTP configuration constants are not defined (SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS).';
        return false;
    }

    $host     = SMTP_HOST;
    $port     = (int)SMTP_PORT;
    $secure   = defined('SMTP_SECURE') ? strtolower(SMTP_SECURE) : 'tls';
    $fromEmail = defined('SMTP_FROM_EMAIL') && SMTP_FROM_EMAIL ? SMTP_FROM_EMAIL : SMTP_USER;
    $fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'Hackley Clubz');

    $timeout   = 20;
    $transport = ($secure === 'ssl') ? "ssl://$host" : $host;

    $fp = @stream_socket_client("$transport:$port", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
    if (!$fp) {
        $errorMsg = "Could not connect to $host:$port — $errstr (errno $errno)";
        return false;
    }
    stream_set_timeout($fp, $timeout);

    $lastLine = '';

    $expect = function(array $codes) use ($fp, &$lastLine): bool {
        do {
            $line = fgets($fp, 515);
            if ($line === false) { $lastLine = 'Connection lost'; return false; }
            $lastLine = trim($line);
            $code     = (int)substr($line, 0, 3);
            $more     = isset($line[3]) && $line[3] === '-';
        } while ($more);
        return in_array($code, $codes, true);
    };

    $send = static fn(string $cmd): bool => fwrite($fp, $cmd . "\r\n") !== false;

    $fail = function(string $msg) use ($fp, &$errorMsg): bool {
        fclose($fp);
        $errorMsg = $msg;
        return false;
    };

    if (!$expect([220])) return $fail("SMTP greeting failed: $lastLine");

    $ehlo = $_SERVER['SERVER_NAME'] ?? 'localhost';
    if (!$send("EHLO $ehlo")) return $fail('Failed to send EHLO');
    if (!$expect([250]))      return $fail("EHLO rejected: $lastLine");

    if ($secure === 'tls') {
        if (!$send('STARTTLS'))                                                             return $fail('Failed to send STARTTLS');
        if (!$expect([220]))                                                                return $fail("STARTTLS rejected: $lastLine");
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT))      return $fail('TLS negotiation failed');
        if (!$send("EHLO $ehlo"))                                                           return $fail('Failed to re-send EHLO after STARTTLS');
        if (!$expect([250]))                                                                return $fail("EHLO (post-TLS) rejected: $lastLine");
    }

    if (!$send('AUTH LOGIN'))                  return $fail('Failed to send AUTH LOGIN');
    if (!$expect([334]))                       return $fail("AUTH LOGIN rejected: $lastLine");
    if (!$send(base64_encode(SMTP_USER)))      return $fail('Failed to send username');
    if (!$expect([334]))                       return $fail("Username rejected: $lastLine");
    if (!$send(base64_encode(SMTP_PASS)))      return $fail('Failed to send password');
    if (!$expect([235]))                       return $fail("Password rejected: $lastLine");

    if (!$send("MAIL FROM:<$fromEmail>"))      return $fail('Failed to send MAIL FROM');
    if (!$expect([250]))                       return $fail("MAIL FROM rejected: $lastLine");
    if (!$send("RCPT TO:<$toEmail>"))          return $fail('Failed to send RCPT TO');
    if (!$expect([250, 251]))                  return $fail("RCPT TO rejected: $lastLine");
    if (!$send('DATA'))                        return $fail('Failed to send DATA');
    if (!$expect([354]))                       return $fail("DATA rejected: $lastLine");

    $headers   = [];
    $headers[] = 'Date: ' . date('r');
    $headers[] = 'From: ' . mb_encode_mimeheader($fromName) . " <$fromEmail>";
    $headers[] = 'To: '   . mb_encode_mimeheader($toName)   . " <$toEmail>";
    $headers[] = 'Subject: ' . mb_encode_mimeheader($subject);
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/html; charset=UTF-8';
    $headers[] = 'Content-Transfer-Encoding: 8bit';

    $body = preg_replace("/\r\n|\r|\n/", "\r\n", $html);
    $body = preg_replace('/^\./m', '..', $body);

    $data = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
    if (!$send($data))    return $fail('Failed to transmit email body');
    if (!$expect([250]))  return $fail("Email body rejected: $lastLine");

    $send('QUIT');
    fclose($fp);
    return true;
}
