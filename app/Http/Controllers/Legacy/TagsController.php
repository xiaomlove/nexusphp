<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/tags.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2" and `docs/migration-recipe.md`.
 *
 * The "BBCode tags reference" page — a static cheatsheet of every
 * BBCode tag the site supports, rendered as a 4-column table per
 * tag: name / description / syntax / example / result. The
 * `?test=<bbcode>` POST renders a `format_comment()`-processed
 * preview at the top of the page.
 *
 * Original legacy flow (~303 LOC):
 *   1. `dbconn();` + `loggedinorreturn();` (implicit through
 *      `bittorrent.php` bootstrap — though the `loggedinorreturn()`
 *      call is missing from the legacy file, the `?test=` form
 *      uses `$CURUSER['username']` in the rendered "quote two"
 *      example, which would crash for guests).
 *   2. 25+ inline calls to a private `insert_tag()` helper, each
 *      rendering one tag row.
 *   3. `stdhead` / `begin_main_frame` / `begin_frame` /
 *      `end_frame` / `end_main_frame` / `stdfoot` chrome.
 *
 * Replacement contract (this controller):
 *   - URL preserved exactly so `include/functions.php:1026` (the
 *     "tags / smilies" footer link rendered inside every compose
 *     helper) and `public/admanage.php:162` (the BBCode help link
 *     in the ad-management form) keep working without template
 *     changes.
 *   - `auth.nexus:nexus-web` middleware — guests redirect to
 *     login. The legacy file lacked `loggedinorreturn()` but
 *     unconditionally read `$CURUSER['username']` (which would
 *     trip a notice for guests); we tighten the contract to the
 *     stricter posture the body actually required.
 *   - POST CSRF-exempt because the legacy `<form method=post
 *     action=?>` had no `@csrf` field; see
 *     `App\Http\Middleware\VerifyCsrfToken::$except`.
 *
 * Output chrome: manual `<html>` wrap, same precedent as
 * `BitBucketLogController`, `ContactStaffController`,
 * `RulesController`.
 *
 * Localisation: legacy `lang/<locale>/lang_tags.php` (19 locales)
 * loaded through `require_once get_langfile_path('tags')`. The
 * 19 dictionaries are NOT deleted in this PR; porting them to
 * Laravel translations is deferred to Phase 5 (same precedent as
 * the rest of the Phase 2 migrations).
 */
class TagsController extends Controller
{
    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            // The middleware redirects guests; this is belt-and-braces.
            abort(401);
        }

        require_once get_langfile_path('tags');

        global $lang_tags;

        $test = (string) $request->input('test', '');
        $siteName = (string) Setting::getSiteName();
        $username = (string) ($viewer->username ?? '');

        $title = htmlspecialchars((string) ($lang_tags['head_tags'] ?? 'BBCode Tags'));
        $bbHeader = htmlspecialchars((string) ($lang_tags['text_tags'] ?? 'Tags'));
        $bbNote = sprintf((string) ($lang_tags['text_bb_tags_note'] ?? '%s supports the following BBCode tags.'), $siteName);
        $submitLabel = htmlspecialchars((string) ($lang_tags['submit_test_this_code'] ?? 'Test this code'));

        $body = '<h2>'.$bbHeader.'</h2>';
        $body .= '<p>'.$bbNote.'</p>';

        // Test form posts to the same URL.
        $testValue = $test !== '' ? htmlspecialchars($test) : '';
        $body .= '<form method=post action=tags.php>';
        $body .= '<textarea name=test cols=60 rows=3>'.$testValue.'</textarea>';
        $body .= '<input type=submit style="height: 23px; margin-left: 5px" value="'.$submitLabel.'" />';
        $body .= '</form>';

        if ($test !== '') {
            $body .= '<p><hr>'.format_comment($test).'</hr></p>';
        }

        $host = getSchemeAndHttpHost();

        // Static cheatsheet of every supported BBCode tag. Each
        // tag is rendered as a small 4-row table: description /
        // syntax / example / result (+ remarks if any). Order
        // mirrors the legacy file byte-for-byte so the page diff
        // versus production is empty.
        $tags = [
            'text_bold',
            'text_italic',
            'text_underline',
            'text_strikethrough',
            'text_hide',
            'text_color_one',
            'text_color_two',
            'text_size',
            'text_font',
            'text_hyperlink_one' => ['example_format' => $host],
            'text_hyperlink_two' => ['example_format' => [$host, $siteName]],
            'text_image_one' => ['example_format' => $host],
            'text_image_two' => ['example_format' => $host],
            'text_quote_one' => ['example_format' => $siteName],
            'text_quote_two' => ['example_format' => [$username, $siteName]],
            'text_list' => ['description_key' => 'text_description'],
            'text_preformat',
            'text_code',
            'text_site',
            'text_siteurl',
            'text_left',
            'text_center',
            'text_right',
            'text_youtube',
            'text_video',
            'text_audio',
            'text_spoiler',
            'text_hr',
        ];

        foreach ($tags as $key => $config) {
            if (is_int($key)) {
                $tagKey = $config;
                $config = [];
            } else {
                $tagKey = $key;
            }
            $body .= $this->renderTag($tagKey, $config);
        }

        return $this->envelope($title, $body);
    }

    /**
     * @param  array{example_format?:string|array<int,string>,description_key?:string}  $config
     */
    private function renderTag(string $key, array $config): string
    {
        global $lang_tags;

        $name = (string) ($lang_tags[$key] ?? $key);
        $descriptionKey = $config['description_key'] ?? $key.'_description';
        $description = (string) ($lang_tags[$descriptionKey] ?? '');
        $syntax = (string) ($lang_tags[$key.'_syntax'] ?? '');
        $exampleTpl = (string) ($lang_tags[$key.'_example'] ?? '');
        $remarks = (string) ($lang_tags[$key.'_remarks'] ?? '');

        if (isset($config['example_format'])) {
            $args = (array) $config['example_format'];
            $example = vsprintf($exampleTpl, $args);
        } else {
            $example = $exampleTpl;
        }

        $result = format_comment($example);

        $labelDescription = htmlspecialchars((string) ($lang_tags['text_description'] ?? 'Description'));
        $labelSyntax = htmlspecialchars((string) ($lang_tags['text_syntax'] ?? 'Syntax'));
        $labelExample = htmlspecialchars((string) ($lang_tags['text_example'] ?? 'Example'));
        $labelResult = htmlspecialchars((string) ($lang_tags['text_result'] ?? 'Result'));
        $labelRemarks = htmlspecialchars((string) ($lang_tags['text_remarks'] ?? 'Remarks'));

        $html = '<p class=sub><b>'.$name."</b></p>\n";
        $html .= '<table class=main width=100% border=1 cellspacing=0 cellpadding=5>'."\n";
        $html .= '<tr valign=top><td width=25%>'.$labelDescription.'</td><td>'.$description."\n";
        $html .= '<tr valign=top><td>'.$labelSyntax.'</td><td><tt>'.$syntax."</tt>\n";
        $html .= '<tr valign=top><td>'.$labelExample.'</td><td><tt>'.$example."</tt>\n";
        $html .= '<tr valign=top><td>'.$labelResult.'</td><td>'.$result."\n";
        if ($remarks !== '' && $remarks !== $key.'_remarks') {
            $html .= '<tr><td>'.$labelRemarks.'</td><td>'.$remarks."\n";
        }
        $html .= "</table>\n";

        return $html;
    }

    private function envelope(string $title, string $body): Response
    {
        $titleEsc = htmlspecialchars($title);
        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}
</body>
</html>
HTML;

        return new Response($html);
    }
}
