<?php

namespace App\Http\Middleware;

use App\Support\Installer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs before the web stack (cookies, sessions, database) so a fresh copy of
 * the app with no .env goes straight to the installer instead of erroring.
 */
class RedirectToInstaller
{
    public function __construct(private Installer $installer) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->installer->installed() || $request->is('install', 'install/*')) {
            return $next($request);
        }

        return redirect('/install');
    }
}
