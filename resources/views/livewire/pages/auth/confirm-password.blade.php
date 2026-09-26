<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="min-h-screen box-border bg-[#F0F1F3] flex items-center justify-center p-6">
    <div class="w-full max-w-[400px] bg-white rounded-2xl border border-gray-100 shadow-[0_4px_24px_rgba(0,0,0,0.06)] px-8 py-9">

        <div class="flex flex-col items-center text-center mb-6">
            <div class="w-[52px] h-[52px] rounded-full bg-brand-light flex items-center justify-center mb-[18px]">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <rect x="5" y="10" width="14" height="10" rx="2" stroke="#C41230" stroke-width="2"/>
                    <path d="M8 10V7a4 4 0 0 1 8 0v3" stroke="#C41230" stroke-width="2"/>
                    <circle cx="12" cy="15" r="1.4" fill="#C41230"/>
                </svg>
            </div>
            <div class="font-display font-bold text-xl mb-2">{{ __('Confirm your password') }}</div>
            <p class="text-sm text-gray-500 leading-relaxed max-w-[290px]">{{ __('For your security, please confirm your password before continuing with this action.') }}</p>
        </div>

        <form wire:submit="confirmPassword" class="flex flex-col gap-[18px]">
            <div>
                <label for="password" class="block text-xs font-semibold text-gray-600 uppercase tracking-wide mb-1.5">{{ __('Password') }}</label>
                <input id="password" type="password" wire:model="password" placeholder="{{ __('Enter your password') }}" required autofocus autocomplete="current-password"
                    class="w-full box-border px-3.5 py-3 rounded-lg text-sm border focus:outline-none focus:ring-0 {{ $errors->has('password') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}" />
                <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-brand" />
            </div>

            <div class="flex gap-2.5">
                <button type="button" onclick="history.back()" class="flex-1 text-center bg-transparent text-gray-600 border border-gray-300 hover:bg-gray-50 py-3 rounded-lg font-semibold text-sm">
                    {{ __('Cancel') }}
                </button>
                <button type="submit" class="flex-[1.3] bg-brand hover:bg-brand-dark text-white py-3 rounded-lg font-semibold text-sm">
                    {{ __('Confirm') }}
                </button>
            </div>
        </form>

        <div class="flex items-center justify-center gap-2 mt-7 pt-5 border-t border-gray-100">
            <x-brand-mark class="w-4 h-4 flex-shrink-0" />
            <div class="font-display font-bold text-xs text-gray-400">{{ __('SEBENARNYA.MY') }}</div>
        </div>
    </div>
</div>
