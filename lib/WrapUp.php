<?php

declare(strict_types=1);

/**
 * Post-event wrap-up notification.
 *
 * Sends one email per event to all participants (validated users) when the
 * gallery is finished, guarded by a settings row so it only fires once.
 * Used by the admin panel button and by scripts/send_wrapup.php (cron).
 */
final class WrapUp
{
    /**
     * Send the wrap-up email if it has not been sent for the current event run.
     *
     * @return array{status: string, count: int}
     *   status: 'sent' | 'already_sent' | 'no_recipients'
     */
    public static function notify(): array
    {
        $stmt  = db()->prepare('SELECT value FROM settings WHERE key = :key');
        $stmt->execute([':key' => 'wrapup_sent']);
        if ($stmt->fetch() !== false) {
            return ['status' => 'already_sent', 'count' => 0];
        }

        $recipients = WrapUp::participantEmails();
        if ($recipients === []) {
            return ['status' => 'no_recipients', 'count' => 0];
        }

        Mailer::make()->sendWrapUp($recipients);

        db()->prepare(
            'INSERT OR REPLACE INTO settings (key, value) VALUES (:key, :value)'
        )->execute([':key' => 'wrapup_sent', ':value' => date('Y-m-d H:i:s')]);

        return ['status' => 'sent', 'count' => count($recipients)];
    }

    /**
     * @return list<string>  Distinct emails of validated participants.
     */
    private static function participantEmails(): array
    {
        $stmt = db()->query(
            "SELECT DISTINCT email FROM users WHERE status = 'validated' AND email IS NOT NULL AND email != ''"
        );
        if ($stmt === false) {
            return [];
        }

        return array_map(
            static fn (array $row): string => trim((string) $row['email']),
            $stmt->fetchAll()
        );
    }
}