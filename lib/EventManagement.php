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
     * $recurrenceRule should be 'weekly' | 'monthly_nth_weekday' | 'custom', or omitted/null
     * for a non-repeating event.  $recurrenceParentId is set internally by
     * createRecurringEvents() for child occurrences; callers should leave it null.
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
        ?int        $photoFileId,
        string      $recurrenceRule     = 'none',
        ?int        $recurrenceParentId = null
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

        $ruleVal   = in_array($recurrenceRule, ['weekly', 'monthly_nth_weekday', 'custom'], true)
                     ? $recurrenceRule : null;
        $endsVal   = $endsAt !== '' ? $endsAt : null;
        $mapsVal   = trim($googleMapsUrl) !== '' ? trim($googleMapsUrl) : null;

        $st = pdo()->prepare(
            'INSERT INTO events
               (club_id, name, starts_at, ends_at, location_name, location_address,
                google_maps_url, description, photo_public_file_id, created_by_user_id,
                recurrence_rule, recurrence_parent_id, created_at)
             VALUES
               (:cid, :name, :starts, :ends, :loc_name, :loc_addr,
                :maps, :desc, :photo, :creator,
                :rec_rule, :rec_parent, NOW())'
        );
        $st->bindValue(':cid',       $clubId,                 \PDO::PARAM_INT);
        $st->bindValue(':name',      $name,                   \PDO::PARAM_STR);
        $st->bindValue(':starts',    $startsAt,               \PDO::PARAM_STR);
        $st->bindValue(':ends',      $endsVal,    $endsVal    ? \PDO::PARAM_STR  : \PDO::PARAM_NULL);
        $st->bindValue(':loc_name',  trim($locationName),     \PDO::PARAM_STR);
        $st->bindValue(':loc_addr',  trim($locationAddress),  \PDO::PARAM_STR);
        $st->bindValue(':maps',      $mapsVal,    $mapsVal    ? \PDO::PARAM_STR  : \PDO::PARAM_NULL);
        $st->bindValue(':desc',      trim($description),      \PDO::PARAM_STR);
        $st->bindValue(':photo',     $photoFileId, $photoFileId ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $st->bindValue(':creator',   $ctx->id,                \PDO::PARAM_INT);
        $st->bindValue(':rec_rule',  $ruleVal,    $ruleVal    ? \PDO::PARAM_STR  : \PDO::PARAM_NULL);
        $st->bindValue(':rec_parent', $recurrenceParentId,
                       $recurrenceParentId !== null ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $st->execute();

        $eventId = (int)pdo()->lastInsertId();
        ActivityLog::log($ctx, 'event.create', ['event_id' => $eventId, 'club_id' => $clubId, 'name' => $name]);
        return $eventId;
    }

    /**
     * Create a recurring event series.
     *
     * Creates the first (parent) event via createEvent(), then generates all
     * child occurrences up to 1 year from the start date (max 52 weekly or
     * 12 monthly occurrences) and inserts them as child rows linked back to
     * the parent via recurrence_parent_id.
     *
     * Supported rules:
     *   'weekly'              — every 7 days.
     *   'monthly_nth_weekday' — same Nth weekday of each subsequent month
     *                           (e.g. "third Saturday").  If a month is too
     *                           short for the 5th occurrence, falls back to
     *                           the 4th.
     *
     * Returns the parent event ID.
     *
     * @throws \RuntimeException on invalid rule or permission failure
     */
    public static function createRecurringEvents(
        UserContext $ctx,
        int         $clubId,
        string      $name,
        string      $startsAt,
        string      $endsAt,
        string      $locationName,
        string      $locationAddress,
        string      $googleMapsUrl,
        string      $description,
        ?int        $photoFileId,
        string      $recurrenceRule
    ): int {
        if (!in_array($recurrenceRule, ['weekly', 'monthly_nth_weekday'], true)) {
            throw new \RuntimeException('Invalid recurrence rule: ' . $recurrenceRule);
        }

        // Create the first (parent) event — permission check happens inside createEvent().
        $parentId = self::createEvent(
            $ctx, $clubId, $name, $startsAt, $endsAt,
            $locationName, $locationAddress, $googleMapsUrl,
            $description, $photoFileId, $recurrenceRule, null
        );

        // Preserve event duration across recurrences.
        $startDt     = new \DateTime($startsAt);
        $durationSec = null;
        if ($endsAt !== '') {
            $endDt       = new \DateTime($endsAt);
            $durationSec = $endDt->getTimestamp() - $startDt->getTimestamp();
        }

        $cutoff   = (clone $startDt)->modify('+1 year');
        $maxCount = ($recurrenceRule === 'weekly') ? 52 : 12;

        $current  = clone $startDt;
        $count    = 0;

        while ($count < $maxCount) {
            if ($recurrenceRule === 'weekly') {
                $current->modify('+7 days');
            } else {
                $current = self::nextNthWeekdayOfMonth($current, $startDt);
            }

            if ($current > $cutoff) {
                break;
            }

            $childStartsAt = $current->format('Y-m-d H:i:s');
            $childEndsAt   = '';
            if ($durationSec !== null) {
                $childEndsAt = (clone $current)
                    ->modify('+' . $durationSec . ' seconds')
                    ->format('Y-m-d H:i:s');
            }

            self::insertChildEventRow(
                $ctx, $clubId, $name, $childStartsAt, $childEndsAt,
                $locationName, $locationAddress, $googleMapsUrl,
                $description, $photoFileId, $recurrenceRule, $parentId
            );
            $count++;
        }

        ActivityLog::log($ctx, 'event.create_recurring', [
            'parent_event_id' => $parentId,
            'club_id'         => $clubId,
            'name'            => $name,
            'rule'            => $recurrenceRule,
            'occurrences'     => $count + 1, // +1 for the parent
        ]);

        return $parentId;
    }

    /**
     * Create events for an explicit list of start-datetimes chosen by the user.
     *
     * The caller provides the exact occurrence dates (already confirmed by the
     * user in the UI); this method does not generate any dates itself.  The
     * duration of each occurrence is preserved from the original start/end pair.
     *
     * The first date in $occurrenceDates becomes the parent event; any
     * additional dates become child rows with recurrence_parent_id pointing
     * back to the parent.  If only one date is in the list the event is
     * created as a standalone (no recurrence columns set).
     *
     * @param UserContext $ctx
     * @param int         $clubId
     * @param string      $name
     * @param string      $baseStartsAt   Template start datetime — used to compute duration.
     * @param string      $baseEndsAt     Template end datetime   — used to compute duration.
     * @param string      $locationName
     * @param string      $locationAddress
     * @param string      $googleMapsUrl
     * @param string      $description
     * @param ?int        $photoFileId
     * @param string      $recurrenceRule  'weekly' | 'monthly_nth_weekday' | 'custom'
     * @param string[]    $occurrenceDates Array of 'YYYY-MM-DD HH:MM:SS' start datetimes
     *                                     (user-selected; sorted ascending before use).
     * @return int  Parent event ID.
     * @throws \RuntimeException on invalid input or permission failure.
     */
    public static function createEventsFromDateList(
        UserContext $ctx,
        int         $clubId,
        string      $name,
        string      $baseStartsAt,
        string      $baseEndsAt,
        string      $locationName,
        string      $locationAddress,
        string      $googleMapsUrl,
        string      $description,
        ?int        $photoFileId,
        string      $recurrenceRule,
        array       $occurrenceDates
    ): int {
        if (!in_array($recurrenceRule, ['weekly', 'monthly_nth_weekday', 'custom'], true)) {
            throw new \RuntimeException('Invalid recurrence rule.');
        }

        // Sanitize and sort the occurrence dates.
        $dates = [];
        foreach ($occurrenceDates as $rawDate) {
            $clean = trim((string)$rawDate);
            if ($clean === '') continue;
            try {
                $dates[] = (new \DateTime($clean))->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                // skip unparseable entries
            }
        }

        if (empty($dates)) {
            throw new \RuntimeException('Please select at least one occurrence date.');
        }

        sort($dates);
        $isRecurringSeries = count($dates) > 1;

        // Compute event duration from the base start/end times so every child
        // occurrence has the same length.
        $durationSec = null;
        if ($baseEndsAt !== '' && $baseStartsAt !== '') {
            try {
                $durationSec = (new \DateTime($baseEndsAt))->getTimestamp()
                             - (new \DateTime($baseStartsAt))->getTimestamp();
                if ($durationSec <= 0) $durationSec = null;
            } catch (\Exception $e) {
                $durationSec = null;
            }
        }

        // Helper: compute ends_at for a given starts_at.
        $endFor = function (string $startsAt) use ($durationSec): string {
            if ($durationSec === null) return '';
            try {
                return (new \DateTime($startsAt))
                    ->modify('+' . $durationSec . ' seconds')
                    ->format('Y-m-d H:i:s');
            } catch (\Exception $e) {
                return '';
            }
        };

        // Create the first (parent) event.  Permission check is inside createEvent().
        $rule     = $isRecurringSeries ? $recurrenceRule : 'none';
        $parentId = self::createEvent(
            $ctx, $clubId, $name,
            $dates[0], $endFor($dates[0]),
            $locationName, $locationAddress, $googleMapsUrl, $description, $photoFileId,
            $rule, null
        );

        if (!$isRecurringSeries) {
            return $parentId;
        }

        // Create child occurrences for every date after the first.
        $childCount = 0;
        for ($i = 1; $i < count($dates); $i++) {
            self::insertChildEventRow(
                $ctx, $clubId, $name,
                $dates[$i], $endFor($dates[$i]),
                $locationName, $locationAddress, $googleMapsUrl, $description, $photoFileId,
                $recurrenceRule, $parentId
            );
            $childCount++;
        }

        ActivityLog::log($ctx, 'event.create_recurring', [
            'parent_event_id' => $parentId,
            'club_id'         => $clubId,
            'name'            => $name,
            'rule'            => $recurrenceRule,
            'occurrences'     => count($dates),
        ]);

        return $parentId;
    }

    // -------------------------------------------------------------------------
    // Private recurrence helpers
    // -------------------------------------------------------------------------

    /**
     * Insert a child recurrence event row directly (no permission check, no
     * individual activity log entry — the batch log in createRecurringEvents()
     * covers all children).
     */
    private static function insertChildEventRow(
        UserContext $ctx,
        int         $clubId,
        string      $name,
        string      $startsAt,
        string      $endsAt,
        string      $locationName,
        string      $locationAddress,
        string      $googleMapsUrl,
        string      $description,
        ?int        $photoFileId,
        string      $recurrenceRule,
        int         $recurrenceParentId
    ): int {
        $endsVal = $endsAt !== '' ? $endsAt : null;
        $mapsVal = trim($googleMapsUrl) !== '' ? trim($googleMapsUrl) : null;

        $st = pdo()->prepare(
            'INSERT INTO events
               (club_id, name, starts_at, ends_at, location_name, location_address,
                google_maps_url, description, photo_public_file_id, created_by_user_id,
                recurrence_rule, recurrence_parent_id, created_at)
             VALUES
               (:cid, :name, :starts, :ends, :loc_name, :loc_addr,
                :maps, :desc, :photo, :creator,
                :rec_rule, :rec_parent, NOW())'
        );
        $st->bindValue(':cid',        $clubId,               \PDO::PARAM_INT);
        $st->bindValue(':name',       $name,                 \PDO::PARAM_STR);
        $st->bindValue(':starts',     $startsAt,             \PDO::PARAM_STR);
        $st->bindValue(':ends',       $endsVal,    $endsVal  ? \PDO::PARAM_STR  : \PDO::PARAM_NULL);
        $st->bindValue(':loc_name',   trim($locationName),   \PDO::PARAM_STR);
        $st->bindValue(':loc_addr',   trim($locationAddress),\PDO::PARAM_STR);
        $st->bindValue(':maps',       $mapsVal,    $mapsVal  ? \PDO::PARAM_STR  : \PDO::PARAM_NULL);
        $st->bindValue(':desc',       trim($description),    \PDO::PARAM_STR);
        $st->bindValue(':photo',      $photoFileId, $photoFileId ? \PDO::PARAM_INT : \PDO::PARAM_NULL);
        $st->bindValue(':creator',    $ctx->id,              \PDO::PARAM_INT);
        $st->bindValue(':rec_rule',   $recurrenceRule,       \PDO::PARAM_STR);
        $st->bindValue(':rec_parent', $recurrenceParentId,   \PDO::PARAM_INT);
        $st->execute();

        return (int)pdo()->lastInsertId();
    }

    /**
     * Compute the same Nth weekday of the month AFTER $current.
     *
     * The Nth weekday and the target day-of-week are derived from $original
     * (the series start date).  For example, if the series starts on the third
     * Saturday of April, this returns the third Saturday of May (given $current
     * somewhere in April), then the third Saturday of June, etc.
     *
     * Edge case: if the target month does not have a 5th occurrence of that
     * weekday, the 4th occurrence is used instead.
     *
     * @param \DateTime $current  The date of the most recently generated occurrence.
     * @param \DateTime $original The series start date (used to derive Nth + DOW).
     * @return \DateTime          The next occurrence, at the same time of day as $current.
     */
    private static function nextNthWeekdayOfMonth(\DateTime $current, \DateTime $original): \DateTime
    {
        $targetDow  = (int)$original->format('w'); // 0 = Sunday … 6 = Saturday
        $dayOfMonth = (int)$original->format('j'); // 1 – 31
        $nth        = (int)ceil($dayOfMonth / 7);  // 1 – 5 (which occurrence)

        // Move to the 1st of the month after $current
        $firstOfNext = (clone $current)->modify('first day of next month');
        $year  = (int)$firstOfNext->format('Y');
        $month = (int)$firstOfNext->format('m');

        // Find the first occurrence of $targetDow in that month
        $firstOfMonth    = new \DateTime(sprintf('%04d-%02d-01 %s', $year, $month, $current->format('H:i:s')));
        $firstDow        = (int)$firstOfMonth->format('w');
        $daysToFirstTarget = ($targetDow - $firstDow + 7) % 7;

        // Advance by (nth - 1) full weeks
        $targetDay = 1 + $daysToFirstTarget + ($nth - 1) * 7;

        // If this month doesn't have that many days, fall back one week (4th instead of 5th)
        $daysInMonth = (int)(new \DateTime(sprintf('%04d-%02d-01', $year, $month)))->format('t');
        if ($targetDay > $daysInMonth) {
            $targetDay -= 7;
        }

        return new \DateTime(sprintf('%04d-%02d-%02d %s', $year, $month, $targetDay, $current->format('H:i:s')));
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
     * Permanently delete a single event occurrence.
     *
     * If the event being deleted is the parent of a recurring series, the
     * earliest remaining child is promoted to become the new parent so that
     * the rest of the series survives (avoids ON DELETE CASCADE wiping them out).
     *
     * @throws \RuntimeException if the actor lacks permission or the event is not found
     */
    public static function deleteEvent(UserContext $ctx, int $eventId): void {
        $event = self::getEventById($eventId);
        if (!$event) {
            throw new \RuntimeException('Event not found.');
        }

        $clubId = (int)$event['club_id'];
        if (!$ctx->admin && !ClubManagement::isUserClubAdmin($ctx->id, $clubId)) {
            throw new \RuntimeException('You must be a club admin to delete events.');
        }

        // If this event is a series parent (has children), promote the earliest
        // child to be the new parent before deleting — otherwise ON DELETE CASCADE
        // would wipe out the entire series.
        $isParent = empty($event['recurrence_parent_id'])
                    && !empty($event['recurrence_rule']);

        if ($isParent) {
            // Find all children ordered by start date so we promote the earliest.
            $childSt = pdo()->prepare(
                'SELECT id FROM events
                  WHERE recurrence_parent_id = :pid
                  ORDER BY starts_at ASC'
            );
            $childSt->bindValue(':pid', $eventId, \PDO::PARAM_INT);
            $childSt->execute();
            $children = $childSt->fetchAll();

            if (!empty($children)) {
                $newParentId          = (int)$children[0]['id'];
                $remainingChildrenIds = array_slice(array_column($children, 'id'), 1);

                // Promote first child: clear its recurrence_parent_id.
                $promoteSt = pdo()->prepare(
                    'UPDATE events SET recurrence_parent_id = NULL WHERE id = :id'
                );
                $promoteSt->bindValue(':id', $newParentId, \PDO::PARAM_INT);
                $promoteSt->execute();

                // Re-point any remaining children to the new parent.
                if (!empty($remainingChildrenIds)) {
                    $placeholders = implode(',', array_fill(0, count($remainingChildrenIds), '?'));
                    $reparentSt   = pdo()->prepare(
                        "UPDATE events SET recurrence_parent_id = ?
                          WHERE id IN ($placeholders)"
                    );
                    $reparentSt->execute(array_merge([$newParentId], $remainingChildrenIds));
                }
            }
        }

        // Now safe to delete — no children point to this row any more.
        $st = pdo()->prepare('DELETE FROM events WHERE id = :id');
        $st->bindValue(':id', $eventId, \PDO::PARAM_INT);
        $st->execute();

        ActivityLog::log($ctx, 'event.delete', [
            'event_id' => $eventId,
            'club_id'  => $clubId,
            'name'     => $event['name'] ?? '',
        ]);
    }

    /**
     * Permanently delete an entire recurring event series.
     *
     * Works whether $eventId is the parent or any child in the series:
     *   - If $eventId has a recurrence_parent_id, that parent becomes the root.
     *   - If $eventId is already the root parent, it is used directly.
     * Deleting the root cascades to all children via the FK constraint.
     *
     * @throws \RuntimeException if the actor lacks permission or the event is not found
     */
    public static function deleteEventSeries(UserContext $ctx, int $eventId): void {
        $event = self::getEventById($eventId);
        if (!$event) {
            throw new \RuntimeException('Event not found.');
        }

        $clubId = (int)$event['club_id'];
        if (!$ctx->admin && !ClubManagement::isUserClubAdmin($ctx->id, $clubId)) {
            throw new \RuntimeException('You must be a club admin to delete events.');
        }

        // Resolve the root parent ID.
        $parentId = $event['recurrence_parent_id']
            ? (int)$event['recurrence_parent_id']
            : $eventId;

        // Count how many events will be removed for the activity log.
        $countSt = pdo()->prepare(
            'SELECT COUNT(*) FROM events
              WHERE id = :pid OR recurrence_parent_id = :pid2'
        );
        $countSt->bindValue(':pid',  $parentId, \PDO::PARAM_INT);
        $countSt->bindValue(':pid2', $parentId, \PDO::PARAM_INT);
        $countSt->execute();
        $totalDeleted = (int)$countSt->fetchColumn();

        // Get the series name from the parent for the log.
        $parentEvent = self::getEventById($parentId);
        $seriesName  = $parentEvent ? ($parentEvent['name'] ?? '') : ($event['name'] ?? '');

        // Delete the parent — FK ON DELETE CASCADE removes all children automatically.
        $st = pdo()->prepare('DELETE FROM events WHERE id = :pid');
        $st->bindValue(':pid', $parentId, \PDO::PARAM_INT);
        $st->execute();

        ActivityLog::log($ctx, 'event.delete_series', [
            'parent_event_id' => $parentId,
            'club_id'         => $clubId,
            'name'            => $seriesName,
            'events_deleted'  => $totalDeleted,
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
     * Return all users who have RSVPed "yes" to an event, ordered by when they
     * RSVPed (earliest first).  Each row contains:
     *   id, first_name, last_name, photo_public_file_id
     *
     * @return array[]
     */
    public static function getEventAttendees(int $eventId): array {
        $st = pdo()->prepare(
            'SELECT u.id, u.first_name, u.last_name, u.photo_public_file_id
             FROM rsvps r
             JOIN users u ON u.id = r.user_id
             WHERE r.event_id = :eid AND r.answer = \'yes\'
             ORDER BY r.created_at ASC'
        );
        $st->bindValue(':eid', $eventId, \PDO::PARAM_INT);
        $st->execute();
        return $st->fetchAll() ?: [];
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
