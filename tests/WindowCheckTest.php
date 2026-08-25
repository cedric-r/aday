<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for lib/WindowCheck.php (US-4).
 * Path A — pure logic, no DB or HTTP needed.
 */
final class WindowCheckTest extends TestCase
{
    private function makeNow(string $datetime, string $tz): DateTimeImmutable
    {
        return new DateTimeImmutable($datetime, new DateTimeZone($tz));
    }

    public function test_open_when_event_date_today_at_10am(): void
    {
        $now = $this->makeNow('2026-08-24 10:00:00', 'UTC');
        $this->assertTrue(WindowCheck::isPostingOpen('UTC', '2026-08-24', $now));
    }

    public function test_open_when_event_date_today_at_235959(): void
    {
        $now = $this->makeNow('2026-08-24 23:59:59', 'UTC');
        $this->assertTrue(WindowCheck::isPostingOpen('UTC', '2026-08-24', $now));
    }

    public function test_closed_when_now_is_next_day(): void
    {
        $now = $this->makeNow('2026-08-25 00:00:00', 'UTC');
        $this->assertFalse(WindowCheck::isPostingOpen('UTC', '2026-08-24', $now));
    }

    public function test_closed_when_different_date(): void
    {
        $now = $this->makeNow('2026-08-23 12:00:00', 'UTC');
        $this->assertFalse(WindowCheck::isPostingOpen('UTC', '2026-08-24', $now));
    }

    public function test_closed_when_event_date_empty(): void
    {
        $now = $this->makeNow('2026-08-24 10:00:00', 'UTC');
        $this->assertFalse(WindowCheck::isPostingOpen('UTC', '', $now));
    }

    public function test_utc_minus_12_user_open_while_utc_is_next_day(): void
    {
        // UTC is 2026-08-25 01:00:00 — but the user is in UTC-12 (Etc/GMT+12),
        // where local time is still 2026-08-24 13:00:00 → window should be open.
        $now = $this->makeNow('2026-08-25 01:00:00', 'UTC');
        $this->assertTrue(WindowCheck::isPostingOpen('Etc/GMT+12', '2026-08-24', $now));
    }

    public function test_timezone_conversion_applied_correctly(): void
    {
        // UTC midnight → already 2026-08-24 in America/New_York (UTC-4 in summer).
        $now = $this->makeNow('2026-08-24 00:30:00', 'UTC');
        // America/New_York is UTC-4, so local time is 2026-08-23 20:30 → closed for 2026-08-24.
        $this->assertFalse(WindowCheck::isPostingOpen('America/New_York', '2026-08-24', $now));
    }

    // -------------------------------------------------------------------------
    // Late submissions (film photographers) — allow_late flag
    // -------------------------------------------------------------------------

    public function test_late_enabled_open_on_event_date(): void
    {
        $now = $this->makeNow('2026-08-24 10:00:00', 'UTC');
        $this->assertTrue(WindowCheck::isPostingOpen('UTC', '2026-08-24', $now, allowLate: true));
    }

    public function test_late_enabled_open_day_after_event(): void
    {
        $now = $this->makeNow('2026-08-25 12:00:00', 'UTC');
        $this->assertTrue(WindowCheck::isPostingOpen('UTC', '2026-08-24', $now, allowLate: true));
    }

    public function test_late_enabled_open_many_days_after_event(): void
    {
        $now = $this->makeNow('2026-09-01 08:00:00', 'UTC');
        $this->assertTrue(WindowCheck::isPostingOpen('UTC', '2026-08-24', $now, allowLate: true));
    }

    public function test_late_enabled_closed_before_event(): void
    {
        $now = $this->makeNow('2026-08-23 12:00:00', 'UTC');
        $this->assertFalse(WindowCheck::isPostingOpen('UTC', '2026-08-24', $now, allowLate: true));
    }

    public function test_late_disabled_closed_after_event(): void
    {
        // Default behaviour (allowLate omitted = false): window closed next day.
        $now = $this->makeNow('2026-08-25 00:00:00', 'UTC');
        $this->assertFalse(WindowCheck::isPostingOpen('UTC', '2026-08-24', $now));
    }
}
