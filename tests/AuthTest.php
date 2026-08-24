<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for api/me.php, api/login.php, api/logout.php, and lib/Auth.php (US-7).
 * Path A — all new code, written before implementation.
 */
final class AuthTest extends TestCase
{
    private string $meFile;
    private string $loginFile;
    private string $logoutFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];

        $this->meFile     = dirname(__DIR__) . '/api/me.php';
        $this->loginFile  = dirname(__DIR__) . '/api/login.php';
        $this->logoutFile = dirname(__DIR__) . '/api/logout.php';
    }

    // -----------------------------------------------------------------------
    // GET /api/me.php
    // -----------------------------------------------------------------------

    public function test_me_returns_authenticated_false_with_no_session(): void
    {
        $res = TestHelper::request($this->meFile);

        $this->assertSame(200, $res['status']);
        $this->assertFalse($res['json']['authenticated']);
    }

    public function test_me_returns_user_data_for_valid_session(): void
    {
        $user = TestHelper::createUser(['username' => 'alice', 'email' => 'alice@example.com']);
        $_SESSION['user_id'] = $user['id'];

        $res = TestHelper::request($this->meFile);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['authenticated']);
        $this->assertSame('alice', $res['json']['username']);
        $this->assertArrayHasKey('is_admin', $res['json']);
        $this->assertArrayHasKey('status', $res['json']);
        $this->assertArrayNotHasKey('password_hash', $res['json']);
    }

    // -----------------------------------------------------------------------
    // POST /api/login.php
    // -----------------------------------------------------------------------

    public function test_login_valid_credentials_returns_200_and_user(): void
    {
        TestHelper::createUser([
            'username'      => 'bob',
            'email'         => 'bob@example.com',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'status'        => 'validated',
        ]);

        $res = TestHelper::request($this->loginFile, 'POST', [
            'username' => 'bob',
            'password' => 'password123',
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertSame('bob', $res['json']['username']);
        $this->assertSame('validated', $res['json']['status']);
    }

    public function test_login_wrong_password_returns_401(): void
    {
        TestHelper::createUser(['username' => 'carol', 'email' => 'carol@example.com']);

        $res = TestHelper::request($this->loginFile, 'POST', [
            'username' => 'carol',
            'password' => 'wrongpassword',
        ]);

        $this->assertSame(401, $res['status']);
        $this->assertStringContainsString('Invalid', $res['json']['error']);
    }

    public function test_login_unknown_username_returns_401(): void
    {
        $res = TestHelper::request($this->loginFile, 'POST', [
            'username' => 'nobody',
            'password' => 'password123',
        ]);

        $this->assertSame(401, $res['status']);
    }

    public function test_login_pending_user_returns_403(): void
    {
        TestHelper::createUser([
            'username' => 'dave',
            'email'    => 'dave@example.com',
            'status'   => 'pending',
        ]);

        $res = TestHelper::request($this->loginFile, 'POST', [
            'username' => 'dave',
            'password' => 'password123',
        ]);

        $this->assertSame(403, $res['status']);
        $this->assertStringContainsString('pending', $res['json']['error']);
    }

    public function test_login_sets_session_user_id(): void
    {
        TestHelper::createUser([
            'username'      => 'eve',
            'email'         => 'eve@example.com',
            'password_hash' => password_hash('password123', PASSWORD_BCRYPT),
            'status'        => 'validated',
        ]);

        TestHelper::request($this->loginFile, 'POST', [
            'username' => 'eve',
            'password' => 'password123',
        ]);

        $this->assertArrayHasKey('user_id', $_SESSION);
        $this->assertIsInt($_SESSION['user_id']);
    }

    // -----------------------------------------------------------------------
    // POST /api/logout.php
    // -----------------------------------------------------------------------

    public function test_logout_returns_200_and_clears_session(): void
    {
        $user = TestHelper::createUser(['username' => 'frank', 'email' => 'frank@example.com']);
        $_SESSION['user_id'] = $user['id'];

        $res = TestHelper::request($this->logoutFile, 'POST');

        $this->assertSame(200, $res['status']);
        $this->assertEmpty($_SESSION, 'Session must be empty after logout');
    }
}
