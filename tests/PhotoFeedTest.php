<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for api/photos.php GET (US-5).
 * Path A — new code.
 */
final class PhotoFeedTest extends TestCase
{
    private string $photosFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];
        $this->photosFile = dirname(__DIR__) . '/api/photos.php';
    }

    private function get(array $query = []): array
    {
        return TestHelper::request($this->photosFile, 'GET', [], $query);
    }

    public function test_empty_result_returns_empty_array_and_null_cursor(): void
    {
        $user = TestHelper::createUser();
        $res = $this->get();

        $this->assertSame(200, $res['status']);
        $this->assertSame([], $res['json']['photos']);
        $this->assertNull($res['json']['next_cursor']);
    }

    public function test_initial_load_returns_newest_first(): void
    {
        $user = TestHelper::createUser();
        TestHelper::createPhoto(['user_id' => $user['id'], 'posted_at' => '2026-08-24 08:00:00', 'filename' => 'a.jpg']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'posted_at' => '2026-08-24 10:00:00', 'filename' => 'b.jpg']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'posted_at' => '2026-08-24 09:00:00', 'filename' => 'c.jpg']);

        $res = $this->get();

        $photos = $res['json']['photos'];
        $this->assertCount(3, $photos);
        $this->assertSame('b.jpg', $photos[0]['filename']); // newest first
        $this->assertSame('c.jpg', $photos[1]['filename']);
        $this->assertSame('a.jpg', $photos[2]['filename']);
    }

    public function test_after_param_returns_only_photos_after_timestamp(): void
    {
        $user = TestHelper::createUser();
        TestHelper::createPhoto(['user_id' => $user['id'], 'posted_at' => '2026-08-24 08:00:00', 'filename' => 'old.jpg']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'posted_at' => '2026-08-24 10:00:00', 'filename' => 'new.jpg']);

        $res = $this->get(['after' => '2026-08-24 09:00:00']);

        $photos = $res['json']['photos'];
        $this->assertCount(1, $photos);
        $this->assertSame('new.jpg', $photos[0]['filename']);
    }

    public function test_before_param_returns_older_photos(): void
    {
        $user = TestHelper::createUser();
        TestHelper::createPhoto(['user_id' => $user['id'], 'posted_at' => '2026-08-24 08:00:00', 'filename' => 'old.jpg']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'posted_at' => '2026-08-24 10:00:00', 'filename' => 'new.jpg']);

        $res = $this->get(['before' => '2026-08-24 09:00:00']);

        $photos = $res['json']['photos'];
        $this->assertCount(1, $photos);
        $this->assertSame('old.jpg', $photos[0]['filename']);
    }

    public function test_photos_from_multiple_users_interleaved_by_posted_at(): void
    {
        $u1 = TestHelper::createUser(['username' => 'alice', 'email' => 'a@example.com']);
        $u2 = TestHelper::createUser(['username' => 'bob', 'email' => 'b@example.com']);

        TestHelper::createPhoto(['user_id' => $u1['id'], 'posted_at' => '2026-08-24 09:00:00', 'filename' => 'a1.jpg']);
        TestHelper::createPhoto(['user_id' => $u2['id'], 'posted_at' => '2026-08-24 10:00:00', 'filename' => 'b1.jpg']);
        TestHelper::createPhoto(['user_id' => $u1['id'], 'posted_at' => '2026-08-24 11:00:00', 'filename' => 'a2.jpg']);

        $res = $this->get();

        $photos = $res['json']['photos'];
        $this->assertCount(3, $photos);
        $this->assertSame('a2.jpg', $photos[0]['filename']);
        $this->assertSame('b1.jpg', $photos[1]['filename']);
        $this->assertSame('a1.jpg', $photos[2]['filename']);
    }

    public function test_tiebreaker_same_timestamp_lower_id_comes_second(): void
    {
        $user = TestHelper::createUser();
        $ts   = '2026-08-24 10:00:00';
        // Insert two photos with same posted_at — first has lower id.
        TestHelper::createPhoto(['user_id' => $user['id'], 'posted_at' => $ts, 'filename' => 'first.jpg']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'posted_at' => $ts, 'filename' => 'second.jpg']);

        $res = $this->get();

        $photos = $res['json']['photos'];
        $this->assertCount(2, $photos);
        // DESC by id → higher id (second) appears first
        $this->assertSame('second.jpg', $photos[0]['filename']);
        $this->assertSame('first.jpg', $photos[1]['filename']);
    }

    public function test_response_includes_user_fields(): void
    {
        $user = TestHelper::createUser(['username' => 'carol', 'email' => 'carol@example.com']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'x.jpg']);

        $res  = $this->get();
        $photo = $res['json']['photos'][0];

        $this->assertArrayHasKey('username', $photo);
        $this->assertArrayHasKey('name', $photo);
        $this->assertArrayHasKey('description', $photo);
        $this->assertArrayHasKey('posted_at', $photo);
    }
}
