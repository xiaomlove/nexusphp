<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\Setting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/faq.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `dbconn();` + `require_once get_langfile_path();` bootstrap.
 *      Auth was commented out (`// loggedinorreturn()`), so the page
 *      is reachable as a guest.
 *   2. `stdhead($lang_faq['head_faq']);` chrome.
 *   3. `$Cache->new_page('faq', 900, true)` — page-level cache with
 *      15-minute TTL, per-language key prefix.
 *   4. Welcome frame with site name/slogan from `Setting`.
 *   5. Resolve the guest language via `get_guest_lang_id()` →
 *      `language.rule_lang` check, fallback to English (id 6).
 *   6. Query `faq` table for `type='categ'` rows (table of contents),
 *      then `type='item'` rows (Q&A pairs), grouped by `categ` →
 *      `link_id` relationship.
 *   7. Render a table of contents with anchor links, then each
 *      category frame with its items (question + answer).
 *   8. `$Cache->end_whole_row(); $Cache->cache_page();`
 *
 * Replacement contract (this controller):
 *   - Public (no auth middleware) — matches the legacy contract.
 *   - 200 with a chrome-less, self-contained HTML envelope.
 *   - Language selection: read the `c_lang_folder` cookie, resolve
 *     it to a `language.id` with `rule_lang = 1 AND site_lang = 1`,
 *     fall back to English (id 6) when the cookie is missing or
 *     unknown. Same logic as `RulesController`.
 *   - Per-language body cached via `Cache::remember('faq:body:<langId>',
 *     900, ...)`. Replaces the legacy `$Cache->new_page` page-cache.
 *   - FAQ items with `flag = '0'` are hidden (same as legacy).
 *   - Items with `flag = '2'` show an "Updated" marker; `flag = '3'`
 *     shows a "New" marker.
 */
class FaqController extends Controller
{
    /** Cache TTL in seconds. Matches the legacy `$Cache->new_page('faq', 900, true)` argument. */
    private const CACHE_TTL = 900;

    /** `language.id` for English in the seeded `language` table. Final fallback. */
    private const ENGLISH_LANGUAGE_ID = 6;

    public function __invoke(): Response
    {
        $langId = $this->resolveLanguageId();

        $body = Cache::remember(
            'faq:body:'.$langId,
            self::CACHE_TTL,
            fn (): string => $this->renderBody($langId),
        );

        $html = <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>NexusPHP :: FAQ</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * Pick the `language.id` for the FAQ content.
     *
     * Same pattern as `RulesController::resolveRuleLanguageId()`:
     * prefer `c_lang_folder` cookie → `language` row with
     * `rule_lang = 1 AND site_lang = 1`; fall back to English.
     */
    private function resolveLanguageId(): int
    {
        $folder = $_COOKIE['c_lang_folder'] ?? null;
        if (is_string($folder) && $folder !== '') {
            $id = NexusDB::table('language')
                ->where('site_lang_folder', $folder)
                ->where('site_lang', 1)
                ->where('rule_lang', 1)
                ->value('id');
            if ($id !== null) {
                return (int) $id;
            }
        }

        return self::ENGLISH_LANGUAGE_ID;
    }

    /**
     * Render the full FAQ body. Called by `Cache::remember` only on
     * a cold cache.
     */
    private function renderBody(int $langId): string
    {
        $siteName = Setting::getSiteName();

        $body = '<h1>Welcome to '.htmlspecialchars((string) $siteName).'</h1>'."\n";

        // Build the category → items structure
        $categories = Faq::query()
            ->where('type', 'categ')
            ->where('lang_id', $langId)
            ->orderBy('order')
            ->get()
            ->toArray();

        $items = Faq::query()
            ->where('type', 'item')
            ->where('lang_id', $langId)
            ->get()
            ->toArray();

        // Group categories by link_id
        $faqCateg = [];
        foreach ($categories as $arr) {
            $faqCateg[$arr['link_id']] = [
                'title' => $arr['question'],
                'flag' => $arr['flag'],
                'link_id' => $arr['link_id'],
                'items' => [],
            ];
        }

        // Assign items to their parent category
        foreach ($items as $arr) {
            $categId = $arr['categ'] ?? null;
            if ($categId !== null && isset($faqCateg[$categId])) {
                $faqCateg[$categId]['items'][$arr['id']] = [
                    'question' => $arr['question'],
                    'answer' => $arr['answer'],
                    'flag' => $arr['flag'],
                    'link_id' => $arr['link_id'],
                ];
            }
        }

        if (empty($faqCateg)) {
            return $body;
        }

        // Table of Contents
        $body .= '<div id="top"><h2>Contents</h2>'."\n";
        foreach ($faqCateg as $categ) {
            if ($categ['flag'] !== '1') {
                continue;
            }
            $body .= '<ul><li><a href="#id'.(int) $categ['link_id'].'"><b>'
                .htmlspecialchars((string) $categ['title']).'</b></a>'."\n".'<ul>'."\n";

            foreach ($categ['items'] as $item) {
                if ($item['flag'] === '0') {
                    continue;
                }
                $marker = '';
                if ($item['flag'] === '2') {
                    $marker = ' <img class="faq_updated" src="pic/trans.gif" alt="Updated" />';
                } elseif ($item['flag'] === '3') {
                    $marker = ' <img class="faq_new" src="pic/trans.gif" alt="New" />';
                }
                $body .= '<li><a href="#id'.(int) $item['link_id'].'" class="faqlink">'
                    .htmlspecialchars((string) $item['question']).'</a>'.$marker.'</li>'."\n";
            }

            $body .= '</ul></li></ul>'."\n";
        }
        $body .= '</div>'."\n";

        // Full content sections
        foreach ($faqCateg as $categ) {
            if ($categ['flag'] !== '1') {
                continue;
            }
            $body .= '<h2><span id="id'.(int) $categ['link_id'].'">'
                .htmlspecialchars((string) $categ['title'])
                .'</span> — <a href="#top">Top</a></h2>'."\n";

            foreach ($categ['items'] as $item) {
                if ($item['flag'] === '0') {
                    continue;
                }
                $body .= '<p><span id="id'.(int) $item['link_id'].'"><b>'
                    .htmlspecialchars((string) $item['question']).'</b></span></p>'."\n"
                    .'<p>'.$item['answer'].'</p>'."\n";
            }
        }

        return $body;
    }
}
