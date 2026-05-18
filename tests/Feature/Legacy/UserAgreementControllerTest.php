<?php

namespace Tests\Feature\Legacy;

use Database\Seeders\TestingDataSeeder;
use Illuminate\Support\Facades\DB;
use Tests\FeatureTestCase;

/**
 * Pins down the `/useragreement.php` HTML contract.
 *
 * The legacy script was a guest-accessible static legal text wrapped
 * in `stdhead`/`begin_main_frame`/`begin_frame` site chrome, with
 * `<?php echo $SITENAME ?>`, `<?php echo $BASEURL ?>`, and
 * `<?php echo $baseUrl ?>` interpolated into the body. The migrated
 * controller keeps the URL stable, drops the legacy chrome (chrome-less
 * envelope with `NexusPHP :: User Agreement` inline), and runs the
 * settings/host substitutions through Blade `{{ }}` autoescaping.
 */
class UserAgreementControllerTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $defaultsLoaded = DB::table('settings')
            ->where('name', 'main.defaultlang')
            ->exists();
        if (! $defaultsLoaded) {
            (new TestingDataSeeder)->run();
        }

        $_SERVER['REQUEST_URI'] = '/useragreement.php';
    }

    public function test_guest_can_view_useragreement(): void
    {
        $response = $this->get('/useragreement.php');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/html; charset=UTF-8');
        $this->assertStringContainsString(
            '<title>NexusPHP :: User Agreement</title>',
            (string) $response->getContent(),
        );
    }

    public function test_body_includes_first_and_last_legal_paragraphs(): void
    {
        $response = $this->get('/useragreement.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        $this->assertStringContainsString(
            'Using this site means you accept its terms.',
            $body,
        );
        $this->assertStringContainsString(
            'BY USING THIS WEBSITE, YOU INDICATE YOUR AGREEMENT',
            $body,
        );
        $this->assertStringContainsString(
            'All rights reserved.',
            $body,
        );
    }

    public function test_sitename_setting_is_interpolated_into_body(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'basic.SITENAME'],
            ['value' => 'Acme Tracker', 'updated_at' => now()],
        );

        $response = $this->get('/useragreement.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString(
            'enjoy viewing these historic treasures on the Acme Tracker website',
            $body,
        );
    }

    public function test_baseurl_setting_is_interpolated_into_body(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'basic.BASEURL'],
            ['value' => 'tracker.example.test', 'updated_at' => now()],
        );

        $response = $this->get('/useragreement.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString(
            'Copyright © 2007 by tracker.example.test.',
            $body,
        );
    }

    public function test_scheme_and_host_is_interpolated_into_body(): void
    {
        $response = $this->get('http://nexus.example.test/useragreement.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString(
            'Links from other websites to <http://nexus.example.test>',
            $body,
        );
    }

    public function test_sitename_with_markup_is_html_escaped(): void
    {
        DB::table('settings')->updateOrInsert(
            ['name' => 'basic.SITENAME'],
            ['value' => '<script>alert(1)</script>', 'updated_at' => now()],
        );

        $response = $this->get('/useragreement.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function test_body_does_not_render_legacy_chrome_helpers(): void
    {
        $response = $this->get('/useragreement.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('stdhead(', $body);
        $this->assertStringNotContainsString('begin_main_frame(', $body);
        $this->assertStringNotContainsString('begin_frame(', $body);
        $this->assertStringNotContainsString('end_frame(', $body);
        $this->assertStringNotContainsString('stdfoot(', $body);
        $this->assertStringNotContainsString('<?php', $body);
    }

    public function test_post_request_returns_method_not_allowed(): void
    {
        $response = $this->post('/useragreement.php', []);

        $this->assertSame(405, $response->getStatusCode());
    }
}
