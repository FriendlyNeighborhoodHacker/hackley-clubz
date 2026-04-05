<?php
declare(strict_types=1);

/**
 * UI helper functions for event display.
 *
 * The rsvpSectionHtml() method is the single source of truth for RSVP section
 * HTML so that both the page render and the AJAX endpoint return identical markup.
 */
final class EventUI {

    // -----------------------------------------------------------------------
    // Public API
    // -----------------------------------------------------------------------

    /**
     * Return the inner HTML for the RSVP area of an event card.
     *
     * @param int         $eventId
     * @param string|null $currentAnswer  'yes' | 'maybe' | 'no' | null (not yet RSVP'd)
     */
    public static function rsvpSectionHtml(int $eventId, ?string $currentAnswer): string {
        if ($currentAnswer === null) {
            return self::rsvpButtonsHtml($eventId);
        }
        return self::rsvpStatusHtml($eventId, $currentAnswer);
    }

    /**
     * Render the "Going (N):" facepile for a list of attendees.
     *
     * Each avatar is a small circle (photo or initial placeholder).
     * Clicking an avatar toggles a small floating name badge below it.
     * Returns '' when $attendees is empty.
     *
     * @param  array[] $attendees  Rows from EventManagement::getEventAttendees()
     *                             Each row must have: id, first_name, last_name,
     *                             photo_public_file_id (nullable)
     */
    public static function facepileHtml(array $attendees): string {
        if (empty($attendees)) {
            return '';
        }

        require_once __DIR__ . '/Files.php';

        $count = count($attendees);
        $h     = static fn(string $s): string =>
            htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $avatarSize = 32; // px

        $html  = '<div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">';
        $html .= '<span style="font-size:0.8rem;color:var(--text-secondary);font-weight:600;'
               . 'white-space:nowrap;">Going (' . $count . '):</span>';
        $html .= '<div style="display:flex;gap:-4px;flex-wrap:wrap;gap:4px;" id="facepile-avatars">';

        foreach ($attendees as $i => $person) {
            $uid       = (int)$person['id'];
            $firstName = $h((string)($person['first_name'] ?? ''));
            $lastName  = $h((string)($person['last_name']  ?? ''));
            $fullName  = trim($firstName . ' ' . $lastName);
            $initial   = strtoupper(substr($person['first_name'] ?? '', 0, 1));
            $photoId   = $person['photo_public_file_id'] ? (int)$person['photo_public_file_id'] : null;
            $photoUrl  = $photoId ? Files::profilePhotoUrl($photoId) : '';
            $badgeId   = 'fp-badge-' . $uid . '-' . $i;

            $html .= '<div style="position:relative;display:inline-block;" '
                   . 'onmouseleave="document.getElementById(\'' . $badgeId . '\').style.display=\'none\'">';

            // Avatar button
            $html .= '<button type="button"'
                   . ' title="' . $fullName . '"'
                   . ' aria-label="' . $fullName . '"'
                   . ' style="width:' . $avatarSize . 'px;height:' . $avatarSize . 'px;'
                   . 'border-radius:50%;border:2px solid var(--surface);'
                   . 'padding:0;background:none;cursor:pointer;overflow:hidden;'
                   . 'display:flex;align-items:center;justify-content:center;'
                   . 'box-shadow:0 1px 3px rgba(0,0,0,.15);transition:transform .15s;"'
                   . ' onmouseenter="this.style.transform=\'scale(1.12)\'"'
                   . ' onmouseleave="this.style.transform=\'scale(1)\'"'
                   . ' onclick="(function(btn,id){'
                   . 'var b=document.getElementById(id);'
                   . 'var shown=b.style.display===\'block\';'
                   . 'document.querySelectorAll(\'.fp-badge\').forEach(function(x){x.style.display=\'none\';});'
                   . 'if(!shown){b.style.display=\'block\';}'
                   . '})(this,\'' . $badgeId . '\')">';

            if ($photoUrl !== '') {
                $html .= '<img src="' . $h($photoUrl) . '" alt="' . $fullName . '"'
                       . ' style="width:100%;height:100%;object-fit:cover;border-radius:50%;display:block;">';
            } else {
                $html .= '<div style="width:100%;height:100%;border-radius:50%;'
                       . 'background:var(--gradient-brand);color:#fff;'
                       . 'display:flex;align-items:center;justify-content:center;'
                       . 'font-size:11px;font-weight:600;">'
                       . $h($initial)
                       . '</div>';
            }

            $html .= '</button>';

            // Name badge (hidden by default, shown on click)
            $html .= '<div id="' . $badgeId . '" class="fp-badge"'
                   . ' style="display:none;position:absolute;top:' . ($avatarSize + 4) . 'px;left:50%;'
                   . 'transform:translateX(-50%);'
                   . 'background:var(--text-primary);color:#fff;'
                   . 'font-size:11px;white-space:nowrap;'
                   . 'padding:3px 8px;border-radius:4px;z-index:100;'
                   . 'pointer-events:none;">'
                   . $fullName
                   . '</div>';

            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';

        // One-time global click handler to dismiss all badges when clicking outside
        static $scriptEmitted = false;
        if (!$scriptEmitted) {
            $html .= '<script>'
                   . 'document.addEventListener("click",function(e){'
                   . 'if(!e.target.closest("[id^=\'fp-badge-\']")&&!e.target.closest("button[onclick]")){'
                   . 'document.querySelectorAll(".fp-badge").forEach(function(b){b.style.display="none";});'
                   . '}'
                   . '});'
                   . '</script>';
            $scriptEmitted = true;
        }

        return $html;
    }

    // -----------------------------------------------------------------------
    // Private helpers
    // -----------------------------------------------------------------------

    /** Render the three RSVP choice buttons (unanswered state). */
    private static function rsvpButtonsHtml(int $eventId): string {
        // Inline SVG icons (no emoji)
        $checkSvg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none"'
                  . ' stroke="currentColor" stroke-width="2.5"'
                  . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                  . '<polyline points="20 6 9 17 4 12"/></svg>';

        $heartSvg = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none"'
                  . ' stroke="currentColor" stroke-width="2.5"'
                  . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                  . '<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06'
                  . 'a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78'
                  . ' 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>';

        $xSvg     = '<svg width="13" height="13" viewBox="0 0 24 24" fill="none"'
                  . ' stroke="currentColor" stroke-width="2.5"'
                  . ' stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
                  . '<line x1="18" y1="6" x2="6" y2="18"/>'
                  . '<line x1="6" y1="6" x2="18" y2="18"/></svg>';

        $base = 'display:inline-flex;align-items:center;gap:5px;padding:5px 13px;'
              . 'font-size:12px;border-radius:var(--radius-sm, 6px);'
              . 'border:1.5px solid var(--coral);color:var(--coral);'
              . 'background:transparent;cursor:pointer;font-family:inherit;'
              . 'transition:background .15s,color .15s;line-height:1.4;';

        $eid = $eventId;

        $hover = "this.style.background='var(--coral)';this.style.color='#fff';";
        $out   = "this.style.background='transparent';this.style.color='var(--coral)';";

        return
            '<div style="display:flex;gap:8px;flex-wrap:wrap;">'
          . "<button type=\"button\" style=\"{$base}\""
          .   " onmouseover=\"{$hover}\" onmouseout=\"{$out}\""
          .   " onclick=\"rsvpSubmit({$eid},'yes',this)\">"
          .   "{$checkSvg} Yes"
          . '</button>'
          . "<button type=\"button\" style=\"{$base}\""
          .   " onmouseover=\"{$hover}\" onmouseout=\"{$out}\""
          .   " onclick=\"rsvpSubmit({$eid},'maybe',this)\">"
          .   "{$heartSvg} Maybe"
          . '</button>'
          . "<button type=\"button\" style=\"{$base}\""
          .   " onmouseover=\"{$hover}\" onmouseout=\"{$out}\""
          .   " onclick=\"rsvpSubmit({$eid},'no',this)\">"
          .   "{$xSvg} No"
          . '</button>'
          . '</div>';
    }

    /** Render the "You RSVP'd Yes. [Change RSVP]" status line. */
    private static function rsvpStatusHtml(int $eventId, string $answer): string {
        $labels = ['yes' => 'Yes', 'maybe' => 'Maybe', 'no' => 'No'];
        $colors = ['yes' => '#22c55e', 'maybe' => '#f97316', 'no' => '#ef4444'];

        $label = $labels[$answer] ?? ucfirst($answer);
        $color = $colors[$answer] ?? 'inherit';
        $safeAnswer = htmlspecialchars($answer, ENT_QUOTES, 'UTF-8');

        return
            '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;'
          . 'font-size:0.85rem;color:var(--text-secondary);">'
          . '<span>You RSVP\'d <strong style="color:' . $color . ';">'
          .   htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
          . '</strong>.</span>'
          . "<button type=\"button\""
          .   " style=\"background:none;border:none;padding:0;color:var(--accent-blue);"
          .   "cursor:pointer;font-size:0.85rem;text-decoration:underline;font-family:inherit;\""
          .   " onclick=\"rsvpOpenModal({$eventId},'{$safeAnswer}')\">"
          .   'Change RSVP'
          . '</button>'
          . '</div>';
    }
}
