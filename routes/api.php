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

    Route::group(['middleware' => ['user']], function () {
        Route::post('logout', [\App\Http\Controllers\AuthenticateController::class, 'logout']);

        Route::get('user-me',[\App\Http\Controllers\UserController::class, 'me'])->name('user.me');
        Route::get('user-publish-torrent',[\App\Http\Controllers\UserController::class, 'publishTorrent']);
        Route::get('user-seeding-torrent',[\App\Http\Controllers\UserController::class, 'seedingTorrent']);
        Route::get('user-leeching-torrent',[\App\Http\Controllers\UserController::class, 'leechingTorrent']);
        Route::get('user-finished-torrent',[\App\Http\Controllers\UserController::class, 'finishedTorrent']);
        Route::get('user-not-finished-torrent',[\App\Http\Controllers\UserController::class, 'notFinishedTorrent']);
        Route::resource('messages', \App\Http\Controllers\MessageController::class);
        Route::get('messages-unread', [\App\Http\Controllers\MessageController::class, 'listUnread']);
        Route::resource('torrents', \App\Http\Controllers\TorrentController::class);
        Route::resource('comments', \App\Http\Controllers\CommentController::class);
        Route::resource('peers', \App\Http\Controllers\PeerController::class);
        Route::resource('files', \App\Http\Controllers\FileController::class);
        Route::resource('thanks', \App\Http\Controllers\ThankController::class);
        Route::resource('snatches', \App\Http\Controllers\SnatchController::class);
        Route::resource('bookmarks', \App\Http\Controllers\BookmarkController::class);
        Route::get('search-box', [\App\Http\Controllers\TorrentController::class, 'searchBox']);
        Route::resource('news', \App\Http\Controllers\NewsController::class);
        Route::get('attend', [\App\Http\Controllers\AttendanceController::class, 'attend']);
        Route::resource('news', \App\Http\Controllers\NewsController::class);
        Route::get('news-latest', [\App\Http\Controllers\NewsController::class, 'latest']);
        Route::resource('polls', \App\Http\Controllers\PollController::class);
        Route::get('polls-latest', [\App\Http\Controllers\PollController::class, 'latest']);
        Route::post('polls-vote', [\App\Http\Controllers\PollController::class, 'vote']);
        Route::resource('rewards', \App\Http\Controllers\RewardController::class);
        Route::get('notifications', [\App\Http\Controllers\ToolController::class, 'notifications']);
    });

    Route::group(['middleware' => ['admin']], function () {
        Route::resource('agent-allows', \App\Http\Controllers\AgentAllowController::class);
        Route::get('all-agent-allows', [\App\Http\Controllers\AgentAllowController::class, 'all']);
        Route::post('agent-check', [\App\Http\Controllers\AgentAllowController::class, 'check']);
        Route::resource('agent-denies', \App\Http\Controllers\AgentDenyController::class);

        Route::resource('users', \App\Http\Controllers\UserController::class);
        Route::get('user-base', [\App\Http\Controllers\UserController::class, 'base']);
        Route::get('user-classes', [\App\Http\Controllers\UserController::class, 'classes']);
        Route::get('user-invite-info', [\App\Http\Controllers\UserController::class, 'inviteInfo']);
        Route::get('user-match-exams', [\App\Http\Controllers\UserController::class, 'matchExams']);
        Route::get('user-mod-comment', [\App\Http\Controllers\UserController::class, 'modComment']);
        Route::post('user-disable', [\App\Http\Controllers\UserController::class, 'disable']);
        Route::post('user-enable', [\App\Http\Controllers\UserController::class, 'enable']);
        Route::post('user-reset-password', [\App\Http\Controllers\UserController::class, 'resetPassword']);
        Route::put('user-increment-decrement', [\App\Http\Controllers\UserController::class, 'incrementDecrement']);
        Route::put('user-remove-two-step', [\App\Http\Controllers\UserController::class, 'removeTwoStepAuthentication']);

        Route::resource('exams', \App\Http\Controllers\ExamController::class);
        Route::get('exams-all', [\App\Http\Controllers\ExamController::class, 'all']);
        Route::get('exam-indexes', [\App\Http\Controllers\ExamController::class, 'indexes']);

        Route::resource('exam-users', \App\Http\Controllers\ExamUserController::class);
        Route::put('exam-users-avoid', [\App\Http\Controllers\ExamUserController::class, 'avoid']);
        Route::put('exam-users-recover', [\App\Http\Controllers\ExamUserController::class, 'recover']);
        Route::put('exam-users-avoid-bulk', [\App\Http\Controllers\ExamUserController::class, 'bulkAvoid']);
        Route::put('exam-users-delete-bulk', [\App\Http\Controllers\ExamUserController::class, 'bulkDelete']);

        Route::get('dashboard/system-info', [\App\Http\Controllers\DashboardController::class, 'systemInfo']);
        Route::get('dashboard/stat-data', [\App\Http\Controllers\DashboardController::class, 'statData']);
        Route::get('dashboard/latest-user', [\App\Http\Controllers\DashboardController::class, 'latestUser']);
        Route::get('dashboard/latest-torrent', [\App\Http\Controllers\DashboardController::class, 'latestTorrent']);

        Route::resource('settings', \App\Http\Controllers\SettingController::class);
        Route::resource('medals', \App\Http\Controllers\MedalController::class);
        Route::resource('user-medals', \App\Http\Controllers\UserMedalController::class);
        Route::resource('tags', \App\Http\Controllers\TagController::class);
        Route::resource('hr', \App\Http\Controllers\HitAndRunController::class);
        Route::get('hr-status', [\App\Http\Controllers\HitAndRunController::class, 'listStatus']);
        Route::put('hr-pardon/{id}', [\App\Http\Controllers\HitAndRunController::class, 'pardon']);
        Route::put('hr-delete', [\App\Http\Controllers\HitAndRunController::class, 'bulkDelete']);
        Route::put('hr-pardon', [\App\Http\Controllers\HitAndRunController::class, 'bulkPardon']);
    });

});

Route::post('login', [\App\Http\Controllers\AuthenticateController::class, 'login']);
