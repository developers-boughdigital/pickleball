<?php

use App\Http\Middleware\RedirectToInstaller;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Symfony\Component\HttpKernel\Exception\HttpException;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // No "web" group here: the installer must work before .env exists.
            Route::prefix('install')->group(base_path('routes/install.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RedirectToInstaller::class);
        $middleware->redirectUsersTo(fn () => route('dashboard'));

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // An expired form on the sign-in pages (left open too long, submitted twice,
        // or signed in from another tab) shouldn't end on a bare 419 page.
        // Laravel has already turned the TokenMismatchException into a 419 here.
        $exceptions->render(function (HttpException $e, Request $request) {
            if (! $e->getPrevious() instanceof TokenMismatchException || ! $request->is('login', 'register', 'logout')) {
                return null;
            }

            if ($request->is('logout')) {
                return $request->user() ? null : redirect()->route('home');
            }

            if ($request->user()) {
                return redirect()->intended(route('dashboard'));
            }

            return redirect('/'.$request->path())
                ->withInput($request->except('password', 'password_confirmation', '_token'))
                ->withErrors(['email' => 'The page had expired, so please try again.']);
        });
    })->create();

// The web root is public_html (shared hosting convention) instead of public.
$app->usePublicPath($app->basePath('public_html'));

return $app;
