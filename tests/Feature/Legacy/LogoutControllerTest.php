<?php

namespace Tests\Feature\Legacy;

use Tests\FeatureTestCase;

/**
 * Pins down the `/logout.php` contract.
 *
 * The legacy script called `logoutcookie()` (clears `c_secure_pass`)
 * and `nexus_redirect("/")`. The migrated controller drops the same
 * cookie with a past expiry and redirects to `/`.
 *
 * Both guest and authenticated requests are accepted — `/logout.php`
 * is reachable as a guest in the legacy code (it just no-ops cookie
 * clearing for an already-clean client) and we preserve that.
 */
class LogoutControllerTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // `LogUserIp` middleware reads `$_SERVER['REQUEST_URI']`
        // directly. The Laravel test client populates the `Request`
        // object but not `$_SERVER` — seed it so the global
        // middleware stack doesn't blow up.
        $_SERVER['REQUEST_URI'] = '/logout.php';
    }

    public function test_logout_redirects_to_root(): void
    {
        $response = $this->get('/logout.php');

        $response->assertRedirect('/');
    }

    public function test_logout_drops_legacy_auth_cookie(): void
    {
        $response = $this->get('/logout.php');

        $cookie = $response->getCookie('c_secure_pass');
        $this->assertNotNull(
            $cookie,
            'Expected the response to drop a `c_secure_pass` cookie.',
        );
        $this->assertSame('', $cookie->getValue());
        $this->assertLessThan(
            time(),
            $cookie->getExpiresTime(),
            'Expected the cookie expiry to be in the past so the browser clears it.',
        );
    }

    public function test_logout_accepts_post(): void
    {
        // Some legacy JS calls `/logout.php` via XHR POST rather than
        // a link click. The route declares `Route::any(...)` to keep
        // both verbs working.
        $response = $this->post('/logout.php');

        $response->assertRedirect('/');
    }
}
