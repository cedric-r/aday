<?php

declare(strict_types=1);

/**
 * Checks whether the photo posting window is open for a given timezone.
 *
 * The window is open when the current local date in $ianaTimezone matches $eventDate,
 * or — when $allowLate is enabled — on the event date and any day after it.
 * Inject $now in tests to avoid relying on global time.
 */
final class WindowCheck
{
    /**
     * Determine whether photo posting is currently open.
     *
     * When $allowLate is false (default), the window is open only on the event
     * date itself. When true, the window stays open from the event date onward
     * (for late submitters, e.g. film photographers).
     *
     * @param  string                  $ianaTimezone  IANA timezone identifier (e.g. "America/New_York").
     * @param  string                  $eventDate     Event date in Y-m-d format. Empty string → always closed.
     * @param  DateTimeImmutable|null  $now           Override "now" for testing. Null = use real clock.
     * @param  bool                    $allowLate     Keep the window open after the event date.
     * @return bool
     */
    public static function isPostingOpen(
        string             $ianaTimezone,
        string             $eventDate,
        ?DateTimeImmutable $now = null,
        bool               $allowLate = false,
    ): bool {
        if ($eventDate === '') {
            return false;
        }

        if ($now === null) {
            $now = new DateTimeImmutable('now', new DateTimeZone($ianaTimezone));
        } else {
            // Ensure the injected $now is expressed in the user's timezone.
            $now = $now->setTimezone(new DateTimeZone($ianaTimezone));
        }

        $today = $now->format('Y-m-d');

        // Y-m-d strings compare lexicographically, so >= also covers "on or after".
        return $allowLate
            ? $today >= $eventDate
            : $today === $eventDate;
    }
}
