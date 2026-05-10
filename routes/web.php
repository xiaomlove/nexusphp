<?php

use App\Http\Controllers\AuthenticateController;
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

Route::middleware(['auth.nexus:nexus-web'])->group(function () {
    Route::get('/browse', TorrentBrowse::class)->name('torrents.browse');

    /*
     * Phase 2 of the legacy migration — replaces `public/thanks.php`,
     * which is deleted in this PR. The URL stays `/thanks.php` so the
     * AJAX call in `public/js/common.js` (`saythanks(torrentid)`)
     * keeps working without a template/JS change. The same path is
     * also added to `App\Http\Middleware\VerifyCsrfToken::$except`
     * (see the comment there for why).
     *
     * The matching nginx rule that routes `/thanks.php` to Laravel
     * lives in `.docker/openresty/sites/app.conf.template` — without
     * it, the catch-all `location ~ \.php$` would `fastcgi_pass` to
     * the (now missing) file and return a fastcgi 404.
     */
    Route::post('/thanks.php', ThanksController::class)->name('legacy.thanks');
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
