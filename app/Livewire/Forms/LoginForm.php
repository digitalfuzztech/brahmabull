<?php

namespace App\Livewire\Forms;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
   // #[Validate('required|string|email')]
   // public string $email = '';

    #[Validate('required|string')]
    public string $login = '';

    #[Validate('required|string')]
    public string $password = '';

    #[Validate('boolean')]
    public bool $remember = false;

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate()
    {
        $this->ensureIsNotRateLimited();

        $field = filter_var($this->login, FILTER_VALIDATE_EMAIL)
            ? 'email'
            : 'username';


        if (! Auth::attempt([
            $field => $this->login,
            'password' => $this->password
        ], $this->remember)) {

            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'form.login' => trans('auth.failed'),
            ]);
        }


        RateLimiter::clear($this->throttleKey());

        $user = Auth::user();


        if (
            $user->hasRole('player')
            && ! $user->hasVerifiedEmail()
        ) {

            Auth::logout();

            throw ValidationException::withMessages([
                'form.login' => 'Please verify your email address before logging in.',
            ]);
        }


        if (! $user->is_active) {

            Auth::logout();

            throw ValidationException::withMessages([
                'form.login' => 'Your account has been disabled. Please contact administrator.',
            ]);
        }


        return $user;
    }
    /**
     * Ensure the authentication request is not rate limited.
     */
    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'form.login' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the authentication rate limiting throttle key.
     */
    protected function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->login).'|'.request()->ip()
        );
    }
}
