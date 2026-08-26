<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for the third feature wave:
 *  - "Follow the sun" hour filter (photos.php ?hour=H, tz-aware, east→west)
 *  - random photo (?random=1)
 *  - stats breadth (photographers + timezones)
 *  - photographer bio (register/admin/profile)
 *  - self-service ZIP download (api/my-export.php)
 */
final class WaveThreeTest extends TestCase
{
    private string $photosFile;
    private string $statsFile;
    private string $myExportFile;
    private string $photographersFile;
    private string $registerFile;
    private string $usersFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];
        $base = dirname(__DIR__);
        $this->photosFile        = $base . '/api/photos.php';
        $this->statsFile         = $base . '/api/stats.php';
        $this->myExportFile      = $base . '/api/my-export.php';
        $this->photographersFile = $base . '/api/photographers.php';
        $this->registerFile      = $base . '/api/register.php';
        $this->usersFile         = $base . '/api/admin/users.php';
    }

    // ── Follow the sun ────────────────────────────────────────────────────

    public function test_hour_filter_matches_local_hour_per_timezone(): void
    {
        // Tokyo (UTC+9): 12:00 UTC → 21:00 local. Paris (UTC+2 summer): 12:00 UTC → 14:00.
        $tokyo = TestHelper::createUser(['username' => 'tok', 'email' => 'tok@example.com', 'timezone' => 'Asia/Tokyo']);
        $paris = TestHelper::createUser(['username' => 'par', 'email' => 'par@example.com', 'timezone' => 'Europe/Paris']);
        TestHelper::createPhoto(['user_id' => $tokyo['id'], 'filename' => 't.jpg', 'posted_at' => '2026-08-24 12:00:00']);
        TestHelper::createPhoto(['user_id' => $paris['id'], 'filename' => 'p.jpg', 'posted_at' => '2026-08-24 12:00:00']);

        $res21 = TestHelper::request($this->photosFile, 'GET', [], ['hour' => '21']);
        $this->assertSame(200, $res21['status']);
        $ids21 = array_column($res21['json']['photos'], 'id');
        $this->assertContains('t.jpg', array_column($res21['json']['photos'], 'filename'));
        $this->assertCount(1, $res21['json']['photos']);

        $res14 = TestHelper::request($this->photosFile, 'GET', [], ['hour' => '14']);
        $this->assertSame(200, $res14['status']);
        $this->assertSame(['p.jpg'], array_column($res14['json']['photos'], 'filename'));

        // Sanity: hour 3 matches neither.
        $res3 = TestHelper::request($this->photosFile, 'GET', [], ['hour' => '3']);
        $this->assertCount(0, $res3['json']['photos']);

        unset($ids21);
    }

    public function test_hour_filter_orders_east_to_west(): void
    {
        // Same local hour 12:00 in each zone → different UTC instants.
        $tokyo = TestHelper::createUser(['username' => 'tok2', 'email' => 'tok2@example.com', 'timezone' => 'Asia/Tokyo']);   // +9
        $paris = TestHelper::createUser(['username' => 'par2', 'email' => 'par2@example.com', 'timezone' => 'Europe/Paris']); // +2 (Aug)
        $ny    = TestHelper::createUser(['username' => 'nyc', 'email' => 'nyc@example.com',  'timezone' => 'America/New_York']); // -4 (Aug)

        // Local noon in each zone:
        // Tokyo 12:00 JST = 03:00 UTC; Paris 12:00 CEST = 10:00 UTC; NY 12:00 EDT = 16:00 UTC.
        TestHelper::createPhoto(['user_id' => $tokyo['id'], 'filename' => 'tokyonoon.jpg', 'posted_at' => '2026-08-24 03:00:00']);
        TestHelper::createPhoto(['user_id' => $paris['id'], 'filename' => 'parisnoon.jpg', 'posted_at' => '2026-08-24 10:00:00']);
        TestHelper::createPhoto(['user_id' => $ny['id'],    'filename' => 'nynoon.jpg',    'posted_at' => '2026-08-24 16:00:00']);

        $res = TestHelper::request($this->photosFile, 'GET', [], ['hour' => '12']);
        $this->assertSame(200, $res['status']);
        $files = array_column($res['json']['photos'], 'filename');
        $this->assertSame(['tokyonoon.jpg', 'parisnoon.jpg', 'nynoon.jpg'], $files); // east → west
        foreach ($res['json']['photos'] as $p) {
            $this->assertArrayHasKey('local_time', $p);
            $this->assertSame('12:00', $p['local_time']);
        }
    }

    public function test_hour_filter_rejects_invalid_values(): void
    {
        // Non-numeric or >23 falls back to the normal feed (no error).
        foreach (['abc', '25', '-1'] as $h) {
            $res = TestHelper::request($this->photosFile, 'GET', [], ['hour' => $h]);
            $this->assertSame(200, $res['status']);
        }
    }

    public function test_hour_filter_excludes_hidden_photos(): void
    {
        db()->prepare("UPDATE photos SET hidden = 1 WHERE id = 0")->execute(); // no-op guard
        $u = TestHelper::createUser(['username' => 'hid', 'email' => 'hid@example.com', 'timezone' => 'UTC']);
        $visible = TestHelper::createPhoto(['user_id' => $u['id'], 'filename' => 'vis.jpg', 'posted_at' => '2026-08-24 05:00:00']);
        db()->prepare('INSERT INTO photos (user_id, filename, description, posted_at, hidden) VALUES (:u, :f, :d, :p, 1)')
            ->execute([':u' => $u['id'], ':f' => 'hid.jpg', ':d' => '', ':p' => '2026-08-24 05:30:00']);

        $res = TestHelper::request($this->photosFile, 'GET', [], ['hour' => '5']);
        $files = array_column($res['json']['photos'], 'filename');
        $this->assertContains('vis.jpg', $files);
        $this->assertNotContains('hid.jpg', $files);
        unset($visible);
    }

    // ── Random photo ──────────────────────────────────────────────────────

    public function test_random_returns_one_public_photo(): void
    {
        $u1 = TestHelper::createUser(['username' => 'r1', 'email' => 'r1@example.com']);
        $u2 = TestHelper::createUser(['username' => 'r2', 'email' => 'r2@example.com']);
        TestHelper::createPhoto(['user_id' => $u1['id'], 'filename' => 'a.jpg']);
        TestHelper::createPhoto(['user_id' => $u2['id'], 'filename' => 'b.jpg']);
        db()->prepare('INSERT INTO photos (user_id, filename, description, posted_at, hidden) VALUES (:u, :f, :d, :p, 1)')
            ->execute([':u' => $u1['id'], ':f' => 'hidden.jpg', ':d' => '', ':p' => '2026-08-24 06:00:00']);

        for ($i = 0; $i < 5; $i++) {
            $res = TestHelper::request($this->photosFile, 'GET', [], ['random' => '1']);
            $this->assertSame(200, $res['status']);
            $this->assertCount(1, $res['json']['photos']);
            $file = $res['json']['photos'][0]['filename'];
            $this->assertContains($file, ['a.jpg', 'b.jpg']);
            $this->assertNotSame('hidden.jpg', $file);
        }
    }

    // ── Stats breadth ─────────────────────────────────────────────────────

    public function test_stats_includes_photographer_and_timezone_breadth(): void
    {
        $a = TestHelper::createUser(['username' => 'sa', 'email' => 'sa@example.com', 'timezone' => 'Asia/Tokyo']);
        $b = TestHelper::createUser(['username' => 'sb', 'email' => 'sb@example.com', 'timezone' => 'Europe/Paris']);
        $c = TestHelper::createUser(['username' => 'sc', 'email' => 'sc@example.com', 'timezone' => 'Europe/Berlin']); // same tz as Paris? No — distinct
        TestHelper::createPhoto(['user_id' => $a['id']]);
        TestHelper::createPhoto(['user_id' => $b['id']]);
        TestHelper::createPhoto(['user_id' => $c['id']]);

        $res = TestHelper::request($this->statsFile);
        $this->assertSame(200, $res['status']);
        $this->assertSame(3, $res['json']['total']);
        $this->assertSame(3, $res['json']['photographers']);
        $this->assertSame(3, $res['json']['timezones']);
    }

    public function test_stats_breadth_excludes_hidden_and_pending(): void
    {
        $a = TestHelper::createUser(['username' => 'hd1', 'email' => 'hd1@example.com', 'timezone' => 'UTC']);
        $b = TestHelper::createUser(['username' => 'hd2', 'email' => 'hd2@example.com', 'timezone' => 'Asia/Tokyo', 'status' => 'pending']);
        TestHelper::createPhoto(['user_id' => $a['id']]);
        TestHelper::createPhoto(['user_id' => $b['id']]); // pending photographer — excluded from breadth
        db()->exec("INSERT INTO photos (user_id, filename, description, posted_at, hidden) VALUES ({$a['id']}, 'x.jpg', '', '2026-08-24 07:00:00', 1)");

        $res = TestHelper::request($this->statsFile);
        // total counts every non-hidden photo (pre-existing semantics) = 2,
        // but breadth only counts *validated* photographers who have posted.
        $this->assertSame(2, $res['json']['total']);
        $this->assertSame(1, $res['json']['photographers']);
        $this->assertSame(1, $res['json']['timezones']);
    }

    // ── Bio ───────────────────────────────────────────────────────────────

    public function test_register_persists_bio(): void
    {
        $_SESSION = ['captcha_index' => 0];
        $res = TestHelper::request($this->registerFile, 'POST', [
            'username'       => 'bioed',
            'name'           => 'Bio Ed',
            'bio'            => 'Street shooter from Lyon.',
            'email'          => 'bioed@example.com',
            'password'       => 'securepassword',
            'timezone'       => 'UTC',
            'captcha_answer' => '7',
        ]);
        $this->assertSame(201, $res['status']);

        $row = db()->query("SELECT bio FROM users WHERE username = 'bioed'")->fetch();
        $this->assertSame('Street shooter from Lyon.', $row['bio']);
    }

    public function test_register_bio_is_capped_at_500_chars(): void
    {
        $_SESSION = ['captcha_index' => 0];
        $longBio = str_repeat('x', 700);
        $res = TestHelper::request($this->registerFile, 'POST', [
            'username'       => 'biolong',
            'name'           => 'Bio Long',
            'bio'            => $longBio,
            'email'          => 'biolong@example.com',
            'password'       => 'securepassword',
            'timezone'       => 'UTC',
            'captcha_answer' => '7',
        ]);
        $this->assertSame(201, $res['status']);
        $row = db()->query("SELECT bio FROM users WHERE username = 'biolong'")->fetch();
        $this->assertSame(500, mb_strlen((string) $row['bio']));
    }

    public function test_admin_can_set_and_clear_bio(): void
    {
        TestHelper::createUser(['username' => 'adminz', 'email' => 'adminz@example.com', 'is_admin' => 1]);
        $_SESSION = ['user_id' => (int) db()->query("SELECT id FROM users WHERE username='adminz'")->fetchColumn()];

        // Create with bio.
        $res = TestHelper::request($this->usersFile, 'POST', [
            'username' => 'withbio',
            'name'     => 'With Bio',
            'email'    => 'withbio@example.com',
            'password' => 'securepassword',
            'timezone' => 'UTC',
            'bio'      => 'A photographer.',
        ]);
        $this->assertSame(201, $res['status']);
        $id = (int) db()->query("SELECT id FROM users WHERE username='withbio'")->fetchColumn();

        // PUT with >500 chars truncates (same as POST — consistent).
        $put = TestHelper::request($this->usersFile, 'PUT', ['bio' => str_repeat('y', 700)], ['id' => $id]);
        $this->assertSame(200, $put['status']);
        $this->assertSame(500, mb_strlen((string) db()->query("SELECT bio FROM users WHERE id = {$id}")->fetchColumn()));

        // Clear it via PUT.
        $clear = TestHelper::request($this->usersFile, 'PUT', ['bio' => ''], ['id' => $id]);
        $this->assertSame(200, $clear['status']);

        $row = db()->query("SELECT bio FROM users WHERE id = {$id}")->fetch();
        $this->assertNull($row['bio']);
    }

    public function test_photographer_profile_returns_bio(): void
    {
        TestHelper::createUser([
            'username' => 'profbio', 'email' => 'profbio@example.com',
            'timezone' => 'UTC', 'bio' => 'Chasing light since 1999.',
        ]);

        $res = TestHelper::request($this->photographersFile, 'GET', [], ['username' => 'profbio']);
        $this->assertSame(200, $res['status']);
        $this->assertSame('Chasing light since 1999.', $res['json']['bio']);
    }

    // ── Self-service download ─────────────────────────────────────────────

    public function test_my_export_requires_auth(): void
    {
        $res = TestHelper::request($this->myExportFile);
        $this->assertSame(401, $res['status']);
    }

    public function test_my_export_streams_zip_of_own_photos(): void
    {
        if (!extension_loaded('zip')) {
            $this->markTestSkipped('ext-zip not available locally.');
        }
        $u = TestHelper::createUser(['username' => 'dlme', 'email' => 'dlme@example.com']);
        TestHelper::createPhoto(['user_id' => $u['id'], 'filename' => 'mine1.jpg', 'description' => 'First!']);
        TestHelper::createPhoto(['user_id' => $u['id'], 'filename' => 'mine2.jpg']);
        // Someone else's photo must not appear.
        $other = TestHelper::createUser(['username' => 'notmine', 'email' => 'notmine@example.com']);
        TestHelper::createPhoto(['user_id' => $other['id'], 'filename' => 'theirs.jpg']);

        $_SESSION['user_id'] = (int) $u['id'];
        $res = TestHelper::request($this->myExportFile);
        $this->assertSame(200, $res['status']);
        $this->assertStringStartsWith('ZIP:', $res['body']);
        $this->assertStringNotContainsString('theirs.jpg', $res['body']);
    }

    public function test_my_export_404_when_no_photos(): void
    {
        if (!extension_loaded('zip')) {
            $this->markTestSkipped('ext-zip not available locally (503 short-circuits before 404).');
        }
        $u = TestHelper::createUser(['username' => 'nophotos', 'email' => 'nophotos@example.com']);
        $_SESSION['user_id'] = (int) $u['id'];
        $res = TestHelper::request($this->myExportFile);
        $this->assertSame(404, $res['status']);
    }
}