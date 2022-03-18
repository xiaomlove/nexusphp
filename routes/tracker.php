<?php
use Illuminate\Support\Facades\Route;

Route::get('announce', [\App\Http\Controllers\TrackerController::class, 'announce']);
