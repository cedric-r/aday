<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for setup.php (US-2).
 * Path A — new code.
 */
final class SetupTest extends TestCase
{
    private string $setupFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];
        $this->setupFile = dirname(__DIR__) . '/setup.php';
    }

    public function test_first_post_creates_admin_and_sets_setup_complete(): void
    {
        $res = TestHelper::request($this->setupFile, 'POST', [
            'username' => 'admin',
            'password' => 'adminpass123',
        ]);

        $this->assertSame(201, $res['status']);

        $user = db()->query("SELECT * FROM users WHERE username = 'admin'")->fetch();
        $this->assertNotFalse($user);
        $this->assertSame(1, (int) $user['is_admin']);
        $this->assertSame('validated', $user['status']);

        $setting = db()->query("SELECT value FROM settings WHERE key = 'setup_complete'")->fetch();
        $this->assertNotFalse($setting);
        $this->assertSame('1', $setting['value']);
    }

    public function test_second_post_returns_403(): void
    {
        // First setup
        TestHelper::request($this->setupFile, 'POST', [
            'username' => 'admin',
            'password' => 'adminpass123',
        ]);

        // Reset response code
        http_response_code(200);

        // Second attempt
        $res = TestHelper::request($this->setupFile, 'POST', [
            'username' => 'admin2',
            'password' => 'adminpass123',
        ]);

        $this->assertSame(403, $res['status']);
    }

    public function test_short_password_returns_422(): void
    {
        $res = TestHelper::request($this->setupFile, 'POST', [
            'username' => 'admin',
            'password' => 'short',
        ]);

        $this->assertSame(422, $res['status']);
    }
}
