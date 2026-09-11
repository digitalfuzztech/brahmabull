<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $user = $this->form->authenticate();

        Session::regenerate();
      //  $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

       // $user = auth()->user();

        if ($user->hasRole('admin')) {
            $this->redirectRoute('admin.dashboard');
            return;
        }

        if ($user->hasRole('agent')) {
            $this->redirectRoute('agent.dashboard');
            return;
        }

        $this->redirect(route('home', absolute: false));
    }
}; ?>

<div class="bb-auth-content">
    <header class="bb-auth-card-heading">
        <p class="bb-auth-card-kicker">Member access</p>
        <h2>Welcome Back</h2>
        <p>Sign in with your username or email to continue.</p>
    </header>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form wire:submit="login" class="bb-auth-form">
        <div>
            <x-input-label for="login" value="Username or Email" />
            <x-text-input wire:model="form.login" id="login" class="block mt-1 w-full" type="text" name="login" required autofocus autocomplete="username" placeholder="Username or email" />
            <x-input-error :messages="$errors->get('form.login')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" :value="__('Password')" />
            <div x-data="{ show:false }" class="relative mt-1">
                <x-text-input wire:model="form.password" id="password" x-bind:type="show ? 'text' : 'password'" class="block w-full pr-12" name="password" required autocomplete="current-password" />
                <button type="button" @click="show = !show" class="bb-auth-password-toggle" aria-label="Show or hide password">
                    <svg x-show="!show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0c-1.73 3.89-6 7-11 7s-9.27-3.11-11-7c1.73-3.89 6-7 11-7s9.27 3.11 11 7z" />
                    </svg>
                    <svg x-show="show" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-5 0-9.27-3.11-11-7 1.02-2.29 2.76-4.2 4.96-5.42M9.88 9.88A3 3 0 0114.12 14.12M3 3l18 18" />
                    </svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
        </div>

        <div class="bb-auth-form-options">
            <label for="remember" class="bb-auth-check-label">
                <input wire:model="form.remember" id="remember" type="checkbox" class="bb-auth-checkbox" name="remember">
                <span>{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="bb-auth-text-link" href="{{ route('password.request') }}" wire:navigate>{{ __('Forgot your password?') }}</a>
            @endif
        </div>

        <x-primary-button class="bb-auth-submit" wire:loading.attr="disabled" wire:target="login">
            <span wire:loading.remove wire:target="login">Sign In</span>
            <span wire:loading wire:target="login">Signing in...</span>
        </x-primary-button>
    </form>

    <p class="bb-auth-switch-copy">
        New here?
        <a href="{{ route('register') }}" class="bb-auth-text-link" wire:navigate>Create Account</a>
    </p>
</div>
