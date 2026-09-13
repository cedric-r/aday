<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;

/**
 * Tests for the Origin / Sec-Fetch-Site CSRF guard in config/session.php.
 *
 * The guard must:
 *   - allow same-origin writes, including when the site runs on a NON-DEFAULT
 *     port (HTTP_HOST then carries the port — a host-only comparison used to
 *     reject these, breaking every write on such a deployment);
 *   - reject a different port on the same host (cross-origin);
 *   - reject a different host;
 *   - leave non-browser clients (no Origin header) alone.
 */
final class CsrfOriginTest extends TestCase
{
    private string $photosFile;

    protected function setUp(): void
    {
        TestHelper::resetDb();
        $_SESSION = [];
        $this->photosFile = dirname(__DIR__) . '/api/photos.php';

        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_SEC_FETCH_SITE']);
    }

    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_SEC_FETCH_SITE'], $_SERVER['HTTP_HOST']);
    }

    /** @return array{status:int,json:mixed} */
    private function post(string $origin, string $host, string $fetchSite = ''): array
    {
        $_SERVER['HTTP_HOST']   = $host;
        $_SERVER['HTTP_ORIGIN'] = $origin;
        if ($fetchSite !== '') {
            $_SERVER['HTTP_SEC_FETCH_SITE'] = $fetchSite;
        } else {
            unset($_SERVER['HTTP_SEC_FETCH_SITE']);
        }

        // Unauthenticated, so an allowed request falls through to the auth
        // guard (401) — distinguishable from a CSRF rejection (403).
        return TestHelper::request($this->photosFile, 'POST');
    }

    private function isCrossOriginRejection(array $res): bool
    {
        $message = is_array($res['json']) ? ($res['json']['message'] ?? '') : '';
        return $res['status'] === 403 && str_contains((string) $message, 'Cross-origin');
    }

    #[RunInSeparateProcess]
    public function test_same_origin_on_non_default_port_is_allowed(): void
    {
        // The regression this guards: HTTP_HOST carries :8899, the Origin host
        // does not. A host-only comparison rejected it.
        $res = $this->post('http://127.0.0.1:8899', '127.0.0.1:8899');

        $this->assertFalse($this->isCrossOriginRejection($res), 'same-origin on a custom port must not be rejected');
        $this->assertSame(401, $res['status']); // reached the auth guard instead
    }

    #[RunInSeparateProcess]
    public function test_same_origin_on_default_port_is_allowed(): void
    {
        $res = $this->post('https://aday.photoni.st', 'aday.photoni.st');

        $this->assertFalse($this->isCrossOriginRejection($res));
        $this->assertSame(401, $res['status']);
    }

    #[RunInSeparateProcess]
    public function test_explicit_default_port_is_normalised(): void
    {
        // Browsers omit the default port from Host; an Origin that spells it
        // out must still match.
        $res = $this->post('https://aday.photoni.st:443', 'aday.photoni.st');

        $this->assertFalse($this->isCrossOriginRejection($res));
        $this->assertSame(401, $res['status']);
    }

    #[RunInSeparateProcess]
    public function test_different_port_same_host_is_rejected(): void
    {
        // A cross-origin caller on another port of the same host used to slip
        // through the host-only comparison.
        $res = $this->post('http://127.0.0.1:9999', '127.0.0.1:8899');

        $this->assertTrue($this->isCrossOriginRejection($res), 'different port must be rejected');
    }

    #[RunInSeparateProcess]
    public function test_different_host_is_rejected(): void
    {
        $res = $this->post('https://evil.example', 'aday.photoni.st');

        $this->assertTrue($this->isCrossOriginRejection($res));
    }

    #[RunInSeparateProcess]
    public function test_no_origin_header_is_allowed(): void
    {
        // Non-browser clients (curl, cron) send no Origin.
        $_SERVER['HTTP_HOST'] = 'aday.photoni.st';
        unset($_SERVER['HTTP_ORIGIN'], $_SERVER['HTTP_SEC_FETCH_SITE']);

        $res = TestHelper::request($this->photosFile, 'POST');

        $this->assertFalse($this->isCrossOriginRejection($res));
        $this->assertSame(401, $res['status']);
    }

    #[RunInSeparateProcess]
    public function test_cross_site_fetch_metadata_is_rejected(): void
    {
        $res = $this->post('', 'aday.photoni.st', 'cross-site');

        $this->assertTrue($this->isCrossOriginRejection($res));
    }

    #[RunInSeparateProcess]
    public function test_get_is_not_origin_checked(): void
    {
        $_SERVER['HTTP_HOST']   = 'aday.photoni.st';
        $_SERVER['HTTP_ORIGIN'] = 'https://evil.example';

        $res = TestHelper::request($this->photosFile, 'GET');

        $this->assertSame(200, $res['status']);
    }
}
