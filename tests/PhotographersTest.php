<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for api/photographers.php (US-6).
 * Path A — new code.
 */
final class PhotographersTest extends TestCase
{
    private string $photographersFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];
        $this->photographersFile = dirname(__DIR__) . '/api/photographers.php';
    }

    private function get(array $query = []): array
    {
        return TestHelper::request($this->photographersFile, 'GET', [], $query);
    }

    public function test_list_returns_only_validated_users(): void
    {
        TestHelper::createUser(['username' => 'alice', 'name' => 'Alice', 'email' => 'alice@example.com', 'status' => 'validated']);
        TestHelper::createUser(['username' => 'pending', 'name' => 'Pending', 'email' => 'pending@example.com', 'status' => 'pending']);

        $res = $this->get();

        $this->assertSame(200, $res['status']);
        $usernames = array_column($res['json'], 'username');
        $this->assertContains('alice', $usernames);
        $this->assertNotContains('pending', $usernames);
    }

    public function test_list_ordered_case_insensitive_by_name(): void
    {
        TestHelper::createUser(['username' => 'charlie', 'name' => 'charlie', 'email' => 'c@example.com']);
        TestHelper::createUser(['username' => 'alice', 'name' => 'Alice', 'email' => 'a@example.com']);
        TestHelper::createUser(['username' => 'bob', 'name' => 'bob', 'email' => 'b@example.com']);

        $res = $this->get();

        $names = array_column($res['json'], 'name');
        $this->assertSame('Alice', $names[0]);
        $this->assertSame('bob', $names[1]);
        $this->assertSame('charlie', $names[2]);
    }

    public function test_photo_count_is_correct(): void
    {
        $user = TestHelper::createUser(['username' => 'alice', 'email' => 'alice@example.com']);
        TestHelper::createPhoto(['user_id' => $user['id']]);
        TestHelper::createPhoto(['user_id' => $user['id']]);

        $res = $this->get();

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['json']);
        $this->assertSame(2, (int) $res['json'][0]['photo_count']);
    }

    public function test_single_user_returns_profile_and_photos(): void
    {
        $user = TestHelper::createUser(['username' => 'alice', 'email' => 'alice@example.com']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'p1.jpg', 'posted_at' => '2026-08-24 09:00:00']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'p2.jpg', 'posted_at' => '2026-08-24 10:00:00']);

        $res = $this->get(['username' => 'alice']);

        $this->assertSame(200, $res['status']);
        $this->assertSame('alice', $res['json']['username']);
        $this->assertIsArray($res['json']['photos']);
        $this->assertCount(2, $res['json']['photos']);
        // Ordered posted_at DESC → newest first
        $this->assertSame('p2.jpg', $res['json']['photos'][0]['filename']);
        $this->assertSame('p1.jpg', $res['json']['photos'][1]['filename']);
    }

    public function test_pending_user_returns_404(): void
    {
        TestHelper::createUser(['username' => 'ghost', 'email' => 'ghost@example.com', 'status' => 'pending']);

        $res = $this->get(['username' => 'ghost']);
        $this->assertSame(404, $res['status']);
    }

    public function test_unknown_username_returns_404(): void
    {
        $res = $this->get(['username' => 'nobody']);
        $this->assertSame(404, $res['status']);
    }

    public function test_user_with_zero_photos_appears_in_list_with_count_zero(): void
    {
        TestHelper::createUser(['username' => 'empty', 'email' => 'empty@example.com']);

        $res = $this->get();

        $this->assertCount(1, $res['json']);
        $this->assertSame(0, (int) $res['json'][0]['photo_count']);
    }

    public function test_single_user_with_no_photos_returns_empty_array(): void
    {
        TestHelper::createUser(['username' => 'empty', 'email' => 'empty@example.com']);

        $res = $this->get(['username' => 'empty']);

        $this->assertSame(200, $res['status']);
        $this->assertSame([], $res['json']['photos']);
    }
}
