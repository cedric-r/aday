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

    public function test_export_without_admin_returns_403(): void
    {
        $res = TestHelper::request($this->exportFile);
        $this->assertSame(403, $res['status']);
    }

    public function test_export_streams_zip_with_correct_content(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];

        // Export with no photos — should return a valid ZIP.
        $res = TestHelper::request($this->exportFile);

        // After ob_end_clean in export.php, headers are sent; body is the raw ZIP bytes.
        // We just check the status is 200 and body starts with PK (ZIP magic bytes).
        $this->assertSame(200, $res['status']);
        $this->assertStringStartsWith('PK', $res['body'], 'Response should be a ZIP file (PK magic bytes)');
    }
}
