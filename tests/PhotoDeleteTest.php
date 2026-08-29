<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for the self-service DELETE on api/photos.php (participants removing
 * their own photos from the "My photos" page).
 */
final class PhotoDeleteTest extends TestCase
{
    private string $photosFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];
        $this->photosFile = dirname(__DIR__) . '/api/photos.php';
    }

    public function test_delete_requires_auth(): void
    {
        $res = TestHelper::request($this->photosFile, 'DELETE', [], ['id' => 1]);
        $this->assertSame(401, $res['status']);
    }

    public function test_delete_requires_validated_user(): void
    {
        $user = TestHelper::createUser(['username' => 'pend', 'email' => 'pend@example.com', 'status' => 'pending']);
        $_SESSION['user_id'] = $user['id'];

        $res = TestHelper::request($this->photosFile, 'DELETE', [], ['id' => 1]);
        $this->assertSame(403, $res['status']);
    }

    public function test_delete_requires_id(): void
    {
        $user = TestHelper::createUser(['username' => 'alice', 'email' => 'alice@example.com']);
        $_SESSION['user_id'] = $user['id'];

        $res = TestHelper::request($this->photosFile, 'DELETE', [], []);
        $this->assertSame(400, $res['status']);
    }

    public function test_delete_own_photo_removes_row_and_file(): void
    {
        $user = TestHelper::createUser(['username' => 'alice', 'email' => 'alice@example.com']);
        $_SESSION['user_id'] = $user['id'];

        // Create a real upload dir + file so we can assert file cleanup.
        $uploads = dirname(__DIR__) . '/uploads/alice';
        @mkdir($uploads, 0777, true);
        @mkdir("$uploads/thumbs", 0777, true);
        file_put_contents("$uploads/photo1.jpg", 'fake-jpeg');
        file_put_contents("$uploads/thumbs/photo1.jpg", 'fake-thumb');

        db()->prepare('INSERT INTO photos (user_id, filename, description) VALUES (:u, :f, :d)')
            ->execute([':u' => $user['id'], ':f' => 'photo1.jpg', ':d' => 'desc']);
        $id = (int) db()->lastInsertId();

        $res = TestHelper::request($this->photosFile, 'DELETE', [], ['id' => $id]);

        $this->assertSame(200, $res['status']);
        $this->assertSame($id, $res['json']['id']);
        $this->assertSame(0, (int) db()->query('SELECT COUNT(*) FROM photos')->fetchColumn());
        $this->assertFileDoesNotExist("$uploads/photo1.jpg");
        $this->assertFileDoesNotExist("$uploads/thumbs/photo1.jpg");

        // Clean up empty dirs.
        @rmdir("$uploads/thumbs");
        @rmdir($uploads);
    }

    public function test_delete_other_users_photo_is_forbidden(): void
    {
        $owner = TestHelper::createUser(['username' => 'bob', 'email' => 'bob@example.com']);
        $other = TestHelper::createUser(['username' => 'eve', 'email' => 'eve@example.com']);
        $_SESSION['user_id'] = $other['id'];

        db()->prepare('INSERT INTO photos (user_id, filename) VALUES (:u, :f)')
            ->execute([':u' => $owner['id'], ':f' => 'bob.jpg']);
        $id = (int) db()->lastInsertId();

        $res = TestHelper::request($this->photosFile, 'DELETE', [], ['id' => $id]);

        $this->assertSame(404, $res['status']);
        $this->assertSame(1, (int) db()->query('SELECT COUNT(*) FROM photos')->fetchColumn());
    }

    public function test_delete_nonexistent_photo_returns_404(): void
    {
        $user = TestHelper::createUser(['username' => 'carol', 'email' => 'carol@example.com']);
        $_SESSION['user_id'] = $user['id'];

        $res = TestHelper::request($this->photosFile, 'DELETE', [], ['id' => 9999]);
        $this->assertSame(404, $res['status']);
    }
}
