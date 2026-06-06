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
            $this->redirect(route('admin.dashboard', absolute: false), navigate: true);
            return;
        }

        if ($user->hasRole('agent')) {
            $this->redirect(route('agent.dashboard', absolute: false), navigate: true);
            return;
        }

        $this->redirect(route('home', absolute: false));
    }
}; ?>

<div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />
    <div class="flex flex-col gap-3 items-center">
        <form wire:submit="login">
            <!-- Email Address -->
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input wire:model="form.email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('form.email')" class="mt-2" />
            </div>

            <!-- Password -->
            <div class="mt-4">
                <x-input-label for="password" :value="__('Password')" />

                <div x-data="{ show:false }" class="relative mt-1">

                    <x-text-input
                        wire:model="form.password"
                        id="password"
                        x-bind:type="show ? 'text' : 'password'"
                        class="block w-full pr-12"
                        name="password"
                        required
                        autocomplete="current-password"
                    />

                    <button
                        type="button"
                        @click="show = !show"
                        class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-purple-400"
                    >

                        {{-- Eye --}}
                        <svg
                            x-show="!show"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0zm6 0c-1.73 3.89-6 7-11 7s-9.27-3.11-11-7c1.73-3.89 6-7 11-7s9.27 3.11 11 7z"
                            />
                        </svg>

                        {{-- Eye Off --}}
                        <svg
                            x-show="show"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="2"
                                d="M13.875 18.825A10.05 10.05 0 0112 19c-5 0-9.27-3.11-11-7 1.02-2.29 2.76-4.2 4.96-5.42M9.88 9.88A3 3 0 0114.12 14.12M3 3l18 18"
                            />
                        </svg>

                    </button>

                </div>

                <x-input-error :messages="$errors->get('form.password')" class="mt-2" />
            </div>

            <!-- Remember Me -->
            <div class="block mt-4">
                <label for="remember" class="inline-flex items-center">
                    <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                    <span class="ms-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="mt-6 flex items-center justify-between">
                @if (Route::has('password.request'))
                    <a class="text-sm text-purple-400 hover:text-purple-300 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('password.request') }}" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </a>
                @endif

                <x-primary-button class="ms-3">
                    {{ __('Log in') }}
                </x-primary-button>




            </div>

        </form>
        <div
            wire:loading
            wire:target="login"
            class="mt-4 flex items-center justify-center gap-3 text-sm font-semibold text-purple-500 animate-pulse"
        >
            <svg class="w-5 h-5 animate-spin" viewBox="0 0 24 24" fill="none">
                <circle
                    class="opacity-25"
                    cx="12"
                    cy="12"
                    r="10"
                    stroke="currentColor"
                    stroke-width="4"
                ></circle>

                <path
                    class="opacity-75"
                    fill="currentColor"
                    d="M4 12a8 8 0 018-8v8z"
                ></path>
            </svg>

            Logging in...
        </div>
    </div>

</div>
