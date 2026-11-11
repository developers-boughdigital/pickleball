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
use App\Http\Controllers\Admin\LocationController as AdminLocationController;
use App\Http\Controllers\Admin\ListingReportController as AdminListingReportController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingReportController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Admin\SeoController;
use App\Models\Role;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

// Location pages: /play/{state}/{city}/{council}/{suburb}/, each level a real page.
Route::controller(LocationController::class)->prefix('play')->name('play')->group(function () {
    Route::get('/', 'hub');
    Route::get('find', 'find')->name('.find');
    Route::get('{state}/{city?}/{council?}/{suburb?}', 'show')->name('.area')->where('state', '[A-Za-z]{2,3}');
});

// Built from what's published; see SitemapController.
Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('sitemaps/{type}.xml', [SitemapController::class, 'show'])->name('sitemap.type');
Route::get('robots.txt', [SitemapController::class, 'robots'])->name('robots');

Route::post('reports', [ListingReportController::class, 'store'])->middleware('throttle:5,60')->name('reports.store');

// The public directory.
Route::controller(ExploreController::class)->prefix('explore')->name('explore')->group(function () {
    Route::get('/', 'index');
    Route::get('clubs', 'clubs')->name('.clubs');
    Route::get('clubs/{club}', 'club')->name('.club');
    Route::get('venues', 'venues')->name('.venues');
    Route::get('venues/{venue}', 'venue')->name('.venue');
    Route::get('coaches', 'coaches')->name('.coaches');
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
        Route::post('venues/status', [VenueController::class, 'status'])->name('venues.status');
        Route::post('clubs/status', [ClubController::class, 'status'])->name('clubs.status');
        Route::resource('venues', VenueController::class);
        Route::get('clubs/export', [ClubController::class, 'export'])->name('clubs.export');
        Route::resource('clubs', ClubController::class);

        Route::get('locations', [AdminLocationController::class, 'index'])->middleware('permission:venues.view')->name('locations');
        Route::post('locations/fill', [AdminLocationController::class, 'fill'])->middleware('permission:venues.update')->name('locations.fill');
        Route::get('reports', [AdminListingReportController::class, 'index'])->middleware('permission:venues.view')->name('reports.index');
        Route::patch('reports/{report}', [AdminListingReportController::class, 'update'])->middleware('permission:venues.update')->name('reports.update');

        Route::controller(ImportController::class)->group(function () {
            Route::get('import', 'index')->name('import');
            Route::post('import', 'store')->name('import.store');
            Route::get('import/template/{type}', 'template')->name('import.template');
            Route::get('import/club-venue/export', 'export')->name('import.club-venue-export');
        });

        Route::get('seo', [SeoController::class, 'index'])->middleware('permission:settings.view')->name('seo');
        Route::post('seo', [SeoController::class, 'store'])->middleware('permission:settings.update')->name('seo.store');
        Route::put('seo/{pageMeta}', [SeoController::class, 'update'])->middleware('permission:settings.update')->name('seo.update');
        Route::delete('seo/{pageMeta}', [SeoController::class, 'destroy'])->middleware('permission:settings.update')->name('seo.destroy');

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
