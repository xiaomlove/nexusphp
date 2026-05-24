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
use App\Http\Controllers\Legacy\AttachmentController;
use App\Http\Controllers\Legacy\AttendanceController;
use App\Http\Controllers\Legacy\BannedEmailsController;
use App\Http\Controllers\Legacy\BansController;
use App\Http\Controllers\Legacy\BitBucketLogController;
use App\Http\Controllers\Legacy\BitBucketUploadController;
use App\Http\Controllers\Legacy\BonusLogController;
use App\Http\Controllers\Legacy\BookmarkController;
use App\Http\Controllers\Legacy\Cc98barController;
use App\Http\Controllers\Legacy\CheaterboxController;
use App\Http\Controllers\Legacy\CheatersController;
use App\Http\Controllers\Legacy\CheckUserController;
use App\Http\Controllers\Legacy\ClaimAjaxController;
use App\Http\Controllers\Legacy\ClaimController;
use App\Http\Controllers\Legacy\ClearCacheController;
use App\Http\Controllers\Legacy\ComplainsController;
use App\Http\Controllers\Legacy\ConfirmController;
use App\Http\Controllers\Legacy\ConfirmEmailController;
use App\Http\Controllers\Legacy\ConfirmResendController;
use App\Http\Controllers\Legacy\ContactStaffController;
use App\Http\Controllers\Legacy\DelAcctAdminController;
use App\Http\Controllers\Legacy\DeleteDisabledController;
use App\Http\Controllers\Legacy\DeleteTorrentController;
use App\Http\Controllers\Legacy\DocleanupController;
use App\Http\Controllers\Legacy\DonateController;
use App\Http\Controllers\Legacy\DonatedController;
use App\Http\Controllers\Legacy\DonorlistController;
use App\Http\Controllers\Legacy\DownloadController;
use App\Http\Controllers\Legacy\DownloadNoticeController;
use App\Http\Controllers\Legacy\DownloadSubsController;
use App\Http\Controllers\Legacy\EditController;
use App\Http\Controllers\Legacy\FaqController;
use App\Http\Controllers\Legacy\FastDeleteController;
use App\Http\Controllers\Legacy\FieldsController;
use App\Http\Controllers\Legacy\FormatsController;
use App\Http\Controllers\Legacy\ForummanageController;
use App\Http\Controllers\Legacy\FreeleechController;
use App\Http\Controllers\Legacy\FriendsController;
use App\Http\Controllers\Legacy\GetAttachmentController;
use App\Http\Controllers\Legacy\GetExtInfoAjaxController;
use App\Http\Controllers\Legacy\GetRssController;
use App\Http\Controllers\Legacy\GetUserTorrentListAjaxController;
use App\Http\Controllers\Legacy\ImageCaptchaController;
use App\Http\Controllers\Legacy\IncrementBulkController;
use App\Http\Controllers\Legacy\InviteController;
use App\Http\Controllers\Legacy\IpCheckController;
use App\Http\Controllers\Legacy\IpHistoryController;
use App\Http\Controllers\Legacy\IpSearchController;
use App\Http\Controllers\Legacy\LinksManageController;
use App\Http\Controllers\Legacy\LoginController;
use App\Http\Controllers\Legacy\LogoutController;
use App\Http\Controllers\Legacy\MagicController;
use App\Http\Controllers\Legacy\MailtestController;
use App\Http\Controllers\Legacy\MakePollController;
use App\Http\Controllers\Legacy\MassmailController;
use App\Http\Controllers\Legacy\MaxLoginController;
use App\Http\Controllers\Legacy\MedalAjaxController;
use App\Http\Controllers\Legacy\MedalController;
use App\Http\Controllers\Legacy\ModrulesController;
use App\Http\Controllers\Legacy\MoforumsController;
use App\Http\Controllers\Legacy\MoreSmiliesController;
use App\Http\Controllers\Legacy\MyBarController;
use App\Http\Controllers\Legacy\MyhrController;
use App\Http\Controllers\Legacy\MysqlStatsController;
use App\Http\Controllers\Legacy\NoWarnController;
use App\Http\Controllers\Legacy\OkController;
use App\Http\Controllers\Legacy\OpensearchController;
use App\Http\Controllers\Legacy\PasskeyAjaxController;
use App\Http\Controllers\Legacy\PollOverviewController;
use App\Http\Controllers\Legacy\PreviewController;
use App\Http\Controllers\Legacy\PromotionLinkController;
use App\Http\Controllers\Legacy\RecoverController;
use App\Http\Controllers\Legacy\ReportController;
use App\Http\Controllers\Legacy\ReportsController;
use App\Http\Controllers\Legacy\ResetController;
use App\Http\Controllers\Legacy\RetriverController;
use App\Http\Controllers\Legacy\RulesController;
use App\Http\Controllers\Legacy\SearchController;
use App\Http\Controllers\Legacy\SearchSuggestController;
use App\Http\Controllers\Legacy\SelfEnableController;
use App\Http\Controllers\Legacy\SendMessageController;
use App\Http\Controllers\Legacy\SignupController;
use App\Http\Controllers\Legacy\SmiliesController;
use App\Http\Controllers\Legacy\SpecialController;
use App\Http\Controllers\Legacy\StaffboxController;
use App\Http\Controllers\Legacy\StaffController;
use App\Http\Controllers\Legacy\StaffMessController;
use App\Http\Controllers\Legacy\StaffPanelController;
use App\Http\Controllers\Legacy\StatsController;
use App\Http\Controllers\Legacy\SuggestController;
use App\Http\Controllers\Legacy\TagsController;
use App\Http\Controllers\Legacy\TakeConfirmController;
use App\Http\Controllers\Legacy\TakeContactController;
use App\Http\Controllers\Legacy\TakeEditController;
use App\Http\Controllers\Legacy\TakeFlushController;
use App\Http\Controllers\Legacy\TakeIncrementBulkController;
use App\Http\Controllers\Legacy\TakeInviteController;
use App\Http\Controllers\Legacy\TakeLoginController;
use App\Http\Controllers\Legacy\TakeMessageController;
use App\Http\Controllers\Legacy\TakeReseedController;
use App\Http\Controllers\Legacy\TakeSignupController;
use App\Http\Controllers\Legacy\TakeStaffMessController;
use App\Http\Controllers\Legacy\TakeUpdateController;
use App\Http\Controllers\Legacy\TakeUploadController;
use App\Http\Controllers\Legacy\TaskController;
use App\Http\Controllers\Legacy\TestIpController;
use App\Http\Controllers\Legacy\ThanksController;
use App\Http\Controllers\Legacy\TorrentInfoController;
use App\Http\Controllers\Legacy\UncoController;
use App\Http\Controllers\Legacy\UploadController;
use App\Http\Controllers\Legacy\UploadersController;
use App\Http\Controllers\Legacy\UserAgreementController;
use App\Http\Controllers\Legacy\UserBanLogController;
use App\Http\Controllers\Legacy\UserHistoryController;
use App\Http\Controllers\Legacy\UsersListController;
use App\Http\Controllers\Legacy\VideoFormatsController;
use App\Http\Controllers\Legacy\ViewFileListController;
use App\Http\Controllers\Legacy\ViewNfoController;
use App\Http\Controllers\Legacy\ViewPeerListController;
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
 * Phase 2 — replaces `public/mybar.php` (deleted in the same PR,
 * −150 LOC). The "userbar" PNG generator embedded in user
 * signatures across forums and the user-control-panel "userbar
 * snippet" widget. Reachable as a guest because forum signatures
 * get rendered in pages/RSS feeds that may be crawled without a
 * session — every embedding `<img src=".../mybar.php?...">` tag
 * stays a hot link without a login round-trip.
 *
 * URL preserved (`/mybar.php?userid=NNN.png&bgpic=N&...`) so the
 * `PromotionLinkController` HTML output and any in-the-wild forum
 * signatures keep working without a template change. The legacy
 * regex requirement (`userid=NNN.png` literal in the request URI)
 * is preserved bit-for-bit by the controller.
 */
Route::get('/mybar.php', MyBarController::class)->name('legacy.mybar');

/*
 * Phase 2 batch B — replaces `public/cc98bar.php` (deleted in the
 * same PR, −168 LOC). Variant of the userbar PNG generator that
 * takes its parameters from a path-style URI rather than the query
 * string. Forum signatures embed `<img src="/cc98bar.php/nn0nr255id42.png">`.
 *
 * The route lives OUTSIDE `auth.nexus:nexus-web` because forum
 * signatures get rendered to guests / crawlers (RSS feeds, public
 * forum pages). Same posture as `/mybar.php` above. Path-style
 * URI captured by a wildcard `{path}` segment so Laravel routing
 * matches the legacy regex shape `/cc98bar.php/.../id<userid>.png`.
 *
 * No nginx exact-location rule is needed: the catch-all
 * `location ~ \.php$` rewrite only fires for URIs that end in
 * `.php`, but the legacy URI ends in `.png` and the path component
 * before the `.php` literal is `/cc98bar.php` itself — nginx
 * already routes everything that hasn't matched a `try_files`
 * to `/nexus.php?$query_string` via the catch-all `location /`,
 * which is the path that hits our Laravel `Route::get(...)`.
 */
Route::get('/cc98bar.php/{path}', Cc98barController::class)
    ->where('path', '.+')
    ->name('legacy.cc98bar');

/*
 * Phase 2 batch B — replaces `public/download.php` (deleted in
 * the same PR, −214 LOC). The torrent download endpoint — every
 * `.torrent` file the site serves goes through here. Three auth
 * modes:
 *   - `?downhash=UID.HASH`  RSS / external client (no session),
 *   - `?passkey=K&id=N`     external download manager (no session,
 *                           requires `torrent.download_support_passkey='yes'`),
 *   - `?id=N`               standard session auth, falls into a
 *                           `LegacyContext::user()` check that
 *                           mirrors `loggedinorreturn()`.
 *
 * The route lives OUTSIDE `auth.nexus:nexus-web` because the first
 * two modes authenticate the viewer themselves, bypassing the
 * session cookie. The standard `?id=` mode redirects to
 * `/login.php?returnto=...` from the controller body when the
 * viewer is not authed.
 *
 * URL preserved exactly so `app/Livewire/TorrentDetail.php`,
 * `app/Repositories/TorrentRepository.php`, `app/Support/Http.php`,
 * the FAQ seeder, the 19 `lang/<locale>/lang_index.php` rendered
 * "download a fresh .torrent" links, and external bookmarks all
 * keep working without template/JS changes.
 */
Route::get('/download.php', DownloadController::class)->name('legacy.download');

/*
 * Phase 2 — replaces `public/news.php` (deleted in the same PR,
 * −139 LOC) with the Filament admin resource
 * `App\Filament\Resources\News\NewsResource` at `/nexusphp/news`.
 *
 * The legacy URL `/news.php` 302s to the new admin so:
 *   - The `[news page]` link rendered by `public/index.php` for
 *     admins (line 54, gated on `user_can('newsmanage')`) keeps
 *     working until the same PR rewrites it inline.
 *   - Stale screenshots / staff-chat URLs / pre-Filament admin
 *     bookmarks pointing at `news.php?action=edit&newsid=N` and
 *     `news.php?action=delete&newsid=N&sure=1` land on the
 *     Filament list instead of a 404 — the user re-issues the edit
 *     or delete through the Filament UI.
 *   - The `AdminpanelTableSeeder` row added in the same PR
 *     (`url=/nexusphp/news`) is the canonical entry; existing
 *     installs upgrade through `Update.php::runExtraQueries()`'s
 *     idempotent `addMenu` step.
 *
 * `Route::any` (vs `Route::redirect`) preserves every verb the
 * legacy script accepted — the `?action=edit` POST that the legacy
 * compose form submitted now lands on the Filament list page; the
 * admin then re-issues the edit through the new UI.
 *
 * The route lives OUTSIDE `auth.nexus:nexus-web` because Filament
 * runs its own authentication via `App\Http\Middleware\Filament` —
 * its redirect target is `/login.php` (same place legacy
 * `loggedinorreturn()` bounced guests), so an unauthenticated
 * request lands on the same login screen either way.
 */
Route::any('/news.php', static fn () => redirect('/nexusphp/news', 302))
    ->name('legacy.news');

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
 * Phase 2 batch — replaces `public/getusertorrentlistajax.php`
 * (deleted in the same PR). AJAX fragment endpoint called by
 * `public/js/common.js::getusertorrentlistajax()` to render the
 * expandable torrent sub-tables on the user-details page.
 * Returns a bare HTML fragment (no `<html>` wrapper).
 * Stays OUTSIDE `auth.nexus:nexus-web` to avoid redirecting XHR
 * callers to the login page and splicing HTML into the expand block.
 * The controller itself returns 401 for unauthenticated requests.
 */
Route::get('/getusertorrentlistajax.php', GetUserTorrentListAjaxController::class)->name('legacy.getusertorrentlistajax');

/*
 * Phase 2 batch — replaces `public/moforums.php` (deleted in the
 * same PR). Over-forum (category group) management page gated on
 * the `forummanage` permission. Accepts GET (list + edit forms) and
 * POST (add + update actions). POST is CSRF-exempt — see
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 */
Route::match(['get', 'post'], '/moforums.php', MoforumsController::class)->name('legacy.moforums');

/*
 * Phase 2 batch — replaces `public/staffbox.php` (deleted in the
 * same PR). Staff private-message inbox, accessible to users with the
 * `staffmem` permission or matching custom tool permissions. Accepts
 * both GET (inbox, viewpm, answermessage, delete, setanswered) and
 * POST (takeanswer, takecontactanswered). POST is CSRF-exempt — see
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 */
Route::match(['get', 'post'], '/staffbox.php', StaffboxController::class)->name('legacy.staffbox');

/*
 * Phase 2 batch — replaces `public/mysql_stats.php` (deleted in the
 * same PR). Sysop-only MySQL server status page (class >= UC_SYSOP).
 * The URL stays `/mysql_stats.php` so the existing
 * `SysoppanelTableSeeder` menu entry keeps working without a data
 * migration.
 */
Route::get('/mysql_stats.php', MysqlStatsController::class)->name('legacy.mysql_stats');

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
 * Phase 2 — replaces `public/viewpeerlist.php` (deleted in the same
 * PR). XHR endpoint called from `public/js/common.js:44`
 * (`viewpeerlist(torrentid)`) — the response body is innerHTML-
 * spliced into the toggle-able peer-list block on `details.php`.
 *
 * Stays OUTSIDE `auth.nexus:nexus-web` for the same reason
 * `/viewfilelist.php` does: the legacy `if (isset($CURUSER))` gate
 * becomes a `LegacyContext` check returning the empty-body envelope.
 * Placing the route inside the auth group would redirect guests to
 * `/login.php?...`, and `ajax.gets` would splice the login-page HTML
 * into the details page (UX bug).
 *
 * Side effects preserved from the legacy script:
 *   - `apply_filter('torrent_seeder_leecher_list', [], $id)` plugin
 *     hook — lets PT plugins override the peer source.
 *   - Reconciliation of `torrents.seeders` / `torrents.leechers`
 *     when the cached counts drift from the resolved peer list.
 *   - `peers.is_seed_box` CASE WHEN update when
 *     `seed_box.enabled` setting is `'yes'`.
 *
 * The matching nginx exact-location entry lives in
 * `.docker/openresty/sites/app.conf.template`.
 */
Route::get('/viewpeerlist.php', ViewPeerListController::class)->name('legacy.viewpeerlist');

/*
 * Phase 2 — replaces `public/complains.php` (deleted in the same
 * PR, −234 LOC). The "Complains" feature is a small ticket-tracker
 * for users whose accounts have been DISABLED — they cannot log in,
 * so the route deliberately lives OUTSIDE `auth.nexus:nexus-web`.
 * Action / permission gating is handled inside the controller:
 *   - GET ?action=compose (default) / view / POST ?action=new / reply
 *     are reachable to guests and disabled-but-stale-session users.
 *   - GET ?action=list, POST ?action=answered/unanswered are
 *     staff-only (`user_can('staffmem')`); a logged-in non-staff
 *     viewer hits a 403 page-level gate inside the controller.
 *
 * URL stays `/complains.php` so the legacy login-page link
 * (`public/login.php:111`) and the staff "open complaints" reminder
 * (`include/functions.php:2481` `complains.php?action=list`) keep
 * working without template changes. POST is CSRF-exempt — the
 * legacy form has no `@csrf` field; see
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 */
Route::match(['get', 'post'], '/complains.php', ComplainsController::class)
    ->name('legacy.complains');

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

/*
 * Phase 2 (this PR — auth-flow batch part 1 of 3): replaces
 * `public/login.php` (deleted in the same PR, −139 LOC) and
 * `public/takelogin.php` (also deleted in the same PR, −120 LOC).
 *
 * Both routes live OUTSIDE `auth.nexus:nexus-web` because — by
 * definition — the login flow is for users who do NOT yet have a
 * session. Already-logged-in callers are 302'd to `/index.php`
 * from inside the controller (`cur_user_check()` parity).
 *
 * URLs preserved exactly so:
 *   - `<form action="takelogin.php">` rendered by `LoginController`
 *     keeps posting to the right endpoint without template/JS
 *     changes (the existing challenge-response JS in
 *     `public/js/common.js` references the form by id);
 *   - the legacy "401 → /login.php?returnto=..." redirect chain
 *     emitted by `auth.nexus` middleware keeps working;
 *   - external links / browser bookmarks / OAuth provider
 *     callback `?returnto=` URLs all keep working;
 *   - the existing E2E `auth` smoke spec keeps passing without
 *     selector edits.
 *
 * `/takelogin.php` is CSRF-exempt — the legacy form has no `@csrf`
 * field. Adding CSRF plumbing to login is a separate cross-cutting
 * change because it touches the challenge-response JS, the OAuth
 * callback flow, and the Passkey login form. See
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 *
 * The matching nginx exact-location entries live in
 * `.docker/openresty/sites/app.conf.template`.
 */
Route::get('/login.php', LoginController::class)->name('legacy.login');
Route::post('/takelogin.php', TakeLoginController::class)->name('legacy.takelogin');

/*
 * Phase 2 (auth-flow batch part 2 of 3): replaces
 * `public/signup.php` (deleted in this PR, −139 LOC),
 * `public/takesignup.php` (also deleted, −279 LOC),
 * `public/recover.php` (also deleted, −168 LOC), and
 * `public/confirm_resend.php` (also deleted, −137 LOC).
 *
 * All four routes live OUTSIDE `auth.nexus:nexus-web` because — by
 * definition — these are pre-authentication flows. Already-logged-in
 * callers are 302'd to `/index.php` from inside the controllers
 * (`cur_user_check()` parity, same as the login pair in PR #304).
 *
 * URLs preserved exactly so:
 *   - `<form action="takesignup.php">` rendered by `SignupController`
 *     keeps posting to the right endpoint without template/JS changes;
 *   - The legacy invitation email template's
 *     `signup.php?type=invite&invitenumber=<hash>` URL (built by
 *     `App\Http\Controllers\Legacy\TakeInviteController::renderInviteEmail`)
 *     keeps resolving;
 *   - The recovery emails sent in production — which embed the
 *     `recover.php?id=...&secret=...` token URL — keep working;
 *   - The `database/seeders/FaqTableSeeder` `<a href="recover.php">`
 *     and `<a href="confirm_resend.php">` FAQ links keep resolving;
 *   - The legacy `lang_login.p_resend_confirm` paragraph rendered
 *     by `LoginController` keeps pointing at `/confirm_resend.php`.
 *
 * `/signup.php` is `Route::get` (form render only). The other three
 * accept both GET and POST because the legacy script branched
 * internally on `$_SERVER['REQUEST_METHOD']`.
 *
 * `/takesignup.php`, `/recover.php`, `/confirm_resend.php` are all
 * CSRF-exempt — the legacy forms have no `@csrf` field, and the
 * recovery emails reach `/recover.php?id=...&secret=...` via a
 * one-shot signed token (the cache key `recover:<hash>` IS the
 * authorisation), not a session cookie. See
 * `App\Http\Middleware\VerifyCsrfToken::$except`.
 */
Route::get('/signup.php', SignupController::class)->name('legacy.signup');
Route::post('/takesignup.php', TakeSignupController::class)->name('legacy.takesignup');
Route::match(['get', 'post'], '/recover.php', RecoverController::class)->name('legacy.recover');
Route::match(['get', 'post'], '/confirm_resend.php', ConfirmResendController::class)->name('legacy.confirmresend');

/*
 * Phase 2.5 (this PR — batch A of the `public/ajax.php` cleanup):
 * replaces 6 of the 26 actions exposed by the legacy reflection-
 * dispatcher in `public/ajax.php` — the Passkey/WebAuthn sub-API.
 *
 * Action map (legacy → new endpoint):
 *   - `getPasskeyCreateArgs`  → POST /passkey/create-args  (authed)
 *   - `processPasskeyCreate`  → POST /passkey/create       (authed)
 *   - `deletePasskey`         → POST /passkey/delete       (authed)
 *   - `getPasskeyList`        → POST /passkey/list         (authed)
 *   - `getPasskeyGetArgs`     → POST /passkey/get-args     (login flow)
 *   - `processPasskeyGet`     → POST /passkey/get          (login flow)
 *
 * Wire-level contract is unchanged:
 *   - Same accepted POST keys (`params[*]`, decoded by the controller
 *     into the same positional arguments `UserPasskeyRepository`
 *     already expects).
 *   - Same `{ret, msg, data}` JSON envelope at HTTP 200, including
 *     on error — `public/js/passkey.js` reads `res.ret !== 0` and
 *     throws `Error(res.msg)` from there.
 *
 * The two login-flow routes (`get-args`, `get`) sit OUTSIDE
 * `auth.nexus:nexus-web` because — by definition — the user does not
 * yet have a session when starting passkey-based login, and
 * `processGet` is the call that mints it via `logincookie()`. This
 * mirrors the explicit gate in the legacy dispatcher:
 *   `if ($action != 'getPasskeyGetArgs' && $action != 'processPasskeyGet')
 *        loggedinorreturn();`
 *
 * The four management routes are CSRF-exempt — `passkey.js` posts a
 * bare `URLSearchParams` body with no `_token`. Adding CSRF plumbing
 * to that JS helper is a separate, larger change. See
 * `App\Http\Middleware\VerifyCsrfToken::$except` (`'passkey/*'`).
 *
 * Companion `passkey.js` patch flips its single `apiUrl` constant
 * to a `legacy-action → endpoint` map and drops the `action` POST
 * key — the controller no longer needs it because the URL itself
 * picks the action.
 */
Route::prefix('passkey')->group(function () {
    // Login flow — unauthenticated. `processGet` is the call that
    // mints the auth cookie via `logincookie()`.
    Route::post('/get-args', [PasskeyAjaxController::class, 'getGetArgs'])
        ->name('passkey.get-args');
    Route::post('/get', [PasskeyAjaxController::class, 'processGet'])
        ->name('passkey.get');

    // Authenticated passkey management. Mirrors the legacy
    // `loggedinorreturn()` gate inside `public/ajax.php`.
    Route::middleware('auth.nexus:nexus-web')->group(function () {
        Route::post('/create-args', [PasskeyAjaxController::class, 'getCreateArgs'])
            ->name('passkey.create-args');
        Route::post('/create', [PasskeyAjaxController::class, 'processCreate'])
            ->name('passkey.create');
        Route::post('/delete', [PasskeyAjaxController::class, 'deletePasskey'])
            ->name('passkey.delete');
        Route::post('/list', [PasskeyAjaxController::class, 'getList'])
            ->name('passkey.list');
    });
});

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
     * Phase 2 (auth-flow batch part 3 of 3): replaces
     * `public/maxlogin.php` (deleted in this PR, −183 LOC).
     *
     * Sysop-only admin tool for the `loginattempts` table — the
     * companion to the per-IP failed-logins ban gate enforced by
     * `LoginController` / `TakeLoginController` (#304) and
     * `RecoverController` / `ConfirmResendController` (#305).
     * When an IP gets stuck on `banned='yes'`, this is the page a
     * sysop visits to flip the row back, raise the `attempts`
     * count, or delete the record outright.
     *
     * URL preserved exactly so the existing
     * `viewunbaniprequest.php` cross-link (`<form
     * action="maxlogin.php">` carrying a `returnto` field) keeps
     * working without a template change. Same for any sysop
     * bookmarks pointing at `?action=showlist`.
     *
     * Sits inside `auth.nexus:nexus-web` (legacy
     * `loggedinorreturn()`); the `User::CLASS_SYSOP` gate is
     * enforced inside the controller via `abort(403)`. POST verb
     * is CSRF-exempt — the legacy edit form has no `@csrf` field;
     * see `App\Http\Middleware\VerifyCsrfToken::$except`.
     */
    Route::match(['get', 'post'], '/maxlogin.php', MaxLoginController::class)
        ->name('legacy.maxlogin');

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
     * Phase 2.5 (this PR — batch C of the `public/ajax.php` cleanup):
     * replaces 5 of the remaining actions exposed by the legacy
     * reflection-dispatcher in `public/ajax.php` — a mixed batch
     * of user-side write actions across four sub-APIs.
     *
     * Action map (legacy → new endpoint, all authed):
     *   - `addClaim`         → POST /claim/add
     *   - `removeClaim`      → POST /claim/remove
     *   - `removeHitAndRun`  → POST /hit-and-run/remove
     *   - `claimTask`        → POST /exam/claim-task
     *   - `consumeBenefit`   → POST /benefit/consume
     *
     * Wire-level contract is unchanged: same accepted POST keys
     * (`params[*]`), same `{ret, msg, data}` JSON envelope at
     * HTTP 200 (including on error). The four first-party JS
     * callers — `public/details.php:315`, `MyhrController:133`,
     * `TaskController:224`, `public/userdetails.php:286`, plus
     * the shared `claimAction(...)` helper in
     * `public/js/nexus.js:140` — are flipped in the same PR.
     *
     * All five routes are CSRF-exempt — the inline-script callers
     * post bare `application/x-www-form-urlencoded` bodies with
     * no `_token`. See `App\Http\Middleware\VerifyCsrfToken::$except`
     * (`'claim/*'`, `'hit-and-run/*'`, `'exam/claim-task'`,
     * `'benefit/consume'`).
     *
     * Naming: grouped in a single `ClaimAjaxController` because
     * the four sub-APIs share the same JSON envelope contract and
     * CSRF posture; splitting them into per-domain controllers
     * would multiply boilerplate without improving clarity. URL
     * prefixes stay per-domain so the routes themselves still
     * read naturally.
     */
    Route::prefix('claim')->group(function () {
        Route::post('/add', [ClaimAjaxController::class, 'addClaim'])
            ->name('claim.add');
        Route::post('/remove', [ClaimAjaxController::class, 'removeClaim'])
            ->name('claim.remove');
    });
    Route::post('/hit-and-run/remove', [ClaimAjaxController::class, 'removeHitAndRun'])
        ->name('hit-and-run.remove');
    Route::post('/exam/claim-task', [ClaimAjaxController::class, 'claimTask'])
        ->name('exam.claim-task');
    Route::post('/benefit/consume', [ClaimAjaxController::class, 'consumeBenefit'])
        ->name('benefit.consume');

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
     * Phase 2 — replaces `public/tags.php` (deleted in the same
     * PR, −303 LOC). The "BBCode tags reference" cheatsheet
     * linked from the compose-helper footer
     * (`include/functions.php:1026`) and from the BBCode-help
     * link in `public/admanage.php:162`. The legacy file lacked
     * `loggedinorreturn()` but unconditionally read
     * `$CURUSER['username']` (used in the rendered "quote two"
     * tag example), which would trip a notice for guests; we
     * tighten the contract by routing through the
     * `auth.nexus:nexus-web` group. POST `?test=` renders a
     * `format_comment()` preview at the top of the page; this
     * branch is CSRF-exempt — see
     * `App\Http\Middleware\VerifyCsrfToken::$except`.
     */
    Route::match(['get', 'post'], '/tags.php', TagsController::class)
        ->name('legacy.tags');

    /*
     * Phase 2 — replaces `public/attachment.php` (deleted in the
     * same PR, −292 LOC). The "attach a file to a post" iframe
     * widget. The compose helper opens this URL in an iframe;
     * after a successful upload the iframe writes back to the
     * parent window via `parent.tag_extimage('[attach]<dlkey>[/attach]')`
     * (or `parent.<callback_func>(<dlkey>, <url>)` for
     * custom-field preview helpers). Same posture as the
     * already-migrated `BitBucketUploadController` — multipart
     * POST with no `@csrf` field, so the route is CSRF-exempt.
     */
    Route::match(['get', 'post'], '/attachment.php', AttachmentController::class)
        ->name('legacy.attachment');

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
     * Phase 2 — replaces `public/report.php` (deleted in the same
     * PR, −234 LOC). Universal "report this thing to staff"
     * endpoint: GET renders a confirmation form for one of seven
     * target types (user / torrent / forumpost / comment / offer /
     * request / subtitle); POST inserts the row into `reports` and
     * busts the staff-dashboard counter cache. Lives inside
     * `auth.nexus:nexus-web` because the legacy script started with
     * `loggedinorreturn();` — guests redirect to login. Parked
     * users get a 403 inside the controller.
     *
     * URL stays `/report.php` so the modern UI report link
     * (`app/Livewire/TorrentDetail.php:282`), every legacy
     * "report this <thing>" deep link in
     * `public/details.php:295` / `forums.php:1056` /
     * `offers.php:221` / `subtitles.php:375` /
     * `userdetails.php:382` / `viewrequests.php:137`, the
     * `include/functions.php:3115` comment-report icon, and
     * `tests/Feature/Livewire/TorrentDetailActionRowTest.php:61`
     * (which pins the URL contract) keep working without template
     * /JS changes.
     *
     * `/report.php` (this route) is the user-facing submission
     * endpoint; the staff-facing `/reports.php` (with -s, plural)
     * is a separate page handled by `ReportsController`. Do not
     * confuse them.
     *
     * POST is CSRF-exempt — the legacy form rendered inside the
     * `stderr()` confirmation envelope has no `@csrf` field; see
     * `App\Http\Middleware\VerifyCsrfToken::$except`.
     */
    Route::match(['get', 'post'], '/report.php', ReportController::class)
        ->name('legacy.report');

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

    /*
     * Phase 2 — replaces `public/claim.php` (deleted in the same
     * PR, −177 LOC). Authed read-only listing of `claims` rows
     * filtered by `?torrent_id` (every user who claimed a given
     * torrent) or `?uid` (every torrent a given user has claimed).
     * GET-only (the legacy script had no POST branch). Settle/cancel
     * action buttons appear only when the listing is scoped to the
     * viewer's own user id (`?uid == $CURUSER['id']`); the buttons
     * themselves keep posting to `ajax.php?action=settleClaim`,
     * which is unchanged.
     *
     * URL stays `/claim.php` so:
     *   - `include/functions.php:2265` (the user-header snippet),
     *   - `app/Livewire/TorrentDetail.php:346` (the modern
     *     torrent-detail "Claim details" link),
     *   - `public/details.php:331` (the legacy details panel link),
     *   - `public/userdetails.php:330` (the legacy user-profile
     *     link),
     *   - `tests/e2e/behavior/torrent-detail-claim.spec.ts` and
     *     `tests/Feature/Livewire/TorrentDetailClaimTest.php`
     *     (which both pin the URL contract)
     * keep working without template/JS changes.
     */
    Route::get('/claim.php', ClaimController::class)
        ->name('legacy.claim');

    /*
     * Phase 2 — replaces `public/attendance.php` (deleted in the
     * same PR, −191 LOC). Authed daily check-in page with a
     * FullCalendar success view + retroactive sign-in for the
     * past `Attendance::MAX_RETROACTIVE_DAYS` days. GET renders
     * the form (with optional image captcha) when the user has
     * not yet attended today, or the success calendar otherwise;
     * POST validates the captcha and performs the check-in. With
     * captcha disabled a GET also performs the check-in silently
     * (legacy parity).
     *
     * URL stays `/attendance.php` so the
     * `include/functions.php:2253` `<a href="attendance.php">`
     * link rendered in the user-header chrome on every legacy page
     * keeps working without a template change. POST is CSRF-exempt
     * (the legacy `<form method="post" action="attendance.php">`
     * had no `@csrf` field) — see
     * `App\Http\Middleware\VerifyCsrfToken::$except`.
     */
    Route::match(['get', 'post'], '/attendance.php', AttendanceController::class)
        ->name('legacy.attendance');

    /*
     * Phase 2 batch (PR #293): replaces five public/*.php pages with
     * Laravel controllers — linksmanage / makepoll / reports /
     * ipsearch / staff. URLs preserved exactly so existing template
     * / JS callers (`public/index.php` home-page footer, the
     * already-migrated `TakeUpdateController` redirect target,
     * `resources/views/legacy/formats.blade.php`, the modpanel
     * navigation, the e2e smoke probe for makepoll.php) keep
     * working without further changes. POST handlers for
     * linksmanage and makepoll are CSRF-exempt — the legacy forms
     * have no `@csrf` field. See
     * `App\Http\Middleware\VerifyCsrfToken::$except`.
     */
    Route::match(['get', 'post'], '/linksmanage.php', LinksManageController::class)
        ->name('legacy.linksmanage');
    Route::match(['get', 'post'], '/makepoll.php', MakePollController::class)
        ->name('legacy.makepoll');
    Route::get('/reports.php', ReportsController::class)
        ->name('legacy.reports');
    Route::get('/ipsearch.php', IpSearchController::class)
        ->name('legacy.ipsearch');
    Route::get('/staff.php', StaffController::class)
        ->name('legacy.staff');

    /*
     * Phase 2 batch (this PR): replaces `public/forummanage.php`
     * (deleted in the same PR). Forum (sub-forum) management page
     * gated on the `forummanage` permission. Accepts GET (list /
     * newforum / editforum / del) and POST (addforum / editforum).
     * URL preserved so the `SysoppanelTableSeeder.url='forummanage.php'`
     * menu entry, the `forums.php:1781` "Forum manager" link, and the
     * `MoforumsController` "back to forum management" link keep
     * working without template changes. POST is CSRF-exempt — see
     * `App\Http\Middleware\VerifyCsrfToken::$except`.
     */
    Route::match(['get', 'post'], '/forummanage.php', ForummanageController::class)
        ->name('legacy.forummanage');

    /*
     * Phase 2 batch (this PR): replaces `public/getrss.php` (deleted
     * in the same PR). Authed-only RSS-feed builder page that lets
     * users craft a personalised `torrentrss.php?…` URL. GET renders
     * the form; POST assembles the query string and prints the
     * resulting RSS link. URL preserved so the global header RSS icon
     * link in `include/functions.php:2304` keeps working without a
     * template change. POST is CSRF-exempt — the legacy form had no
     * `@csrf` field. See
     * `App\Http\Middleware\VerifyCsrfToken::$except`.
     */
    Route::match(['get', 'post'], '/getrss.php', GetRssController::class)
        ->name('legacy.getrss');

    /*
     * Phase 2 batch B (this PR): replaces five public/*.php pages
     * with Laravel controllers — task / downloadnotice / search /
     * takemessage / friends. URLs preserved so existing template
     * / JS callers (the search box in `include/functions.php:2270`,
     * the user-header `task.php` link, the `DownloadController`
     * interstitial redirect target, the `SendMessageController` /
     * `messages.php` <form action="takemessage.php">, and the
     * `userdetails.php` "add friend / block" links) keep working
     * without further changes. POST endpoints for downloadnotice
     * and takemessage are CSRF-exempt — the legacy forms have
     * no `@csrf` field. See
     * `App\Http\Middleware\VerifyCsrfToken::$except`.
     */
    Route::get('/task.php', TaskController::class)
        ->name('legacy.task');
    Route::match(['get', 'post'], '/downloadnotice.php', DownloadNoticeController::class)
        ->name('legacy.downloadnotice');
    Route::get('/search.php', SearchController::class)
        ->name('legacy.search');
    Route::post('/takemessage.php', TakeMessageController::class)
        ->name('legacy.takemessage');
    Route::get('/friends.php', FriendsController::class)
        ->name('legacy.friends');

    /*
     * Phase 2 (this PR): replaces `public/upload.php` (260 LOC),
     * `public/takeupload.php` (490 LOC), `public/edit.php` (358 LOC),
     * `public/takeedit.php` (311 LOC) — the torrent upload + edit
     * lifecycle. All four legacy files deleted in the same PR.
     *
     * - `/upload.php`     GET  → UploadController     (form render)
     * - `/takeupload.php` POST → TakeUploadController (write-handler)
     * - `/edit.php`       GET  → EditController       (form render)
     * - `/takeedit.php`   POST → TakeEditController   (write-handler)
     *
     * URLs preserved exactly so:
     *   - `include/functions.php:1887` (the global "Upload" nav link),
     *   - `include/functions.php:3639` (the staff-edit icon),
     *   - the rendered forms' `<form action="takeupload.php">` /
     *     `<form action="takeedit.php">`,
     *   - the legacy `?uploaded=1` / `?edited=1` post-success
     *     redirects from the migrated `details.php` chain
     *   keep working without template / JS changes.
     *
     * `takeupload.php` and `takeedit.php` POSTs are CSRF-exempt — the
     * legacy forms had no `@csrf` field; see
     * `App\Http\Middleware\VerifyCsrfToken::$except`.
     *
     * Bark/exit transformation: the legacy `bark($msg); exit;` helper
     * threw the FPM worker out mid-request (skipping Laravel
     * middleware). The migrated controllers raise an internal
     * `BarkException` instead and convert it to the same
     * `genbark()`-rendered error page captured into a `Response`.
     */
    Route::get('/upload.php', UploadController::class)
        ->name('legacy.upload');
    Route::post('/takeupload.php', TakeUploadController::class)
        ->name('legacy.takeupload');
    Route::get('/edit.php', EditController::class)
        ->name('legacy.edit');
    Route::post('/takeedit.php', TakeEditController::class)
        ->name('legacy.takeedit');

    /*
     * Phase 2 (PR #301): replaces `public/invite.php` (deleted in
     * the same PR, −346 LOC). Authed-only invite-system page.
     * GET-only — every POST happens on a separate URL:
     *   - `?type=new` form submits to `/takeinvite.php` (still legacy).
     *   - The invitee-checkbox form submits to `/takeconfirm.php`
     *     (already migrated → `TakeConfirmController`).
     *
     * Permission gate: `$CURUSER['id'] == $id || user_can('viewinvite')`
     * — preserved verbatim by the controller.
     *
     * URL stays `/invite.php` so:
     *   - `include/functions.php:2256` (the user-header invite link),
     *   - `public/usercp.php:1083` (the user-control-panel row),
     *   - `public/userdetails.php:134` (the user profile row),
     *   - `public/takeinvite.php:142` (the post-send 302 to
     *     `/invite.php?id=...&sent=1`),
     *   - `app/Http/Controllers/Legacy/TakeConfirmController.php` (the
     *     redirect-back-on-success path)
     *   keep working without template / JS changes.
     */
    Route::get('/invite.php', InviteController::class)
        ->name('legacy.invite');

    /*
     * Phase 2 (this PR): replaces `public/takeinvite.php` (deleted in
     * the same PR, −146 LOC). Follow-up to PR #301 (`InviteController`,
     * which migrated the read-only `/invite.php` form-render).
     *
     * POST-only "send invitation" write-handler. Receives a
     * `<form action="/takeinvite.php?id=...">` POST submitted from the
     * `?type=new` form rendered by `InviteController::renderNewForm()`.
     *
     * Auth posture: inside `auth.nexus:nexus-web` (legacy
     * `loggedinorreturn()`); parked users → 403 inside the
     * controller. CSRF-exempt — see
     * `App\Http\Middleware\VerifyCsrfToken::$except`.
     *
     * URL stays `/takeinvite.php` so the form action in
     * `InviteController::renderNewForm()` (line ~239) keeps posting
     * to the same endpoint without template changes. The legacy
     * 302 target — `/invite.php?id=<id>&sent=1` — is preserved
     * verbatim so `InviteController` can detect the post-send
     * banner state via `?sent=1`.
     */
    Route::post('/takeinvite.php', TakeInviteController::class)
        ->name('legacy.takeinvite');

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
