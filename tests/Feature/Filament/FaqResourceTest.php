<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Faq\FaqResource;
use App\Filament\Resources\Faq\FaqResource\Pages\CreateFaq;
use App\Filament\Resources\Faq\FaqResource\Pages\EditFaq;
use App\Filament\Resources\Faq\FaqResource\Pages\ListFaqs;
use App\Models\Faq;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Nexus\Database\NexusDB;
use Tests\Concerns\CreatesLegacyTestUsers;
use Tests\FeatureTestCase;

/**
 * Feature tests for the Filament `FaqResource` (replaces
 * `public/faqmanage.php` + `public/faqactions.php` — see
 * `app/Filament/Resources/Faq/FaqResource.php`).
 *
 * Coverage focus:
 *   - `Faq::canAccess()` admin-class gate (mirrors the legacy
 *     `if (get_user_class() < UC_ADMINISTRATOR) permissiondenied();`).
 *   - The auto-fill `creating` event (`link_id` and `order` are set
 *     to `MAX(...)+1` per `(lang_id, type, [categ])` scope).
 *   - Cache invalidation on save / delete — the public-facing
 *     `FaqController` reads `Cache::remember('faq:body:'.$langId, ...)`,
 *     so an admin write must drop those keys.
 *   - Filament Livewire shells (List/Create/Edit) render and submit.
 *
 * The legacy URLs `/faqmanage.php` and `/faqactions.php` 302-redirect
 * to `/nexusphp/faqs`; the redirect contract is covered separately by
 * `LegacyFaqAdminRedirectTest`.
 */
class FaqResourceTest extends FeatureTestCase
{
    use CreatesLegacyTestUsers;

    private const ENGLISH_LANGUAGE_ID = 6;

    /** @var array<int,int> Faq IDs to clean up. */
    private array $createdFaqIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/nexusphp/faqs';
    }

    protected function tearDown(): void
    {
        if (! empty($this->createdFaqIds)) {
            NexusDB::table('faq')->whereIn('id', $this->createdFaqIds)->delete();
        }
        $this->createdFaqIds = [];

        parent::tearDown();
    }

    public function test_can_access_returns_false_for_non_admin_user(): void
    {
        $user = $this->createTestUser(['class' => User::CLASS_USER]);
        $this->actingAs($user, 'nexus-web');

        $this->assertFalse(FaqResource::canAccess());
    }

    public function test_can_access_returns_true_for_administrator(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $this->assertTrue(FaqResource::canAccess());
    }

    public function test_creating_a_section_auto_fills_link_id_and_order(): void
    {
        $faq = $this->makeSection(['question' => 'auto-fill-section']);
        $this->createdFaqIds[] = $faq->id;

        $this->assertGreaterThan(0, $faq->link_id);
        $this->assertGreaterThan(0, $faq->order);
    }

    public function test_creating_a_second_section_increments_link_id_and_order(): void
    {
        $first = $this->makeSection(['question' => 'first-section']);
        $this->createdFaqIds[] = $first->id;

        $second = $this->makeSection(['question' => 'second-section']);
        $this->createdFaqIds[] = $second->id;

        $this->assertSame((int) $first->link_id + 1, (int) $second->link_id);
        $this->assertSame((int) $first->order + 1, (int) $second->order);
    }

    public function test_creating_an_item_auto_fills_link_id_and_order_per_section(): void
    {
        $section = $this->makeSection(['question' => 'parent-section']);
        $this->createdFaqIds[] = $section->id;

        $first = $this->makeItem($section, ['question' => 'q-1']);
        $this->createdFaqIds[] = $first->id;
        $second = $this->makeItem($section, ['question' => 'q-2']);
        $this->createdFaqIds[] = $second->id;

        $this->assertSame((int) $first->link_id + 1, (int) $second->link_id);
        $this->assertSame((int) $first->order + 1, (int) $second->order);
        $this->assertSame((int) $section->link_id, (int) $first->categ);
        $this->assertSame((int) $section->link_id, (int) $second->categ);
    }

    public function test_saving_an_faq_invalidates_public_cache_for_its_language(): void
    {
        $langId = self::ENGLISH_LANGUAGE_ID;
        $key = 'faq:body:'.$langId;
        Cache::put($key, 'cached-body-from-public-faqcontroller', 900);
        $this->assertSame('cached-body-from-public-faqcontroller', Cache::get($key));

        $faq = $this->makeSection(['question' => 'cache-bust-section']);
        $this->createdFaqIds[] = $faq->id;

        $this->assertNull(
            Cache::get($key),
            'Saving an Faq row should drop the matching `faq:body:<langId>` '
            .'cache key so the public FaqController re-renders on the next '
            .'request.'
        );
    }

    public function test_deleting_an_faq_invalidates_public_cache_for_its_language(): void
    {
        $faq = $this->makeSection(['question' => 'cache-bust-on-delete']);
        $this->createdFaqIds[] = $faq->id;

        $key = 'faq:body:'.self::ENGLISH_LANGUAGE_ID;
        Cache::put($key, 'cached', 900);

        $faq->delete();

        $this->assertNull(Cache::get($key));
    }

    public function test_administrator_can_render_the_list_page(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $section = $this->makeSection(['question' => 'list-page-section']);
        $this->createdFaqIds[] = $section->id;

        Livewire::test(ListFaqs::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$section]);
    }

    public function test_administrator_can_create_a_section_through_the_form(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $title = 'filament-created-section-'.bin2hex(random_bytes(3));

        Livewire::test(CreateFaq::class)
            ->fillForm([
                'type' => Faq::TYPE_CATEG,
                'lang_id' => self::ENGLISH_LANGUAGE_ID,
                'question' => $title,
                'flag' => Faq::FLAG_NORMAL,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $row = Faq::query()->where('question', $title)->first();
        $this->assertNotNull($row);
        $this->createdFaqIds[] = $row->id;

        $this->assertSame(Faq::TYPE_CATEG, $row->type);
        $this->assertSame(self::ENGLISH_LANGUAGE_ID, (int) $row->lang_id);
        $this->assertSame(Faq::FLAG_NORMAL, (int) $row->flag);
        $this->assertSame(0, (int) $row->categ);
        $this->assertSame('', (string) $row->answer);
        $this->assertGreaterThan(0, (int) $row->link_id);
        $this->assertGreaterThan(0, (int) $row->order);
    }

    public function test_administrator_can_edit_an_existing_item(): void
    {
        $admin = $this->createTestUser(['class' => User::CLASS_ADMINISTRATOR]);
        $this->actingAs($admin, 'nexus-web');

        $section = $this->makeSection(['question' => 'parent-for-edit']);
        $this->createdFaqIds[] = $section->id;
        $item = $this->makeItem($section, [
            'question' => 'before-edit',
            'answer' => 'before-edit-body',
        ]);
        $this->createdFaqIds[] = $item->id;

        Livewire::test(EditFaq::class, ['record' => $item->id])
            ->fillForm([
                'question' => 'after-edit',
                'answer' => 'after-edit-body',
                'flag' => Faq::FLAG_UPDATED,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $reloaded = Faq::query()->find($item->id);
        $this->assertSame('after-edit', $reloaded->question);
        $this->assertSame('after-edit-body', $reloaded->answer);
        $this->assertSame(Faq::FLAG_UPDATED, (int) $reloaded->flag);
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function makeSection(array $overrides = []): Faq
    {
        $faq = Faq::create(array_merge([
            'type' => Faq::TYPE_CATEG,
            'lang_id' => self::ENGLISH_LANGUAGE_ID,
            'question' => 'test-section-'.bin2hex(random_bytes(3)),
            'answer' => '',
            'flag' => Faq::FLAG_NORMAL,
            'categ' => 0,
        ], $overrides));

        return $faq;
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function makeItem(Faq $section, array $overrides = []): Faq
    {
        return Faq::create(array_merge([
            'type' => Faq::TYPE_ITEM,
            'lang_id' => (int) $section->lang_id,
            'categ' => (int) $section->link_id,
            'question' => 'test-item-'.bin2hex(random_bytes(3)),
            'answer' => 'test-item-body',
            'flag' => Faq::FLAG_NORMAL,
        ], $overrides));
    }

    /**
     * @param  array<string,mixed>  $overrides
     */
    private function createTestUser(array $overrides = []): User
    {
        $username = $overrides['username'] ?? 'faq-admin-'.bin2hex(random_bytes(3));
        unset($overrides['username']);

        return $this->createLegacyUser(
            overrides: array_merge(
                ['username' => $username, 'lang' => self::ENGLISH_LANGUAGE_ID],
                $overrides,
            ),
        );
    }
}
