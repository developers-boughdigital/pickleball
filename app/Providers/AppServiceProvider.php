<?php

namespace App\Providers;

use App\Models\Setting;
use App\Support\Installer;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The live site is HTTPS only, so every generated link, canonical and sitemap URL is too.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // Settings saved in System Settings win over .env. Before install, or before
        // the settings table is migrated, there's nothing to load.
        if ($this->app->make(Installer::class)->installed()) {
            rescue(fn () => Setting::applyToConfig(), report: false);
        }
    }
}
