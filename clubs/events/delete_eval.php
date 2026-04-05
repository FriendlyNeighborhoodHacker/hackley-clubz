<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/Application.php';
require_once __DIR__ . '/../../lib/Auth.php';
require_once __DIR__ . '/../../lib/EventManagement.php';
require_once __DIR__ . '/../../lib/UserContext.php';

Application::init();
Auth::requireLogin();
csrf_verify();

$eventId = (int)($_POST['event_id'] ?? 0);
if ($eventId <= 0) {
    Flash::set('error', 'Invalid event.');
    redirect('/clubs/browse.php');
}

// 'single' deletes only this occurrence; 'series' deletes the whole recurring series.
$deleteScope = trim((string)($_POST['delete_scope'] ?? 'single'));
if (!in_array($deleteScope, ['single', 'series'], true)) {
    $deleteScope = 'single';
}

try {
    $ctx   = UserContext::getLoggedInUserContext();
    $event = EventManagement::getEventById($eventId);
    if (!$event) {
        Flash::set('error', 'Event not found.');
        redirect('/clubs/browse.php');
    }

    $clubId = (int)$event['club_id'];

    if ($deleteScope === 'series') {
        EventManagement::deleteEventSeries($ctx, $eventId);
        Flash::set('success', 'All events in the series have been deleted.');
    } else {
        EventManagement::deleteEvent($ctx, $eventId);
        Flash::set('success', 'Event deleted.');
    }

    redirect('/clubs/events/index.php?id=' . $clubId);

} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/clubs/events/event.php?id=' . $eventId);
}
