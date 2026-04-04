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

$editUrl = '/clubs/events/edit.php?id=' . $eventId;

/**
 * Convert an HTML datetime-local string ("YYYY-MM-DDTHH:MM") to MySQL format
 * ("YYYY-MM-DD HH:MM:SS"), or return empty string if blank/invalid.
 */
function parseDatetimeLocal(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';
    try {
        $dt = new \DateTime($raw);
        return $dt->format('Y-m-d H:i:s');
    } catch (\Exception $e) {
        return '';
    }
}

$name          = trim((string)($_POST['name'] ?? ''));
$startsAt      = parseDatetimeLocal($_POST['starts_at'] ?? '');
$endsAt        = parseDatetimeLocal($_POST['ends_at'] ?? '');
$locationName  = trim((string)($_POST['location_name'] ?? ''));
$locationAddr  = trim((string)($_POST['location_address'] ?? ''));
$googleMapsUrl = trim((string)($_POST['google_maps_url'] ?? ''));
$description   = trim((string)($_POST['description'] ?? ''));
$clearPhoto    = !empty($_POST['clear_photo']);

if ($name === '') {
    Flash::set('error', 'Event name is required.');
    redirect($editUrl);
}
if ($startsAt === '') {
    Flash::set('error', 'Start date/time is required.');
    redirect($editUrl);
}

try {
    $ctx = UserContext::getLoggedInUserContext();

    // Handle optional canvas-cropped event image (base64 data URL)
    $photoFileId = null;
    $photoData   = trim((string)($_POST['photo_data'] ?? ''));
    if ($photoData !== '') {
        $event  = EventManagement::getEventById($eventId);
        $clubId = $event ? (int)$event['club_id'] : 0;
        $photoFileId = EventManagement::saveEventImageFromDataUrl(
            $photoData,
            'event_photo_' . $clubId . '_' . $eventId . '.jpg',
            $ctx->id
        );
        $clearPhoto = false; // New upload overrides clear
    }

    EventManagement::updateEvent(
        $ctx,
        $eventId,
        $name,
        $startsAt,
        $endsAt,
        $locationName,
        $locationAddr,
        $googleMapsUrl,
        $description,
        $photoFileId,
        $clearPhoto
    );

    Flash::set('success', 'Event updated successfully.');
    redirect('/clubs/events/event.php?id=' . $eventId);

} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect($editUrl);
}
