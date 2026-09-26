<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.auth')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<x-auth-card>
    <div class="font-display font-bold text-2xl mb-5">{{ __('Set New Password') }}</div>

    <form wire:submit="resetPassword" class="flex flex-col gap-4">
        <input type="hidden" wire:model="email" />
        <x-input-error :messages="$errors->get('email')" class="text-brand" />

        <div x-data="{ show: false, pw: '' }">
            <label for="password" class="block text-[13px] font-medium mb-1.5">{{ __('New Password') }}</label>
            <div class="relative">
                <input id="password" :type="show ? 'text' : 'password'" wire:model="password" x-model="pw" placeholder="Enter new password" required autocomplete="new-password"
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

        <div>
            <label for="password_confirmation" class="block text-[13px] font-medium mb-1.5">{{ __('Confirm Password') }}</label>
            <input id="password_confirmation" type="password" wire:model="password_confirmation" placeholder="Re-enter password" required autocomplete="new-password"
                class="w-full box-border h-10 px-3 rounded-lg text-sm border focus:outline-none focus:ring-0 {{ $errors->has('password_confirmation') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5 text-brand" />
        </div>

        <button type="submit" class="h-10 rounded-lg bg-brand hover:bg-brand-dark text-white font-semibold text-sm">
            {{ __('Update Password') }}
        </button>
    </form>
</x-auth-card>
