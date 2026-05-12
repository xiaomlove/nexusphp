<?php

use App\Http\Controllers\AuthenticateController;
use App\Http\Controllers\Dev\ComponentGalleryController;
use App\Http\Controllers\Legacy\BookmarkController;
use App\Http\Controllers\Legacy\ConfirmController;
use App\Http\Controllers\Legacy\ConfirmEmailController;
use App\Http\Controllers\Legacy\ImageCaptchaController;
use App\Http\Controllers\Legacy\LogoutController;
use App\Http\Controllers\Legacy\MoreSmiliesController;
use App\Http\Controllers\Legacy\PreviewController;
use App\Http\Controllers\Legacy\SearchSuggestController;
use App\Http\Controllers\Legacy\SpecialController;
use App\Http\Controllers\Legacy\SuggestController;
use App\Http\Controllers\Legacy\TakeContactController;
use App\Http\Controllers\Legacy\TakeUpdateController;
use App\Http\Controllers\Legacy\ThanksController;
use App\Http\Controllers\OauthController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\TorrentController;
use App\Livewire\ForumIndex;
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

    Route::get('/torrents', TorrentBrowse::class)->name('torrents.browse.alias');
    Route::get('/forum', ForumIndex::class)->name('forum.index');
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
