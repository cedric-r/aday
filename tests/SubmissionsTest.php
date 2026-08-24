<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for api/admin/submissions.php and api/admin/export.php (US-3).
 * Path A — new code.
 */
final class SubmissionsTest extends TestCase
{
    private string $submissionsFile;
    private string $exportFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];

        $this->submissionsFile = dirname(__DIR__) . '/api/admin/submissions.php';
        $this->exportFile      = dirname(__DIR__) . '/api/admin/export.php';
    }

    public function test_submissions_without_admin_returns_403(): void
    {
        $res = TestHelper::request($this->submissionsFile);
        $this->assertSame(403, $res['status']);
    }

    public function test_submissions_returns_photo_and_user_data(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $user  = TestHelper::createUser(['username' => 'alice', 'email' => 'alice@example.com']);
        TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'photo.jpg', 'description' => 'Test photo']);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->submissionsFile);

        $this->assertSame(200, $res['status']);
        $this->assertIsArray($res['json']);
        $this->assertCount(1, $res['json']);
        $this->assertSame('alice', $res['json'][0]['username']);
        $this->assertSame('photo.jpg', $res['json'][0]['filename']);
        $this->assertSame('Test photo', $res['json'][0]['description']);
    }

    // -----------------------------------------------------------------------
    // DELETE /api/admin/submissions.php?id=N
    // -----------------------------------------------------------------------

    public function test_delete_submission_without_admin_returns_403(): void
    {
        $user  = TestHelper::createUser(['username' => 'alice', 'email' => 'alice@example.com']);
        $photo = TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'photo.jpg']);

        $res = TestHelper::request($this->submissionsFile, 'DELETE', [], ['id' => $photo['id']]);
        $this->assertSame(403, $res['status']);
    }

    public function test_delete_submission_not_found_returns_404(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->submissionsFile, 'DELETE', [], ['id' => 99999]);
        $this->assertSame(404, $res['status']);
    }

    public function test_delete_submission_removes_db_record_and_file(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $user  = TestHelper::createUser(['username' => 'alice', 'email' => 'alice@example.com']);
        $photo = TestHelper::createPhoto(['user_id' => $user['id'], 'filename' => 'todelete.jpg']);
        $_SESSION['user_id'] = $admin['id'];

        // Create the physical file so delete-from-disk can be verified.
        $uploadDir = dirname(__DIR__) . '/uploads/alice';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $filePath = "{$uploadDir}/todelete.jpg";
        file_put_contents($filePath, 'fake image data');

        $res = TestHelper::request($this->submissionsFile, 'DELETE', [], ['id' => $photo['id']]);

        $this->assertSame(200, $res['status']);

        // DB record gone.
        $row = db()->query("SELECT id FROM photos WHERE id = {$photo['id']}")->fetch();
        $this->assertFalse($row, 'Photo DB record must be deleted');

        // File removed from disk.
        $this->assertFileDoesNotExist($filePath, 'Photo file must be removed from disk');

        // Cleanup directory.
        if (is_dir($uploadDir) && count(scandir($uploadDir)) === 2) {
            rmdir($uploadDir);
        }
    }

    // -----------------------------------------------------------------------
    // Export
    // -----------------------------------------------------------------------

    public function test_export_without_admin_returns_403(): void
    {
        $res = TestHelper::request($this->exportFile);
        $this->assertSame(403, $res['status']);
    }

    public function test_export_streams_zip_with_correct_content(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->exportFile);

        $this->assertSame(200, $res['status']);
        $this->assertStringStartsWith('PK', $res['body'], 'Response should be a ZIP file (PK magic bytes)');
    }
}
