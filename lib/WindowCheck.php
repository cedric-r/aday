<?php

declare(strict_types=1);

/**
 * Checks whether the photo posting window is open for a given timezone.
 *
 * The window is open when the current local date in $ianaTimezone matches $eventDate.
 * Inject $now in tests to avoid relying on global time.
 */
final class WindowCheck
{
    /**
     * Determine whether photo posting is currently open.
     *
     * @param  string                  $ianaTimezone  IANA timezone identifier (e.g. "America/New_York").
     * @param  string                  $eventDate     Event date in Y-m-d format. Empty string → always closed.
     * @param  DateTimeImmutable|null  $now           Override "now" for testing. Null = use real clock.
     * @return bool
     */
    public static function isPostingOpen(
        string             $ianaTimezone,
        string             $eventDate,
        ?DateTimeImmutable $now = null,
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

        return $now->format('Y-m-d') === $eventDate;
    }
}
