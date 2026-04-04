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

$clubId = (int)($_POST['club_id'] ?? 0);
if ($clubId <= 0) {
    Flash::set('error', 'Invalid club.');
    redirect('/clubs/browse.php');
}

$createUrl = '/clubs/events/create.php?id=' . $clubId;

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

if ($name === '') {
    Flash::set('error', 'Event name is required.');
    redirect($createUrl);
}
if ($startsAt === '') {
    Flash::set('error', 'Start date/time is required.');
    redirect($createUrl);
}

try {
    $ctx = UserContext::getLoggedInUserContext();

    // Handle optional canvas-cropped event image (base64 data URL)
    $photoFileId = null;
    $photoData   = trim((string)($_POST['photo_data'] ?? ''));
    if ($photoData !== '') {
        $photoFileId = EventManagement::saveEventImageFromDataUrl(
            $photoData,
            'event_photo_' . $clubId . '_' . time() . '.jpg',
            $ctx->id
        );
    }

    $eventId = EventManagement::createEvent(
        $ctx,
        $clubId,
        $name,
        $startsAt,
        $endsAt,
        $locationName,
        $locationAddr,
        $googleMapsUrl,
        $description,
        $photoFileId
    );

    Flash::set('success', 'Event created successfully.');
    redirect('/clubs/events/event.php?id=' . $eventId);

} catch (\RuntimeException $e) {
    Flash::set('error', $e->getMessage());
    redirect($createUrl);
}
