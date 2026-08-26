<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test double for Mailer — records broadcast recipients without SMTP.
 */
class SpyBroadcastMailer extends Mailer
{
    /** @var list<array{recipients: list<string>, subject: string, body: string}> */
    public array $broadcasts = [];

    public function __construct()
    {
        // Skip parent — no SMTP config in tests.
    }

    public function sendBroadcast(array $recipients, string $subject, string $body): array
    {
        $this->broadcasts[] = ['recipients' => $recipients, 'subject' => $subject, 'body' => $body];
        return ['sent' => count($recipients), 'failed' => 0];
    }
}

/**
 * Tests for api/admin/email.php — broadcast emails to registered users.
 */
final class EmailAdminTest extends TestCase
{
    private string $emailFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];
        $this->emailFile = dirname(__DIR__) . '/api/admin/email.php';
    }

    protected function tearDown(): void
    {
        Mailer::setTestInstance(null);
    }

    private function loginAsAdmin(string $username = 'admin'): array
    {
        $admin = TestHelper::createUser(['username' => $username, 'email' => 'admin@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];
        return $admin;
    }

    // -----------------------------------------------------------------------
    // Auth guard
    // -----------------------------------------------------------------------

    public function test_email_endpoint_blocks_unauthenticated(): void
    {
        $res = TestHelper::request($this->emailFile);
        $this->assertSame(403, $res['status']);
    }

    public function test_email_endpoint_blocks_non_admin(): void
    {
        $user = TestHelper::createUser(['username' => 'nobody', 'email' => 'nobody@example.com']);
        $_SESSION['user_id'] = $user['id'];

        $res = TestHelper::request($this->emailFile);
        $this->assertSame(403, $res['status']);
    }

    // -----------------------------------------------------------------------
    // GET — recipient preview count
    // -----------------------------------------------------------------------

    public function test_get_validated_scope_excludes_pending_and_admins(): void
    {
        $this->loginAsAdmin();
        TestHelper::createUser(['username' => 'valid1', 'email' => 'v1@example.com', 'status' => 'validated']);
        TestHelper::createUser(['username' => 'valid2', 'email' => 'v2@example.com', 'status' => 'validated']);
        TestHelper::createUser(['username' => 'pend1', 'email' => 'p1@example.com', 'status' => 'pending']);

        $res = TestHelper::request($this->emailFile, 'GET', [], ['scope' => 'validated']);

        $this->assertSame(200, $res['status']);
        $this->assertSame('validated', $res['json']['scope']);
        $this->assertSame(2, $res['json']['recipients']); // admin + pending excluded
    }

    public function test_get_all_scope_includes_pending_but_not_admins(): void
    {
        $this->loginAsAdmin();
        TestHelper::createUser(['username' => 'valid1', 'email' => 'v1@example.com', 'status' => 'validated']);
        TestHelper::createUser(['username' => 'pend1', 'email' => 'p1@example.com', 'status' => 'pending']);

        $res = TestHelper::request($this->emailFile, 'GET', [], ['scope' => 'all']);

        $this->assertSame(200, $res['status']);
        $this->assertSame('all', $res['json']['scope']);
        $this->assertSame(2, $res['json']['recipients']); // admin excluded, pending included
    }

    public function test_get_defaults_to_validated_scope(): void
    {
        $this->loginAsAdmin();
        TestHelper::createUser(['username' => 'valid1', 'email' => 'v1@example.com', 'status' => 'validated']);
        TestHelper::createUser(['username' => 'pend1', 'email' => 'p1@example.com', 'status' => 'pending']);

        $res = TestHelper::request($this->emailFile, 'GET', [], []);

        $this->assertSame('validated', $res['json']['scope']);
        $this->assertSame(1, $res['json']['recipients']);
    }

    public function test_get_dedupes_same_email(): void
    {
        $this->loginAsAdmin();
        // email is UNIQUE in users, but DISTINCT still guards the query.
        TestHelper::createUser(['username' => 'a', 'email' => 'same@example.com', 'status' => 'validated']);

        $res = TestHelper::request($this->emailFile, 'GET', [], ['scope' => 'validated']);

        $this->assertSame(1, $res['json']['recipients']);
    }

    // -----------------------------------------------------------------------
    // POST — validation
    // -----------------------------------------------------------------------

    public function test_post_requires_subject(): void
    {
        $this->loginAsAdmin();
        TestHelper::createUser(['username' => 'valid1', 'email' => 'v1@example.com', 'status' => 'validated']);

        $res = TestHelper::request($this->emailFile, 'POST', ['subject' => '', 'body' => 'hello']);

        $this->assertSame(422, $res['status']);
    }

    public function test_post_requires_body(): void
    {
        $this->loginAsAdmin();
        TestHelper::createUser(['username' => 'valid1', 'email' => 'v1@example.com', 'status' => 'validated']);

        $res = TestHelper::request($this->emailFile, 'POST', ['subject' => 'Reminder', 'body' => '']);

        $this->assertSame(422, $res['status']);
    }

    public function test_post_rejects_subject_with_newline(): void
    {
        $this->loginAsAdmin();
        TestHelper::createUser(['username' => 'valid1', 'email' => 'v1@example.com', 'status' => 'validated']);

        $res = TestHelper::request($this->emailFile, 'POST', ['subject' => "Reminder\nBcc: evil@x.com", 'body' => 'hello']);

        $this->assertSame(422, $res['status']);
    }

    public function test_post_rejects_subject_over_200(): void
    {
        $this->loginAsAdmin();
        TestHelper::createUser(['username' => 'valid1', 'email' => 'v1@example.com', 'status' => 'validated']);

        $res = TestHelper::request($this->emailFile, 'POST', ['subject' => str_repeat('x', 201), 'body' => 'hello']);

        $this->assertSame(422, $res['status']);
    }

    public function test_post_rejects_body_over_5000(): void
    {
        $this->loginAsAdmin();
        TestHelper::createUser(['username' => 'valid1', 'email' => 'v1@example.com', 'status' => 'validated']);

        $res = TestHelper::request($this->emailFile, 'POST', ['subject' => 'Reminder', 'body' => str_repeat('x', 5001)]);

        $this->assertSame(422, $res['status']);
    }

    // -----------------------------------------------------------------------
    // POST — sending
    // -----------------------------------------------------------------------

    public function test_post_sends_to_validated_recipients(): void
    {
        $this->loginAsAdmin();
        TestHelper::createUser(['username' => 'valid1', 'email' => 'v1@example.com', 'status' => 'validated']);
        TestHelper::createUser(['username' => 'valid2', 'email' => 'v2@example.com', 'status' => 'validated']);
        TestHelper::createUser(['username' => 'pend1', 'email' => 'p1@example.com', 'status' => 'pending']);

        $spy = new SpyBroadcastMailer();
        Mailer::setTestInstance($spy);

        $res = TestHelper::request($this->emailFile, 'POST', [
            'subject' => 'Event reminder',
            'body'    => 'The event is on the 14th.',
            'scope'   => 'validated',
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertSame('sent', $res['json']['status']);
        $this->assertSame(2, $res['json']['sent']);
        $this->assertCount(1, $spy->broadcasts);
        $this->assertSame(['v1@example.com', 'v2@example.com'], $spy->broadcasts[0]['recipients']);
        $this->assertSame('Event reminder', $spy->broadcasts[0]['subject']);
        $this->assertSame('The event is on the 14th.', $spy->broadcasts[0]['body']);
    }

    public function test_post_sends_to_all_registered_when_scope_all(): void
    {
        $this->loginAsAdmin();
        TestHelper::createUser(['username' => 'valid1', 'email' => 'v1@example.com', 'status' => 'validated']);
        TestHelper::createUser(['username' => 'pend1', 'email' => 'p1@example.com', 'status' => 'pending']);

        $spy = new SpyBroadcastMailer();
        Mailer::setTestInstance($spy);

        $res = TestHelper::request($this->emailFile, 'POST', [
            'subject' => 'Hi all',
            'body'    => 'Reminder for everyone.',
            'scope'   => 'all',
        ]);

        $this->assertSame('sent', $res['json']['status']);
        $this->assertSame(2, $res['json']['sent']);
        $this->assertSame(['v1@example.com', 'p1@example.com'], $spy->broadcasts[0]['recipients']);
    }

    public function test_post_no_recipients_returns_no_recipients(): void
    {
        $this->loginAsAdmin();

        $spy = new SpyBroadcastMailer();
        Mailer::setTestInstance($spy);

        $res = TestHelper::request($this->emailFile, 'POST', ['subject' => 'x', 'body' => 'y']);

        $this->assertSame(200, $res['status']);
        $this->assertSame('no_recipients', $res['json']['status']);
        $this->assertCount(0, $spy->broadcasts);
    }

    public function test_post_defaults_to_validated_scope(): void
    {
        $this->loginAsAdmin();
        TestHelper::createUser(['username' => 'valid1', 'email' => 'v1@example.com', 'status' => 'validated']);
        TestHelper::createUser(['username' => 'pend1', 'email' => 'p1@example.com', 'status' => 'pending']);

        $spy = new SpyBroadcastMailer();
        Mailer::setTestInstance($spy);

        $res = TestHelper::request($this->emailFile, 'POST', ['subject' => 'x', 'body' => 'y']);

        $this->assertSame(1, $res['json']['sent']);
        $this->assertSame(['v1@example.com'], $spy->broadcasts[0]['recipients']);
    }
}
