<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test double for Mailer — records sendUserValidated() calls without SMTP.
 */
class RecordingValidatedMailer extends Mailer
{
    /** @var list<array<string, mixed>> */
    public array $validatedUsers = [];

    public function __construct()
    {
        // Skip parent — no SMTP config in tests.
    }

    public function sendUserValidated(array $user): void
    {
        $this->validatedUsers[] = $user;
    }
}

/**
 * Tests that users are emailed when an admin validates them, across all three
 * admin validation paths: the HMAC link, the admin edit (PUT), and admin create (POST).
 */
final class UserValidatedEmailTest extends TestCase
{
    private string $validateFile;
    private string $usersFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];
        $this->validateFile = dirname(__DIR__) . '/api/admin/validate.php';
        $this->usersFile    = dirname(__DIR__) . '/api/admin/users.php';
    }

    protected function tearDown(): void
    {
        Mailer::setTestInstance(null);
    }

    private function installSpy(): RecordingValidatedMailer
    {
        $spy = new RecordingValidatedMailer();
        Mailer::setTestInstance($spy);
        return $spy;
    }

    private function loginAsAdmin(): array
    {
        $admin = TestHelper::createUser(['username' => 'rootadmin', 'email' => 'rootadmin@example.com', 'is_admin' => 1]);
        $_SESSION['user_id'] = $admin['id'];
        return $admin;
    }

    // -----------------------------------------------------------------------
    // validate.php (HMAC link)
    // -----------------------------------------------------------------------

    public function test_validate_link_emails_the_user_on_success(): void
    {
        $spy = $this->installSpy();
        $user = TestHelper::createUser([
            'username' => 'pendlink',
            'name'     => 'Pend Link',
            'email'    => 'pendlink@example.com',
            'status'   => 'pending',
        ]);

        $nonce   = bin2hex(random_bytes(16));
        db()->prepare('UPDATE users SET validation_nonce = :nonce WHERE id = :id')
            ->execute([':nonce' => $nonce, ':id' => $user['id']]);

        $expires = time() + 3600;
        $token   = Mailer::buildToken((int) $user['id'], 'pendlink@example.com', $nonce, $expires, (string) env('APP_SECRET'));

        $res = TestHelper::request(
            $this->validateFile,
            'GET',
            [],
            ['id' => $user['id'], 'expires' => $expires, 'token' => $token]
        );

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $spy->validatedUsers, 'validation email sent on success');
        $this->assertSame('pendlink@example.com', $spy->validatedUsers[0]['email']);
        $this->assertSame('Pend Link', $spy->validatedUsers[0]['name']);
    }

    public function test_validate_link_does_not_email_when_already_validated(): void
    {
        $spy = $this->installSpy();
        $user = TestHelper::createUser([
            'username' => 'alr',
            'email'    => 'alr@example.com',
            'status'   => 'validated',
            'validation_nonce' => null,
        ]);

        $res = TestHelper::request(
            $this->validateFile,
            'GET',
            [],
            ['id' => $user['id'], 'expires' => time() + 3600, 'token' => 'anything']
        );

        // Already-validated path returns gracefully (200) but must NOT email again.
        $this->assertSame(200, $res['status']);
        $this->assertCount(0, $spy->validatedUsers);
    }

    // -----------------------------------------------------------------------
    // users.php PUT (admin edit)
    // -----------------------------------------------------------------------

    public function test_put_transition_to_validated_emails_user(): void
    {
        $spy = $this->installSpy();
        $this->loginAsAdmin();
        $user = TestHelper::createUser([
            'username' => 'pendput',
            'name'     => 'Pend Put',
            'email'    => 'pendput@example.com',
            'status'   => 'pending',
        ]);

        $res = TestHelper::request(
            $this->usersFile,
            'PUT',
            ['status' => 'validated'],
            ['id' => $user['id']]
        );

        $this->assertSame(200, $res['status']);
        $this->assertCount(1, $spy->validatedUsers);
        $this->assertSame('pendput@example.com', $spy->validatedUsers[0]['email']);
    }

    public function test_put_without_status_change_does_not_email(): void
    {
        $spy = $this->installSpy();
        $this->loginAsAdmin();
        $user = TestHelper::createUser([
            'username' => 'noput',
            'email'    => 'noput@example.com',
            'status'   => 'validated',
        ]);

        // No status in payload → no transition → no email.
        $res = TestHelper::request(
            $this->usersFile,
            'PUT',
            ['name' => 'Renamed'],
            ['id' => $user['id']]
        );

        $this->assertSame(200, $res['status']);
        $this->assertCount(0, $spy->validatedUsers);
    }

    public function test_put_already_validated_does_not_email(): void
    {
        $spy = $this->installSpy();
        $this->loginAsAdmin();
        $user = TestHelper::createUser([
            'username' => 'alrput',
            'email'    => 'alrput@example.com',
            'status'   => 'validated',
        ]);

        $res = TestHelper::request(
            $this->usersFile,
            'PUT',
            ['status' => 'validated'],
            ['id' => $user['id']]
        );

        $this->assertSame(200, $res['status']);
        $this->assertCount(0, $spy->validatedUsers, 'validated→validated is not a transition');
    }

    public function test_put_to_pending_or_disabled_does_not_email(): void
    {
        $spy = $this->installSpy();
        $this->loginAsAdmin();
        $user = TestHelper::createUser([
            'username' => 'tozero',
            'email'    => 'tozero@example.com',
            'status'   => 'validated',
        ]);

        $res = TestHelper::request(
            $this->usersFile,
            'PUT',
            ['status' => 'pending'],
            ['id' => $user['id']]
        );

        $this->assertSame(200, $res['status']);
        $this->assertCount(0, $spy->validatedUsers);
    }

    // -----------------------------------------------------------------------
    // users.php POST (admin create)
    // -----------------------------------------------------------------------

    public function test_post_creating_validated_user_emails_them(): void
    {
        $spy = $this->installSpy();
        $this->loginAsAdmin();

        $res = TestHelper::request($this->usersFile, 'POST', [
            'username' => 'newbie',
            'name'     => 'Newbie',
            'email'    => 'newbie@example.com',
            'password' => 'password123',
            'timezone' => 'Europe/Paris',
        ]);

        $this->assertSame(201, $res['status']);
        $this->assertCount(1, $spy->validatedUsers);
        $this->assertSame('newbie@example.com', $spy->validatedUsers[0]['email']);
        $this->assertSame('Newbie', $spy->validatedUsers[0]['name']);
    }
}
