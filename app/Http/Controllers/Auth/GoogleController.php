<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\User as GoogleUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

class GoogleController extends Controller
{
    public static function enabled(): bool
    {
        return filled(config('services.google.client_id')) && filled(config('services.google.client_secret'));
    }

    public function redirect(): RedirectResponse|SymfonyRedirect
    {
        if (! static::enabled()) {
            return $this->backToLogin('Google sign-in isn’t set up yet.');
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request): RedirectResponse
    {
        if (! static::enabled()) {
            return $this->backToLogin('Google sign-in isn’t set up yet.');
        }

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable $e) {
            // Cancelled on Google's screen, an expired state, or Google being unreachable.
            report($e);

            return $this->backToLogin('We couldn’t sign you in with Google. Please try again.');
        }

        if (! $google->getEmail()) {
            return $this->backToLogin('Your Google account didn’t share an email address.');
        }

        [$user, $isNew] = $this->findOrCreateUser($google);

        if (! $user) {
            return $this->backToLogin('An account with this email already exists. Please sign in with your password.');
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        $isNew
            ? ActivityLog::record('auth.registered', "Registered with Google as {$user->email}", $user, $user->name)
            : ActivityLog::record('auth.login', 'Signed in with Google', $user, $user->email);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * @return array{0: ?User, 1: bool} the user, and whether they were just created
     */
    private function findOrCreateUser(GoogleUser $google): array
    {
        if ($user = User::firstWhere('google_id', $google->getId())) {
            $user->forceFill(['avatar' => $google->getAvatar()])->save();

            return [$user, false];
        }

        $email = strtolower($google->getEmail());

        if ($user = User::firstWhere('email', $email)) {
            // Only link to an existing account when Google has verified the address,
            // otherwise anyone could claim someone else's account by email.
            if (! ($google->user['email_verified'] ?? false)) {
                return [null, false];
            }

            $user->forceFill([
                'google_id' => $google->getId(),
                'avatar' => $google->getAvatar(),
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            return [$user, false];
        }

        $user = User::forceCreate([
            'name' => $google->getName() ?: $google->getNickname() ?: strstr($email, '@', true),
            'email' => $email,
            'google_id' => $google->getId(),
            'avatar' => $google->getAvatar(),
            'email_verified_at' => now(),
            'password' => null,
        ]);

        return [$user, true];
    }

    private function backToLogin(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => $message]);
    }
}
