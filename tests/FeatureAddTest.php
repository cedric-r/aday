<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for the second-wave features:
 *  - gear + EXIF columns on upload (photos.php POST)
 *  - single-photo lookup and highlight filter (photos.php GET)
 *  - stats endpoint (api/stats.php)
 *  - admin highlight toggle + metadata CSV export
 */
final class FeatureAddTest extends TestCase
{
    private string $photosFile;
    private string $statsFile;
    private string $submissionsFile;
    private string $exportFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];
        $base = dirname(__DIR__);
        $this->photosFile      = $base . '/api/photos.php';
        $this->statsFile       = $base . '/api/stats.php';
        $this->submissionsFile = $base . '/api/admin/submissions.php';
        $this->exportFile      = $base . '/api/admin/export.php';
    }

    private function fakeJpegTemp(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aday_test_');
        file_put_contents($tmp, "\xFF\xD8\xFF\xE0" . str_repeat("\x00", 16));
        return $tmp;
    }

    private function fakeFiles(string $tmp, string $name = 'test.jpg'): array
    {
        return [
            'photo' => [
                'error'    => UPLOAD_ERR_OK,
                'size'     => filesize($tmp),
                'tmp_name' => $tmp,
                'name'     => $name,
            ],
        ];
    }

    private function upload(string $username, array $extra = []): array
    {
        $user = TestHelper::createUser(['username' => $username, 'email' => $username . '@example.com']);
        $_SESSION['user_id'] = $user['id'];
        TestHelper::setSetting('event_date', date('Y-m-d'));

        $tmp = $this->fakeJpegTemp();
        $res = TestHelper::request($this->photosFile, 'POST', $extra, [], [], $this->fakeFiles($tmp));

        $uploadDir = dirname(__DIR__) . "/uploads/{$username}/";
        if (is_dir($uploadDir)) {
            foreach (glob($uploadDir . '*.jpg') ?: [] as $f) {
                unlink($f);
            }
        }

        return $res;
    }

    public function test_upload_stores_gear_and_defaults_highlight_to_off(): void
    {
        $res = $this->upload('gearuser', ['description' => 'x', 'gear' => 'Hasselblad 500C/M · Portra 400']);

        $this->assertSame(201, $res['status']);

        $row = db()->query('SELECT * FROM photos ORDER BY id DESC LIMIT 1')->fetch();
        $this->assertSame('Hasselblad 500C/M · Portra 400', $row['gear']);
        $this->assertSame(0, (int) $row['highlight']);
    }

    public function test_upload_without_gear_stores_null(): void
    {
        $res = $this->upload('nogear');

        $this->assertSame(201, $res['status']);
        $row = db()->query('SELECT * FROM photos ORDER BY id DESC LIMIT 1')->fetch();
        $this->assertNull($row['gear']);
    }

    public function test_get_single_photo_returns_gear_field(): void
    {
        $user = TestHelper::createUser(['username' => 'single', 'email' => 'single@example.com']);
        $photo = TestHelper::createPhoto(['user_id' => $user['id'], 'gear' => 'Leica M6']);

        $res = TestHelper::request($this->photosFile, 'GET', [], ['photo' => (string) $photo['id']]);

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['json']['photos']);
        $this->assertSame((int) $photo['id'], (int) $res['json']['photos'][0]['id']);
        $this->assertSame('Leica M6', $res['json']['photos'][0]['gear']);
        $this->assertSame(0, (int) $res['json']['photos'][0]['highlight']);
    }

    public function test_highlight_filter_returns_only_flagged(): void
    {
        $user = TestHelper::createUser(['username' => 'hl', 'email' => 'hl@example.com']);
        $a = TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'a.jpg']);
        $b = TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'b.jpg']);
        db()->prepare('UPDATE photos SET highlight = 1 WHERE id = :id')->execute([':id' => $b['id']]);

        $res = TestHelper::request($this->photosFile, 'GET', [], ['highlight' => '1']);

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['json']['photos']);
        $this->assertSame((int) $b['id'], (int) $res['json']['photos'][0]['id']);
    }

    public function test_photographer_filter_returns_only_that_users_photos(): void
    {
        $alice = TestHelper::createUser(['username' => 'alicef', 'email' => 'alicef@example.com']);
        $bob   = TestHelper::createUser(['username' => 'bobf', 'email' => 'bobf@example.com']);
        TestHelper::createPhoto(['user_id' => $alice['id'], 'filename' => 'a1.jpg']);
        TestHelper::createPhoto(['user_id' => $bob['id'], 'filename' => 'b1.jpg']);

        $res = TestHelper::request($this->photosFile, 'GET', [], ['photographer' => 'bobf']);

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['json']['photos']);
        $this->assertSame('bobf', $res['json']['photos'][0]['username']);
    }

    public function test_photographer_filter_ignores_pending_users(): void
    {
        $pending = TestHelper::createUser(['username' => 'pendf', 'email' => 'pendf@example.com', 'status' => 'pending']);
        TestHelper::createPhoto(['user_id' => $pending['id'], 'filename' => 'p1.jpg']);

        $res = TestHelper::request($this->photosFile, 'GET', [], ['photographer' => 'pendf']);

        $this->assertSame(200, $res['status']);
        $this->assertCount(0, $res['json']['photos']);
    }

    public function test_hidden_photo_excluded_from_single_and_feed(): void
    {
        $user = TestHelper::createUser(['username' => 'hid', 'email' => 'hid@example.com']);
        $photo = TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'h.jpg']);
        db()->prepare('UPDATE photos SET hidden = 1 WHERE id = :id')->execute([':id' => $photo['id']]);

        // Feed (default) must not include it.
        $feed = TestHelper::request($this->photosFile);
        $this->assertCount(0, $feed['json']['photos']);

        // Single-photo lookup must treat it as not found.
        $single = TestHelper::request($this->photosFile, 'GET', [], ['photo' => (string) $photo['id']]);
        $this->assertCount(0, $single['json']['photos']);
    }

    public function test_photo_without_thumb_returns_null_thumb_url(): void
    {
        $user = TestHelper::createUser(['username' => 'tnull', 'email' => 'tnull@example.com']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'no-thumb.jpg']);

        $res = TestHelper::request($this->photosFile);

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['json']['photos']);
        $this->assertArrayHasKey('thumb_url', $res['json']['photos'][0]);
        $this->assertNull($res['json']['photos'][0]['thumb_url']);
    }

    public function test_stats_returns_total_and_48_hour_buckets(): void
    {
        $user = TestHelper::createUser(['username' => 'stats', 'email' => 'stats@example.com']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'posted_at' => gmdate('Y-m-d H:i:00')]);
        TestHelper::createPhoto(['user_id' => $user['id'], 'posted_at' => gmdate('Y-m-d', strtotime('yesterday')) . ' 10:00:00']);

        $res = TestHelper::request($this->statsFile);

        $this->assertSame(200, $res['status']);
        $this->assertSame(2, $res['json']['total']);
        $this->assertCount(48, $res['json']['by_hour']);

        $nowHour = gmdate('Y-m-d H:00');
        $bucket = array_values(array_filter(
            $res['json']['by_hour'],
            static fn (array $b): bool => $b['hour'] === $nowHour
        ));
        $this->assertCount(1, $bucket);
        $this->assertGreaterThanOrEqual(1, $bucket[0]['count']);
    }

    public function test_admin_toggle_highlight_and_get_includes_flag(): void
    {
        $admin = TestHelper::createUser(['username' => 'adminx', 'email' => 'adminx@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];
        $photo = TestHelper::createPhoto(['user_id' => $admin['id'], 'filename' => 'c.jpg']);

        $res = TestHelper::request($this->submissionsFile, 'POST', [
            'id'        => $photo['id'],
            'highlight' => true,
        ]);

        $this->assertSame(200, $res['status']);
        $stored = (int) db()->query('SELECT highlight FROM photos WHERE id = ' . (int) $photo['id'])->fetchColumn();
        $this->assertSame(1, $stored);

        $list = TestHelper::request($this->submissionsFile);
        $this->assertSame(200, $list['status']);
        $this->assertCount(1, $list['json']);
        $this->assertSame(1, (int) $list['json'][0]['highlight']);
    }

    public function test_admin_toggle_hidden_updates_flag_and_get_includes_it(): void
    {
        $admin = TestHelper::createUser(['username' => 'adminh', 'email' => 'adminh@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];
        $photo = TestHelper::createPhoto(['user_id' => $admin['id'], 'filename' => 'c.jpg']);

        $res = TestHelper::request($this->submissionsFile, 'POST', [
            'id'     => $photo['id'],
            'hidden' => true,
        ]);

        $this->assertSame(200, $res['status']);
        $stored = (int) db()->query('SELECT hidden FROM photos WHERE id = ' . (int) $photo['id'])->fetchColumn();
        $this->assertSame(1, $stored);

        $list = TestHelper::request($this->submissionsFile);
        $this->assertSame(200, $list['status']);
        $this->assertSame(1, (int) $list['json'][0]['hidden']);
    }

    public function test_metadata_csv_export_includes_headers_and_rows(): void
    {
        $admin = TestHelper::createUser(['username' => 'adminex', 'email' => 'adminex@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];
        TestHelper::createPhoto([
            'user_id'     => $admin['id'],
            'filename'    => 'd.jpg',
            'description' => 'Morning light',
            'gear'        => 'Nikon F3',
        ]);

        $res = TestHelper::request($this->exportFile, 'GET', [], ['format' => 'csv']);

        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('id,username,name,timezone', $res['body']);
        $this->assertStringContainsString('Nikon F3', $res['body']);
        $this->assertStringContainsString('Morning light', $res['body']);
    }

    public function test_metadata_json_export_returns_rows(): void
    {
        $admin = TestHelper::createUser(['username' => 'adminj', 'email' => 'adminj@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];
        TestHelper::createPhoto(['user_id' => $admin['id'], 'filename' => 'e.jpg']);

        $res = TestHelper::request($this->exportFile, 'GET', [], ['format' => 'json']);

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $res['json']['photos']);
        $this->assertArrayHasKey('gear', $res['json']['photos'][0]);
    }
}