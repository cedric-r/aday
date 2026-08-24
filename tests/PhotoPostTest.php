<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for api/photos.php POST (US-4).
 * Path A — new code.
 */
final class PhotoPostTest extends TestCase
{
    private string $photosFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];
        $this->photosFile = dirname(__DIR__) . '/api/photos.php';
    }

    public function test_unauthenticated_returns_401(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $res = TestHelper::request($this->photosFile, 'POST');
        $this->assertSame(401, $res['status']);
    }

    public function test_pending_user_returns_403(): void
    {
        $user = TestHelper::createUser(['username' => 'pending', 'email' => 'p@example.com', 'status' => 'pending']);
        $_SESSION['user_id'] = $user['id'];

        $res = TestHelper::request($this->photosFile, 'POST');
        $this->assertSame(403, $res['status']);
    }

    public function test_event_not_configured_returns_503(): void
    {
        $user = TestHelper::createUser(['username' => 'alice', 'email' => 'alice@example.com']);
        $_SESSION['user_id'] = $user['id'];

        $res = TestHelper::request($this->photosFile, 'POST');
        $this->assertSame(503, $res['status']);
    }

    public function test_window_closed_returns_403(): void
    {
        $user = TestHelper::createUser(['username' => 'alice', 'email' => 'alice@example.com']);
        $_SESSION['user_id'] = $user['id'];
        // Set event date to yesterday → window closed.
        TestHelper::setSetting('event_date', date('Y-m-d', strtotime('-1 day')));

        $res = TestHelper::request($this->photosFile, 'POST');
        $this->assertSame(403, $res['status']);
        $this->assertStringContainsString('closed', $res['json']['error']);
    }

    public function test_valid_post_with_fake_file_returns_201_and_stores_in_db(): void
    {
        $user = TestHelper::createUser(['username' => 'alice', 'email' => 'alice@example.com']);
        $_SESSION['user_id'] = $user['id'];
        TestHelper::setSetting('event_date', date('Y-m-d'));

        // Create a real temp file with JPEG magic bytes.
        $tmp = tempnam(sys_get_temp_dir(), 'aday_test_');
        file_put_contents($tmp, "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 16));

        $fakeFiles = [
            'photo' => [
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tmp),
                'tmp_name' => $tmp,
                'name'     => 'test.jpg',
            ],
        ];

        $res = TestHelper::request(
            $this->photosFile,
            'POST',
            ['description' => 'A test photo'],
            [],
            [],
            $fakeFiles
        );

        // Clean up uploaded file if it exists.
        $uploadDir = dirname(__DIR__) . '/uploads/alice/';
        if (is_dir($uploadDir)) {
            foreach (glob($uploadDir . '*.jpg') ?: [] as $f) {
                unlink($f);
            }
        }

        $this->assertSame(201, $res['status']);
        $this->assertArrayHasKey('id', $res['json']);
        $this->assertArrayHasKey('filename', $res['json']);

        $row = db()->query('SELECT * FROM photos LIMIT 1')->fetch();
        $this->assertNotFalse($row);
        $this->assertSame((int) $user['id'], (int) $row['user_id']);
        $this->assertSame('A test photo', $row['description']);
    }
}
