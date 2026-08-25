<?php

declare(strict_types=1);

/**
 * Post-event wrap-up notification.
 *
 * Sends one email per event to all participants (validated users) when the
 * gallery is finished, guarded by a settings row so it only fires once.
 * Used by the admin panel button and by scripts/send_wrapup.php (cron).
 *
 * Concurrency: the settings row is claimed FIRST (INSERT OR IGNORE — atomic
 * in SQLite); whoever wins the insert is the only one allowed to send. If the
 * send fails, the claim is revoked so it can be retried instead of silently
 * marking the event done.
 */
final class WrapUp
{
    /**
     * Send the wrap-up email if it has not been sent for the current event run.
     *
     * @return array{status: 'sent'|'already_sent'|'no_recipients'|'failed', count: int}
     */
    public static function notify(): array
    {
        // Claim first — prevents the admin-button + cron double-send race.
        $claim = db()->prepare(
            'INSERT OR IGNORE INTO settings (key, value) VALUES (:key, :value)'
        );
        $claim->execute([':key' => 'wrapup_sent', ':value' => date('Y-m-d H:i:s')]);
        if ($claim->rowCount() === 0) {
            return ['status' => 'already_sent', 'count' => 0];
        }

        $recipients = WrapUp::participantEmails();
        if ($recipients === []) {
            // No one to email yet — release the claim so a later attempt can send.
            self::releaseClaim();
            return ['status' => 'no_recipients', 'count' => 0];
        }

        if (!Mailer::make()->sendWrapUp($recipients)) {
            // Relay failure — release the claim and report the failure so the
            // admin/cron can retry instead of silently losing the email.
            self::releaseClaim();
            return ['status' => 'failed', 'count' => count($recipients)];
        }

        return ['status' => 'sent', 'count' => count($recipients)];
    }

    private static function releaseClaim(): void
    {
        db()->prepare('DELETE FROM settings WHERE key = :key')->execute([':key' => 'wrapup_sent']);
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