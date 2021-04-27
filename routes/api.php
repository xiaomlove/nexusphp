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

Route::group(['middleware' => ['auth:sanctum', 'permission']], function () {
    Route::post('logout', [\App\Http\Controllers\AuthenticateController::class, 'logout']);

    Route::resource('agent-allow', \App\Http\Controllers\AgentAllowController::class);

    Route::resource('user', \App\Http\Controllers\UserController::class);
    Route::get('user-base', [\App\Http\Controllers\UserController::class, 'base']);
    Route::get('user-classes', [\App\Http\Controllers\UserController::class, 'classes']);
    Route::get('user-match-exams', [\App\Http\Controllers\UserController::class, 'matchExams']);

    Route::resource('exam', \App\Http\Controllers\ExamController::class);
    Route::get('exam-indexes', [\App\Http\Controllers\ExamController::class, 'indexes']);

    Route::resource('exam-users', \App\Http\Controllers\ExamUserController::class);

});

Route::post('login', [\App\Http\Controllers\AuthenticateController::class, 'login']);

