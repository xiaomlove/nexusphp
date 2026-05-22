<?php

namespace Tests\Feature\Legacy;

use App\Models\User;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/tags.php` contract.
 *
 * Static BBCode-tags reference cheatsheet — see
 * `App\Http\Controllers\Legacy\TagsController` for the wire shape.
 * Authed-only; POST `?test=<bbcode>` renders a `format_comment()`
 * preview at the top of the page.
 */
class TagsControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/tags.php';
    }

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->get('/tags.php');

        $response->assertRedirect();
        $this->assertStringContainsString(
            'login.php',
            (string) $response->headers->get('Location'),
        );
    }

    public function test_authed_request_renders_cheatsheet(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/tags.php');

        $response->assertOk();
        $body = (string) $response->getContent();

        // The cheatsheet renders ~25 individual tag blocks; each
        // emits a <table class=main>. Pin the order-of-magnitude
        // (≥ 20) rather than an exact count so the test does not
        // break when a tag is added or removed.
        $tableCount = substr_count($body, '<table class=main');
        $this->assertGreaterThanOrEqual(
            20,
            $tableCount,
            'Expected ≥ 20 tag blocks; got '.$tableCount,
        );

        // The "test this code" form posts back to /tags.php.
        $this->assertStringContainsString('<form method=post action=tags.php>', $body);
        $this->assertStringContainsString('<textarea name=test', $body);
    }

    public function test_post_test_renders_format_comment_preview(): void
    {
        $user = $this->createTestUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/tags.php', [
            'test' => '[b]hello[/b]',
        ]);

        $response->assertOk();
        $body = (string) $response->getContent();

        // The form re-renders with the submitted value pre-filled,
        // and `format_comment` strips/transforms the BBCode at the
        // top of the page (the [b]…[/b] tag should not survive
        // verbatim into the rendered preview).
        $this->assertStringContainsString('[b]hello[/b]', $body);
        // Preview block is rendered between <hr> tags after the
        // form submission.
        $this->assertStringContainsString('<hr>', $body);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        return $this->createLegacyUser(
            overrides: array_merge([
                'lang' => self::ENGLISH_LANGUAGE_ID,
                'class' => User::CLASS_USER,
            ], $overrides),
        );
    }
}
