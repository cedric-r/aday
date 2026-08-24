<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for lib/Exporter.php (US-3).
 * Path A — uses temp files to simulate uploads on disk.
 */
final class ExporterTest extends TestCase
{
    protected function setUp(): void
    {
        TestHelper::resetDb();
    }

    /**
     * Create a real file at uploads/{username}/{filename} and return its path.
     */
    private function seedFile(string $username, string $filename): string
    {
        $dir = dirname(__DIR__) . "/uploads/{$username}";
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = "{$dir}/{$filename}";
        file_put_contents($path, "fake image data for {$filename}");
        return $path;
    }

    private function cleanUploads(string ...$usernames): void
    {
        foreach ($usernames as $username) {
            $dir = dirname(__DIR__) . "/uploads/{$username}";
            if (is_dir($dir)) {
                foreach (glob("{$dir}/*") ?: [] as $f) {
                    unlink($f);
                }
                rmdir($dir);
            }
        }
    }

    private function openZip(string $path): ZipArchive
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'ZIP should open without error');
        return $zip;
    }

    public function test_single_photographer_two_photos_correct_structure(): void
    {
        $user = TestHelper::createUser(['username' => 'alice', 'email' => 'alice@example.com']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'img1.jpg', 'description' => 'First', 'posted_at' => '2026-08-24 08:00:00']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'img2.jpg', 'description' => 'Second', 'posted_at' => '2026-08-24 09:00:00']);

        $this->seedFile('alice', 'img1.jpg');
        $this->seedFile('alice', 'img2.jpg');

        $path = (new Exporter())->buildZip();
        $zip  = $this->openZip($path);

        $this->assertNotFalse($zip->locateName('alice/img1.jpg'));
        $this->assertNotFalse($zip->locateName('alice/img2.jpg'));
        $this->assertNotFalse($zip->locateName('alice/descriptions.txt'));

        $zip->close();
        unlink($path);
        $this->cleanUploads('alice');
    }

    public function test_descriptions_txt_ordered_by_posted_at_asc(): void
    {
        $user = TestHelper::createUser(['username' => 'bob', 'email' => 'bob@example.com']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'z.jpg', 'description' => 'Late', 'posted_at' => '2026-08-24 10:00:00']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'a.jpg', 'description' => 'Early', 'posted_at' => '2026-08-24 08:00:00']);

        $this->seedFile('bob', 'z.jpg');
        $this->seedFile('bob', 'a.jpg');

        $path = (new Exporter())->buildZip();
        $zip  = $this->openZip($path);

        $content = $zip->getFromName('bob/descriptions.txt');
        $this->assertIsString($content);

        $lines = explode("\n", trim($content));
        $this->assertStringStartsWith('a.jpg: Early', $lines[0]);
        $this->assertStringStartsWith('z.jpg: Late', $lines[1]);

        $zip->close();
        unlink($path);
        $this->cleanUploads('bob');
    }

    public function test_two_photographers_two_subfolders(): void
    {
        $u1 = TestHelper::createUser(['username' => 'carol', 'email' => 'carol@example.com']);
        $u2 = TestHelper::createUser(['username' => 'dave', 'email' => 'dave@example.com']);
        TestHelper::createPhoto(['user_id' => $u1['id'], 'filename' => 'c1.jpg']);
        TestHelper::createPhoto(['user_id' => $u2['id'], 'filename' => 'd1.jpg']);
        $this->seedFile('carol', 'c1.jpg');
        $this->seedFile('dave', 'd1.jpg');

        $path = (new Exporter())->buildZip();
        $zip  = $this->openZip($path);

        $this->assertNotFalse($zip->locateName('carol/c1.jpg'));
        $this->assertNotFalse($zip->locateName('dave/d1.jpg'));

        $zip->close();
        unlink($path);
        $this->cleanUploads('carol', 'dave');
    }

    public function test_photographer_with_no_photos_has_no_subfolder(): void
    {
        TestHelper::createUser(['username' => 'empty', 'email' => 'empty@example.com']);
        // No photos for this user.
        $u2 = TestHelper::createUser(['username' => 'active', 'email' => 'active@example.com']);
        TestHelper::createPhoto(['user_id' => $u2['id'], 'filename' => 'a.jpg']);
        $this->seedFile('active', 'a.jpg');

        $path = (new Exporter())->buildZip();
        $zip  = $this->openZip($path);

        $this->assertFalse($zip->locateName('empty/descriptions.txt'), 'User with no photos should not have a subfolder');

        $zip->close();
        unlink($path);
        $this->cleanUploads('active');
    }

    public function test_missing_file_on_disk_adds_note_and_does_not_crash(): void
    {
        $user = TestHelper::createUser(['username' => 'frank', 'email' => 'frank@example.com']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'missing.jpg', 'description' => 'Gone']);
        // Do NOT seed the file on disk.

        $path = (new Exporter())->buildZip();
        $zip  = $this->openZip($path);

        $content = $zip->getFromName('frank/descriptions.txt');
        $this->assertIsString($content);
        $this->assertStringContainsString('[file missing]', $content);
        $this->assertFalse($zip->locateName('frank/missing.jpg'));

        $zip->close();
        unlink($path);
    }

    public function test_empty_event_produces_valid_zip(): void
    {
        // No users, no photos.
        $path = (new Exporter())->buildZip();
        $zip  = $this->openZip($path);

        $this->assertSame(0, $zip->count());

        $zip->close();
        unlink($path);
    }
}
