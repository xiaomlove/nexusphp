<?php

namespace Tests\Feature\Legacy;

use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Pins down the `/preview.php` HTML-fragment contract.
 *
 * The legacy script echoed `<table>...format_comment($body)...</table>`
 * on POST; callers (the comment editor "Preview" button in
 * `public/js/comments.js`) inject the response straight into the DOM,
 * so the wrapper element shape MUST stay stable.
 */
class PreviewControllerTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    public function test_guest_request_redirects_to_login(): void
    {
        $response = $this->post('/preview.php', ['body' => 'hello']);

        $response->assertRedirect();
        $this->assertStringContainsString('login.php', (string) $response->headers->get('Location'));
    }

    public function test_post_with_body_returns_formatted_comment_table(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/preview.php', ['body' => 'hello world']);

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<table', $body);
        $this->assertStringContainsString('hello world', $body);
        $this->assertStringContainsString('</table>', $body);
    }

    public function test_post_with_empty_body_still_returns_table(): void
    {
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->post('/preview.php', ['body' => '']);

        // Legacy script happily echoed an empty cell; matching that
        // behaviour avoids surprising the comment-editor JS.
        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('<table', $body);
        $this->assertStringContainsString('</table>', $body);
    }

    public function test_get_request_also_returns_table(): void
    {
        // The legacy script accepted `$_POST['body']` only, which
        // PHP returns as `''` for GET requests. The migrated route
        // accepts both methods because some templates render the
        // preview iframe via GET; in that case the body is read from
        // the query string so the JS is symmetric.
        $user = $this->createLegacyUser();
        $this->actingAs($user, 'nexus-web');

        $response = $this->get('/preview.php?body=greetings');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('greetings', $body);
    }
}
