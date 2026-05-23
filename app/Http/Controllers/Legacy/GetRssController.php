<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Replacement for `public/getrss.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration. RSS-feed builder page that lets
 * users craft a personalised `torrentrss.php?…` URL with category,
 * sub-category, sticky, paid, item-title-type and rows-per-page
 * filters. Authed-only (legacy `loggedinorreturn()`).
 *
 *   - GET  → renders the form
 *   - POST → assembles the query string, prints the resulting RSS
 *            link to the user (legacy `stdmsg()`), no DB writes
 *
 * URL preserved exactly so `include/functions.php:2304` (the RSS
 * icon link in the global header chrome on every legacy page) and
 * any user bookmarks keep working without template changes.
 *
 * POST is CSRF-exempt — the legacy `<form method="post"
 * action="getrss.php">` had no `@csrf` field; see
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 *
 * The legacy script called `stdhead()` / `stdfoot()` for site
 * chrome. The controller renders a chrome-less envelope (same
 * pattern as `ForummanageController` / `MoforumsController` /
 * `PollOverviewController`). The RSS link itself remains pointed at
 * `torrentrss.php` (already migrated → `TorrentRssController`).
 */
class GetRssController extends Controller
{
    /** @var list<string> */
    private const ALLOWED_SHOWROWS = ['10', '50'];

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response
    {
        $user = $this->context->user();
        if ($user === null) {
            abort(401);
        }

        $lang = $this->loadLang();

        if ($request->isMethod('POST')) {
            return $this->handlePost($request, $user, $lang);
        }

        return $this->renderForm($user, $lang);
    }

    // ─── POST: build query string ────────────────────────────────────────

    /**
     * @param  array<string,string>  $lang
     */
    private function handlePost(Request $request, User $user, array $lang): Response
    {
        $title = htmlspecialchars((string) ($lang['head_rss_feeds'] ?? 'RSS Feeds'));

        $showrows = (string) $request->input('showrows', '');
        if (! in_array($showrows, self::ALLOWED_SHOWROWS, true)) {
            $body = $this->renderStdMsg(
                (string) ($lang['std_error'] ?? 'Error'),
                (string) ($lang['std_no_row'] ?? 'No rows-per-page selected.'),
            );

            return $this->wrap($title, $body);
        }

        $brsectiontype = $GLOBALS['browsecatmode'] ?? '';
        $spsectiontype = $GLOBALS['specialcatmode'] ?? '';
        $enableSpecial = ($GLOBALS['enablespecial'] ?? '') === 'yes' && user_can('view_special_torrent');

        $query = ['passkey='.$user->passkey];
        $query[] = 'rows='.(int) $showrows;

        // Categories from the browse section.
        foreach ((array) genrelist($brsectiontype) as $cat) {
            $catId = (int) ($cat['id'] ?? 0);
            if ($catId > 0 && $request->input('cat'.$catId) !== null) {
                $query[] = 'cat'.$catId.'=1';
            }
        }
        // Categories from the special section.
        if ($enableSpecial) {
            foreach ((array) genrelist($spsectiontype) as $cat) {
                $catId = (int) ($cat['id'] ?? 0);
                if ($catId > 0 && $request->input('cat'.$catId) !== null) {
                    $query[] = 'cat'.$catId.'=1';
                }
            }
        }

        // Sub-category checkboxes (sources/media/codecs/...).
        $subgroups = [
            'sou' => ['sources', 'showsource'],
            'med' => ['media', 'showmedium'],
            'cod' => ['codecs', 'showcodec'],
            'sta' => ['standards', 'showstandard'],
            'pro' => ['processings', 'showprocessing'],
            'tea' => ['teams', 'showteam'],
            'aud' => ['audiocodecs', 'showaudiocodec'],
        ];
        foreach ($subgroups as $prefix => [$listKey, $flagKey]) {
            $shown = (bool) get_searchbox_value($brsectiontype, $flagKey)
                || ($enableSpecial && (bool) get_searchbox_value($spsectiontype, $flagKey));
            if (! $shown) {
                continue;
            }
            foreach ((array) searchbox_item_list($listKey, $brsectiontype) as $item) {
                $itemId = (int) ($item['id'] ?? 0);
                if ($itemId > 0 && $request->input($prefix.$itemId) !== null) {
                    $query[] = $prefix.$itemId.'=1';
                }
            }
        }

        // Item-title-type flags (legacy querystring shape preserved).
        if ($request->input('itemcategory') !== null) {
            $query[] = 'icat=1';
        }
        if ($request->input('itemsmalldescr') !== null) {
            $query[] = 'ismalldescr=1';
        }
        if ($request->input('itemsize') !== null) {
            $query[] = 'isize=1';
        }
        if ($request->input('itemuploader') !== null) {
            $query[] = 'iuplder=1';
        }

        // Search.
        $searchstr = trim((string) $request->input('search', ''));
        if ($searchstr !== '') {
            $query[] = 'search='.rawurlencode($searchstr);
            $rawSearchMode = $request->input('search_mode');
            if ($rawSearchMode !== null && $rawSearchMode !== '') {
                $searchMode = (int) $rawSearchMode;
                if (! in_array($searchMode, [0, 2], true)) {
                    $searchMode = 0;
                }
                $query[] = 'search_mode='.$searchMode;
            }
        }

        // Sticky multi-select.
        $sticky = $request->input('sticky');
        if (is_array($sticky) && ! empty($sticky)) {
            $stickyClean = array_filter(array_map('intval', $sticky), fn (int $v): bool => in_array($v, [0, 1, 2], true));
            if (! empty($stickyClean)) {
                $query[] = 'sticky='.implode(',', $stickyClean);
            }
        }

        // Paid radio.
        $paid = $request->input('paid');
        if ($paid !== null && $paid !== '') {
            $paidInt = (int) $paid;
            if (in_array($paidInt, [0, 1, 2], true)) {
                $query[] = 'paid='.$paidInt;
            }
        }

        // Bookmarked radio (carried as a separate suffix).
        $inclbookmarked = (int) $request->input('inclbookmarked', 0);
        $addInclBm = '';
        if (in_array($inclbookmarked, [0, 1], true) && $inclbookmarked !== 0) {
            $addInclBm = '&inclbookmarked='.$inclbookmarked;
        }

        $base = get_protocol_prefix().($GLOBALS['BASEURL'] ?? '').'/torrentrss.php';
        $queries = implode('&', $query);
        $link = $queries !== '' ? $base.'?'.$queries : $base;

        $msg = (string) ($lang['std_use_following_url'] ?? 'Use the following URL:')
            ."\n".$link
            ."\n\n"
            .(string) ($lang['std_utorrent_feed_url'] ?? 'µTorrent feed URL:')
            ."\n".$link.'&linktype=dl'.$addInclBm;

        $body = $this->renderStdMsg(
            (string) ($lang['std_done'] ?? 'Done'),
            format_comment($msg),
        );

        return $this->wrap($title, $body);
    }

    // ─── GET: render form ────────────────────────────────────────────────

    /**
     * @param  array<string,string>  $lang
     */
    private function renderForm(User $user, array $lang): Response
    {
        $title = htmlspecialchars((string) ($lang['head_rss_feeds'] ?? 'RSS Feeds'));

        $browseCatMode = $GLOBALS['browsecatmode'] ?? '';
        $specialCatMode = $GLOBALS['specialcatmode'] ?? '';
        $enableSpecial = ($GLOBALS['main']['spsct'] ?? get_setting('main.spsct')) === 'yes';
        $paidEnabled = get_setting('torrent.paid_torrent_enabled') === 'yes';

        $stickyTypes = [
            0 => nexus_trans('torrent.pos_state_normal'),
            1 => nexus_trans('torrent.pos_state_sticky'),
            2 => nexus_trans('torrent.pos_state_r_sticky'),
        ];

        $categoriesHtml = (string) build_search_box_category_table(
            $browseCatMode,
            'yes',
            'torrents.php?allsec=1&',
            false,
            3,
            '',
            ['section_name' => true],
        );

        $specialHtml = '';
        if ($enableSpecial) {
            $specialHtml = '<div style="height:1px;background-color:#eee;margin:10px 0"></div>'
                .(string) build_search_box_category_table(
                    $specialCatMode,
                    'yes',
                    'special.php?allsec=1&',
                    false,
                    3,
                    '',
                    ['section_name' => true],
                );
        }

        $stickyHtml = '';
        foreach ($stickyTypes as $key => $value) {
            $stickyHtml .= sprintf(
                '<label><input type="checkbox" name="sticky[]" value="%d">%s</label>',
                (int) $key,
                htmlspecialchars((string) $value),
            );
        }

        $rowsHtml = '';
        foreach (self::ALLOWED_SHOWROWS as $showrow) {
            $rowsHtml .= sprintf(
                '<option value="%s">%s</option>',
                htmlspecialchars($showrow, ENT_QUOTES),
                htmlspecialchars($showrow),
            );
        }

        $h1 = htmlspecialchars((string) ($lang['text_rss_feeds'] ?? 'RSS Feeds'));
        $rowCats = htmlspecialchars((string) ($lang['row_categories_to_retrieve'] ?? 'Categories to retrieve'));
        $rowBookmarked = htmlspecialchars((string) ($lang['row_show_bookmarked'] ?? 'Show bookmarked'));
        $textAll = htmlspecialchars((string) ($lang['text_all'] ?? 'All'));
        $textOnlyBm = htmlspecialchars((string) ($lang['text_only_bookmarked'] ?? 'Only bookmarked'));
        $textBmNote = htmlspecialchars((string) ($lang['text_show_bookmarked_note'] ?? ''));
        $rowSticky = htmlspecialchars((string) ($lang['row_sticky'] ?? 'Sticky'));
        $rowPaid = htmlspecialchars((string) ($lang['row_paid'] ?? 'Paid'));
        $paidNo = htmlspecialchars((string) ($lang['paid_no'] ?? 'Free only'));
        $paidYes = htmlspecialchars((string) ($lang['paid_yes'] ?? 'Paid only'));
        $paidAll = htmlspecialchars((string) ($lang['paid_all'] ?? 'All'));
        $rowPaidHelp = htmlspecialchars((string) ($lang['row_paid_help'] ?? ''));
        $rowItemTitle = htmlspecialchars((string) ($lang['row_item_title_type'] ?? 'Item title type'));
        $textItemCat = htmlspecialchars((string) ($lang['text_item_category'] ?? 'Category'));
        $textItemTitle = htmlspecialchars((string) ($lang['text_item_title'] ?? 'Title'));
        $textItemSmall = htmlspecialchars((string) ($lang['text_item_small_description'] ?? 'Small description'));
        $textItemSize = htmlspecialchars((string) ($lang['text_item_size'] ?? 'Size'));
        $textItemUploader = htmlspecialchars((string) ($lang['text_item_uploader'] ?? 'Uploader'));
        $rowRowsPerPage = htmlspecialchars((string) ($lang['row_rows_per_page'] ?? 'Rows per page'));
        $submitGen = htmlspecialchars((string) ($lang['submit_generatte_rss_link'] ?? 'Generate RSS link'), ENT_QUOTES);

        $paidBlock = '';
        if ($paidEnabled) {
            $paidBlock = '<tr>'
                .'<td class="rowhead">'.$rowPaid.'</td>'
                .'<td class="rowfollow" align="left">'
                .'<label><input type="radio" name="paid" value="0" checked>'.$paidNo.'</label> '
                .'<label><input type="radio" name="paid" value="1">'.$paidYes.'</label> '
                .'<label><input type="radio" name="paid" value="2">'.$paidAll.'</label>'
                .'<div>'.$rowPaidHelp.'</div>'
                .'</td></tr>';
        }

        $body = '<h1 align="center">'.$h1.'</h1>'
            .'<form method="post" action="/getrss.php">'
            .'<table cellspacing="1" cellpadding="5" width="97%">'
            .'<tr>'
            .'<td class="rowhead">'.$rowCats.'</td>'
            .'<td class="rowfollow" align="left">'.$categoriesHtml.$specialHtml.'</td>'
            .'</tr>'
            .'<tr>'
            .'<td class="rowhead">'.$rowBookmarked.'</td>'
            .'<td class="rowfollow" align="left">'
            .'<input type="radio" name="inclbookmarked" id="inclbookmarked0" value="0" checked="checked" />'
            .'<label for="inclbookmarked0">'.$textAll.'</label>&nbsp;'
            .'<input type="radio" name="inclbookmarked" id="inclbookmarked1" value="1" />'
            .'<label for="inclbookmarked1">'.$textOnlyBm.'</label>'
            .'<div>'.$textBmNote.'</div>'
            .'</td></tr>'
            .'<tr>'
            .'<td class="rowhead">'.$rowSticky.'</td>'
            .'<td class="rowfollow" align="left">'.$stickyHtml.'</td>'
            .'</tr>'
            .$paidBlock
            .'<tr>'
            .'<td class="rowhead">'.$rowItemTitle.'</td>'
            .'<td class="rowfollow" align="left">'
            .'<input type="checkbox" name="itemcategory" value="1" />'.$textItemCat.'&nbsp;'
            .'<input type="checkbox" name="itemtitle" checked="checked" disabled="disabled" />'.$textItemTitle.'&nbsp;'
            .'<input type="checkbox" name="itemsmalldescr" value="1" />'.$textItemSmall.'&nbsp;'
            .'<input type="checkbox" name="itemsize" value="1" />'.$textItemSize.'&nbsp;'
            .'<input type="checkbox" name="itemuploader" value="1" />'.$textItemUploader
            .'</td></tr>'
            .'<tr>'
            .'<td class="rowhead">'.$rowRowsPerPage.'</td>'
            .'<td class="rowfollow" align="left">'
            .'<select name="showrows">'.$rowsHtml.'</select>'
            .'</td></tr>'
            .'<tr><td colspan="2" align="center">'
            .'<input type="submit" value="'.$submitGen.'" />'
            .'</td></tr>'
            .'</table></form>';

        return $this->wrap($title, $body);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

    private function renderStdMsg(string $title, string $body): string
    {
        return '<table border="0" cellspacing="0" cellpadding="10" width="100%" align="center">'
            .'<tr><td class="colhead" align="left">'.htmlspecialchars($title).'</td></tr>'
            .'<tr><td class="text" align="left">'.$body.'</td></tr>'
            .'</table>';
    }

    private function wrap(string $title, string $body): Response
    {
        $titleEsc = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5);
        $html = <<<HTML
<!DOCTYPE html>
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{$titleEsc}</title>
</head>
<body>
{$body}
</body></html>
HTML;

        return new Response($html);
    }

    /** @return array<string,string> */
    private function loadLang(): array
    {
        $path = base_path(get_langfile_path('getrss.php'));
        $lang_getrss = [];
        if (is_file($path)) {
            require $path;
        }

        return is_array($lang_getrss) ? $lang_getrss : [];
    }
}
