<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Test double for Mailer — counts sendAdminValidation() calls without touching SMTP.
 */
class SpyMailer extends Mailer
{
    public int $callCount = 0;

    public function __construct()
    {
        // Skip parent — no SMTP config required in tests.
    }

    public function sendAdminValidation(array $user): void
    {
        $this->callCount++;
    }
}

/**
 * Tests for api/captcha-question.php and api/register.php (US-1).
 * Path A — tests committed before implementation.
 */
final class RegisterTest extends TestCase
{
    private string $captchaFile;
    private string $registerFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();

        $this->captchaFile  = dirname(__DIR__) . '/api/captcha-question.php';
        $this->registerFile = dirname(__DIR__) . '/api/register.php';

        // Seed a valid captcha index in session
        $_SESSION = ['captcha_index' => 0]; // answer for index 0 is '7'
    }

    protected function tearDown(): void
    {
        // Reset injected mailer so it does not leak between tests.
        Mailer::setTestInstance(null);
    }

    // -----------------------------------------------------------------------
    // Captcha endpoint
    // -----------------------------------------------------------------------

    public function test_captcha_question_returns_index_and_question(): void
    {
        $res = TestHelper::request($this->captchaFile);

        $this->assertSame(200, $res['status']);
        $this->assertArrayHasKey('index', $res['json']);
        $this->assertArrayHasKey('question', $res['json']);
        $this->assertArrayNotHasKey('a', $res['json'], 'Answer must not be in response');
        $this->assertIsInt($res['json']['index']);
        $this->assertGreaterThanOrEqual(0, $res['json']['index']);
        $this->assertLessThanOrEqual(49, $res['json']['index']);
    }

    public function test_captcha_sets_session_index(): void
    {
        TestHelper::request($this->captchaFile);
        $this->assertArrayHasKey('captcha_index', $_SESSION);
    }

    // -----------------------------------------------------------------------
    // Registration — success
    // -----------------------------------------------------------------------

    public function test_valid_registration_returns_201_and_user_pending(): void
    {
        $_SESSION = ['captcha_index' => 0]; // answer '7'

        $res = TestHelper::request($this->registerFile, 'POST', [
            'username'       => 'alice',
            'name'           => 'Alice Example',
            'substack_url'   => 'https://alice.substack.com',
            'email'          => 'alice@example.com',
            'password'       => 'securepassword',
            'timezone'       => 'UTC',
            'captcha_answer' => '7',
        ]);

        $this->assertSame(201, $res['status']);
        $this->assertStringContainsString('Awaiting admin approval', $res['body']);

        $user = db()->query("SELECT * FROM users WHERE username = 'alice'")->fetch();
        $this->assertNotFalse($user);
        $this->assertSame('pending', $user['status']);
    }

    public function test_password_stored_as_bcrypt_not_plaintext(): void
    {
        $_SESSION = ['captcha_index' => 0];

        TestHelper::request($this->registerFile, 'POST', [
            'username'       => 'bob',
            'name'           => 'Bob',
            'email'          => 'bob@example.com',
            'password'       => 'mypassword123',
            'timezone'       => 'UTC',
            'captcha_answer' => '7',
        ]);

        $user = db()->query("SELECT * FROM users WHERE username = 'bob'")->fetch();
        $this->assertNotFalse($user);
        $this->assertNotSame('mypassword123', $user['password_hash']);
        $this->assertTrue(password_verify('mypassword123', $user['password_hash']));
    }

    // -----------------------------------------------------------------------
    // Registration — conflicts (409)
    // -----------------------------------------------------------------------

    public function test_duplicate_username_returns_409_with_field(): void
    {
        TestHelper::createUser(['username' => 'carol', 'email' => 'carol@example.com']);
        $_SESSION = ['captcha_index' => 0];

        $res = TestHelper::request($this->registerFile, 'POST', [
            'username'       => 'carol',
            'name'           => 'Carol 2',
            'email'          => 'carol2@example.com',
            'password'       => 'password123',
            'timezone'       => 'UTC',
            'captcha_answer' => '7',
        ]);

        $this->assertSame(409, $res['status']);
        $this->assertStringContainsString('username', $res['body']);
    }

    public function test_duplicate_email_returns_409_with_field(): void
    {
        TestHelper::createUser(['username' => 'dave', 'email' => 'dave@example.com']);
        $_SESSION = ['captcha_index' => 0];

        $res = TestHelper::request($this->registerFile, 'POST', [
            'username'       => 'dave2',
            'name'           => 'Dave 2',
            'email'          => 'dave@example.com',
            'password'       => 'password123',
            'timezone'       => 'UTC',
            'captcha_answer' => '7',
        ]);

        $this->assertSame(409, $res['status']);
        $this->assertStringContainsString('email', $res['body']);
    }

    // -----------------------------------------------------------------------
    // Registration — validation failures (422)
    // -----------------------------------------------------------------------

    public function test_invalid_timezone_returns_422(): void
    {
        $_SESSION = ['captcha_index' => 0];

        $res = TestHelper::request($this->registerFile, 'POST', [
            'username'       => 'eve',
            'name'           => 'Eve',
            'email'          => 'eve@example.com',
            'password'       => 'password123',
            'timezone'       => 'NotATimezone/Invalid',
            'captcha_answer' => '7',
        ]);

        $this->assertSame(422, $res['status']);
    }

    public function test_wrong_captcha_answer_returns_422(): void
    {
        $_SESSION = ['captcha_index' => 0]; // correct answer is '7'

        $res = TestHelper::request($this->registerFile, 'POST', [
            'username'       => 'frank',
            'name'           => 'Frank',
            'email'          => 'frank@example.com',
            'password'       => 'password123',
            'timezone'       => 'UTC',
            'captcha_answer' => 'wrong',
        ]);

        $this->assertSame(422, $res['status']);
    }

    public function test_missing_required_field_returns_422(): void
    {
        $_SESSION = ['captcha_index' => 0];

        $res = TestHelper::request($this->registerFile, 'POST', [
            // missing username
            'name'           => 'Grace',
            'email'          => 'grace@example.com',
            'password'       => 'password123',
            'timezone'       => 'UTC',
            'captcha_answer' => '7',
        ]);

        $this->assertSame(422, $res['status']);
    }

    // -----------------------------------------------------------------------
    // Registration — Mailer invocation (MAJOR 2)
    // -----------------------------------------------------------------------

    public function test_mailer_called_once_on_success(): void
    {
        $spy = new SpyMailer();
        Mailer::setTestInstance($spy);

        $_SESSION = ['captcha_index' => 0];

        $res = TestHelper::request($this->registerFile, 'POST', [
            'username'       => 'mailertest',
            'name'           => 'Mailer Test',
            'email'          => 'mailertest@example.com',
            'password'       => 'password123',
            'timezone'       => 'UTC',
            'captcha_answer' => '7',
        ]);

        $this->assertSame(201, $res['status']);
        $this->assertSame(1, $spy->callCount, 'sendAdminValidation() must be called exactly once on successful registration');
    }

    // -----------------------------------------------------------------------
    // Method guard — 405 (MINOR 6)
    // -----------------------------------------------------------------------

    public function test_non_post_method_returns_405(): void
    {
        $res = TestHelper::request($this->registerFile, 'GET');
        $this->assertSame(405, $res['status']);
    }

    // -----------------------------------------------------------------------
    // Registration — event closed (423)
    // -----------------------------------------------------------------------

    public function test_registration_closed_when_event_date_is_today_returns_423(): void
    {
        TestHelper::setSetting('event_date', date('Y-m-d'));
        $_SESSION = ['captcha_index' => 0];

        $res = TestHelper::request($this->registerFile, 'POST', [
            'username'       => 'henry',
            'name'           => 'Henry',
            'email'          => 'henry@example.com',
            'password'       => 'password123',
            'timezone'       => 'UTC',
            'captcha_answer' => '7',
        ]);

        $this->assertSame(423, $res['status']);
    }
}
