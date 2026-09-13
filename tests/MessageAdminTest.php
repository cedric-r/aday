<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for the Notifications feature:
 *   - api/messages.php        GET  (validated users read messages)
 *   - api/admin/messages.php  GET/POST/DELETE (admin composes + retracts)
 */
final class MessageAdminTest extends TestCase
{
    private string $messagesFile;
    private string $adminFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];
        $this->messagesFile = dirname(__DIR__) . '/api/messages.php';
        $this->adminFile    = dirname(__DIR__) . '/api/admin/messages.php';
    }

    private function loginAsAdmin(): array
    {
        $admin = TestHelper::createUser(['username' => 'msgadmin', 'email' => 'msgadmin@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];
        return $admin;
    }

    private function seedMessage(string $subject = 'Hello', string $body = 'Body text'): int
    {
        db()->prepare('INSERT INTO messages (subject, body) VALUES (:s, :b)')
            ->execute([':s' => $subject, ':b' => $body]);
        return (int) db()->lastInsertId();
    }

    // -----------------------------------------------------------------------
    // Read feed — api/messages.php
    // -----------------------------------------------------------------------

    public function test_user_feed_requires_auth(): void
    {
        $res = TestHelper::request($this->messagesFile, 'GET');
        $this->assertSame(401, $res['status']);
    }

    public function test_user_feed_requires_validated_user(): void
    {
        $user = TestHelper::createUser(['username' => 'pending', 'email' => 'p@example.com', 'status' => 'pending']);
        $_SESSION['user_id'] = $user['id'];

        $res = TestHelper::request($this->messagesFile, 'GET');
        $this->assertSame(403, $res['status']);
    }

    public function test_validated_user_reads_messages_newest_first(): void
    {
        $user = TestHelper::createUser(['username' => 'reader', 'email' => 'reader@example.com', 'status' => 'validated']);
        $_SESSION['user_id'] = $user['id'];

        db()->prepare('INSERT INTO messages (subject, body, created_at) VALUES (:s, :b, :c)')
            ->execute([':s' => 'Older', ':b' => 'first', ':c' => '2026-09-01 10:00:00']);
        db()->prepare('INSERT INTO messages (subject, body, created_at) VALUES (:s, :b, :c)')
            ->execute([':s' => 'Newer', ':b' => 'second', ':c' => '2026-09-02 10:00:00']);

        $res = TestHelper::request($this->messagesFile, 'GET');

        $this->assertSame(200, $res['status']);
        $this->assertCount(2, $res['json']['messages']);
        $this->assertSame('Newer', $res['json']['messages'][0]['subject']);
        $this->assertSame('Older', $res['json']['messages'][1]['subject']);
    }

    public function test_user_feed_is_empty_with_no_messages(): void
    {
        $user = TestHelper::createUser(['username' => 'nomsg', 'email' => 'nomsg@example.com']);
        $_SESSION['user_id'] = $user['id'];

        $res = TestHelper::request($this->messagesFile, 'GET');
        $this->assertSame(200, $res['status']);
        $this->assertSame([], $res['json']['messages']);
    }

    // -----------------------------------------------------------------------
    // Admin auth guard
    // -----------------------------------------------------------------------

    public function test_admin_endpoint_blocks_unauthenticated(): void
    {
        $res = TestHelper::request($this->adminFile, 'GET');
        $this->assertSame(403, $res['status']);
    }

    public function test_admin_endpoint_blocks_non_admin(): void
    {
        $user = TestHelper::createUser(['username' => 'plain', 'email' => 'plain@example.com']);
        $_SESSION['user_id'] = $user['id'];

        $res = TestHelper::request($this->adminFile, 'GET');
        $this->assertSame(403, $res['status']);
    }

    // -----------------------------------------------------------------------
    // Admin GET — list + recipient count
    // -----------------------------------------------------------------------

    public function test_admin_get_returns_messages_and_recipient_count(): void
    {
        $this->loginAsAdmin();
        TestHelper::createUser(['username' => 'v1', 'email' => 'v1@example.com', 'status' => 'validated']);
        TestHelper::createUser(['username' => 'v2', 'email' => 'v2@example.com', 'status' => 'validated']);
        TestHelper::createUser(['username' => 'p1', 'email' => 'p1@example.com', 'status' => 'pending']);
        $this->seedMessage('Announcement', 'Hi all');

        $res = TestHelper::request($this->adminFile, 'GET');

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['json']['messages']);
        $this->assertSame('Announcement', $res['json']['messages'][0]['subject']);
        // validated non-admin users only (admin + pending excluded)
        $this->assertSame(2, $res['json']['recipients']);
    }

    // -----------------------------------------------------------------------
    // Admin POST — validation
    // -----------------------------------------------------------------------

    public function test_post_requires_subject(): void
    {
        $this->loginAsAdmin();
        $res = TestHelper::request($this->adminFile, 'POST', ['subject' => '', 'body' => 'x']);
        $this->assertSame(422, $res['status']);
    }

    public function test_post_requires_body(): void
    {
        $this->loginAsAdmin();
        $res = TestHelper::request($this->adminFile, 'POST', ['subject' => 'Hello', 'body' => '']);
        $this->assertSame(422, $res['status']);
    }

    public function test_post_rejects_newline_in_subject(): void
    {
        $this->loginAsAdmin();
        $res = TestHelper::request($this->adminFile, 'POST', ['subject' => "Bad\nHeader", 'body' => 'x']);
        $this->assertSame(422, $res['status']);
    }

    public function test_post_rejects_overlong_subject(): void
    {
        $this->loginAsAdmin();
        $res = TestHelper::request($this->adminFile, 'POST', ['subject' => str_repeat('x', 201), 'body' => 'x']);
        $this->assertSame(422, $res['status']);
    }

    public function test_post_rejects_overlong_body(): void
    {
        $this->loginAsAdmin();
        $res = TestHelper::request($this->adminFile, 'POST', ['subject' => 'ok', 'body' => str_repeat('x', 5001)]);
        $this->assertSame(422, $res['status']);
    }

    // -----------------------------------------------------------------------
    // Admin POST — creation
    // -----------------------------------------------------------------------

    public function test_post_creates_message_visible_to_users(): void
    {
        $admin = $this->loginAsAdmin();
        TestHelper::createUser(['username' => 'reader', 'email' => 'reader@example.com', 'status' => 'validated']);

        $res = TestHelper::request($this->adminFile, 'POST', [
            'subject' => 'Event reminder',
            'body'    => 'The event is on the 16th.',
        ]);

        $this->assertSame(201, $res['status']);
        $this->assertSame('Event reminder', $res['json']['notification']['subject']);

        // Stored with the author recorded.
        $row = db()->query('SELECT subject, body, created_by FROM messages')->fetch();
        $this->assertSame('Event reminder', $row['subject']);
        $this->assertSame('The event is on the 16th.', $row['body']);
        $this->assertSame((int) $admin['id'], (int) $row['created_by']);

        // And readable by a validated user.
        $_SESSION['user_id'] = (int) db()->query("SELECT id FROM users WHERE username='reader'")->fetchColumn();
        $feed = TestHelper::request($this->messagesFile, 'GET');
        $this->assertSame(200, $feed['status']);
        $this->assertCount(1, $feed['json']['messages']);
        $this->assertSame('Event reminder', $feed['json']['messages'][0]['subject']);
    }

    // -----------------------------------------------------------------------
    // Admin DELETE — retract
    // -----------------------------------------------------------------------

    public function test_delete_removes_message(): void
    {
        $this->loginAsAdmin();
        $id = $this->seedMessage();

        $res = TestHelper::request($this->adminFile, 'DELETE', [], ['id' => $id]);

        $this->assertSame(200, $res['status']);
        $this->assertSame($id, $res['json']['id']);
        $this->assertSame(0, (int) db()->query('SELECT COUNT(*) FROM messages')->fetchColumn());
    }

    public function test_delete_requires_id(): void
    {
        $this->loginAsAdmin();
        $res = TestHelper::request($this->adminFile, 'DELETE', [], []);
        $this->assertSame(400, $res['status']);
    }

    public function test_delete_unknown_returns_404(): void
    {
        $this->loginAsAdmin();
        $res = TestHelper::request($this->adminFile, 'DELETE', [], ['id' => 9999]);
        $this->assertSame(404, $res['status']);
    }

    public function test_delete_requires_admin(): void
    {
        $user = TestHelper::createUser(['username' => 'plain2', 'email' => 'plain2@example.com']);
        $_SESSION['user_id'] = $user['id'];
        $id = $this->seedMessage();

        $res = TestHelper::request($this->adminFile, 'DELETE', [], ['id' => $id]);

        $this->assertSame(403, $res['status']);
        $this->assertSame(1, (int) db()->query('SELECT COUNT(*) FROM messages')->fetchColumn());
    }
}
