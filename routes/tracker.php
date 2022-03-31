<?php
use Illuminate\Support\Facades\Route;

Route::get('announce', [\App\Http\Controllers\TrackerController::class, 'announce']);
Route::get('scrape', [\App\Http\Controllers\TrackerController::class, 'scrape']);
