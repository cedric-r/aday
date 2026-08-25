<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for api/admin/users.php, api/admin/validate.php, api/admin/settings.php (US-2).
 * Path A — new code.
 */
final class AdminUsersTest extends TestCase
{
    private string $usersFile;
    private string $validateFile;
    private string $settingsFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];

        $this->usersFile    = dirname(__DIR__) . '/api/admin/users.php';
        $this->validateFile = dirname(__DIR__) . '/api/admin/validate.php';
        $this->settingsFile = dirname(__DIR__) . '/api/admin/settings.php';
    }

    // -----------------------------------------------------------------------
    // Auth guard
    // -----------------------------------------------------------------------

    public function test_users_endpoint_blocks_unauthenticated(): void
    {
        $res = TestHelper::request($this->usersFile);
        $this->assertSame(403, $res['status']);
    }

    public function test_users_endpoint_blocks_non_admin(): void
    {
        $user = TestHelper::createUser(['username' => 'nobody', 'email' => 'nobody@example.com']);
        $_SESSION['user_id'] = $user['id'];

        $res = TestHelper::request($this->usersFile);
        $this->assertSame(403, $res['status']);
    }

    // -----------------------------------------------------------------------
    // GET — list users
    // -----------------------------------------------------------------------

    public function test_get_returns_all_users_ordered_by_name(): void
    {
        $admin = TestHelper::createUser(['username' => 'zadmin', 'name' => 'Z Admin', 'email' => 'z@example.com', 'is_admin' => 1]);
        TestHelper::createUser(['username' => 'bob', 'name' => 'Bob', 'email' => 'bob@example.com']);
        TestHelper::createUser(['username' => 'alice', 'name' => 'Alice', 'email' => 'alice@example.com']);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->usersFile);

        $this->assertSame(200, $res['status']);
        $this->assertIsArray($res['json']);
        $this->assertCount(3, $res['json']);
        $this->assertSame('Alice', $res['json'][0]['name']);
        $this->assertSame('Bob', $res['json'][1]['name']);
        $this->assertArrayNotHasKey('password_hash', $res['json'][0]);
    }

    // -----------------------------------------------------------------------
    // POST — create user
    // -----------------------------------------------------------------------

    public function test_post_creates_validated_user(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->usersFile, 'POST', [
            'username' => 'newuser',
            'name'     => 'New User',
            'email'    => 'new@example.com',
            'password' => 'password123',
            'timezone' => 'UTC',
            'is_admin' => 0,
        ]);

        $this->assertSame(201, $res['status']);

        $user = db()->query("SELECT * FROM users WHERE username = 'newuser'")->fetch();
        $this->assertNotFalse($user);
        $this->assertSame('validated', $user['status']);
    }

    // -----------------------------------------------------------------------
    // DELETE — safety guards
    // -----------------------------------------------------------------------

    public function test_delete_self_returns_400(): void
    {
        // Two admins so last-admin guard does not fire first — isolates self-delete guard.
        $admin1 = TestHelper::createUser(['username' => 'admin1', 'email' => 'admin1@example.com', 'is_admin' => 1]);
        $admin2 = TestHelper::createUser(['username' => 'admin2', 'email' => 'admin2@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin2['id'];

        $res = TestHelper::request($this->usersFile, 'DELETE', [], ['id' => $admin2['id']]);
        $this->assertSame(400, $res['status']);
        $this->assertStringContainsString('own account', $res['body']);
    }

    public function test_delete_last_admin_returns_400(): void
    {
        // Only 1 admin — last-admin guard must fire and return 400.
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        TestHelper::createUser(['username' => 'user', 'email' => 'user@example.com']);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->usersFile, 'DELETE', [], ['id' => $admin['id']]);
        $this->assertSame(400, $res['status']);
        $this->assertStringContainsString('last admin', $res['body']);
    }

    public function test_can_delete_admin_when_multiple_admins_exist(): void
    {
        // Two admins — deleting one should succeed.
        $admin1 = TestHelper::createUser(['username' => 'admin1', 'email' => 'admin1@example.com', 'is_admin' => 1]);
        $admin2 = TestHelper::createUser(['username' => 'admin2', 'email' => 'admin2@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin2['id'];

        $res = TestHelper::request($this->usersFile, 'DELETE', [], ['id' => $admin1['id']]);
        $this->assertSame(200, $res['status']);
    }

    // -----------------------------------------------------------------------
    // HMAC validation
    // -----------------------------------------------------------------------

    public function test_hmac_token_validates_and_sets_status_validated(): void
    {
        $user = TestHelper::createUser([
            'username' => 'pending',
            'email'    => 'pending@example.com',
            'status'   => 'pending',
        ]);

        // Mimic Mailer::sendAdminValidation: mint a nonce and a bound token.
        $nonce   = bin2hex(random_bytes(16));
        db()->prepare('UPDATE users SET validation_nonce = :nonce WHERE id = :id')
            ->execute([':nonce' => $nonce, ':id' => $user['id']]);

        $expires = time() + 3600;
        $token   = Mailer::buildToken((int) $user['id'], 'pending@example.com', $nonce, $expires, (string) env('APP_SECRET'));

        $res = TestHelper::request(
            $this->validateFile,
            'GET',
            [],
            ['id' => $user['id'], 'expires' => $expires, 'token' => $token]
        );

        $this->assertSame(200, $res['status']);

        $updated = db()->query("SELECT status, validation_nonce FROM users WHERE id = {$user['id']}")->fetch();
        $this->assertSame('validated', $updated['status']);
        $this->assertNull($updated['validation_nonce']); // single-use: nonce consumed
    }

    public function test_invalid_hmac_returns_401(): void
    {
        $user = TestHelper::createUser([
            'username' => 'pending2',
            'email'    => 'pending2@example.com',
            'status'   => 'pending',
        ]);

        $nonce = bin2hex(random_bytes(16));
        db()->prepare('UPDATE users SET validation_nonce = :nonce WHERE id = :id')
            ->execute([':nonce' => $nonce, ':id' => $user['id']]);

        $res = TestHelper::request(
            $this->validateFile,
            'GET',
            [],
            ['id' => $user['id'], 'expires' => time() + 3600, 'token' => 'invalidtoken']
        );

        $this->assertSame(401, $res['status']);
    }

    public function test_expired_validation_link_returns_401(): void
    {
        $user = TestHelper::createUser([
            'username' => 'pending3',
            'email'    => 'pending3@example.com',
            'status'   => 'pending',
        ]);
        $nonce = bin2hex(random_bytes(16));
        db()->prepare('UPDATE users SET validation_nonce = :nonce WHERE id = :id')
            ->execute([':nonce' => $nonce, ':id' => $user['id']]);

        $expired = time() - 10;
        $token   = Mailer::buildToken((int) $user['id'], 'pending3@example.com', $nonce, $expired, (string) env('APP_SECRET'));

        $res = TestHelper::request(
            $this->validateFile,
            'GET',
            [],
            ['id' => $user['id'], 'expires' => $expired, 'token' => $token]
        );

        $this->assertSame(401, $res['status']);
        $status = db()->query("SELECT status FROM users WHERE id = {$user['id']}")->fetchColumn();
        $this->assertSame('pending', $status); // not validated
    }

    public function test_legacy_id_only_token_is_rejected(): void
    {
        // The old scheme (HMAC over id only, no nonce/expiry) must not work.
        $user = TestHelper::createUser([
            'username' => 'pending4',
            'email'    => 'pending4@example.com',
            'status'   => 'pending',
        ]);
        $legacy = hash_hmac('sha256', (string) $user['id'], (string) env('APP_SECRET'));

        $res = TestHelper::request(
            $this->validateFile,
            'GET',
            [],
            ['id' => $user['id'], 'token' => $legacy]
        );

        $this->assertSame(400, $res['status']); // missing expires param
        $status = db()->query("SELECT status FROM users WHERE id = {$user['id']}")->fetchColumn();
        $this->assertSame('pending', $status);
    }

    // -----------------------------------------------------------------------
    // Event date settings
    // -----------------------------------------------------------------------

    public function test_event_date_upsert_stores_in_settings(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->settingsFile, 'POST', [
            'event_date' => '2026-08-24',
        ]);

        $this->assertSame(200, $res['status']);

        $row = db()->query("SELECT value FROM settings WHERE key = 'event_date'")->fetch();
        $this->assertNotFalse($row);
        $this->assertSame('2026-08-24', $row['value']);
    }

    public function test_get_event_date_returns_current_value(): void
    {
        TestHelper::setSetting('event_date', '2026-12-01');
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->settingsFile);

        $this->assertSame(200, $res['status']);
        $this->assertSame('2026-12-01', $res['json']['event_date']);
    }

    public function test_get_allow_late_submissions_defaults_to_false(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->settingsFile);

        $this->assertSame(200, $res['status']);
        $this->assertFalse($res['json']['allow_late_submissions']);
    }

    public function test_post_saves_allow_late_submissions(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->settingsFile, 'POST', [
            'event_date'             => '2026-08-24',
            'allow_late_submissions' => true,
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertTrue($res['json']['allow_late_submissions']);

        $row = db()->query("SELECT value FROM settings WHERE key = 'allow_late_submissions'")->fetch();
        $this->assertNotFalse($row);
        $this->assertSame('1', $row['value']);
    }

    public function test_post_defaults_allow_late_submissions_to_off_when_absent(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->settingsFile, 'POST', [
            'event_date' => '2026-08-24',
        ]);

        $this->assertSame(200, $res['status']);
        $this->assertFalse($res['json']['allow_late_submissions']);

        $row = db()->query("SELECT value FROM settings WHERE key = 'allow_late_submissions'")->fetch();
        $this->assertNotFalse($row);
        $this->assertSame('0', $row['value']);
    }

    // -----------------------------------------------------------------------
    // PUT — substack_url + password fields
    // -----------------------------------------------------------------------

    public function test_put_updates_substack_url(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $user  = TestHelper::createUser(['username' => 'alice', 'email' => 'alice@example.com']);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->usersFile, 'PUT', [
            'substack_url' => 'https://alice.substack.com',
        ], ['id' => $user['id']]);

        $this->assertSame(200, $res['status']);

        $row = db()->query("SELECT substack_url FROM users WHERE id = {$user['id']}")->fetch();
        $this->assertSame('https://alice.substack.com', $row['substack_url']);
    }

    public function test_put_clears_substack_url_when_sent_as_empty_string(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $user  = TestHelper::createUser([
            'username'     => 'bob',
            'email'        => 'bob@example.com',
            'substack_url' => 'https://bob.substack.com',
        ]);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->usersFile, 'PUT', [
            'substack_url' => '',
        ], ['id' => $user['id']]);

        $this->assertSame(200, $res['status']);

        $row = db()->query("SELECT substack_url FROM users WHERE id = {$user['id']}")->fetch();
        $this->assertNull($row['substack_url'], 'Empty substack_url must be stored as NULL');
    }

    public function test_put_resets_password_when_provided(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $user  = TestHelper::createUser(['username' => 'carol', 'email' => 'carol@example.com']);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->usersFile, 'PUT', [
            'password' => 'newpassword99',
        ], ['id' => $user['id']]);

        $this->assertSame(200, $res['status']);

        $row = db()->query("SELECT password_hash FROM users WHERE id = {$user['id']}")->fetch();
        $this->assertTrue(password_verify('newpassword99', $row['password_hash']));
    }

    public function test_put_short_password_returns_422(): void
    {
        $admin = TestHelper::createUser(['username' => 'admin', 'email' => 'admin@example.com', 'is_admin' => 1]);
        $user  = TestHelper::createUser(['username' => 'dave', 'email' => 'dave@example.com']);
        $_SESSION['user_id'] = $admin['id'];

        $res = TestHelper::request($this->usersFile, 'PUT', [
            'password' => 'short',
        ], ['id' => $user['id']]);

        $this->assertSame(422, $res['status']);
    }
}
