<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/UserContext.php';
require_once __DIR__ . '/ActivityLog.php';
require_once __DIR__ . '/Files.php';
require_once __DIR__ . '/ClubManagement.php';

/**
 * All event-related database operations.
 *
 * Rules:
 *  - Every write method accepts a UserContext (for the ActivityLog).
 *  - SQL lives only in this class.
 *  - Errors are thrown as exceptions; callers decide how to handle them.
 */
final class EventManagement {

    // -------------------------------------------------------------------------
    // Reads
    // -------------------------------------------------------------------------

    /**
     * Return a single event row, or null if not found.
     */
    public static function getEventById(int $id): ?array {
        $st = pdo()->prepare(
            'SELECT e.*,
                    c.name AS club_name,
                    c.photo_public_file_id AS club_photo_file_id
             FROM events e
             JOIN clubs c ON c.id = e.club_id
             WHERE e.id = :id
             LIMIT 1'
        );
        $st->bindValue(':id', $id, \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return $row ?: null;
    }

    /**
     * Return upcoming events for a club, ordered by starts_at ascending.
     * Pass $includesPast = true to also return past events.
     *
     * @return array[]
     */
    public static function listClubEvents(int $clubId, bool $includePast = false): array {
        $where = 'e.club_id = :cid';
        if (!$includePast) {
            // Show events that haven't ended yet (or haven't started if no end time)
            $where .= ' AND COALESCE(e.ends_at, DATE_ADD(e.starts_at, INTERVAL 2 HOUR)) >= NOW()';
        }
        $st = pdo()->prepare(
            'SELECT e.*,
                    c.name AS club_name,
                    c.photo_public_file_id AS club_photo_file_id
             FROM events e
             JOIN clubs c ON c.id = e.club_id
             WHERE ' . $where . '
             ORDER BY e.starts_at ASC'
        );
        $st->bindValue(':cid', $clubId, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll() ?: [];
    }

    /**
     * Return upcoming events for all clubs a user is a member of.
     *
     * @return array[]
     */
    public static function listUpcomingEventsForUser(int $userId): array {
        $st = pdo()->prepare(
            'SELECT e.*,
                    c.name AS club_name,
                    c.photo_public_file_id AS club_photo_file_id
             FROM events e
             JOIN clubs c ON c.id = e.club_id
             JOIN club_memberships cm ON cm.club_id = e.club_id AND cm.user_id = :uid
             WHERE COALESCE(e.ends_at, DATE_ADD(e.starts_at, INTERVAL 2 HOUR)) >= NOW()
             ORDER BY e.starts_at ASC'
        );
        $st->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll() ?: [];
    }

    // -------------------------------------------------------------------------
    // Writes
    // -------------------------------------------------------------------------

    /**
     * Create a new event and return its ID.
     *
     * @throws \RuntimeException if the actor lacks permission or required fields are missing
     */
    public static function createEvent(
        UserContext $ctx,
        int         $clubId,
        string      $name,
        string      $startsAt,
        string      $endsAt,
        string      $locationName,
        string      $locationAddress,
        string      $googleMapsUrl,
        string      $description,
        ?int        $photoFileId
    ): int {
        if (!$ctx->admin && !ClubManagement::isUserClubAdmin($ctx->id, $clubId)) {
            throw new \RuntimeException('You must be a club admin to create events.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException('Event name is required.');
        }
        if ($startsAt === '') {
            throw new \RuntimeException('Start date/time is required.');
        }

        $st = pdo()->prepare(
            'INSERT INTO events
               (club_id, name, starts_at, ends_at, location_name, location_address,
                google_maps_url, description, photo_public_file_id, created_by_user_id, created_at)
             VALUES
               (:cid, :name, :starts, :ends, :loc_name, :loc_addr,
                :maps, :desc, :photo, :creator, NOW())'
        );
        $endsVal   = $endsAt !== '' ? $endsAt : null;
        $mapsVal   = trim($googleMapsUrl) !== '' ? trim($googleMapsUrl) : null;

        $st->bindValue(':cid',     $clubId,                  \PDO::PARAM_INT);
        $st->bindValue(':name',    $name,                    \PDO::PARAM_STR);
        $st->bindValue(':starts',  $startsAt,                \PDO::PARAM_STR);
        $st->bindValue(':ends',    $endsVal,   $endsVal   ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $st->bindValue(':loc_name',trim($locationName),      \PDO::PARAM_STR);
        $st->bindValue(':loc_addr',trim($locationAddress),   \PDO::PARAM_STR);
        $st->bindValue(':maps',    $mapsVal,   $mapsVal   ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
        $st->bindValue(':desc',    trim($description),       \PDO::PARAM_STR);
        $st->bindValue(':photo',   $photoFileId, $photoFileId ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $st->bindValue(':creator', $ctx->id,                 \PDO::PARAM_INT);
        $st->execute();

        $eventId = (int)pdo()->lastInsertId();
        ActivityLog::log($ctx, 'event.create', ['event_id' => $eventId, 'club_id' => $clubId, 'name' => $name]);
        return $eventId;
    }

    /**
     * Update an existing event's fields.
     *
     * Pass a non-null $photoFileId to replace the event image.
     * Pass $clearPhoto = true to remove the image without replacing it.
     *
     * @throws \RuntimeException if the actor lacks permission or required fields are missing
     */
    public static function updateEvent(
        UserContext $ctx,
        int         $eventId,
        string      $name,
        string      $startsAt,
        string      $endsAt,
        string      $locationName,
        string      $locationAddress,
        string      $googleMapsUrl,
        string      $description,
        ?int        $photoFileId,
        bool        $clearPhoto = false
    ): void {
        $event = self::getEventById($eventId);
        if (!$event) {
            throw new \RuntimeException('Event not found.');
        }

        if (!$ctx->admin && !ClubManagement::isUserClubAdmin($ctx->id, (int)$event['club_id'])) {
            throw new \RuntimeException('You must be a club admin to edit events.');
        }

        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException('Event name is required.');
        }
        if ($startsAt === '') {
            throw new \RuntimeException('Start date/time is required.');
        }

        $sets   = [
            'name = :name',
            'starts_at = :starts',
            'ends_at = :ends',
            'location_name = :loc_name',
            'location_address = :loc_addr',
            'google_maps_url = :maps',
            'description = :desc',
        ];
        $endsVal = $endsAt !== '' ? $endsAt : null;
        $mapsVal = trim($googleMapsUrl) !== '' ? trim($googleMapsUrl) : null;

        $params = [
            ':name'     => $name,
            ':starts'   => $startsAt,
            ':ends'     => $endsVal,
            ':loc_name' => trim($locationName),
            ':loc_addr' => trim($locationAddress),
            ':maps'     => $mapsVal,
            ':desc'     => trim($description),
            ':id'       => $eventId,
        ];

        if ($photoFileId !== null) {
            $sets[]           = 'photo_public_file_id = :photo';
            $params[':photo'] = $photoFileId;
        } elseif ($clearPhoto) {
            $sets[] = 'photo_public_file_id = NULL';
        }

        $sql = 'UPDATE events SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $st  = pdo()->prepare($sql);
        foreach ($params as $k => $v) {
            $st->bindValue($k, $v, is_int($v) || $v === null
                ? ($v === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT)
                : \PDO::PARAM_STR);
        }
        $st->execute();

        ActivityLog::log($ctx, 'event.update', ['event_id' => $eventId, 'name' => $name]);
    }

    /**
     * Permanently delete an event.
     *
     * @throws \RuntimeException if the actor lacks permission or the event is not found
     */
    public static function deleteEvent(UserContext $ctx, int $eventId): void {
        $event = self::getEventById($eventId);
        if (!$event) {
            throw new \RuntimeException('Event not found.');
        }

        if (!$ctx->admin && !ClubManagement::isUserClubAdmin($ctx->id, (int)$event['club_id'])) {
            throw new \RuntimeException('You must be a club admin to delete events.');
        }

        $st = pdo()->prepare('DELETE FROM events WHERE id = :id');
        $st->bindValue(':id', $eventId, \PDO::PARAM_INT);
        $st->execute();

        ActivityLog::log($ctx, 'event.delete', [
            'event_id' => $eventId,
            'club_id'  => (int)$event['club_id'],
            'name'     => $event['name'] ?? '',
        ]);
    }

    // -------------------------------------------------------------------------
    // RSVPs
    // -------------------------------------------------------------------------

    /**
     * Batch-fetch RSVP answers for a user across multiple events.
     *
     * Returns an associative array keyed by event_id with values 'yes'|'maybe'|'no'.
     * Events with no RSVP are absent from the array.
     *
     * @param  int   $userId
     * @param  int[] $eventIds
     * @return array<int,string>
     */
    public static function getUserRsvpsForEvents(int $userId, array $eventIds): array {
        if (empty($eventIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $st = pdo()->prepare(
            "SELECT event_id, answer FROM rsvps
              WHERE user_id = ? AND event_id IN ($placeholders)"
        );
        $st->execute(array_merge([$userId], $eventIds));
        $map = [];
        foreach ($st->fetchAll() as $row) {
            $map[(int)$row['event_id']] = $row['answer'];
        }
        return $map;
    }

    /**
     * Get a single user's RSVP answer for one event, or null if none exists.
     */
    public static function getUserRsvp(int $eventId, int $userId): ?string {
        $st = pdo()->prepare(
            'SELECT answer FROM rsvps WHERE event_id = :eid AND user_id = :uid LIMIT 1'
        );
        $st->bindValue(':eid', $eventId, \PDO::PARAM_INT);
        $st->bindValue(':uid', $userId,  \PDO::PARAM_INT);
        $st->execute();
        $row = $st->fetch();
        return $row ? $row['answer'] : null;
    }

    /**
     * Create or update an RSVP for the acting user.
     *
     * @param  string $answer  One of 'yes', 'maybe', 'no'
     * @throws \RuntimeException on invalid answer or missing event
     */
    public static function setRsvp(UserContext $ctx, int $eventId, string $answer): void {
        if (!in_array($answer, ['yes', 'maybe', 'no'], true)) {
            throw new \RuntimeException('Invalid RSVP answer.');
        }

        $event = self::getEventById($eventId);
        if (!$event) {
            throw new \RuntimeException('Event not found.');
        }

        $st = pdo()->prepare(
            'INSERT INTO rsvps (event_id, user_id, entered_by, answer, created_at, updated_at)
             VALUES (:eid, :uid, :entered_by, :answer, NOW(), NOW())
             ON DUPLICATE KEY UPDATE answer = VALUES(answer), updated_at = NOW()'
        );
        $st->bindValue(':eid',        $eventId,  \PDO::PARAM_INT);
        $st->bindValue(':uid',        $ctx->id,  \PDO::PARAM_INT);
        $st->bindValue(':entered_by', $ctx->id,  \PDO::PARAM_INT);
        $st->bindValue(':answer',     $answer,   \PDO::PARAM_STR);
        $st->execute();

        ActivityLog::log($ctx, 'rsvp.set', [
            'event_id' => $eventId,
            'answer'   => $answer,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Save an event image from a canvas data URL (base64-encoded JPEG).
     *
     * Accepts strings of the form "data:image/jpeg;base64,..." as produced
     * by canvas.toDataURL() in the browser crop widget.
     *
     * Returns the new public_file ID, or null if $dataUrl is blank.
     *
     * @throws \RuntimeException on malformed data or decode failure
     */
    public static function saveEventImageFromDataUrl(
        string $dataUrl,
        string $filename,
        ?int   $uploadedByUserId
    ): ?int {
        $dataUrl = trim($dataUrl);
        if ($dataUrl === '') {
            return null;
        }

        // Strip the data URI header: "data:image/jpeg;base64,"
        if (!preg_match('#^data:(image/[a-z]+);base64,#i', $dataUrl, $m)) {
            throw new \RuntimeException('Invalid image data.');
        }
        $mime    = strtolower($m[1]);
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowed, true)) {
            throw new \RuntimeException('Only JPEG, PNG, WebP, or GIF images are allowed.');
        }

        $b64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $bin = base64_decode($b64, true);
        if ($bin === false || strlen($bin) === 0) {
            throw new \RuntimeException('Could not decode image data.');
        }

        return Files::insertPublicFile($bin, $mime, $filename, $uploadedByUserId);
    }

    /**
     * Format a datetime string as "Nov 2, 4:30pm".
     */
    public static function formatDateTime(string $datetime): string {
        if ($datetime === '') return '';
        try {
            $dt = new \DateTime($datetime);
            $min = $dt->format('i');
            return $dt->format('M j, g') . ($min !== '00' ? ':' . $min : '') . $dt->format('a');
        } catch (\Exception $e) {
            return $datetime;
        }
    }

    /**
     * Format a date range as "Nov 2, 4:30pm – 5:30pm" or "Nov 2, 4:30pm – Nov 3, 10:00am".
     */
    public static function formatDateRange(string $startsAt, ?string $endsAt): string {
        $start = self::formatDateTime($startsAt);
        if (!$endsAt || $endsAt === '') return $start;

        try {
            $s = new \DateTime($startsAt);
            $e = new \DateTime($endsAt);
            $eMin = $e->format('i');
            $endTime = $e->format('g') . ($eMin !== '00' ? ':' . $eMin : '') . $e->format('a');
            if ($s->format('Y-m-d') === $e->format('Y-m-d')) {
                return $start . ' – ' . $endTime;
            }
            return $start . ' – ' . self::formatDateTime($endsAt);
        } catch (\Exception $ex) {
            return $start;
        }
    }

    /**
     * Build a Google Calendar add-event URL for an event row.
     */
    public static function googleCalendarUrl(array $event): string {
        $title   = urlencode($event['name'] ?? '');
        $details = urlencode(strip_tags($event['description'] ?? ''));
        $loc     = urlencode(($event['location_name'] ?? '') . ' ' . ($event['location_address'] ?? ''));

        // Format as local datetime (no Z suffix) and pass the server timezone via ctz.
        // Using Z would tell Google the times are UTC, but they are stored in local time.
        $fmt = fn(string $dt): string => (new \DateTime($dt))->format('Ymd\THis');
        $tz  = urlencode(date_default_timezone_get());
        try {
            $start = $fmt($event['starts_at']);
            $end   = $event['ends_at'] ? $fmt($event['ends_at']) : $start;
        } catch (\Exception $e) {
            return '';
        }

        return 'https://calendar.google.com/calendar/render?action=TEMPLATE'
             . '&text=' . $title
             . '&dates=' . $start . '/' . $end
             . '&ctz=' . $tz
             . '&details=' . $details
             . '&location=' . $loc;
    }
}
