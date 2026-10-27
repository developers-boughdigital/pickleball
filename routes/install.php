<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

// Registered without the "web" middleware group, see bootstrap/app.php.
Route::get('/', [InstallController::class, 'show'])->name('install');
Route::post('database', [InstallController::class, 'testDatabase'])->name('install.database');
Route::post('/', [InstallController::class, 'store'])->name('install.store');
