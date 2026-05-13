<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/rules.php` (deleted in the same PR).
 *
 * Phase 2 batch #8 of the legacy migration — see
 * `docs/legacy-strategy.md` § "Phase 2" and `docs/migration-recipe.md`.
 *
 * Original legacy flow:
 *   1. `dbconn();` + `require_once get_langfile_path();` bootstrap
 *      (auth was commented out — page reachable as a guest).
 *   2. `stdhead($lang_rules['head_rules']);` + `begin_main_frame();`
 *      + a `language` lookup to pick the requested `rule_lang`, with
 *      English (id 6) as the fallback when the picked locale has no
 *      translated ruleset.
 *   3. For each `rules` row in that language: `begin_frame($title);`
 *      `echo format_comment($text);` `end_frame();`.
 *   4. `end_main_frame();` + `stdfoot();`.
 *   5. Whole page body cached via `$Cache->new_page('rules', 900, true)`
 *      with a 15-minute TTL and a per-language key prefix.
 *
 * Replacement contract (this controller):
 *   - Public (no auth middleware) — matches the legacy contract.
 *   - 200 with a chrome-less, self-contained HTML envelope wrapping
 *     a `<table>` of rule rows. Follows the same precedent as
 *     `MoreSmiliesController` / `AllAgentsController`: the legacy
 *     `stdhead()` / `stdfoot()` chrome will come back in Phase 5
 *     once it has native Blade partials.
 *     The `<title>` is preserved as `NexusPHP :: Rules` so the
 *     existing E2E smoke spec in
 *     `tests/e2e/smoke/legacy-pages-extra.spec.ts` stays green.
 *   - Language selection: read the `c_lang_folder` cookie, resolve
 *     it to a `language.id` with `rule_lang = 1 AND site_lang = 1`,
 *     and fall back to English (id 6) when the cookie is missing,
 *     unknown, or points at a locale that has no rule translation.
 *   - Per-language body cached on the default Redis store via
 *     `Cache::remember('rules:body:'.$langId, 900, ...)`. The legacy
 *     class-cache page-cache (`$Cache->new_page` / `get_page`) is
 *     intentionally replaced — `Illuminate\Cache\RedisStore`'s wire
 *     format is byte-for-byte compatible with `class_cache_redis`
 *     (see `docs/legacy-strategy.md` § "Why class_cache_redis is not
 *     being collapsed") and `Cache::` is the modern call style every
 *     other Phase 2 controller already uses.
 *
 * `format_comment()` is a legacy helper from `include/functions.php`
 * which `bootstrap/app.php` already requires unconditionally, so it
 * is callable from any Laravel-pipeline controller.
 */
class RulesController extends Controller
{
    /** Cache TTL in seconds. Matches the legacy `$Cache->new_page('rules', 900, true)` argument. */
    private const CACHE_TTL = 900;

    /** `language.id` for English in the seeded `language` table. Final fallback. */
    private const ENGLISH_LANGUAGE_ID = 6;

    public function __invoke(): Response
    {
        $langId = $this->resolveRuleLanguageId();

        $body = Cache::remember(
            'rules:body:'.$langId,
            self::CACHE_TTL,
            fn (): string => $this->renderBody($langId),
        );

        $html = <<<HTML
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>NexusPHP :: Rules</title>
</head>
<body>
{$body}</body>
</html>
HTML;

        return new Response($html);
    }

    /**
     * Pick the `language.id` to render rules in.
     *
     * Mirrors the legacy logic: prefer the `c_lang_folder` cookie when
     * the row exists, has `rule_lang = 1` and `site_lang = 1`; fall
     * back to English otherwise. The legacy script also asked
     * `language.rule_lang` for the picked id and switched to English
     * when the row was not flagged as a rules-language — folding the
     * two queries into one `WHERE rule_lang = 1` lookup preserves the
     * same observable behaviour with one query instead of two.
     */
    private function resolveRuleLanguageId(): int
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
     * Render the rules table body. Called by `Cache::remember` only
     * on a cold cache; subsequent hits within the 15-minute window
     * return the cached string without touching the DB.
     */
    private function renderBody(int $langId): string
    {
        $rules = NexusDB::table('rules')
            ->where('lang_id', $langId)
            ->orderBy('id')
            ->get();

        $rows = '';
        foreach ($rules as $rule) {
            $arr = (array) $rule;
            $title = htmlspecialchars((string) ($arr['title'] ?? ''));
            // `format_comment` returns HTML — do NOT escape its output.
            $text = format_comment((string) ($arr['text'] ?? ''));
            $rows .= '<tr><td class="rowhead" align="left"><h2>'.$title.'</h2></td></tr>'."\n"
                .'<tr><td class="text" align="left">'.$text.'</td></tr>'."\n";
        }

        return '<table align="center" border="1" cellspacing="0" cellpadding="10" width="100%">'."\n"
            .$rows
            .'</table>'."\n";
    }
}
