<?php

namespace Tests\Feature\Legacy;

use Tests\FeatureTestCase;

/**
 * Pin the URL-preservation contract introduced when
 * `public/location.php` (the SYSOP-only `locations`-table CRUD
 * with the quirky GET-with-querystring write protocol) was
 * strangled onto the Filament resource at `/nexusphp/locations`.
 *
 * The legacy script accepted at least five distinct URL shapes:
 *   - bare GET → list
 *   - `?delid=N` → delete confirmation
 *   - `?delid=N&sure=yes` → perform delete
 *   - `?editid=N` → edit form
 *   - `?edited=1&...` → edit submit (full row payload in querystring)
 *   - `?add=true&...` → add submit (full row payload in querystring)
 *   - `?check_range=true&range_start_ip=...&range_end_ip=...` → range query
 *
 * Every one of those URL shapes is preserved as a 302 to
 * `/nexusphp/locations`. From there the SYSOP picks up the same
 * action through Filament's standard list / create / edit pages.
 */
class LegacyLocationRedirectTest extends FeatureTestCase
{
    public function test_get_location_redirects_to_filament(): void
    {
        $this->get('/location.php')
            ->assertRedirect('/nexusphp/locations')
            ->assertStatus(302);
    }

    public function test_get_location_with_editid_redirects(): void
    {
        $this->get('/location.php?editid=42')
            ->assertRedirect('/nexusphp/locations')
            ->assertStatus(302);
    }

    public function test_get_location_with_delid_redirects(): void
    {
        $this->get('/location.php?delid=99&sure=yes')
            ->assertRedirect('/nexusphp/locations')
            ->assertStatus(302);
    }

    public function test_get_location_add_form_submit_redirects(): void
    {
        // Legacy "add" form posted via GET with the full row in the
        // querystring. Some staff bookmarks / chats may still carry
        // these. The redirect target ignores the payload — the user
        // re-issues the create through Filament.
        $this->get('/location.php?add=true&name=Foo&start_ip=10.0.0.0&end_ip=10.0.0.255'
            .'&theory_upspeed=10&practical_upspeed=10&theory_downspeed=10'
            .'&practical_downspeed=10&location_main=Foo&location_sub=Bar&flagpic=')
            ->assertRedirect('/nexusphp/locations')
            ->assertStatus(302);
    }

    public function test_post_location_redirects(): void
    {
        // The legacy "edit" form was GET, but defensively we also
        // accept POST so an in-flight legacy submit doesn't 404.
        $this->post('/location.php?edited=1&id=1', [
            'name' => 'whatever',
        ])
            ->assertRedirect('/nexusphp/locations')
            ->assertStatus(302);
    }
}
