<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Application mailer — thin wrapper around PHPMailer.
 *
 * Reads SMTP_FROM and ADMIN_EMAIL from the environment.
 * All send failures are logged to logs/mail.log and swallowed
 * so that registration always succeeds regardless of mail status.
 */
class Mailer
{
    private string $smtpFrom;
    private string $adminEmail;

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
        $this->smtpFrom   = (string) env('SMTP_FROM', 'noreply@localhost');
        $this->adminEmail = (string) env('ADMIN_EMAIL', 'admin@localhost');
    }

    /**
     * Send the admin validation email for a newly registered user.
     *
     * The email body contains the user's details and a validation link using
     * an HMAC token: GET /api/admin/validate.php?id={id}&token={hmac}
     *
     * @param  array<string, mixed> $user  User row from the database.
     * @return void
     */
    public function sendAdminValidation(array $user): void
    {
        $id    = (int) $user['id'];
        $token = hash_hmac('sha256', (string) $id, (string) env('APP_SECRET', ''));

        // Absolute URL — email clients need a full link to make it clickable.
        $base = rtrim((string) env('APP_URL', 'https://aday.photoni.st'), '/');
        $link = "{$base}/api/admin/validate.php?id={$id}&token={$token}";

        $body = implode("\n", [
            "A new user has registered and requires validation.",
            "",
            "Username : {$user['username']}",
            "Name     : {$user['name']}",
            "Email    : {$user['email']}",
            "Timezone : {$user['timezone']}",
            "",
            "Validate: {$link}",
        ]);

        try {
            $mail = $this->buildMailer();
            $mail->addAddress($this->adminEmail);
            $mail->Subject = "[Document Your Life] Validate user: {$user['username']}";
            $mail->Body    = $body;
            $mail->send();
        } catch (PHPMailerException $e) {
            error_log(
                date('Y-m-d H:i:s') . " [Mailer] Failed to send validation email for user {$id}: " . $e->getMessage() . "\n",
                3,
                dirname(__DIR__) . '/logs/mail.log'
            );
        }
    }

    /**
     * Build and configure a PHPMailer instance.
     */
    private function buildMailer(): PHPMailer
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
