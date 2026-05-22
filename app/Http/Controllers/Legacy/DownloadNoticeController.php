<?php

namespace App\Http\Controllers\Legacy;

use App\Http\Controllers\Controller;
use App\Legacy\LegacyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Nexus\Database\NexusDB;

/**
 * Replacement for `public/downloadnotice.php` (deleted in the same PR).
 *
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`
 * § "Phase 2".
 *
 * Pre-download interstitial that `DownloadController` redirects to
 * before the user is allowed to fetch the .torrent file. Three
 * variants:
 *
 *   - `?type=firsttime` — first-ever download for this account.
 *     Renders both the "private tracker / ratio" and "use allowed
 *     clients" panels. The "Don't show again" checkbox unsets
 *     `users.showdlnotice`.
 *   - `?type=client` — the user's previous announce was rejected
 *     because of a banned/disallowed BitTorrent client. Renders
 *     the "use allowed clients" panel only, with a checkbox that
 *     unsets `users.showclienterror`.
 *   - `?type=ratio` — the user's ratio is below the
 *     `leechwarnuntil` threshold. Renders the "private tracker /
 *     ratio" panel only, with a force-checked "let me download
 *     anyway" gate (no opt-out checkbox — they have to acknowledge
 *     before each download until ratio recovers).
 *
 * Original legacy flow (`public/downloadnotice.php`, 156 LOC):
 *   1. `loggedinorreturn()` bootstrap.
 *   2. POST branch: validates `?id` + `?type` whitelist, optionally
 *      flips `users.showdlnotice` / `users.showclienterror`,
 *      `nexus_redirect()`s to `/download.php?id=N&letdown=1`.
 *   3. GET branch: renders the interstitial form using strings
 *      from `lang_downloadnotice.php` (kept in tree).
 *
 * Replacement contract:
 *   - Same URL — referenced from `DownloadController`'s redirect
 *     branches.
 *   - Inside `auth.nexus:nexus-web` middleware (legacy
 *     `loggedinorreturn()` mirror).
 *   - POST is CSRF-exempt — the legacy form has no `@csrf` field;
 *     see `App\Http\Middleware\VerifyCsrfToken::$except`.
 *   - POST validation tightened: missing/invalid `?id` or `?type`
 *     returns 422 (legacy: silent `exit('error')` HTTP 200).
 *   - GET preserves the legacy panel layout, the panel-gating
 *     logic per type, and the "Don't show again" semantics.
 *
 * Sweep findings:
 *   - 19 `lang/<locale>/lang_downloadnotice.php` files KEPT —
 *     actively consumed through `require get_langfile_path()`
 *     inside the controller.
 *   - The only caller is `public/download.php` (about to be
 *     migrated to `DownloadController`); URLs preserved on both
 *     sides of the redirect chain so neither end has to change.
 */
class DownloadNoticeController extends Controller
{
    private const VALID_TYPES = ['firsttime', 'client', 'ratio'];

    public function __construct(private readonly LegacyContext $context) {}

    public function __invoke(Request $request): Response|RedirectResponse
    {
        $viewer = $this->context->user();
        if ($viewer === null) {
            abort(401);
        }

        if ($request->isMethod('post')) {
            return $this->handlePost($request, $viewer);
        }

        return $this->handleGet($request, $viewer);
    }

    private function handlePost(Request $request, $viewer): RedirectResponse
    {
        $torrentId = (int) $request->input('id', 0);
        $type = (string) $request->input('type', '');
        if ($torrentId <= 0 || ! in_array($type, self::VALID_TYPES, true)) {
            abort(422, 'error');
        }

        $hideNotice = (string) $request->input('hidenotice', '') !== '';

        if ($type === 'firsttime' && $hideNotice) {
            NexusDB::table('users')
                ->where('id', (int) $viewer->id)
                ->update(['showdlnotice' => 0]);
        } elseif ($type === 'client' && $hideNotice) {
            NexusDB::table('users')
                ->where('id', (int) $viewer->id)
                ->update(['showclienterror' => 'no']);
        }

        // `?type=ratio` accepts no opt-out — the form's checkbox is
        // disabled in the GET render, so we ignore `hidenotice` for
        // it and just fall through to the redirect.
        return redirect('/download.php?id='.$torrentId.'&letdown=1');
    }

    private function handleGet(Request $request, $viewer): Response
    {
        $torrentId = (int) $request->query('torrentid', 0);
        $type = (string) $request->query('type', 'firsttime');
        if (! in_array($type, self::VALID_TYPES, true)) {
            $type = 'firsttime';
        }

        // Load the lang dictionary the legacy script depended on.
        // `get_langfile_path()` resolves to the user's selected
        // locale or the site default.
        $langDownloadNotice = $this->loadLangDictionary();

        [$title, $note, $noticeNextTime, $showRatio, $showClient, $forceCheck] =
            $this->resolvePanel($type, $viewer, $langDownloadNotice);

        $tdAttr = $showRatio && $showClient
            ? 'width="50%"'
            : 'colspan="2" width="100%"';

        $headTitle = htmlspecialchars(
            (string) ($langDownloadNotice['head_download_notice'] ?? 'Download notice'),
            ENT_QUOTES | ENT_HTML5, 'UTF-8',
        );
        $titleHtml = htmlspecialchars($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $noteHtml = (string) $note; // pre-translated, may contain HTML

        $html = '<!DOCTYPE html><html><head>'
            .'<meta http-equiv="Content-Type" content="text/html; charset=utf-8">'
            ."<title>{$headTitle}</title></head><body>"
            ."<h2>{$titleHtml}</h2>"
            .'<table width="100%"><tr>'
            ."<td colspan=\"2\" class=\"text\" align=\"left\"><p>{$noteHtml}</p></td>"
            .'</tr><tr>';

        if ($showRatio) {
            $html .= $this->ratioPanel($langDownloadNotice, $tdAttr);
        }
        if ($showClient) {
            $html .= $this->clientPanel($langDownloadNotice, $tdAttr);
        }
        $html .= '</tr>';

        if ($torrentId > 0) {
            $html .= $this->submitForm(
                $langDownloadNotice,
                $torrentId,
                $type,
                $forceCheck,
                $noticeNextTime,
            );
        }
        $html .= '</table></body></html>';

        return new Response($html);
    }

    /**
     * @return array<string,string>
     */
    private function loadLangDictionary(): array
    {
        if (function_exists('get_langfile_path')) {
            $path = (string) get_langfile_path();
            if ($path !== '' && is_file($path)) {
                $lang_downloadnotice = [];
                require $path;
                if (is_array($lang_downloadnotice)) {
                    return $lang_downloadnotice;
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string,string>  $lang
     * @return array{0:string,1:string,2:string,3:bool,4:bool,5:bool}
     */
    private function resolvePanel(string $type, $viewer, array $lang): array
    {
        switch ($type) {
            case 'client':
                return [
                    (string) ($lang['text_client_banned_notice'] ?? 'Client banned'),
                    (string) ($lang['text_client_banned_note'] ?? ''),
                    (string) ($lang['text_notice_not_show_again'] ?? "Don't show again"),
                    false, true, false,
                ];

            case 'ratio':
                $note = '';
                $until = strtotime((string) ($viewer->leechwarnuntil ?? '0'));
                if ($until > time()) {
                    $kicktimeout = function_exists('gettime')
                        ? (string) gettime((string) $viewer->leechwarnuntil, false, false, true)
                        : (string) ($viewer->leechwarnuntil ?? '');
                    $note = (string) ($lang['text_low_ratio_note_one'] ?? '')
                        .$kicktimeout
                        .(string) ($lang['text_low_ratio_note_two'] ?? '');
                }

                return [
                    (string) ($lang['text_low_ratio_notice'] ?? 'Low ratio'),
                    $note,
                    (string) ($lang['text_notice_always_show'] ?? 'Show every time'),
                    true, false, true,
                ];

            case 'firsttime':
            default:
                return [
                    (string) ($lang['text_first_time_download_notice'] ?? 'First-time download'),
                    (string) ($lang['text_first_time_download_note'] ?? ''),
                    (string) ($lang['text_notice_not_show_again'] ?? "Don't show again"),
                    true, true, false,
                ];
        }
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function ratioPanel(array $lang, string $tdAttr): string
    {
        $h3 = htmlspecialchars((string) ($lang['text_this_is_private_tracker'] ?? 'Private tracker'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return "<td class=\"text\" align=\"left\" valign=\"top\" {$tdAttr}>"
            ."<h3>{$h3}</h3>"
            .'<p>'.((string) ($lang['text_private_tracker_note_one'] ?? '')).'</p>'
            .'<p>'.((string) ($lang['text_private_tracker_note_two'] ?? '')).'</p>'
            .'<p>'.((string) ($lang['text_private_tracker_note_three'] ?? '')).'</p>'
            .'<img src="pic/ratio.png" alt="ratio" />'
            .'<p>'.((string) ($lang['text_private_tracker_note_four'] ?? '')).'</p>'
            .'</td>';
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function clientPanel(array $lang, string $tdAttr): string
    {
        $h3 = htmlspecialchars((string) ($lang['text_use_allowed_clients'] ?? 'Use allowed clients'), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return "<td class=\"text\" align=\"left\" valign=\"top\" {$tdAttr}>"
            ."<h3>{$h3}</h3>"
            .'<p>'.((string) ($lang['text_allowed_clients_note_one'] ?? '')).'</p>'
            .'<p>'.((string) ($lang['text_allowed_clients_note_two'] ?? '')).'</p>'
            .'<table width="100%"><tr>'
            .'<td class="embedded" style="text-align:center;padding:5px" width="50%">'
            .'<a href="https://www.qbittorrent.org/download" target="_blank">qBittorrent</a></td>'
            .'<td class="embedded" style="text-align:center;padding:5px" width="50%">'
            .'<a href="https://transmissionbt.com/download/" target="_blank">Transmission</a></td>'
            .'</tr></table></td>';
    }

    /**
     * @param  array<string,string>  $lang
     */
    private function submitForm(
        array $lang,
        int $torrentId,
        string $type,
        bool $forceCheck,
        string $noticeNextTime,
    ): string {
        $typeEsc = htmlspecialchars($type, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $checked = $forceCheck ? ' disabled="disabled"' : ' checked="checked"';
        $noticeEsc = htmlspecialchars($noticeNextTime, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $submitDisabled = $forceCheck ? ' disabled="disabled"' : '';
        $submitLabel = htmlspecialchars(
            (string) ($lang['submit_download_the_torrent'] ?? 'Continue'),
            ENT_QUOTES | ENT_HTML5, 'UTF-8',
        );

        $html = '<tr><td class="text" colspan="2">'
            .'<form action="?" method="post">'
            ."<input type=\"hidden\" name=\"id\" value=\"{$torrentId}\" />"
            ."<input type=\"hidden\" name=\"type\" value=\"{$typeEsc}\" />"
            ."<input type=\"checkbox\" name=\"hidenotice\" id=\"hidenotice\" value=\"1\"{$checked} />"
            ."<label for=\"hidenotice\">{$noticeEsc}</label>";

        if ($forceCheck) {
            $letMeLabel = htmlspecialchars(
                (string) ($lang['text_let_me_download'] ?? 'Let me download anyway'),
                ENT_QUOTES | ENT_HTML5, 'UTF-8',
            );
            $html .= '<br /><input type="checkbox" name="letmedown" id="letmedown" value="'.$typeEsc.'"'
                .' onclick="document.getElementById(\'continuedownload\').disabled = !this.checked;" />'
                ."<label for=\"letmedown\">{$letMeLabel}</label>";
        }

        $html .= "<div><input type=\"submit\" name=\"submit\" id=\"continuedownload\" value=\"{$submitLabel}\"{$submitDisabled} /></div>"
            .'</form></td></tr>';

        return $html;
    }
}
