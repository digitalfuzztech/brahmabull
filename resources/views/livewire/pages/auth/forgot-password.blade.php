<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div class="bb-auth-content">
    <header class="bb-auth-card-heading">
        <p class="bb-auth-card-kicker">Account recovery</p>
        <h2>Forgot Password?</h2>
        <p>Enter the email associated with your account and we’ll send your secure reset link.</p>
    </header>

    <x-auth-session-status class="mb-5" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="bb-auth-form">
        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autofocus autocomplete="email" placeholder="you@example.com" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <x-primary-button class="bb-auth-submit" wire:loading.attr="disabled" wire:target="sendPasswordResetLink">
            <span wire:loading.remove wire:target="sendPasswordResetLink">Email Password Reset Link</span>
            <span wire:loading wire:target="sendPasswordResetLink">Sending...</span>
        </x-primary-button>
    </form>

    <p class="bb-auth-switch-copy">
        Remembered your password?
        <a href="{{ route('login') }}" class="bb-auth-text-link" wire:navigate>Return to Login</a>
    </p>
</div>
