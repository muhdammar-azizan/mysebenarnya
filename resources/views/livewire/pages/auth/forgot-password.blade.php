<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth')] class extends Component
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

<x-auth-card>
    <div class="font-display font-bold text-2xl mb-1.5">{{ __('Reset Your Password') }}</div>
    <div class="text-[13px] text-gray-400 mb-6">{{ __("Enter your email and we'll send a reset link.") }}</div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="sendPasswordResetLink" class="flex flex-col gap-4">
        <div>
            <label for="email" class="block text-[13px] font-medium mb-1.5">{{ __('Email') }}</label>
            <input id="email" type="email" wire:model="email" placeholder="you@example.com" required autofocus
                class="w-full box-border h-10 px-3 rounded-lg text-sm border focus:outline-none focus:ring-0 {{ $errors->has('email') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}" />
            <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-brand" />
        </div>

        <button type="submit" class="h-10 rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold text-sm">
            {{ __('Send Reset Link') }}
        </button>

        <div class="text-center">
            <a href="{{ route('login') }}" wire:navigate class="text-[13px] text-brand font-medium">{{ __('Back to Login') }}</a>
        </div>
    </form>
</x-auth-card>
