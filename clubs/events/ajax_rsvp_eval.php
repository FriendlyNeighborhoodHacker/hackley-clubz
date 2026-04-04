<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/EventManagement.php';
require_once __DIR__ . '/../../lib/EventUI.php';
require_once __DIR__ . '/../../lib/UserContext.php';

Application::init();

// ── Guards ───────────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => 0, 'error' => 'Method not allowed.']);
    exit;
}

if (empty($_SESSION['uid'])) {
    echo json_encode(['success' => 0, 'error' => 'Please log in to RSVP.']);
    exit;
}

try {
    csrf_verify();
} catch (\RuntimeException $e) {
    echo json_encode(['success' => 0, 'error' => $e->getMessage()]);
    exit;
}

// ── Input ─────────────────────────────────────────────────────────────────────

$eventId = (int)($_POST['event_id'] ?? 0);
$answer  = trim($_POST['answer'] ?? '');

if ($eventId <= 0) {
    echo json_encode(['success' => 0, 'error' => 'Invalid event.']);
    exit;
}

// ── Commit & respond ─────────────────────────────────────────────────────────

try {
    $ctx = UserContext::getLoggedInUserContext();
    EventManagement::setRsvp($ctx, $eventId, $answer);
    $html = EventUI::rsvpSectionHtml($eventId, $answer);
    echo json_encode(['success' => 1, 'html' => $html]);
} catch (\Exception $e) {
    echo json_encode(['success' => 0, 'error' => $e->getMessage()]);
}
exit;
