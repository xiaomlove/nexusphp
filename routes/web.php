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
use App\Http\Controllers\Legacy\BookmarkController;
use App\Http\Controllers\Legacy\ClearCacheController;
use App\Http\Controllers\Legacy\ConfirmController;
use App\Http\Controllers\Legacy\ConfirmEmailController;
use App\Http\Controllers\Legacy\ContactStaffController;
use App\Http\Controllers\Legacy\DelAcctAdminController;
use App\Http\Controllers\Legacy\DeleteDisabledController;
use App\Http\Controllers\Legacy\DonatedController;
use App\Http\Controllers\Legacy\DonorlistController;
use App\Http\Controllers\Legacy\FreeleechController;
use App\Http\Controllers\Legacy\GetAttachmentController;
use App\Http\Controllers\Legacy\GetExtInfoAjaxController;
use App\Http\Controllers\Legacy\ImageCaptchaController;
use App\Http\Controllers\Legacy\LogoutController;
use App\Http\Controllers\Legacy\MagicController;
use App\Http\Controllers\Legacy\MailtestController;
use App\Http\Controllers\Legacy\MoreSmiliesController;
use App\Http\Controllers\Legacy\NoWarnController;
use App\Http\Controllers\Legacy\OkController;
use App\Http\Controllers\Legacy\OpensearchController;
use App\Http\Controllers\Legacy\PreviewController;
use App\Http\Controllers\Legacy\RulesController;
use App\Http\Controllers\Legacy\SearchSuggestController;
use App\Http\Controllers\Legacy\SmiliesController;
use App\Http\Controllers\Legacy\SpecialController;
use App\Http\Controllers\Legacy\SuggestController;
use App\Http\Controllers\Legacy\TakeConfirmController;
use App\Http\Controllers\Legacy\TakeContactController;
use App\Http\Controllers\Legacy\TakeFlushController;
use App\Http\Controllers\Legacy\TakeReseedController;
use App\Http\Controllers\Legacy\TakeStaffMessController;
use App\Http\Controllers\Legacy\TakeUpdateController;
use App\Http\Controllers\Legacy\ThanksController;
use App\Http\Controllers\Legacy\UserBanLogController;
use App\Http\Controllers\OauthController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\TorrentController;
use App\Livewire\ForumIndex;
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
     * Phase 2 batch — replaces `public/takestaffmess.php` (deleted in
     * this PR). The URL stays `/takestaffmess.php` so the legacy
     * `<form action="takestaffmess.php">` rendered by
     * `public/staffmess.php` keeps posting to the same endpoint
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

    Route::get('/torrents', TorrentBrowse::class)->name('torrents.browse.alias');
    Route::get('/forum', ForumIndex::class)->name('forum.index');
    Route::get('/forum/unread', ForumUnread::class)->name('forum.unread');
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
