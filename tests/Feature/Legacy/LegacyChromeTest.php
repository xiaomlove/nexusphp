<?php

namespace Tests\Feature\Legacy;

use App\Legacy\LegacyChrome;
use Illuminate\Support\Facades\File;
use Tests\FeatureTestCase;

/**
 * Pins down the legacy chrome shim. See `app/Legacy/LegacyChrome.php`
 * and Phase 1.3 in `docs/legacy-strategy.md`.
 *
 * The real production bootstrap (`include/bittorrent.php`) drags in
 * 6 MB of legacy globals and is not friendly to repeated loading
 * inside one process — so the tests point `LegacyChrome` at a
 * lightweight fixture that defines its own `legacy_chrome_test_*`
 * helpers and override `legacy.chrome.head_function` /
 * `legacy.chrome.foot_function` to call them. We intentionally do NOT
 * shadow the global `stdhead` / `stdfoot` symbols: `bootstrap/app.php`
 * loads `include/functions.php` early, so those names are already
 * defined in the PHPUnit process and a redeclaration would be a fatal
 * error.
 */
class LegacyChromeTest extends FeatureTestCase
{
    private string $bootstrapFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bootstrapFile = storage_path(
            'app/legacy-chrome-fixture-'.bin2hex(random_bytes(4)).'.php'
        );
        File::put($this->bootstrapFile, <<<'PHP'
<?php
if (!function_exists('legacy_chrome_test_stdhead')) {
    function legacy_chrome_test_stdhead($title = '', $msgalert = true) {
        echo '[HEAD title='.$title.';alert='.($msgalert ? '1' : '0').']';
    }
}
if (!function_exists('legacy_chrome_test_stdfoot')) {
    function legacy_chrome_test_stdfoot() {
        echo '[FOOT]';
    }
}
PHP);

        config([
            'legacy.bootstrap_file' => $this->bootstrapFile,
            'legacy.chrome.head_function' => 'legacy_chrome_test_stdhead',
            'legacy.chrome.foot_function' => 'legacy_chrome_test_stdfoot',
        ]);

        // Force a fresh singleton — `bootstrapped` state is per-instance.
        $this->app->forgetInstance(LegacyChrome::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->bootstrapFile) && File::exists($this->bootstrapFile)) {
            File::delete($this->bootstrapFile);
        }
        parent::tearDown();
    }

    public function test_head_captures_stdhead_output(): void
    {
        $chrome = $this->app->make(LegacyChrome::class);

        $html = $chrome->head('Logout');

        $this->assertSame('[HEAD title=Logout;alert=1]', $html);
    }

    public function test_head_passes_msgalert_flag(): void
    {
        $chrome = $this->app->make(LegacyChrome::class);

        $html = $chrome->head('NoAlerts', false);

        $this->assertSame('[HEAD title=NoAlerts;alert=0]', $html);
    }

    public function test_foot_captures_stdfoot_output(): void
    {
        $chrome = $this->app->make(LegacyChrome::class);

        $this->assertSame('[FOOT]', $chrome->foot());
    }

    public function test_blade_layout_wraps_content_in_chrome(): void
    {
        $rendered = view('layouts.legacy', [
            'title' => 'Welcome',
            'content' => '<p>middle</p>',
        ])->render();

        $this->assertStringContainsString('[HEAD title=Welcome;alert=1]', $rendered);
        $this->assertStringContainsString('<p>middle</p>', $rendered);
        $this->assertStringContainsString('[FOOT]', $rendered);

        // Order matters — head before content before foot.
        $headPos = strpos($rendered, '[HEAD');
        $bodyPos = strpos($rendered, '<p>middle</p>');
        $footPos = strpos($rendered, '[FOOT]');
        $this->assertNotFalse($headPos);
        $this->assertNotFalse($bodyPos);
        $this->assertNotFalse($footPos);
        $this->assertTrue($headPos < $bodyPos, 'stdhead() must render before content');
        $this->assertTrue($bodyPos < $footPos, 'stdfoot() must render after content');
    }

    public function test_throws_when_bootstrap_file_missing(): void
    {
        config(['legacy.bootstrap_file' => '/dev/null/does-not-exist.php']);
        $this->app->forgetInstance(LegacyChrome::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('legacy.bootstrap_file');

        $this->app->make(LegacyChrome::class)->head();
    }

    public function test_throws_when_configured_head_function_is_unknown(): void
    {
        config(['legacy.chrome.head_function' => 'legacy_chrome_test_unknown_function']);
        $this->app->forgetInstance(LegacyChrome::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('legacy_chrome_test_unknown_function');

        $this->app->make(LegacyChrome::class)->head();
    }
}
