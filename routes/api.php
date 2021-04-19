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

Route::group([], function () {
    Route::resource('agent-allow', \App\Http\Controllers\AgentAllowController::class);
    Route::resource('user', \App\Http\Controllers\UserController::class);
    Route::resource('exam', \App\Http\Controllers\ExamController::class);
    Route::get('class', [\App\Http\Controllers\UserController::class, 'classes']);
});
