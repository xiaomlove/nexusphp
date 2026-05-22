<?php

use App\Http\Controllers\AuthenticateController;
use App\Http\Controllers\Dev\ComponentGalleryController;
use App\Http\Controllers\ForumPostRedirectController;
use App\Http\Controllers\ForumTopicRedirectController;
use App\Http\Controllers\Legacy\AboutNexusController;
use App\Http\Controllers\Legacy\AddUserController;
use App\Http\Controllers\Legacy\AdRedirectController;
use App\Http\Controllers\Legacy\AllAgentsController;
use App\Http\Controllers\Legacy\AllowedEmailsController;
use App\Http\Controllers\Legacy\BannedEmailsController;
use App\Http\Controllers\Legacy\BansController;
use App\Http\Controllers\Legacy\BitBucketLogController;
use App\Http\Controllers\Legacy\BitBucketUploadController;
use App\Http\Controllers\Legacy\BonusLogController;
use App\Http\Controllers\Legacy\BookmarkController;
use App\Http\Controllers\Legacy\CheaterboxController;
use App\Http\Controllers\Legacy\CheatersController;
use App\Http\Controllers\Legacy\CheckUserController;
use App\Http\Controllers\Legacy\ClearCacheController;
use App\Http\Controllers\Legacy\ConfirmController;
use App\Http\Controllers\Legacy\ConfirmEmailController;
use App\Http\Controllers\Legacy\ContactStaffController;
use App\Http\Controllers\Legacy\DelAcctAdminController;
use App\Http\Controllers\Legacy\DeleteDisabledController;
use App\Http\Controllers\Legacy\DeleteTorrentController;
use App\Http\Controllers\Legacy\DocleanupController;
use App\Http\Controllers\Legacy\DonateController;
use App\Http\Controllers\Legacy\DonatedController;
use App\Http\Controllers\Legacy\DonorlistController;
use App\Http\Controllers\Legacy\DownloadSubsController;
use App\Http\Controllers\Legacy\FaqController;
use App\Http\Controllers\Legacy\FastDeleteController;
use App\Http\Controllers\Legacy\FieldsController;
use App\Http\Controllers\Legacy\FormatsController;
use App\Http\Controllers\Legacy\FreeleechController;
use App\Http\Controllers\Legacy\GetAttachmentController;
use App\Http\Controllers\Legacy\GetExtInfoAjaxController;
use App\Http\Controllers\Legacy\ImageCaptchaController;
use App\Http\Controllers\Legacy\IncrementBulkController;
use App\Http\Controllers\Legacy\IpCheckController;
use App\Http\Controllers\Legacy\IpHistoryController;
use App\Http\Controllers\Legacy\LogoutController;
use App\Http\Controllers\Legacy\MagicController;
use App\Http\Controllers\Legacy\MailtestController;
use App\Http\Controllers\Legacy\MassmailController;
use App\Http\Controllers\Legacy\MedalController;
use App\Http\Controllers\Legacy\ModrulesController;
use App\Http\Controllers\Legacy\MoreSmiliesController;
use App\Http\Controllers\Legacy\MyhrController;
use App\Http\Controllers\Legacy\NoWarnController;
use App\Http\Controllers\Legacy\OkController;
use App\Http\Controllers\Legacy\OpensearchController;
use App\Http\Controllers\Legacy\PollOverviewController;
use App\Http\Controllers\Legacy\PreviewController;
use App\Http\Controllers\Legacy\PromotionLinkController;
use App\Http\Controllers\Legacy\ResetController;
use App\Http\Controllers\Legacy\RetriverController;
use App\Http\Controllers\Legacy\RulesController;
use App\Http\Controllers\Legacy\SearchSuggestController;
use App\Http\Controllers\Legacy\SelfEnableController;
use App\Http\Controllers\Legacy\SendMessageController;
use App\Http\Controllers\Legacy\SmiliesController;
use App\Http\Controllers\Legacy\SpecialController;
use App\Http\Controllers\Legacy\StaffMessController;
use App\Http\Controllers\Legacy\StaffPanelController;
use App\Http\Controllers\Legacy\StatsController;
use App\Http\Controllers\Legacy\SuggestController;
use App\Http\Controllers\Legacy\TakeConfirmController;
use App\Http\Controllers\Legacy\TakeContactController;
use App\Http\Controllers\Legacy\TakeFlushController;
use App\Http\Controllers\Legacy\TakeIncrementBulkController;
use App\Http\Controllers\Legacy\TakeReseedController;
use App\Http\Controllers\Legacy\TakeStaffMessController;
use App\Http\Controllers\Legacy\TakeUpdateController;
use App\Http\Controllers\Legacy\TestIpController;
use App\Http\Controllers\Legacy\ThanksController;
use App\Http\Controllers\Legacy\TorrentInfoController;
use App\Http\Controllers\Legacy\UncoController;
use App\Http\Controllers\Legacy\UploadersController;
use App\Http\Controllers\Legacy\UserAgreementController;
use App\Http\Controllers\Legacy\UserBanLogController;
use App\Http\Controllers\Legacy\UserHistoryController;
use App\Http\Controllers\Legacy\UsersListController;
use App\Http\Controllers\Legacy\VideoFormatsController;
use App\Http\Controllers\Legacy\ViewFileListController;
use App\Http\Controllers\Legacy\ViewNfoController;
use App\Http\Controllers\Legacy\ViewSnatchesController;
use App\Http\Controllers\Legacy\WarnedController;
use App\Http\Controllers\OauthController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\TorrentController;
use App\Livewire\ForumIndex;
use App\Livewire\ForumSearch;
use App\Livewire\ForumUnread;
use App\Livewire\ForumView;
use App\Livewire\NewTopicForm;
use App\Livewire\TopicView;
use App\Livewire\TorrentBrowse;
use App\Livewire\TorrentDetail;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect('index.php');
});

Route::get('/error', [ToolController::class, 'error']);

/*
 * Phase 2 of the legacy migration — see `docs/legacy-strategy.md`.
 * Each route here replaces a legacy `public/<page>.php` file that is
 * deleted in the same PR. The URLs are kept stable so the existing
 * front-end (templates, AJAX helpers, browser OpenSearch metadata)
 * keeps working without template/JS changes.
 *
 * The matching nginx rules that route these URLs to Laravel live in
 * `.docker/openresty/sites/app.conf.template` — without them, the
 * catch-all `location ~ \.php$` would `fastcgi_pass` to a file that
 * no longer exists and return a fastcgi 404.
 *
 * POST endpoints exempt from CSRF (because their callers are legacy
 * forms / XHRs that do not send a token) are listed in
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 */

// Public, no auth required.
Route::get('/searchsuggest.php', SearchSuggestController::class)->name('legacy.searchsuggest');
Route::get('/suggest.php', SuggestController::class)->name('legacy.suggest');

/*
 * Phase 2 — replaces `public/opensearch.php` (deleted in PR #210).
 * Static-ish OpenSearch description XML discovered by every
 * `stdhead()` page through the `<link rel="search">` element in
 * `include/functions.php:2367`. Reachable as a guest (legacy script
 * had no auth check), URL stays `/opensearch.php` so browsers'
 * cached search-engine registrations keep working.
 */
Route::get('/opensearch.php', OpensearchController::class)->name('legacy.opensearch');

/*
 * Phase 2 — replaces `public/ok.php` (deleted in the same PR).
 * Generic post-signup / post-confirmation message page that
 * legacy signup/confirm flows redirect users onto. Guests must be
 * able to land here unauthenticated (e.g. directly after the
 * confirmation email link), so the route stays outside any auth
 * middleware. URL stays `/ok.php` so the existing redirects in
 * `takesignup.php`, `confirm_resend.php`, and `ConfirmController`
 * keep working without further changes.
 */
Route::get('/ok.php', OkController::class)->name('legacy.ok');

/*
 * Phase 2 batch #8 — replaces `public/rules.php` (deleted in the
 * same PR). The legacy script had `loggedinorreturn()` commented out
 * and was reachable as a guest, so the route stays outside the
 * `auth.nexus` middleware. The URL stays `/rules.php` so existing
 * navigation, the E2E smoke spec, and external links keep working
 * without template changes. The matching nginx exact-location entry
 * lives in `.docker/openresty/sites/app.conf.template`.
 */
Route::get('/rules.php', RulesController::class)->name('legacy.rules');

/*
 * Phase 2 — replaces `public/faq.php` (deleted in the same PR).
 * The legacy script had `loggedinorreturn()` commented out and was
 * reachable as a guest, so the route stays outside the `auth.nexus`
 * middleware. The URL stays `/faq.php` so existing navigation links
 * in `include/functions.php:1897`, `AgentAllowRepository` error
 * messages, and the cross-site `faq.php#idNN` anchors keep working
 * without template changes.
 */
Route::get('/faq.php', FaqController::class)->name('legacy.faq');

/*
 * Phase 2 — replaces `public/faqmanage.php` and `public/faqactions.php`
 * (both deleted in the same PR) with the Filament admin resource
 * `App\Filament\Resources\Faq\FaqResource`, which lives at
 * `/nexusphp/faqs`.
 *
 * The legacy URLs are preserved as 302 redirects so:
 *   - The `AdminpanelTableSeeder` row #6 (`url=faqmanage.php`) keeps
 *     working until a fresh `db:seed` lands on every install. The
 *     row is also rewritten to `/nexusphp/faqs` directly in the same
 *     seeder change, but existing installs hit the redirect.
 *   - Any bookmark, in-page link, or screenshot pointing at
 *     `faqactions.php?action=…` still lands on the new admin instead
 *     of a 404 (the legacy action verbs no longer exist; the user
 *     reaches the list page and re-issues the action through Filament).
 *
 * Both routes live OUTSIDE `auth.nexus:nexus-web` because the
 * Filament panel runs its own authentication via the
 * `App\Http\Middleware\Filament` middleware — its redirect target is
 * `/login.php` (see `app/Http/Middleware/Filament.php`), which is
 * exactly what the legacy `loggedinorreturn()` did, so an
 * unauthenticated request lands on the same login screen either way.
 */
Route::redirect('/faqmanage.php', '/nexusphp/faqs', 302)
    ->name('legacy.faqmanage');
Route::any('/faqactions.php', static fn () => redirect('/nexusphp/faqs', 302))
    ->name('legacy.faqactions');

/*
 * Phase 2 — strangles `public/catmanage.php` (deleted in the same
 * PR, −885 LOC). The legacy script was an 11-tab admin hub for the
 * `categories` / `sources` / `media` / `codecs` / `standards` /
 * `processings` / `teams` / `audiocodecs` / `searchbox` / `caticon`
 * / `secondicon` lookup tables; **all eleven targets already have
 * Filament resources** under `app/Filament/Resources/Section/`
 * (CategoryResource / SourceResource / MediaResource / CodecResource
 * / StandardResource / ProcessingResource / TeamResource /
 * AudioCodecResource / SectionResource / IconResource /
 * SecondIconResource), grouped under the `"Section"` navigation
 * group in the Filament admin panel. So no new code is needed here —
 * just retire the legacy hub and 302 callers into the modern one.
 *
 * Landing target: `/nexusphp/categories` (the resource the legacy
 * `?type=category` tab fed into, which is also the most frequently
 * used row of the legacy admin). From there an admin can hop to any
 * of the other ten resources via the `"Section"` navigation group.
 *
 * The `AdminpanelTableSeeder` row #8 (`url=catmanage.php`) is
 * rewritten to `/nexusphp/categories` in the same PR, and
 * `nexus/Install/Update.php::runExtraQueries()` already does
 * `removeMenu(['catmanage.php'])` (idempotent, since `@since 1.8.0`)
 * — so existing installs that haven't re-seeded keep landing on the
 * redirect during the upgrade window.
 *
 * The `Route::any` shape (vs `Route::redirect`) is intentional: the
 * legacy script accepted GET form-submits (e.g.
 * `?action=add&type=category`, `?action=del&type=codec&id=N`,
 * `?action=update`). Some of those URLs are still pasted into staff
 * chats / screenshots / private docs from the pre-Filament era; we
 * preserve every verb-and-querystring shape with a single redirect
 * so none of them 404.
 */
Route::any('/catmanage.php', static fn () => redirect('/nexusphp/categories', 302))
    ->name('legacy.catmanage');

/*
 * Phase 2 — strangles `public/location.php` (deleted in the same
 * PR, −249 LOC). The legacy script was a SYSOP-only CRUD over the
 * `locations` table with a quirky GET-with-querystring write
 * protocol (`?delid=N&sure=yes` for delete, `?editid=N` for edit
 * form, `?edited=1&...` for edit submit, `?add=true&...` for add
 * submit, `?check_range=true&range_start_ip=...` for range query).
 * Replaced with the standard Filament list / create / edit pages
 * at `/nexusphp/locations` (`App\Filament\Resources\System\
 * LocationResource`).
 *
 * `Route::any` (rather than `Route::redirect`) preserves every
 * verb-and-querystring shape the legacy script accepted. SYSOP
 * bookmarks pasted from the pre-Filament era — including the
 * GET-form-submit URLs that contain a full `&start_ip=...&end_ip=`
 * payload — all 302 to the new admin without 404'ing.
 *
 * `SysoppanelTableSeeder` row 10 (`url=location.php`) is rewritten
 * to `/nexusphp/locations` in the same PR; existing installs reach
 * the new admin through this redirect during the upgrade window
 * until `nexus/Install/Update.php::runExtraQueries()` swaps the
 * row for them.
 *
 * The route lives OUTSIDE `auth.nexus:nexus-web` because the
 * Filament panel runs its own authentication via
 * `App\Http\Middleware\Filament` — its redirect target is
 * `/login.php` (same place legacy `loggedinorreturn()` bounced
 * guests), so an unauthenticated request lands on the same login
 * screen either way.
 */
Route::any('/location.php', static fn () => redirect('/nexusphp/locations', 302))
    ->name('legacy.location');

/*
 * Phase 2 — replaces `public/useragreement.php` (deleted in the same
 * PR). The legacy script never gated on `loggedinorreturn()` and is
 * linked from `lang/<locale>/lang_faq.php`'s `text_welcome_content_two`
 * welcome paragraph (visible to guests on the FAQ), so the route stays
 * outside the `auth.nexus` middleware. The URL stays `/useragreement.php`
 * so the FAQ link, the body's self-reference link, and any external
 * bookmarks keep working without template changes. The matching nginx
 * exact-location entry lives in `.docker/openresty/sites/app.conf.template`.
 */
Route::get('/useragreement.php', UserAgreementController::class)->name('legacy.useragreement');

/*
 * Phase 2 batch #10 — replaces `public/getextinfoajax.php` (deleted
 * in the same PR). The legacy script was the XML AJAX endpoint
 * called from `public/js/common.js:371` (`get_ext_info_ajax(...)`).
 * It is loaded by torrent-details pages before any session check,
 * so the route stays outside the `auth.nexus` middleware. The
 * matching nginx exact-location entry lives in
 * `.docker/openresty/sites/app.conf.template`.
 */
Route::get('/getextinfoajax.php', GetExtInfoAjaxController::class)->name('legacy.getextinfoajax');

/*
 * Phase 2 — replaces `public/viewfilelist.php` (deleted in the same
 * PR). XHR endpoint called from `public/js/common.js:22`
 * (`viewfilelist(torrentid)`) — the response body is innerHTML-
 * spliced into the toggle-able file-list block on `details.php`.
 *
 * Stays OUTSIDE `auth.nexus:nexus-web` for legacy parity: the
 * legacy `if (isset($CURUSER))` gate becomes a `LegacyContext`
 * check returning the empty-body envelope. Placing the route
 * inside the auth group would redirect guests to `/login.php?...`,
 * and `ajax.gets` would splice the login-page HTML into the
 * details page (UX bug). The matching nginx exact-location entry
 * lives in `.docker/openresty/sites/app.conf.template`.
 */
Route::get('/viewfilelist.php', ViewFileListController::class)->name('legacy.viewfilelist');

/*
 * Phase 3 — replaces `public/aboutnexus.php` (deleted in the same PR).
 * The legacy page never gated on `loggedinorreturn()` and is linked
 * from the global `Powered by NexusPHP` footer, so the route stays
 * outside the `auth.nexus` middleware. The URL is unchanged so the
 * footer link, anchored "#version" / "#stylesheet" / etc. links, and
 * the existing E2E smoke probe keep working without template changes.
 */
Route::get('/aboutnexus.php', AboutNexusController::class)->name('legacy.aboutnexus');

// Phase 2 batch #2 — public legacy routes (no auth).
//
// `/logout.php`: clearing the legacy auth cookie is a guest-safe op.
// `/image.php`: signup-form CAPTCHA image, served before login.
// `/bookmark.php`: returns a `failed` token for guests rather than
//   redirecting — the front-end JS expects a plain-text response.
// `/confirmemail.php/{id}/{md5}/{email}`: the signed URL is the auth
//   token; no session cookie is required.
Route::any('/logout.php', LogoutController::class)->name('legacy.logout');
Route::get('/image.php', ImageCaptchaController::class)->name('legacy.image');
Route::get('/bookmark.php', BookmarkController::class)->name('legacy.bookmark');
Route::get('/confirmemail.php/{id}/{md5}/{email}', ConfirmEmailController::class)
    ->where('id', '[0-9]+')
    ->where('md5', '[a-fA-F0-9]{32}')
    ->where('email', '.*')
    ->name('legacy.confirmemail');

/*
 * Phase 2 — replaces `public/confirm.php` (deleted in the same PR).
 * The signed `?id=<int>&secret=<md5>` URL is the only auth token, so
 * the route stays outside `auth.nexus` middleware. The URL itself is
 * unchanged — every signup-confirmation email in the wild points at
 * `/confirm.php?id=...&secret=...`.
 */
Route::get('/confirm.php', ConfirmController::class)->name('legacy.confirm');

Route::middleware(['auth.nexus:nexus-web'])->group(function () {
    Route::get('/browse', TorrentBrowse::class)->name('torrents.browse');

    /*
     * Phase 3.4 / Modern UI A3 — `App\Livewire\TorrentDetail` is the
     * Modern UI shell for the legacy `public/details.php` page. Only
     * the core metadata + download CTA is rendered here; the
     * "View full legacy page" link in the page header is the escape
     * hatch back to the still-canonical detail page. `?legacy=1`
     * also redirects to `/details.php?id={id}&legacy=1` so the
     * canary rollback flag works the same way it does on `/browse`.
     */
    Route::get('/torrent/{id}', TorrentDetail::class)
        ->where('id', '[0-9]+')
        ->name('torrents.detail');

    /*
     * Internal Modern-UI component gallery (admin-only).
     * Renders every `<x-ui.*>` Blade component under
     * `resources/views/components/ui/`. See the controller doc-block
     * for the rationale; the route is staff-gated so it's never
     * surfaced through public navigation.
     */
    Route::get('/dev/components', ComponentGalleryController::class)->name('dev.components');

    Route::post('/thanks.php', ThanksController::class)->name('legacy.thanks');

    /*
     * Phase 3 — replaces `public/magic.php` (deleted in this PR).
     * "Give magic" XHR endpoint that
     * `public/js/common.js#saveMagicValue` POSTs to with `id`
     * (torrent id) + `value` (bonus amount). Same CSRF carve-out
     * and nginx exact-location companion rule as `/thanks.php`.
     * Response is the legacy `{ret, msg, data}` JSON envelope at
     * HTTP 200 — the JS only inspects `res.ret`.
     */
    Route::post('/magic.php', MagicController::class)->name('legacy.magic');

    Route::get('/special.php', SpecialController::class)->name('legacy.special');

    Route::match(['get', 'post'], '/preview.php', PreviewController::class)
        ->name('legacy.preview');

    /*
     * Phase 2.3 — replaces `public/takecontact.php` (deleted in this
     * PR). The URL stays `/takecontact.php` so the legacy
     * `<form action="takecontact.php">` in `public/contactstaff.php`
     * keeps posting to the same endpoint without template changes.
     * Same CSRF carve-out as `/thanks.php` (the legacy form has no
     * `@csrf` token and adding one is a separate change touching every
     * legacy form helper). Same nginx companion rule in
     * `.docker/openresty/sites/app.conf.template`.
     */
    Route::post('/takecontact.php', TakeContactController::class)->name('legacy.takecontact');

    /*
     * Phase 2 batch — replaces `public/staffmess.php` (deleted in
     * this PR). The URL stays `/staffmess.php` so existing admin
     * bookmarks and the `TakeStaffMessController` post-success
     * redirect to `/staffmess.php?sent=1` keep working without a
     * template change. The rendered `<form action="takestaffmess.php">`
     * still posts to `/takestaffmess.php` so the existing
     * `TakeStaffMessController` write-handler keeps receiving
     * POSTs without a URL change. Admin-only (the controller checks
     * `User::CLASS_ADMINISTRATOR` and `abort(403)`s on lower
     * classes).
     */
    Route::get('/staffmess.php', StaffMessController::class)->name('legacy.staffmess');

    /*
     * Phase 2 batch — replaces `public/takestaffmess.php` (deleted in
     * this PR). The URL stays `/takestaffmess.php` so the legacy
     * `<form action="takestaffmess.php">` rendered by
     * `StaffMessController` keeps posting to the same endpoint
     * without a template change. Same CSRF carve-out as
     * `/takecontact.php` (the legacy form has no `@csrf` token).
     * Inside the controller the actual fan-out runs as a
     * `SendStaffMassMessage` queue job — the legacy script ran the
     * `while (true) { LIMIT ?,10000 }` loop inline and blocked the
     * browser; the migrated endpoint dispatches and returns a 302
     * to `/staffmess.php?sent=1` (same redirect target as the
     * legacy script, so the form-render page's "?sent=1" branch
     * still shows the "The message has been sent." confirmation
     * without a template change).
     */
    Route::post('/takestaffmess.php', TakeStaffMessController::class)->name('legacy.takestaffmess');

    Route::post('/takeupdate.php', TakeUpdateController::class)->name('legacy.takeupdate');

    /*
     * Phase 2 batch #3 — replaces `public/moresmilies.php` (deleted
     * in this PR). The URL stays `/moresmilies.php` so the legacy
     * compose helper in `include/functions.php:985` (which calls
     * `window.open("moresmilies.php?form=...&text=...", ...)`)
     * keeps opening the popup without template changes. Chrome-less
     * popup; no companion nginx rule needed (covered by the
     * default `location ~* \.php$` → `@nexus_app` rewrite).
     */
    Route::get('/moresmilies.php', MoreSmiliesController::class)->name('legacy.moresmilies');

    /*
     * Phase 2 batch #4 — replaces `public/smilies.php` (deleted in
     * this PR). The URL stays `/smilies.php` so legacy compose-helper
     * links keep working without template changes. Authed-only, same
     * `auth.nexus:nexus-web` guard as the rest of this group.
     */
    Route::get('/smilies.php', SmiliesController::class)->name('legacy.smilies');

    /*
     * Phase 2 — replaces `public/formats.php` (deleted in the same
     * PR, −215 LOC). Static user-facing guide on common file
     * extensions and the apps that open them — no DB queries, no
     * per-locale lang lookups, no permission-dependent branching;
     * just `loggedinorreturn()` + `stdhead()` + a wall of inline
     * HTML. The migrated controller renders the body verbatim from
     * `resources/views/legacy/formats.blade.php` inside a
     * chrome-less envelope. Same shape as `MoreSmiliesController`
     * / `RulesController`.
     *
     * URL stays `/formats.php` because nothing in the codebase
     * references it (no menu seeder row, no legacy `<a href>` in
     * `include/functions.php`); preserving it costs us one route
     * and saves any external bookmark / SE result that points at
     * the page.
     */
    Route::get('/formats.php', FormatsController::class)->name('legacy.formats');

    /*
     * Phase 2 — replaces `public/videoformats.php` (deleted in the
     * same PR, −204 LOC). Same shape as `/formats.php`: static
     * user-facing glossary of video-rip release tags (CAM / TS /
     * TC / SCR / DVDRip / TVRip / WP / NUKED / DUPE / ...). The
     * body has zero dynamic content and is rendered verbatim from
     * `resources/views/legacy/videoformats.blade.php`.
     */
    Route::get('/videoformats.php', VideoFormatsController::class)->name('legacy.videoformats');

    /*
     * Phase 2 batch #4 — replaces `public/allagents.php` (deleted in
     * this PR). Moderator-only listing of BitTorrent peer agents and
     * their connection counts; the class check lives inside the
     * controller (returns 403 below the threshold).
     */
    Route::get('/allagents.php', AllAgentsController::class)->name('legacy.allagents');

    /*
     * Phase 2 batch #4 — replaces `public/clearcache.php` (deleted in
     * this PR). Moderator-only tool that issues `Cache::forget()` for
     * a single Redis key (with optional per-language-folder fan-out
     * via the `multilang=yes` checkbox). Same `auth.nexus:nexus-web`
     * guard + in-controller class gate as `/allagents.php`. The POST
     * verb is CSRF-exempt — see `App\Http\Middleware\VerifyCsrfToken`.
     */
    Route::match(['get', 'post'], '/clearcache.php', ClearCacheController::class)
        ->name('legacy.clearcache');

    /*
     * Phase 2 batch #5 — replaces `public/contactstaff.php` (deleted
     * in this PR). Authed-only "Contact Staff" form that posts to
     * the already-migrated `/takecontact.php`. Chrome-less envelope;
     * the BBCode editor / smilies panel from the legacy
     * `begin_compose()` helper are not reproduced — users can paste
     * `[emN]` tokens directly. No companion nginx rule needed
     * (covered by the default `location ~* \.php$` → `@nexus_app`
     * rewrite).
     */
    Route::get('/contactstaff.php', ContactStaffController::class)->name('legacy.contactstaff');

    /*
     * Phase 2 batch #5 — replaces `public/donated.php` (deleted in
     * this PR). Sysop-only tool for setting `users.donated` for a
     * given username. GET renders the form; POST updates the row
     * and 302s to `/userdetails.php?id=<id>`. The POST verb is
     * CSRF-exempt — see `App\Http\Middleware\VerifyCsrfToken`.
     */
    Route::match(['get', 'post'], '/donated.php', DonatedController::class)
        ->name('legacy.donated');

    /*
     * Phase 2 batch #5 — replaces `public/takeflush.php` (deleted in
     * this PR). Self-flush / moderator-only action that deletes a
     * user's "ghost" peers (peers with `last_action` older than
     * `deadtime()`). Legacy verb was GET (the link comes from
     * userdetails.php); preserved here.
     */
    Route::get('/takeflush.php', TakeFlushController::class)->name('legacy.takeflush');

    /*
     * Phase 2 — replaces `public/adredir.php` (deleted in this PR).
     * Authed-only ad click tracker that records the click in
     * `adclicks`, optionally awards `users.seedbonus +=
     * advertisement.adclickbonus` on first click per user/ad,
     * and 302s to the click target URL. The migrated controller
     * closes the original open-redirect by accepting `?url=` only
     * when it matches a URL embedded in `advertisements.code` by
     * `public/admanage.php` (the SYSOP-only admin tool that owns the
     * ad lifecycle); see `AdRedirectController` PHPDoc.
     */
    Route::get('/adredir.php', AdRedirectController::class)->name('legacy.adredir');

    /*
     * Phase 2 batch #6 — replaces `public/bannedemails.php` (deleted
     * in this PR). Sysop-only single-row key-value editor for the
     * registration blacklist (`bannedemails.value`). The POST verb
     * is CSRF-exempt — see `App\Http\Middleware\VerifyCsrfToken`.
     */
    Route::match(['get', 'post'], '/bannedemails.php', BannedEmailsController::class)
        ->name('legacy.bannedemails');

    /*
     * Phase 2 batch #6 — replaces `public/allowedemails.php`
     * (deleted in this PR). Mirror of `/bannedemails.php` for the
     * registration whitelist (`allowedemails.value`). Same CSRF
     * carve-out.
     */
    Route::match(['get', 'post'], '/allowedemails.php', AllowedEmailsController::class)
        ->name('legacy.allowedemails');

    /*
     * Phase 2 batch #6 — replaces `public/nowarn.php` (deleted in
     * this PR). Moderator-only bulk action that removes warnings
     * (`usernw[]`) and/or disables accounts (`desact[]`) and 302s
     * to `/warned.php`. POST-only (the legacy script never had a
     * GET branch); CSRF-exempt.
     */
    Route::post('/nowarn.php', NoWarnController::class)->name('legacy.nowarn');

    /*
     * Phase 2 batch #7 — replaces `public/freeleech.php` (deleted
     * in this PR). Administrator-only global promotion-state
     * editor: `?action=...` flips `torrents_state.global_sp_state`,
     * flushes the `global_promotion_state` cache, and dispatches
     * `TorrentPromotionChanged`. POST verb is CSRF-exempt — the
     * legacy admin UI uses GET links from the menu, but POST is
     * accepted as well to match the legacy script's
     * `$_POST['action'] ?? $_GET['action']` precedence.
     */
    Route::match(['get', 'post'], '/freeleech.php', FreeleechController::class)
        ->name('legacy.freeleech');

    /*
     * Phase 2 batch #7 — replaces `public/deletedisabled.php`
     * (deleted in this PR). Sysop-only bulk delete of users with
     * `enabled='no'`. POST `sure=1` triggers the delete; CSRF-exempt.
     */
    Route::match(['get', 'post'], '/deletedisabled.php', DeleteDisabledController::class)
        ->name('legacy.deletedisabled');

    /*
     * Phase 2 batch #7 — replaces `public/delacctadmin.php` (deleted
     * in this PR). Permission `user-delete` (admin+) form that
     * deletes a single account via `UserRepository::destroy`.
     * CSRF-exempt.
     */
    Route::match(['get', 'post'], '/delacctadmin.php', DelAcctAdminController::class)
        ->name('legacy.delacctadmin');

    /*
     * Phase 2 batch #9 — replaces `public/donorlist.php` (deleted in
     * this PR). Admin+ listing of users with `donor='yes'`, paginated
     * via `?page=<n>` (50 rows per page).
     */
    Route::get('/donorlist.php', DonorlistController::class)
        ->name('legacy.donorlist');

    /*
     * Phase 2 batch #9 — replaces `public/mailtest.php` (deleted in
     * this PR). Sysop-only SMTP test page. GET shows the form, POST
     * `action=sendmail` triggers `ToolRepository::sendMail`.
     * CSRF-exempt.
     */
    Route::match(['get', 'post'], '/mailtest.php', MailtestController::class)
        ->name('legacy.mailtest');

    /*
     * Phase 2 — replaces `public/massmail.php` (deleted in this PR).
     * SYSOP+ "Mass E-mail Gateway" — sends one e-mail per user
     * matching a `class <op> <threshold>` filter. Single endpoint
     * (legacy form posts to itself); GET renders the form, POST
     * validates input and dispatches the `App\Jobs\SendMassMail`
     * queue job, then 302s to `/massmail.php?sent=1`. URL stays
     * `/massmail.php` so the
     * `SysoppanelTableSeeder.url='massmail.php'` menu entry, the
     * legacy `<form action=massmail.php>` self-submit on the
     * rendered form, and any admin bookmarks keep working without
     * template changes. POST is CSRF-exempt — the legacy form has
     * no `@csrf` field.
     */
    Route::match(['get', 'post'], '/massmail.php', MassmailController::class)
        ->name('legacy.massmail');

    /*
     * Phase 2 batch #9 — replaces `public/adduser.php` (deleted in
     * this PR). Administrator+ user creation form. GET shows the
     * form, POST proxies to `UserRepository::store` and redirects to
     * `/userdetails.php?id={new_id}` on success. CSRF-exempt.
     */
    Route::match(['get', 'post'], '/adduser.php', AddUserController::class)
        ->name('legacy.adduser');

    /*
     * Phase 2 batch #11 — replaces `public/user-ban-log.php`
     * (deleted in this PR). Administrator+ listing of
     * `user_ban_logs` entries, paginated via `?page=<n>` (50 rows
     * per page) and filtered by an optional `?q=<username>`
     * substring. Linked from `public/complains.php:170` (a
     * staff-only "view ban log" deep link); the URL is unchanged
     * so the existing complain form keeps working without template
     * changes.
     */
    Route::get('/user-ban-log.php', UserBanLogController::class)
        ->name('legacy.userbanlog');

    /*
     * Phase 2 batch #11 — replaces `public/takereseed.php` (deleted
     * in this PR). The "Ask for Reseed" GET endpoint linked from
     * `public/details.php:191` for dead torrents. Power-user+ only
     * (gated by the legacy `$AUTHORITY['askreseed']` knob). Fans
     * out a PM to every finished snatcher, stamps
     * `torrents.last_reseed = NOW()`, and renders a chrome-less
     * success page.
     */
    Route::get('/takereseed.php', TakeReseedController::class)
        ->name('legacy.takereseed');

    /*
     * Phase 2 batch #11 — replaces `public/takeconfirm.php` (deleted
     * in this PR). Authed POST endpoint that confirms one or more
     * pending invitees (`users.status='pending'` →
     * `'confirmed'`). The legacy contract is `$CURUSER['id'] == $id
     * || user_can('viewinvite')`; reproduced verbatim in the
     * controller. CSRF-exempt — see
     * `App\Http\Middleware\VerifyCsrfToken`.
     */
    Route::post('/takeconfirm.php', TakeConfirmController::class)
        ->name('legacy.takeconfirm');

    /*
     * Phase 2 batch — replaces `public/bitbucketlog.php` (deleted in
     * this PR). Administrator+ paginated list of uploaded BitBucket
     * images with an inline `?delete=<id>` action. URL stays
     * `/bitbucketlog.php` so the `SysoppanelTableSeeder.url='bitbucketlog.php'`
     * menu entry keeps working without template changes.
     */
    Route::get('/bitbucketlog.php', BitBucketLogController::class)
        ->name('legacy.bitbucketlog');

    /*
     * Phase 2 — replaces `public/bitbucket-upload.php` (deleted in
     * this PR). User-facing avatar-upload tool. GET renders a
     * `<form action="bitbucket-upload.php" enctype="multipart/form-data">`,
     * POST validates + GD-resamples the image to fit `200×150`,
     * writes the file under `public/<main.bitbucket>/`, INSERTs a
     * `bitbucket` row, and updates `users.avatar`. The endpoint
     * is gated on three conditions: authed, not parked, and
     * `Setting::get('main.enablebitbucket') === 'yes'`. URL stays
     * `/bitbucket-upload.php` so the FAQ link, the
     * `lang/<locale>/lang_usercp.php text_bitbucket_note`
     * paragraph rendered on `public/usercp.php:255`, and any user
     * bookmarks keep working without template changes. POST is
     * CSRF-exempt — the legacy form has no `@csrf` field.
     */
    Route::match(['get', 'post'], '/bitbucket-upload.php', BitBucketUploadController::class)
        ->name('legacy.bitbucket-upload');

    /*
     * Phase 2 — replaces `public/polloverview.php` (deleted in this
     * PR). Administrator+ poll-overview tool. GET-only — the legacy
     * script had no POST branch. Two render branches: `?id=<n>`
     * shows the poll detail (header + options + paginated voter
     * list); no `?id` lists every poll. URL stays
     * `/polloverview.php` so the `public/index.php:492` poll-digest
     * deep link, the `AdminpanelTableSeeder.url='polloverview.php'`
     * menu entry, and the `/polloverview.php` E2E smoke spec all
     * keep working without template changes.
     */
    Route::get('/polloverview.php', PollOverviewController::class)
        ->name('legacy.polloverview');

    /*
     * Phase 2 — replaces `public/testip.php` (deleted in this PR).
     * Moderator-only IP-ban check tool. Accepts both GET (the form
     * itself + `?ip=<addr>` deep links from `public/usersearch.php`
     * line 737 and the modpanel "IP Test" menu entry) and POST
     * (the legacy `<form method=post action=testip.php>` body).
     * CSRF-exempt — see `App\Http\Middleware\VerifyCsrfToken`.
     */
    Route::match(['get', 'post'], '/testip.php', TestIpController::class)
        ->name('legacy.testip');

    Route::get('/ipcheck.php', IpCheckController::class)
        ->name('legacy.ipcheck');

    Route::match(['get', 'post'], '/bans.php', BansController::class)
        ->name('legacy.bans');

    Route::get('/iphistory.php', IpHistoryController::class)
        ->name('legacy.iphistory');

    Route::get('/staffpanel.php', StaffPanelController::class)
        ->name('legacy.staffpanel');

    /*
     * Phase 2 — replaces `public/delete.php` (deleted in the same PR).
     * POST-only torrent deletion endpoint. Permission-gated on
     * `torrent-delete`; validates ownership or `torrentmanage`.
     * Deletes from ES, calls `deletetorrent()`, deducts karma,
     * sends PM to owner. CSRF-exempt — the legacy form in
     * `details.php` has no `@csrf` field.
     */
    Route::post('/delete.php', DeleteTorrentController::class)
        ->name('legacy.delete');

    /*
     * Phase 2 — replaces `public/medal.php` (deleted in the same PR).
     * Authed medal shop. Paginated (20/page) listing of purchasable
     * medals with buy/gift AJAX buttons (POST to `ajax.php`).
     * Optional `?q=` name filter.
     */
    Route::get('/medal.php', MedalController::class)
        ->name('legacy.medal');

    /*
     * Phase 2 — replaces `public/users.php` (deleted in the same PR).
     * Permission-gated on `viewuserlist`. Paginated (50/page) user
     * listing with search, class filter, country filter, A-Z index.
     */
    Route::get('/users.php', UsersListController::class)
        ->name('legacy.users');

    /*
     * Phase 2 — replaces `public/myhr.php` (deleted in the same PR).
     * Authed H&R listing. Defaults to current user; cross-user view
     * requires `viewhistory` permission. Status filter, pagination
     * (50/page), remove-HR AJAX button (POST to `ajax.php`).
     */
    Route::get('/myhr.php', MyhrController::class)
        ->name('legacy.myhr');

    /*
     * Phase 2 — replaces `public/uploaders.php` (deleted in the same
     * PR). Uploader+ (class >= 12) monthly activity stats. Year/month
     * selector, sortable by username/size/count.
     */
    Route::get('/uploaders.php', UploadersController::class)
        ->name('legacy.uploaders');

    /*
     * Phase 2 — replaces `public/torrent_info.php` (deleted in the
     * same PR). Authed endpoint gated on the `torrentstructure`
     * permission (default class 8 = Insane User). Reads the .torrent
     * file from disk, decodes the bencode, and renders an expandable
     * HTML tree of the file structure. URL stays `/torrent_info.php`
     * so the existing details-page link keeps working.
     */
    Route::get('/torrent_info.php', TorrentInfoController::class)
        ->name('legacy.torrentinfo');

    /*
     * Phase 2 — replaces `public/donate.php` (deleted in the same
     * PR). Authed donation page. Checks `main.donation` setting;
     * renders PayPal/Alipay forms when configured, or a
     * "not accepting donations" message. `?do=thanks` branch for
     * the PayPal return URL. URL stays `/donate.php` so the PayPal
     * `return` callback keeps working.
     */
    Route::get('/donate.php', DonateController::class)
        ->name('legacy.donate');

    /*
     * Phase 2 — replaces `public/cheaters.php` (deleted in the same
     * PR). Moderator+ cheat-analysis tool. Paginated (20/page, max
     * 100) list of users ranked by `cheat` score with filters for
     * class threshold (`?c=`) and ratio threshold (`?r=`). URL stays
     * `/cheaters.php` so existing staff bookmarks keep working.
     */
    Route::get('/cheaters.php', CheatersController::class)
        ->name('legacy.cheaters');

    /*
     * Phase 2 — replaces `public/stats.php` (deleted in the same PR).
     * Moderator+ uploader/category activity stats. The URL stays
     * `/stats.php` so the `ModpanelTableSeeder.url='stats.php'` menu
     * entry keeps working without template changes.
     */
    Route::get('/stats.php', StatsController::class)
        ->name('legacy.stats');

    /*
     * Phase 2 — replaces `public/modrules.php` (deleted in the same
     * PR). Administrator+ CRUD for the `rules` table. The URL stays
     * `/modrules.php` so the `AdminpanelTableSeeder.url='modrules.php'`
     * menu entry keeps working without template changes. POST is
     * CSRF-exempt — the legacy forms have no `@csrf` field.
     */
    Route::match(['get', 'post'], '/modrules.php', ModrulesController::class)
        ->name('legacy.modrules');

    Route::get('/viewnfo.php', ViewNfoController::class)
        ->name('legacy.viewnfo');

    /*
     * Phase 2 — replaces `public/getattachment.php` (deleted in the
     * same PR). The attachment-download endpoint linked from
     * `include/functions.php:210` (the `<a href="getattachment.php?
     * id=N&dlkey=K">` block rendered inside attachment-bearing
     * posts/comments). The URL is unchanged so the existing hrefs
     * keep working without any template edits. Lives inside the
     * `auth.nexus:nexus-web` group because the legacy script called
     * `loggedinorreturn(); parked();` — guests redirect to login,
     * parked users get a 403.
     */
    Route::get('/getattachment.php', GetAttachmentController::class)
        ->name('legacy.getattachment');

    /*
     * Phase 2 — replaces `public/fastdelete.php` (deleted in this PR).
     * Two-step GET-delete: `?id=<n>` renders confirmation,
     * `?id=<n>&sure=1` performs delete and 302s to `/torrents.php`.
     * Entry point is the action link in `include/functions.php:3971`
     * (`<a href="fastdelete.php?id=N">`); URL is preserved.
     */
    Route::get('/fastdelete.php', FastDeleteController::class)
        ->name('legacy.fastdelete');

    Route::get('/downloadsubs.php', DownloadSubsController::class)
        ->name('legacy.downloadsubs');

    Route::get('/docleanup.php', DocleanupController::class)
        ->name('legacy.docleanup');

    Route::get('/unco.php', UncoController::class)->name('legacy.unco');

    /*
     * Phase 2 — replaces `public/warned.php` (deleted in this PR).
     * Moderator+ listing of warned, enabled accounts; the embedded
     * `<form action="nowarn.php">` posts to the already migrated
     * `/nowarn.php` endpoint. GET-only.
     */
    Route::get('/warned.php', WarnedController::class)->name('legacy.warned');

    /*
     * Phase 2 — replaces `public/checkuser.php` (deleted in this PR).
     * Detail view of a pending account; reachable by the inviter or
     * any moderator+. The embedded `<form action="takeconfirm.php">`
     * posts to the already migrated `/takeconfirm.php` endpoint.
     */
    Route::get('/checkuser.php', CheckUserController::class)
        ->name('legacy.checkuser');

    Route::match(['get', 'post'], '/reset.php', ResetController::class)
        ->name('legacy.reset');

    /*
     * Phase 2 — replaces `public/self-enable.php` (deleted in this
     * PR). User-facing "buy your way out of a ban with seedbonus"
     * page. The URL is the redirect target of
     * `include/functions.php:3169` (`nexus_redirect('self-enable.php')`
     * fires from `loggedinorreturn()` whenever a logged-in user with
     * `enabled != 'yes'` hits any other legacy page), so the route
     * must be reachable to disabled users — the `auth.nexus` guard
     * authenticates them but does not gate on `enabled`. Accepts
     * both GET (form render) and POST (legacy `<form method=post>`
     * confirmation submit); CSRF-exempt for the legacy form.
     */
    Route::match(['get', 'post'], '/self-enable.php', SelfEnableController::class)
        ->name('legacy.selfenable');

    /*
     * Phase 3 — replaces `public/bonus-log.php` (deleted in this PR).
     * Authed read-only listing of a user's bonus-log rows, paginated
     * via `?page=<n>` (50 rows per page) and filterable by
     * `?category=common|seeding` + `?business_type=<n>`. The legacy
     * script defaulted `?uid` to `$CURUSER['id']`; cross-user view
     * still requires the `viewhistory` permission (carried over via
     * `PermissionEnum::VIEW_USER_HISTORY`). The chrome-less envelope
     * follows the same Response pattern as `AboutNexusController`.
     */
    Route::get('/bonus-log.php', BonusLogController::class)
        ->name('legacy.bonus-log');

    Route::get('/userhistory.php', UserHistoryController::class)
        ->name('legacy.userhistory');

    Route::get('/viewsnatches.php', ViewSnatchesController::class)
        ->name('legacy.viewsnatches');

    Route::get('/sendmessage.php', SendMessageController::class)
        ->name('legacy.sendmessage');

    Route::match(['get', 'post'], '/cheaterbox.php', CheaterboxController::class)
        ->name('legacy.cheaterbox');

    /*
     * Phase 2 — replaces `public/fields.php` (deleted in this PR).
     * Administrator+ admin tool for the `torrents_custom_fields`
     * table (custom-field manager). GET-only on the read paths
     * (`view` / `add` / `edit` / `del`); the legacy
     * `?action=submit` POST handler had been deprecated since 1.10
     * and just `exit()`d with a hard-coded "go to the management
     * system" message — preserved verbatim by the controller, hence
     * the `match(['get','post'])`. CSRF-exempt because the legacy
     * `Field::buildFieldForm()` template (used by `add`/`edit`)
     * still renders a `<form method=post action=fields.php?action=submit>`
     * with no `@csrf` field. URL stays `/fields.php` so the
     * `AdminpanelTableSeeder.url='fields.php'` menu entry, the
     * `nexus/Install/Update.php::runExtraQueries()` `addMenu`
     * block, and any admin bookmarks keep working without template
     * changes.
     */
    Route::match(['get', 'post'], '/fields.php', FieldsController::class)
        ->name('legacy.fields');

    Route::get('/promotionlink.php', PromotionLinkController::class)
        ->name('legacy.promotionlink');

    /*
     * Phase 2 — replaces `public/increment-bulk.php` and
     * `public/take-increment-bulk.php` (both deleted in this PR).
     * SYSOP+ "batch add bonus / attendance card / invites / uploaded
     * / temporary invites" form (`/increment-bulk.php`) and its
     * write-handler (`/take-increment-bulk.php`). The pair mirrors
     * the `staffmess.php` / `takestaffmess.php` precedent: the
     * form-render half is GET, the write-handler is POST and
     * dispatches `App\Jobs\SendIncrementBulkBonus` so the inline
     * `while (true) { LIMIT ?,2000 }` fan-out loop no longer blocks
     * the browser. URLs stay unchanged so the
     * `SysoppanelTableSeeder.url='increment-bulk.php'` menu entry,
     * the `Update.php::runExtraQueries()` `addMenu` block, and any
     * admin bookmarks keep working without template changes.
     * `/take-increment-bulk.php` is CSRF-exempt (the legacy form
     * has no `@csrf` field) — see
     * `App\Http\Middleware\VerifyCsrfToken`.
     */
    Route::get('/increment-bulk.php', IncrementBulkController::class)
        ->name('legacy.incrementbulk');
    Route::post('/take-increment-bulk.php', TakeIncrementBulkController::class)
        ->name('legacy.takeincrementbulk');

    /*
     * Phase 2 — replaces `public/retriver.php` (deleted in this PR).
     * Authed-only "refresh external info" endpoint linked from
     * `public/details.php:449,473` (legacy IMDb cache refresh,
     * `?siteid=1`) and `nexus/PTGen/PTGen.php:143` (PTGen ratings
     * update, `?siteid=imdb|douban|bangumi`). GET-only — the
     * callers are anchor links, not forms. URL stays `/retriver.php`
     * (typo preserved — it's frozen into the public contract) so
     * the rendered details/ptgen blocks keep working without a
     * template change. Permission `updateextinfo` (default class
     * 7 — Extreme User) is enforced inside the controller.
     */
    Route::get('/retriver.php', RetriverController::class)
        ->name('legacy.retriver');

    Route::get('/torrents', TorrentBrowse::class)->name('torrents.browse.alias');
    Route::get('/forum', ForumIndex::class)->name('forum.index');
    Route::get('/forum/unread', ForumUnread::class)->name('forum.unread');
    Route::get('/forum/search', ForumSearch::class)->name('forum.search');
    Route::get('/forum/{forum}', ForumView::class)
        ->whereNumber('forum')
        ->name('forum.view');
    Route::get('/forum/{forum}/new', NewTopicForm::class)
        ->whereNumber('forum')
        ->name('forum.topic.new');
    Route::get('/forum/{forum}/topic/{topic}', TopicView::class)
        ->whereNumber('forum')
        ->whereNumber('topic')
        ->name('forum.topic');

    /*
     * Bare-topic-ID shortcut: resolves to `/forum/{forumid}/topic/{topic}`
     * after a `topics` table lookup. Used by the
     * `/forums.php?action=viewtopic&topicid=N` Strangler Fig redirect
     * (which deliberately does not run any DB query before Laravel
     * boots). See `docs/legacy-strategy.md` § "Phase 3.x: forums.php
     * flip" for the full migration plan.
     */
    Route::get('/forum/topic/{topic}', ForumTopicRedirectController::class)
        ->whereNumber('topic')
        ->name('forum.topic.shortcut');

    Route::get('/forum/post/{post}', ForumPostRedirectController::class)
        ->whereNumber('post')
        ->name('forum.post.shortcut');

    Route::post('/api/push/subscribe', [PushSubscriptionController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/api/push/unsubscribe', [PushSubscriptionController::class, 'unsubscribe'])->name('push.unsubscribe');
});

Route::group(['prefix' => 'web', 'middleware' => ['auth.nexus:nexus-web']], function () {
    Route::get('torrent-approval-page', [TorrentController::class, 'approvalPage']);
    Route::get('torrent-approval-logs', [TorrentController::class, 'approvalLogs']);
    Route::post('torrent-approval', [TorrentController::class, 'approval']);
    Route::post('token/add', [TokenController::class, 'addToken']);
    Route::post('token/del', [TokenController::class, 'delToken']);
});

if (! isRunningInConsole()) {
    $passkeyLoginUri = get_setting('security.login_secret');
    if (! empty($passkeyLoginUri) && get_setting('security.login_type') == 'passkey') {
        Route::get("$passkeyLoginUri/{passkey}", [AuthenticateController::class, 'passkeyLogin']);
    }
}

Route::group(['prefix' => 'oauth'], function () {
    Route::get('user-info', [OauthController::class, 'userInfo'])->name('oauth.user_info')->middleware('auth:api');
    Route::get('redirect/{uuid}', [OauthController::class, 'redirect']);
    Route::get('callback/{uuid}', [OauthController::class, 'callback']);
});
