<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['middleware' => ['auth:sanctum', 'locale']], function () {

    Route::post('logout', [\App\Http\Controllers\AuthenticateController::class, 'logout']);

    Route::get('user-me',[\App\Http\Controllers\UserController::class, 'me'])->name('user.me');
    Route::get('user-publish-torrent',[\App\Http\Controllers\UserController::class, 'publishTorrent']);
    Route::get('user-seeding-torrent',[\App\Http\Controllers\UserController::class, 'seedingTorrent']);
    Route::get('user-leeching-torrent',[\App\Http\Controllers\UserController::class, 'leechingTorrent']);
    Route::get('user-finished-torrent',[\App\Http\Controllers\UserController::class, 'finishedTorrent']);
    Route::get('user-not-finished-torrent',[\App\Http\Controllers\UserController::class, 'notFinishedTorrent']);
    Route::resource('messages', \App\Http\Controllers\MessageController::class);
    Route::resource('torrents', \App\Http\Controllers\TorrentController::class);
    Route::resource('comments', \App\Http\Controllers\CommentController::class);
    Route::resource('peers', \App\Http\Controllers\PeerController::class);
    Route::resource('files', \App\Http\Controllers\FileController::class);
    Route::resource('thanks', \App\Http\Controllers\ThankController::class);
    Route::resource('snatches', \App\Http\Controllers\SnatchController::class);
    Route::get('search-box', [\App\Http\Controllers\TorrentController::class, 'searchBox']);

    Route::group(['middleware' => ['admin']], function () {
        Route::resource('agent-allows', \App\Http\Controllers\AgentAllowController::class);

        Route::resource('users', \App\Http\Controllers\UserController::class);
        Route::get('user-base', [\App\Http\Controllers\UserController::class, 'base']);
        Route::get('user-classes', [\App\Http\Controllers\UserController::class, 'classes']);
        Route::get('user-invite-info', [\App\Http\Controllers\UserController::class, 'inviteInfo']);
        Route::get('user-match-exams', [\App\Http\Controllers\UserController::class, 'matchExams']);
        Route::get('user-mod-comment', [\App\Http\Controllers\UserController::class, 'modComment']);
        Route::post('user-disable', [\App\Http\Controllers\UserController::class, 'disable']);
        Route::post('user-enable', [\App\Http\Controllers\UserController::class, 'enable']);
        Route::post('user-reset-password', [\App\Http\Controllers\UserController::class, 'resetPassword']);

        Route::resource('exams', \App\Http\Controllers\ExamController::class);
        Route::get('exam-indexes', [\App\Http\Controllers\ExamController::class, 'indexes']);

        Route::resource('exam-users', \App\Http\Controllers\ExamUserController::class);
        Route::put('exam-users-avoid', [\App\Http\Controllers\ExamUserController::class, 'avoid']);
        Route::put('exam-users-recover', [\App\Http\Controllers\ExamUserController::class, 'recover']);

        Route::get('dashboard/system-info', [\App\Http\Controllers\DashboardController::class, 'systemInfo']);
        Route::get('dashboard/stat-data', [\App\Http\Controllers\DashboardController::class, 'statData']);
        Route::get('dashboard/latest-user', [\App\Http\Controllers\DashboardController::class, 'latestUser']);
        Route::get('dashboard/latest-torrent', [\App\Http\Controllers\DashboardController::class, 'latestTorrent']);

        Route::resource('settings', \App\Http\Controllers\SettingController::class);
    });

});

Route::post('login', [\App\Http\Controllers\AuthenticateController::class, 'login']);

