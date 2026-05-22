<?php

namespace Tests\Feature\Legacy;

use Tests\FeatureTestCase;

/**
 * Pin the URL-preservation contract introduced when `public/catmanage.php`
 * (the 11-tab legacy admin hub for the `categories` / `sources` / `media`
 * / `codecs` / `standards` / `processings` / `teams` / `audiocodecs` /
 * `searchbox` / `caticon` / `secondicon` lookup tables) was strangled
 * onto the existing Filament resources at `/nexusphp/{slug}`.
 *
 * Every legacy URL shape — bare `/catmanage.php`, the per-tab variant
 * (`?action=view&type=...`), the per-row edit/delete variants
 * (`?action=edit&type=...&id=...`, `?action=del&type=...&id=...`), and
 * the form-submit verbs (`POST /catmanage.php?action=update`) — must
 * 302 to `/nexusphp/categories`. From there the admin reaches every
 * other lookup-table resource through the Filament `"Section"`
 * navigation group.
 *
 * The redirect target is `/nexusphp/categories` rather than a
 * per-`?type=` mapping table because:
 *   - Filament resources auto-discover their slugs from model names
 *     (`CategoryResource → /nexusphp/categories`, `CodecResource →
 *     /nexusphp/codecs`, etc.); a hard-coded mapping table would have
 *     to be kept in sync forever.
 *   - The `"Section"` navigation group already groups all eleven, so
 *     one click in the Filament sidebar reaches the right resource.
 *   - Most legacy bookmarks did not specify `?type=` at all (the
 *     legacy script defaulted to `view` over `searchbox`), so a
 *     per-type mapping wouldn't help the most common case.
 */
class LegacyCatmanageRedirectTest extends FeatureTestCase
{
    public function test_get_catmanage_redirects_to_filament_categories(): void
    {
        $this->get('/catmanage.php')
            ->assertRedirect('/nexusphp/categories')
            ->assertStatus(302);
    }

    public function test_get_catmanage_with_view_action_and_type_redirects(): void
    {
        // Every per-tab landing URL the legacy hub exposed
        // (`?action=view&type=...`) maps to the same Filament landing
        // page. Admins navigate to the right resource from there.
        $tabs = [
            '/catmanage.php?action=view&type=searchbox',
            '/catmanage.php?action=view&type=category',
            '/catmanage.php?action=view&type=codec',
            '/catmanage.php?action=view&type=audiocodec',
            '/catmanage.php?action=view&type=processing',
            '/catmanage.php?action=view&type=team',
        ];

        foreach ($tabs as $url) {
            $this->get($url)
                ->assertRedirect('/nexusphp/categories')
                ->assertStatus(302);
        }
    }

    public function test_get_catmanage_with_edit_action_redirects(): void
    {
        $this->get('/catmanage.php?action=edit&type=category&id=42')
            ->assertRedirect('/nexusphp/categories')
            ->assertStatus(302);
    }

    public function test_get_catmanage_with_delete_action_redirects(): void
    {
        $this->get('/catmanage.php?action=del&type=codec&id=99')
            ->assertRedirect('/nexusphp/categories')
            ->assertStatus(302);
    }

    public function test_post_catmanage_form_submit_redirects(): void
    {
        // The legacy "edit row" form posted to
        // `?action=update&type=...&id=...`. We accept the verb to keep
        // the redirect path uniform — staff who paste an old URL into
        // their browser address bar (which fires GET) and staff who
        // resubmit an in-flight legacy form (which fires POST) both
        // land on the new admin instead of a 404.
        $this->post('/catmanage.php?action=update&type=category&id=42', [
            'name' => 'Whatever',
            'class' => 1,
        ])
            ->assertRedirect('/nexusphp/categories')
            ->assertStatus(302);
    }
}
