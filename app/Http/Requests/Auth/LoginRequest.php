<?php

namespace App\Http\Requests\Auth;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate, allowing 5 failed attempts per email/IP before locking out.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            event(new Lockout($this));
            $this->logFailure('auth.locked_out', 'Locked out after too many sign-in attempts');

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey())]),
            ]);
        }

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());
            $this->logFailure('auth.login_failed', 'Failed sign-in: wrong email or password');

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Attribute the attempt to the account when the email exists, otherwise
     * keep the email that was typed so repeated guesses are still visible.
     */
    private function logFailure(string $action, string $description): void
    {
        $email = Str::lower($this->string('email'));
        $user = User::where('email', $email)->first();

        ActivityLog::record($action, $description, $user, $email, succeeded: false, actor: $user, actorName: $email);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
