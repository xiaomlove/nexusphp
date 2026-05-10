<?php

namespace Tests\Feature\Legacy;

use App\Http\Controllers\Legacy\LegacyPageController;
use App\Legacy\LegacyContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the contract of the Phase 1 "wrap" controller.
 *
 * Verifies:
 *
 *  - The allowlist is enforced (unknown page → 404, file-not-found → 404).
 *  - `$GLOBALS['CURUSER']` is populated from the typed seam, not from
 *    `userlogin()`. Guest requests get `null`; authenticated requests
 *    get the legacy-shaped array.
 *  - The controller returns the buffered legacy `echo` output as a
 *    Symfony `Response`, so Laravel middleware can inspect/wrap it.
 *
 * The production allowlist {@see LegacyPageController::ALLOWED} is
 * intentionally empty in Phase 1 — adding pages is gated on a
 * per-page process-isolation review (legacy code that calls `die()`
 * mid-render kills the FPM worker). We use a tiny test subclass
 * below to exercise the rendering path against a fixture file.
 */
class LegacyPageControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset the global the controller writes into — otherwise a
        // previous test (or the harness) could leak `$CURUSER` into
        // the next run.
        unset($GLOBALS['CURUSER']);
    }

    public function test_unknown_page_returns_404(): void
    {
        $controller = $this->makeProductionController();

        try {
            $controller(Request::create('/legacy-test'), 'does-not-exist');
            $this->fail('Expected HttpException(404) for unknown page.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_allowlist_default_is_empty_in_phase_1(): void
    {
        // Use the real production controller binding (empty ALLOWED).
        // Even a page that *does* exist on disk is rejected unless it
        // has been added to the allowlist by an explicit follow-up PR.
        $controller = $this->app->make(LegacyPageController::class);

        try {
            // public/index.php exists, but it must not render through
            // the wrap controller until somebody explicitly
            // allowlists it.
            $controller(Request::create('/legacy-test'), 'index');
            $this->fail('Expected HttpException(404) when allowlist is empty.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_allowlisted_page_renders_with_guest_curuser(): void
    {
        $controller = $this->makeFixtureController();

        $response = $controller(Request::create('/legacy-test'), 'sample');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            'sample-fixture-rendered|user=guest',
            $response->getContent(),
            'A guest request must populate $GLOBALS[CURUSER] with null '
            .'(legacy code reading $CURUSER on guest pages then falls '
            .'into its own guest branch).'
        );
        $this->assertNull($GLOBALS['CURUSER']);
    }

    public function test_allowlisted_page_renders_with_authenticated_curuser(): void
    {
        $user = $this->createLegacyUser(overrides: ['username' => 'wraptest']);
        $this->actingAs($user, 'nexus-web');

        $controller = $this->makeFixtureController();

        $response = $controller(Request::create('/legacy-test'), 'sample');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            'sample-fixture-rendered|user=wraptest',
            $response->getContent(),
            'An authenticated request must populate $GLOBALS[CURUSER] '
            .'with the legacy-shaped array; this is what lets us drop '
            .'the in-script `userlogin()` call once a page is wrapped.'
        );
        $this->assertIsArray($GLOBALS['CURUSER']);
        $this->assertSame('wraptest', $GLOBALS['CURUSER']['username']);
    }

    public function test_missing_file_returns_404_even_when_allowlisted(): void
    {
        // An entry on the allowlist that no longer has a corresponding
        // file on disk (a deletion regression) must surface as a 404,
        // not a TypeError / "include failed" stack trace.
        $controller = new class($this->app->make(LegacyContext::class), __DIR__.'/../../Fixtures/Legacy') extends LegacyPageController
        {
            protected const ALLOWED = ['gone'];
        };

        try {
            $controller(Request::create('/legacy-test'), 'gone');
            $this->fail('Expected HttpException(404) for missing legacy file.');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    private function makeProductionController(): LegacyPageController
    {
        // Force the production binding even if a previous test rebound
        // the controller for fixture rendering.
        return new LegacyPageController(
            $this->app->make(LegacyContext::class),
            base_path('public'),
        );
    }

    private function makeFixtureController(): LegacyPageController
    {
        return new class($this->app->make(LegacyContext::class), __DIR__.'/../../Fixtures/Legacy') extends LegacyPageController
        {
            protected const ALLOWED = ['sample'];
        };
    }
}
