<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\DatabaseController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\ClubController;
use App\Http\Controllers\Admin\ImportController;
use App\Http\Controllers\Admin\VenueController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExploreController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : view('home'))->name('home');

// The public directory.
Route::controller(ExploreController::class)->prefix('explore')->name('explore')->group(function () {
    Route::get('/', 'index');
    Route::get('clubs/{club}', 'club')->name('.club');
    Route::get('venues/{venue}', 'venue')->name('.venue');
});

// no_store keeps browsers from restoring these forms from history with an old CSRF token.
Route::middleware(['guest', 'cache.headers:no_store;private'])->group(function () {
    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store']);

    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);

    Route::get('auth/google', [GoogleController::class, 'redirect'])->name('auth.google');
    Route::get('auth/google/callback', [GoogleController::class, 'callback'])->name('auth.google.callback');
});

Route::middleware('auth')->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class);
        Route::resource('roles', RoleController::class)->except('show');
        Route::resource('permissions', PermissionController::class)->except('show');
        Route::get('activity-log', ActivityLogController::class)->name('activity');

        // Before the resources, so "export" isn't read as a slug.
        Route::get('venues/export', [VenueController::class, 'export'])->name('venues.export');
        Route::resource('venues', VenueController::class);
        Route::get('clubs/export', [ClubController::class, 'export'])->name('clubs.export');
        Route::resource('clubs', ClubController::class);

        Route::controller(ImportController::class)->group(function () {
            Route::get('import', 'index')->name('import');
            Route::post('import', 'store')->name('import.store');
            Route::get('import/template/{type}', 'template')->name('import.template');
            Route::get('import/club-venue/export', 'export')->name('import.club-venue-export');
        });

        Route::get('settings', [SettingController::class, 'edit'])->middleware('permission:settings.view')->name('settings');
        Route::put('settings', [SettingController::class, 'update'])->middleware('permission:settings.update')->name('settings.update');

        Route::middleware('role:'.Role::SUPER_ADMIN)->controller(DatabaseController::class)->group(function () {
            Route::get('database', 'index')->name('database');
            Route::post('database/migrate', 'migrate')->name('database.migrate');
            Route::post('database/rollback', 'rollback')->name('database.rollback');
            Route::post('database/rollback-migration', 'rollbackMigration')->name('database.rollback-migration');
            Route::post('database/seed', 'seed')->name('database.seed');
        });
    });

    Route::post('logout', LogoutController::class)->name('logout');
});
