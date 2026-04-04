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

try {
    $ctx   = UserContext::getLoggedInUserContext();
    $event = EventManagement::getEventById($eventId);
    if (!$event) {
        Flash::set('error', 'Event not found.');
        redirect('/clubs/browse.php');
    }

    $clubId = (int)$event['club_id'];
    EventManagement::deleteEvent($ctx, $eventId);

    Flash::set('success', 'Event deleted.');
    redirect('/clubs/events/index.php?id=' . $clubId);

} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect('/clubs/events/event.php?id=' . $eventId);
}
