<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Application mailer — thin wrapper around PHPMailer.
 *
 * Reads SMTP_FROM from the environment; notification recipients are the admin
 * users in the database (users.is_admin = 1), not a hard-coded env address.
 * All send failures are logged to logs/mail.log and swallowed so that
 * registration always succeeds regardless of mail status.
 */
class Mailer
{
    /** Validation links expire after this long. */
    public const TOKEN_TTL = 72 * 3600;

    private string $smtpFrom;

    /** @var Mailer|null  Override injected in tests via setTestInstance(). */
    private static ?Mailer $testInstance = null;

    /**
     * Return the active Mailer instance.
     * Tests may inject a spy via setTestInstance(); production always gets a real Mailer.
     */
    public static function make(): self
    {
        return self::$testInstance ?? new self();
    }

    /**
     * Inject a test double. Call setTestInstance(null) in tearDown() to reset.
     */
    public static function setTestInstance(?Mailer $instance): void
    {
        self::$testInstance = $instance;
    }

    public function __construct()
    {
        $this->smtpFrom = (string) env('SMTP_FROM', 'noreply@localhost');
    }

    /**
     * Send the admin validation email for a newly registered user.
     *
     * The email is sent to every admin user (users.is_admin = 1) and contains
     * the new user's details plus a validation link bound to
     * id | email | expiry | nonce (single-use, see api/admin/validate.php):
     *   GET /api/admin/validate.php?id={id}&expires={ts}&token={hmac}
     *
     * Fail-closed on the signing secret: if APP_SECRET is missing, shorter
     * than 32 bytes, or the .env.example placeholder, no token is minted and
     * the email is skipped (the admin panel can still validate). Registration
     * itself always succeeds regardless of mail status.
     *
     * @param  array<string, mixed> $user  User row from the database.
     * @return void
     */
    public function sendAdminValidation(array $user): void
    {
        $id    = (int) $user['id'];
        $email = (string) $user['email'];

        try {
            $secret = self::requiredSecret();
        } catch (RuntimeException $e) {
            error_log(
                date('Y-m-d H:i:s') . " [Mailer] Cannot mint validation link for user {$id}: " . $e->getMessage() . "\n",
                3,
                dirname(__DIR__) . '/logs/mail.log'
            );
            return;
        }

        // Single-use nonce — stored on the user; cleared when the link is used.
        $nonce = bin2hex(random_bytes(16));
        db()->prepare('UPDATE users SET validation_nonce = :nonce WHERE id = :id')
            ->execute([':nonce' => $nonce, ':id' => $id]);

        $expires = time() + self::TOKEN_TTL;
        $token   = self::buildToken($id, $email, $nonce, $expires, $secret);

        // Absolute URL — email clients need a full link to make it clickable.
        $base = rtrim((string) env('APP_URL', 'https://aday.photoni.st'), '/');
        $link = "{$base}/api/admin/validate.php?id={$id}&expires={$expires}&token={$token}";

        $body = implode("\n", [
            "A new user has registered and requires validation.",
            "",
            "Username : {$user['username']}",
            "Name     : {$user['name']}",
            "Email    : {$user['email']}",
            "Timezone : {$user['timezone']}",
            "",
            "Validate: {$link}",
            "",
            "The link expires in 72 hours.",
        ]);

        $admins = $this->adminEmails();
        if ($admins === []) {
            error_log(
                date('Y-m-d H:i:s') . " [Mailer] No admin users in DB — skipping validation email for user {$id}.\n",
                3,
                dirname(__DIR__) . '/logs/mail.log'
            );
            return;
        }

        try {
            $mail = $this->buildMailer();
            foreach ($admins as $email) {
                $mail->addAddress($email);
            }
            $mail->Subject = "[Document Your Life] Validate user: {$user['username']}";
            $mail->Body    = $body;
            $mail->send();
        } catch (PHPMailerException $e) {
            error_log(
                date('Y-m-d H:i:s') . " [Mailer] Failed to send validation email for user {$id}: " . self::maskLog($e->getMessage()) . "\n",
                3,
                dirname(__DIR__) . '/logs/mail.log'
            );
        }
    }

    /**
     * Mask email addresses in log lines (audit m6) — PHPMailer exceptions
     * can include recipient addresses.
     */
    private static function maskLog(string $message): string
    {
        return preg_replace(
            '/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/',
            '***@***',
            $message
        ) ?? $message;
    }

    /**
     * Recipient list: every admin user's email address.
     *
     * @return list<string>
     */
    private function adminEmails(): array
    {
        $stmt = db()->query(
            "SELECT DISTINCT email FROM users WHERE is_admin = 1 AND email IS NOT NULL AND email != ''"
        );
        if ($stmt === false) {
            return [];
        }

        return array_map(
            static fn (array $row): string => trim((string) $row['email']),
            $stmt->fetchAll()
        );
    }

    /**
     * Fail-closed signing secret: refuses to mint tokens when APP_SECRET is
     * missing, shorter than 32 bytes, or still the .env.example placeholder.
     *
     * @return non-empty-string
     */
    public static function requiredSecret(): string
    {
        $secret = (string) env('APP_SECRET', '');

        if (strlen($secret) < 32) {
            throw new RuntimeException(
                'APP_SECRET is missing or shorter than 32 bytes — set a random secret in .env'
            );
        }
        if ($secret === 'change-me-to-a-random-64-char-hex-string') {
            throw new RuntimeException(
                'APP_SECRET is still the .env.example placeholder — set a random secret in .env'
            );
        }

        return $secret;
    }

    /**
     * HMAC token bound to id | email | expiry | nonce.
     *
     * Public so tests can mint links in the same format.
     */
    public static function buildToken(int $id, string $email, string $nonce, int $expires, string $secret): string
    {
        return hash_hmac('sha256', "{$id}|{$email}|{$expires}|{$nonce}", $secret);
    }

    /**
     * Send the post-event wrap-up email to a list of participant addresses.
     *
     * @param  list<string> $recipients
     * @return bool True only when PHPMailer accepted + sent the message.
     */
    public function sendWrapUp(array $recipients): bool
    {
        $base = rtrim((string) env('APP_URL', 'https://aday.photoni.st'), '/');

        $body = implode("\n", [
            "Thanks for taking part in Document Your Life.",
            "",
            "The gallery is live — relive the day and see every photographer's",
            "take on the same 24 hours:",
            "",
            "  {$base}/",
            "",
            "Each photo has its own page — find yours and share it:",
            "  {$base}/embed",
            "",
            "See you next event.",
        ]);

        try {
            $mail = $this->buildMailer();
            foreach ($recipients as $email) {
                $mail->addAddress($email);
            }
            $mail->Subject = '[Document Your Life] Your photos are live';
            $mail->Body    = $body;
            $mail->send();
            return true;
        } catch (PHPMailerException $e) {
            error_log(
                date('Y-m-d H:i:s') . " [Mailer] Failed to send wrap-up email: " . self::maskLog($e->getMessage()) . "\n",
                3,
                dirname(__DIR__) . '/logs/mail.log'
            );
            return false;
        }
    }

    /**
     * Build and configure a PHPMailer instance.
     */
    protected function buildMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = 'localhost';
        $mail->Port       = 25;
        $mail->SMTPAuth   = false;
        $mail->CharSet    = PHPMailer::CHARSET_UTF8;
        $mail->setFrom($this->smtpFrom);
        return $mail;
    }
}