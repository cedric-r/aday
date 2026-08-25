<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Captures the addresses a Mailer would send to, without touching SMTP.
 */
final class RecordingPhpMailer extends PHPMailer
{
    /** @var list<string> */
    public $to = [];
    public bool $sent = false;

    public function addAddress($address, $name = ''): bool
    {
        $this->to[] = $address;
        return true;
    }

    public function send(): bool
    {
        $this->sent = true;
        return true;
    }
}

/**
 * Mailer subclass that returns a recording PHPMailer instead of a real SMTP one.
 */
final class RecordingMailer extends Mailer
{
    public RecordingPhpMailer $lastMail;
    public bool $built = false;

    protected function buildMailer(): PHPMailer
    {
        $this->built = true;
        return $this->lastMail = new RecordingPhpMailer();
    }
}

/**
 * Tests that the registration notification goes to the DB admin users.
 */
final class MailerTest extends TestCase
{
    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        Mailer::setTestInstance(null);
    }

    public function test_sends_to_all_admin_users_in_db(): void
    {
        TestHelper::createUser([
            'username' => 'admin1', 'email' => 'admin1@example.com', 'is_admin' => 1,
        ]);
        TestHelper::createUser([
            'username' => 'admin2', 'email' => 'admin2@example.com', 'is_admin' => 1,
        ]);
        TestHelper::createUser([
            'username' => 'normal', 'email' => 'normal@example.com', 'is_admin' => 0,
        ]);

        $mailer = new RecordingMailer();
        Mailer::setTestInstance($mailer);

        $mailer->sendAdminValidation([
            'id'       => 42,
            'username' => 'newbie',
            'name'     => 'Newbie',
            'email'    => 'newbie@example.com',
            'timezone' => 'UTC',
        ]);

        sort($mailer->lastMail->to);
        $this->assertSame(['admin1@example.com', 'admin2@example.com'], $mailer->lastMail->to);
        $this->assertTrue($mailer->lastMail->sent);
        $this->assertNotContains('normal@example.com', $mailer->lastMail->to);
        $this->assertNotContains('newbie@example.com', $mailer->lastMail->to);
    }

    public function test_does_not_send_when_no_admins(): void
    {
        $mailer = new RecordingMailer();
        Mailer::setTestInstance($mailer);

        $mailer->sendAdminValidation([
            'id'       => 42,
            'username' => 'newbie',
            'name'     => 'Newbie',
            'email'    => 'newbie@example.com',
            'timezone' => 'UTC',
        ]);

        $this->assertFalse($mailer->lastMail->sent ?? false);
    }

    public function test_subject_contains_username_and_absolute_link(): void
    {
        TestHelper::createUser([
            'username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1,
        ]);

        $mailer = new RecordingMailer();
        $mailer->sendAdminValidation([
            'id'       => 7,
            'username' => 'bob',
            'name'     => 'Bob',
            'email'    => 'bob@example.com',
            'timezone' => 'UTC',
        ]);

        $this->assertSame('[Document Your Life] Validate user: bob', $mailer->lastMail->Subject);
        $this->assertStringContainsString(
            'https://aday.photoni.st/api/admin/validate.php?id=7&',
            $mailer->lastMail->Body
        );
    }

    // -----------------------------------------------------------------------
    // Wrap-up email — sent once, to validated participants
    // -----------------------------------------------------------------------

    public function test_wrapup_sends_to_validated_participants_once_and_guards(): void
    {
        TestHelper::createUser(['username' => 'u1', 'email' => 'u1@example.com', 'status' => 'validated']);
        TestHelper::createUser(['username' => 'u2', 'email' => 'u2@example.com', 'status' => 'validated']);
        TestHelper::createUser(['username' => 'u3', 'email' => 'u3@example.com', 'status' => 'pending']);

        $mailer = new RecordingMailer();
        Mailer::setTestInstance($mailer);

        $first = WrapUp::notify();
        $this->assertSame('sent', $first['status']);
        $this->assertSame(2, $first['count']);

        sort($mailer->lastMail->to);
        $this->assertSame(['u1@example.com', 'u2@example.com'], $mailer->lastMail->to);
        $this->assertSame('[Document Your Life] Your photos are live', $mailer->lastMail->Subject);

        // Second call is a no-op.
        $second = WrapUp::notify();
        $this->assertSame('already_sent', $second['status']);
    }

    public function test_wrapup_no_participants_returns_no_recipients(): void
    {
        // Only a pending user — not validated, so no recipients.
        TestHelper::createUser(['username' => 'p1', 'email' => 'p1@example.com', 'status' => 'pending']);

        $result = WrapUp::notify();
        $this->assertSame('no_recipients', $result['status']);
    }
}