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

Route::group(['middleware' => ['auth:sanctum', 'permission', 'locale']], function () {
    Route::post('logout', [\App\Http\Controllers\AuthenticateController::class, 'logout']);

    Route::resource('agent-allows', \App\Http\Controllers\AgentAllowController::class);

    Route::resource('users', \App\Http\Controllers\UserController::class);
    Route::get('user-base', [\App\Http\Controllers\UserController::class, 'base']);
    Route::get('user-classes', [\App\Http\Controllers\UserController::class, 'classes']);
    Route::get('user-match-exams', [\App\Http\Controllers\UserController::class, 'matchExams']);
    Route::post('user-disable', [\App\Http\Controllers\UserController::class, 'disable']);

    Route::resource('exams', \App\Http\Controllers\ExamController::class);
    Route::get('exam-indexes', [\App\Http\Controllers\ExamController::class, 'indexes']);

    Route::resource('exam-users', \App\Http\Controllers\ExamUserController::class);

    Route::get('system-info', [\App\Http\Controllers\ToolController::class, 'systemInfo']);

});

Route::post('login', [\App\Http\Controllers\AuthenticateController::class, 'login']);

