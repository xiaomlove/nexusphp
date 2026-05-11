<?php

use App\Http\Controllers\AuthenticateController;
use App\Http\Controllers\Legacy\PreviewController;
use App\Http\Controllers\Legacy\SearchSuggestController;
use App\Http\Controllers\Legacy\SpecialController;
use App\Http\Controllers\Legacy\SuggestController;
use App\Http\Controllers\Legacy\TakeContactController;
use App\Http\Controllers\Legacy\TakeUpdateController;
use App\Http\Controllers\Legacy\ThanksController;
use App\Http\Controllers\OauthController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\ToolController;
use App\Http\Controllers\TorrentController;
use App\Livewire\TorrentBrowse;
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

Route::middleware(['auth.nexus:nexus-web'])->group(function () {
    Route::get('/browse', TorrentBrowse::class)->name('torrents.browse');

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
