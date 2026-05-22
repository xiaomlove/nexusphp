<?php

namespace Tests\Feature\Legacy;

use Tests\FeatureTestCase;

/**
 * Pin the URL-preservation contract introduced when
 * `public/faqmanage.php` and `public/faqactions.php` were replaced
 * by the Filament resource at `/nexusphp/faqs`. Any caller that
 * still hits the legacy URLs — admin bookmarks, the
 * `AdminpanelTableSeeder` row that pre-dates the seeder migration,
 * a stray `<a href="faqactions.php?action=edit&id=...">` link in
 * an old screenshot — must land on the new admin instead of a 404.
 */
class LegacyFaqAdminRedirectTest extends FeatureTestCase
{
    public function test_get_faqmanage_redirects_to_filament(): void
    {
        $this->get('/faqmanage.php')
            ->assertRedirect('/nexusphp/faqs')
            ->assertStatus(302);
    }

    public function test_get_faqactions_redirects_to_filament(): void
    {
        $this->get('/faqactions.php?action=edit&id=99999')
            ->assertRedirect('/nexusphp/faqs')
            ->assertStatus(302);
    }

    public function test_post_faqactions_redirects_to_filament(): void
    {
        // The legacy edit form posted to `/faqactions.php?action=edititem`.
        // Some browsers will retry an in-flight POST against the redirect
        // target — that's fine, the user lands on the new admin and
        // re-issues the edit through Filament.
        $this->post('/faqactions.php?action=edititem', [
            'id' => 1,
            'question' => 'q',
            'answer' => 'a',
            'flag' => 1,
            'categ' => 1,
        ])
            ->assertRedirect('/nexusphp/faqs')
            ->assertStatus(302);
    }
}
