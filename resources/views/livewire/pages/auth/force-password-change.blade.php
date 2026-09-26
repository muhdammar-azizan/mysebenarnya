<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.auth')] class extends Component
{
    public string $tempPassword = '';
    public string $password = '';
    public string $password_confirmation = '';
    public bool $passwordUpdated = false;

    public function updatePassword(): void
    {
        $this->validate([
            'tempPassword' => ['required', 'current_password'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = Auth::user();

        $user->forceFill([
            'password' => Hash::make($this->password),
            'must_change_password' => false,
        ])->save();

        $this->passwordUpdated = true;
    }
}; ?>

<div class="min-h-screen box-border bg-[#F7F7F8] px-6 py-14 flex flex-col items-center">
    <div class="flex items-center gap-2.5 mb-6">
        <x-brand-mark-simple />
        <div class="font-display font-extrabold text-base tracking-tight">
            <span class="text-gray-900">SE</span><span class="text-brand">BENAR</span><span class="text-gray-900">NYA.MY</span>
        </div>
    </div>

    <div class="w-full max-w-[420px] bg-white border border-gray-100 rounded-lg shadow-sm p-7">

        <div class="flex gap-2.5 bg-amber-50 border border-amber-200 rounded-lg px-3.5 py-3 mb-5 text-[12.5px] text-amber-800 leading-relaxed">
            <div class="flex-shrink-0">🔒</div>
            <div>{{ __('For your security, you must set a new password before continuing.') }}</div>
        </div>

        @if (! $passwordUpdated)
            <div class="font-display font-bold text-[22px] mb-1.5">{{ __('Set a New Password') }}</div>
            <div class="text-[13px] text-gray-400 mb-5">{{ __('This is your first login. Please create a secure password to replace your temporary one.') }}</div>

            <form wire:submit="updatePassword" class="flex flex-col gap-4"
                x-data="{
                    show1: false, show2: false, show3: false,
                    newPw: '', confirmPw: '',
                    get reqs() {
                        return [
                            { label: 'At least 8 characters', met: this.newPw.length >= 8 },
                            { label: 'Upper & lower case letters', met: /[a-z]/.test(this.newPw) && /[A-Z]/.test(this.newPw) },
                            { label: 'At least one number', met: /[0-9]/.test(this.newPw) },
                            { label: 'At least one special character', met: /[^A-Za-z0-9]/.test(this.newPw) },
                        ];
                    },
                    get strength() {
                        const n = this.reqs.filter(r => r.met).length;
                        if (!this.newPw) return { bars: ['#EDEEF0','#EDEEF0','#EDEEF0'], label: '', color: '' };
                        if (n <= 1) return { bars: ['#C41230','#EDEEF0','#EDEEF0'], label: 'Weak password', color: '#C41230' };
                        if (n <= 3) return { bars: ['#C41230','#E8A33D','#EDEEF0'], label: 'Medium strength', color: '#E8A33D' };
                        return { bars: ['#1E8E5A','#1E8E5A','#1E8E5A'], label: 'Strong password', color: '#1E8E5A' };
                    },
                    get allMet() { return this.reqs.every(r => r.met); },
                    get updateDisabled() { return !this.allMet || this.newPw !== this.confirmPw || !this.confirmPw; }
                }"
            >
                <div>
                    <label for="tempPassword" class="block text-[13px] font-medium mb-1.5">{{ __('Temporary Password') }}</label>
                    <div class="relative">
                        <input id="tempPassword" :type="show1 ? 'text' : 'password'" wire:model="tempPassword" placeholder="{{ __('The temporary password from your email') }}" required
                            class="w-full box-border h-10 px-3 pr-10 rounded-lg text-sm border focus:outline-none focus:ring-0 {{ $errors->has('tempPassword') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}" />
                        <button type="button" @click="show1 = !show1" class="absolute right-3 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-gray-400 flex items-center justify-center">
                            <svg x-show="!show1" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.8"/></svg>
                            <svg x-show="show1" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10.6 5.2A10.6 10.6 0 0112 5c6.5 0 10 6 10 6a15.6 15.6 0 01-3.3 4M6.6 6.6C4 8.3 2 12 2 12s1.6 3 4.6 4.7M9.9 14.1a3 3 0 004.2-4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>
                    <x-input-error :messages="$errors->get('tempPassword')" class="mt-1.5 text-brand" />
                </div>

                <div>
                    <label for="password" class="block text-[13px] font-medium mb-1.5">{{ __('New Password') }}</label>
                    <div class="relative">
                        <input id="password" :type="show2 ? 'text' : 'password'" wire:model="password" x-model="newPw" placeholder="{{ __('Create a new password') }}" required
                            class="w-full box-border h-10 px-3 pr-10 rounded-lg text-sm border border-gray-300 focus:outline-none focus:ring-0" />
                        <button type="button" @click="show2 = !show2" class="absolute right-3 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-gray-400 flex items-center justify-center">
                            <svg x-show="!show2" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.8"/></svg>
                            <svg x-show="show2" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10.6 5.2A10.6 10.6 0 0112 5c6.5 0 10 6 10 6a15.6 15.6 0 01-3.3 4M6.6 6.6C4 8.3 2 12 2 12s1.6 3 4.6 4.7M9.9 14.1a3 3 0 004.2-4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>

                    <div class="flex gap-1 mt-2">
                        <div class="flex-1 h-1 rounded-full" :style="'background:' + strength.bars[0]"></div>
                        <div class="flex-1 h-1 rounded-full" :style="'background:' + strength.bars[1]"></div>
                        <div class="flex-1 h-1 rounded-full" :style="'background:' + strength.bars[2]"></div>
                    </div>
                    <div class="text-[11.5px] font-semibold mt-1" :style="'color:' + strength.color" x-text="strength.label"></div>

                    <div class="flex flex-col gap-1.5 mt-3">
                        <template x-for="req in reqs" :key="req.label">
                            <div class="flex items-center gap-2 text-[12.5px]" :class="req.met ? 'text-gray-700' : 'text-gray-400'">
                                <div class="w-4 h-4 rounded-full flex items-center justify-center text-[10px] text-white flex-shrink-0" :class="req.met ? 'bg-[#1E8E5A]' : 'bg-gray-300'">
                                    <span x-text="req.met ? '✓' : ''"></span>
                                </div>
                                <span x-text="req.label"></span>
                            </div>
                        </template>
                    </div>

                    <x-input-error :messages="$errors->get('password')" class="mt-1.5 text-brand" />
                </div>

                <div>
                    <label for="password_confirmation" class="block text-[13px] font-medium mb-1.5">{{ __('Confirm New Password') }}</label>
                    <div class="relative">
                        <input id="password_confirmation" :type="show3 ? 'text' : 'password'" wire:model="password_confirmation" x-model="confirmPw" placeholder="{{ __('Re-enter your new password') }}" required
                            class="w-full box-border h-10 px-3 pr-10 rounded-lg text-sm border focus:outline-none focus:ring-0 {{ $errors->has('password_confirmation') ? 'border-brand ring-2 ring-brand/10' : 'border-gray-300' }}" />
                        <button type="button" @click="show3 = !show3" class="absolute right-3 top-1/2 -translate-y-1/2 w-[18px] h-[18px] text-gray-400 flex items-center justify-center">
                            <svg x-show="!show3" width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.6" stroke="currentColor" stroke-width="1.8"/></svg>
                            <svg x-show="show3" x-cloak width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M10.6 5.2A10.6 10.6 0 0112 5c6.5 0 10 6 10 6a15.6 15.6 0 01-3.3 4M6.6 6.6C4 8.3 2 12 2 12s1.6 3 4.6 4.7M9.9 14.1a3 3 0 004.2-4.2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </button>
                    </div>
                    <template x-if="confirmPw && newPw !== confirmPw">
                        <div class="text-xs text-brand mt-1.5">{{ __('Passwords do not match.') }}</div>
                    </template>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1.5 text-brand" />
                </div>

                <button type="submit" :disabled="updateDisabled"
                    :class="updateDisabled ? 'bg-gray-300 cursor-not-allowed' : 'bg-brand hover:bg-brand-dark cursor-pointer'"
                    class="h-11 rounded-lg text-white font-bold text-sm">
                    {{ __('Update Password & Continue') }}
                </button>
            </form>
        @else
            <div class="text-center py-5" x-data x-init="setTimeout(() => Livewire.navigate('{{ route('dashboard') }}'), 900)">
                <div class="w-[52px] h-[52px] rounded-full bg-green-100 text-green-700 text-2xl flex items-center justify-center mx-auto mb-4">✓</div>
                <div class="font-display font-bold text-lg">{{ __('Password updated successfully!') }}</div>
            </div>
        @endif
    </div>
</div>
