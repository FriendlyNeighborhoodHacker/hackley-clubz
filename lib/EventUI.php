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
