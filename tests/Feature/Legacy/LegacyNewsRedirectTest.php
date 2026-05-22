<?php

namespace Tests\Feature\Legacy;

use Tests\FeatureTestCase;

/**
 * Pin the URL-preservation contract introduced when `public/news.php`
 * was replaced by the Filament resource at `/nexusphp/news`. Any
 * caller that still hits the legacy URL — admin bookmarks, the
 * `[news page]` link rendered by `public/index.php` for admins, a
 * stray `<a href="news.php?action=edit&newsid=N">` in an old
 * screenshot — must land on the new admin instead of a 404.
 *
 * The redirect is registered with `Route::any('/news.php', ...)` so
 * every verb-and-querystring shape the legacy script accepted (GET
 * default, GET `?action=edit&newsid=N`, GET
 * `?action=delete&newsid=N&sure=1`, POST `?action=add` form submit,
 * POST `?action=edit&newsid=N` form submit) lands on the new
 * Filament list page.
 */
class LegacyNewsRedirectTest extends FeatureTestCase
{
    public function test_get_news_redirects_to_filament(): void
    {
        $this->get('/news.php')
            ->assertRedirect('/nexusphp/news')
            ->assertStatus(302);
    }

    public function test_get_news_with_edit_action_redirects_to_filament(): void
    {
        $this->get('/news.php?action=edit&newsid=42')
            ->assertRedirect('/nexusphp/news')
            ->assertStatus(302);
    }

    public function test_get_news_with_delete_action_redirects_to_filament(): void
    {
        $this->get('/news.php?action=delete&newsid=42&sure=1')
            ->assertRedirect('/nexusphp/news')
            ->assertStatus(302);
    }

    public function test_post_news_add_redirects_to_filament(): void
    {
        // The legacy compose form posted to `/news.php?action=add`.
        // Some browsers will retry an in-flight POST against the
        // redirect target — that's fine, the user lands on the new
        // admin and re-issues the create through Filament.
        $this->post('/news.php?action=add', [
            'subject' => 'Some news title',
            'body' => 'Some news body',
            'notify' => 'yes',
        ])
            ->assertRedirect('/nexusphp/news')
            ->assertStatus(302);
    }

    public function test_post_news_edit_redirects_to_filament(): void
    {
        $this->post('/news.php?action=edit&newsid=42', [
            'subject' => 'Updated title',
            'body' => 'Updated body',
        ])
            ->assertRedirect('/nexusphp/news')
            ->assertStatus(302);
    }
}
