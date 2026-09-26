<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Update the password for the currently authenticated user.
     */
    public function updatePassword(): void
    {
        try {
            $validated = $this->validate([
                'current_password' => ['required', 'string', 'current_password'],
                'password' => ['required', 'string', Password::defaults(), 'confirmed'],
            ]);
        } catch (ValidationException $e) {
            $this->reset('current_password', 'password', 'password_confirmation');

            throw $e;
        }

        Auth::user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }
}; ?>

<section>
    <header>
        <h2 class="font-display font-bold text-lg text-gray-900">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm text-gray-500">
            {{ __('Ensure your account is using a long, random password to stay secure.') }}
        </p>
    </header>

    <form wire:submit="updatePassword" class="mt-6 flex flex-col gap-5">
        <div x-data="{ show: false }">
            <label for="update_password_current_password" class="block text-[13px] font-medium mb-1.5">{{ __('Current Password') }}</label>
            <div class="relative">
                <input id="update_password_current_password" :type="show ? 'text' : 'password'" wire:model="current_password" autocomplete="current-password"
                    class="w-full box-border h-10 px-3 pr-10 rounded-lg text-sm border focus:outline-none focus:ring-0 {{ $errors->has('current_password') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}" />
                <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-gray-400 flex items-center justify-center">
                    <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.8"/></svg>
                    <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10.6 5.2A10.6 10.6 0 0112 5c6.5 0 10 6 10 6a15.6 15.6 0 01-3.3 4M6.6 6.6C4 8.3 2 12 2 12s1.6 3 4.6 4.7M9.9 14.1a3 3 0 004.2-4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('current_password')" class="mt-1.5 text-brand" />
        </div>

        <div x-data="{ show: false, pw: '' }">
            <label for="update_password_password" class="block text-[13px] font-medium mb-1.5">{{ __('New Password') }}</label>
            <div class="relative">
                <input id="update_password_password" :type="show ? 'text' : 'password'" wire:model="password" x-model="pw" autocomplete="new-password"
                    class="w-full box-border h-10 px-3 pr-10 rounded-lg text-sm border focus:outline-none focus:ring-0 {{ $errors->has('password') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}" />
                <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-gray-400 flex items-center justify-center">
                    <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.8"/></svg>
                    <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10.6 5.2A10.6 10.6 0 0112 5c6.5 0 10 6 10 6a15.6 15.6 0 01-3.3 4M6.6 6.6C4 8.3 2 12 2 12s1.6 3 4.6 4.7M9.9 14.1a3 3 0 004.2-4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
            <template x-if="pw">
                <div x-data="{
                    get strength() {
                        if (pw.length < 6) return { bars: ['#C41230','#EDEEF0','#EDEEF0'], label: 'Weak password' };
                        if (pw.length < 10) return { bars: ['#C41230','#E8A33D','#EDEEF0'], label: 'Medium strength' };
                        return { bars: ['#1E8E5A','#1E8E5A','#1E8E5A'], label: 'Strong password' };
                    }
                }">
                    <div class="flex gap-1 mt-2">
                        <div class="flex-1 h-1 rounded-full" :style="'background:' + strength.bars[0]"></div>
                        <div class="flex-1 h-1 rounded-full" :style="'background:' + strength.bars[1]"></div>
                        <div class="flex-1 h-1 rounded-full" :style="'background:' + strength.bars[2]"></div>
                    </div>
                    <div class="text-xs text-gray-400 mt-1" x-text="strength.label"></div>
                </div>
            </template>
            <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-brand" />
        </div>

        <div x-data="{ show: false }">
            <label for="update_password_password_confirmation" class="block text-[13px] font-medium mb-1.5">{{ __('Confirm Password') }}</label>
            <div class="relative">
                <input id="update_password_password_confirmation" :type="show ? 'text' : 'password'" wire:model="password_confirmation" autocomplete="new-password"
                    class="w-full box-border h-10 px-3 pr-10 rounded-lg text-sm border focus:outline-none focus:ring-0 {{ $errors->has('password_confirmation') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}" />
                <button type="button" @click="show = !show" class="absolute right-3 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-gray-400 flex items-center justify-center">
                    <svg x-show="!show" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.8"/></svg>
                    <svg x-show="show" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10.6 5.2A10.6 10.6 0 0112 5c6.5 0 10 6 10 6a15.6 15.6 0 01-3.3 4M6.6 6.6C4 8.3 2 12 2 12s1.6 3 4.6 4.7M9.9 14.1a3 3 0 004.2-4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5 text-brand" />
        </div>

        <div class="flex items-center gap-4">
            <button type="submit" class="h-10 px-5 rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold text-sm">{{ __('Save') }}</button>

            <x-action-message class="text-sm text-green-600 font-medium" on="password-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
