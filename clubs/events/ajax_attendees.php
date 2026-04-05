<?php
declare(strict_types=1);

/**
 * GET /clubs/events/ajax_attendees.php?event_id=X
 *
 * Returns the facepile HTML fragment for all "yes" RSVPs on an event.
 * Used to refresh the Going section after an RSVP change without a full page reload.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/EventManagement.php';
require_once __DIR__ . '/../../lib/EventUI.php';

Application::init();
Auth::requireLogin();

$eventId = (int)($_GET['event_id'] ?? 0);
if ($eventId <= 0) {
    http_response_code(400);
    echo '';
    exit;
}

$event = EventManagement::getEventById($eventId);
if (!$event) {
    http_response_code(404);
    echo '';
    exit;
}

$attendees   = EventManagement::getEventAttendees($eventId);
$facepileHtml = EventUI::facepileHtml($attendees);

if ($facepileHtml !== '') {
    echo '<hr style="border:none; border-top:1px solid var(--border); margin:20px 0;">';
    echo $facepileHtml;
}
