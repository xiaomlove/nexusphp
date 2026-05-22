<?php

namespace Tests\Feature\Legacy;

use App\Models\Faq;
use Illuminate\Support\Facades\Cache;
use Tests\FeatureTestCase;

/**
 * Pins down the `/faq.php` HTML contract.
 *
 * The legacy script:
 *   - Was reachable as a guest (`loggedinorreturn()` was commented out).
 *   - Resolved the FAQ language via the `c_lang_folder` cookie +
 *     `language.rule_lang` + `language.site_lang` flags, with English
 *     (id 6) as the fallback.
 *   - Rendered a table of contents linking to FAQ category sections,
 *     then each category with its Q&A items.
 *   - Page-cached the rendered body for 15 minutes with a
 *     per-language key prefix.
 *
 * The migrated controller keeps the URL stable, drops the legacy chrome,
 * and replaces the legacy page-cache with
 * `Cache::remember('faq:body:<lang>', 900, ...)`.
 */
class FaqControllerTest extends FeatureTestCase
{
    private const ENGLISH_LANGUAGE_ID = 6;

    private const CHINESE_LANGUAGE_ID = 25;

    protected function setUp(): void
    {
        parent::setUp();

        $_SERVER['REQUEST_URI'] = '/faq.php';
        unset($_COOKIE['c_lang_folder']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        unset($_COOKIE['c_lang_folder']);

        parent::tearDown();
    }

    public function test_guest_request_returns_faq_page(): void
    {
        $this->seedFaqCategory(self::ENGLISH_LANGUAGE_ID, 1, 'Getting Started', 1);
        $this->seedFaqItem(self::ENGLISH_LANGUAGE_ID, 1, 'How do I register?', 'Click the signup link.', 1, 10);

        $response = $this->get('/faq.php');

        $response->assertOk();
        $body = (string) $response->getContent();
        $this->assertStringContainsString('NexusPHP :: FAQ', $body);
        $this->assertStringContainsString('Getting Started', $body);
        $this->assertStringContainsString('How do I register?', $body);
        $this->assertStringContainsString('Click the signup link.', $body);
    }

    public function test_faq_titles_are_html_escaped(): void
    {
        $this->seedFaqCategory(self::ENGLISH_LANGUAGE_ID, 1, '<script>alert(1)</script>', 1);

        $response = $this->get('/faq.php');

        $body = (string) $response->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function test_hidden_items_are_not_rendered(): void
    {
        $this->seedFaqCategory(self::ENGLISH_LANGUAGE_ID, 1, 'Visible Category', 1);
        $this->seedFaqItem(self::ENGLISH_LANGUAGE_ID, 1, 'Visible item', 'Yes', 1, 10);
        $this->seedFaqItem(self::ENGLISH_LANGUAGE_ID, 1, 'Hidden item', 'No', 0, 11);

        $response = $this->get('/faq.php');

        $body = (string) $response->getContent();
        $this->assertStringContainsString('Visible item', $body);
        $this->assertStringNotContainsString('Hidden item', $body);
    }

    public function test_cookie_with_translated_language_picks_that_language(): void
    {
        $this->seedFaqCategory(self::ENGLISH_LANGUAGE_ID, 1, 'English FAQ', 1);
        $this->seedFaqItem(self::ENGLISH_LANGUAGE_ID, 1, 'English Q', 'English A', 1, 10);
        $this->seedFaqCategory(self::CHINESE_LANGUAGE_ID, 2, '中文FAQ', 1);
        $this->seedFaqItem(self::CHINESE_LANGUAGE_ID, 2, '中文问题', '中文答案', 1, 20);

        $_COOKIE['c_lang_folder'] = 'chs';

        $response = $this->get('/faq.php');

        $body = (string) $response->getContent();
        $this->assertStringContainsString('中文FAQ', $body);
        $this->assertStringNotContainsString('English FAQ', $body);
    }

    public function test_cookie_with_unknown_folder_falls_back_to_english(): void
    {
        $this->seedFaqCategory(self::ENGLISH_LANGUAGE_ID, 1, 'English FAQ', 1);
        $this->seedFaqItem(self::ENGLISH_LANGUAGE_ID, 1, 'English Q', 'English A', 1, 10);

        $_COOKIE['c_lang_folder'] = 'no-such-folder';

        $response = $this->get('/faq.php');

        $body = (string) $response->getContent();
        $this->assertStringContainsString('English FAQ', $body);
    }

    public function test_body_is_cached_for_fifteen_minutes(): void
    {
        $this->seedFaqCategory(self::ENGLISH_LANGUAGE_ID, 1, 'First version', 1);
        $this->seedFaqItem(self::ENGLISH_LANGUAGE_ID, 1, 'Q1', 'A1', 1, 10);

        // Cold cache: response renders v1.
        $first = (string) $this->get('/faq.php')->getContent();
        $this->assertStringContainsString('First version', $first);

        // Mutate the DB underneath.
        Faq::query()
            ->where('lang_id', self::ENGLISH_LANGUAGE_ID)
            ->where('type', 'categ')
            ->update(['question' => 'Second version']);

        // Cached hit: should still show v1.
        $second = (string) $this->get('/faq.php')->getContent();
        $this->assertStringContainsString('First version', $second);
        $this->assertStringNotContainsString('Second version', $second);
    }

    public function test_updated_and_new_markers_are_rendered(): void
    {
        $this->seedFaqCategory(self::ENGLISH_LANGUAGE_ID, 1, 'Category', 1);
        $this->seedFaqItem(self::ENGLISH_LANGUAGE_ID, 1, 'Updated Q', 'Updated A', 2, 10);
        $this->seedFaqItem(self::ENGLISH_LANGUAGE_ID, 1, 'New Q', 'New A', 3, 11);

        $response = $this->get('/faq.php');

        $body = (string) $response->getContent();
        $this->assertStringContainsString('alt="Updated"', $body);
        $this->assertStringContainsString('alt="New"', $body);
    }

    private function seedFaqCategory(int $langId, int $linkId, string $title, int $flag, int $order = 1): int
    {
        return Faq::query()->insertGetId([
            'type' => 'categ',
            'lang_id' => $langId,
            'link_id' => $linkId,
            'question' => $title,
            'answer' => '',
            'flag' => (string) $flag,
            'categ' => 0,
            'order' => $order,
        ]);
    }

    private function seedFaqItem(int $langId, int $categ, string $question, string $answer, int $flag, int $linkId): int
    {
        return Faq::query()->insertGetId([
            'type' => 'item',
            'lang_id' => $langId,
            'link_id' => $linkId,
            'question' => $question,
            'answer' => $answer,
            'flag' => (string) $flag,
            'categ' => $categ,
            'order' => 0,
        ]);
    }
}
