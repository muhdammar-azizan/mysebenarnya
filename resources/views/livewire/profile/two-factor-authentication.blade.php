<?php

use App\Services\TwoFactorAuthentication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Volt\Component;

new class extends Component
{
    public string $code = '';

    public string $password = '';

    /** Which password-protected action is waiting for the password: '', 'disable' or 'regenerate'. */
    public string $confirming = '';

    /** Recovery codes are shown once, straight after they are created. */
    public array $shownRecoveryCodes = [];

    public function mount(): void
    {
        abort_unless(Auth::user()->canUseTwoFactor(), 403);
    }

    public function enable(TwoFactorAuthentication $twoFactor): void
    {
        $user = Auth::user();

        if ($user->hasTwoFactorEnabled()) {
            return;
        }

        // Saved unconfirmed: it only counts once the user proves they can produce a code.
        $user->forceFill([
            'two_factor_secret' => $twoFactor->generateSecret(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestamp' => null,
        ])->save();

        $this->reset(['code', 'password', 'confirming']);
    }

    public function confirm(TwoFactorAuthentication $twoFactor): void
    {
        $this->validate(['code' => ['required', 'string', 'max:10']]);

        $user = Auth::user();

        if ($user->two_factor_secret === null || $user->hasTwoFactorEnabled()) {
            return;
        }

        $timestamp = $twoFactor->verify($user->two_factor_secret, $this->code);

        if ($timestamp === null) {
            $this->addError('code', __('That code is not valid. Please try again.'));

            return;
        }

        $codes = $twoFactor->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_last_timestamp' => $timestamp,
            'two_factor_recovery_codes' => $codes,
        ])->save();

        Log::info('Two-factor authentication enabled.', ['user_id' => $user->id]);

        $this->shownRecoveryCodes = $codes;
        $this->reset(['code']);
    }

    public function cancelSetup(): void
    {
        $user = Auth::user();

        if (! $user->hasTwoFactorEnabled()) {
            $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null])->save();
        }

        $this->reset(['code', 'password', 'confirming']);
        $this->resetErrorBag();
    }

    public function startConfirming(string $action): void
    {
        if (! in_array($action, ['disable', 'regenerate'], true)) {
            return;
        }

        $this->confirming = $action;
        $this->password = '';
        $this->resetErrorBag();
    }

    public function stopConfirming(): void
    {
        $this->reset(['confirming', 'password']);
        $this->resetErrorBag();
    }

    public function disable(): void
    {
        $this->validate(['password' => ['required', 'current_password']]);

        $user = Auth::user();

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_last_timestamp' => null,
        ])->save();

        Log::info('Two-factor authentication disabled.', ['user_id' => $user->id]);

        $this->reset(['password', 'confirming', 'shownRecoveryCodes']);
    }

    public function regenerate(TwoFactorAuthentication $twoFactor): void
    {
        $this->validate(['password' => ['required', 'current_password']]);

        $user = Auth::user();

        if (! $user->hasTwoFactorEnabled()) {
            return;
        }

        $codes = $twoFactor->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        Log::info('Two-factor recovery codes regenerated.', ['user_id' => $user->id]);

        $this->shownRecoveryCodes = $codes;
        $this->reset(['password', 'confirming']);
    }

    public function dismissCodes(): void
    {
        $this->shownRecoveryCodes = [];
    }

    public function getQrSvgProperty(): ?string
    {
        $user = Auth::user();

        if ($user->two_factor_secret === null || $user->hasTwoFactorEnabled()) {
            return null;
        }

        return app(TwoFactorAuthentication::class)->qrCodeSvg($user, $user->two_factor_secret);
    }
}; ?>

<section>
    @php $user = Auth::user(); @endphp

    <header>
        <h2 class="text-lg font-medium text-gray-900">{{ __('Two-Factor Authentication') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('Add a second step at login: a 6-digit code from an authenticator app such as Google Authenticator or Microsoft Authenticator.') }}</p>
    </header>

    {{-- Recovery codes (shown once) --}}
    @if (count($shownRecoveryCodes))
        <div class="mt-6 bg-amber-50 border border-amber-200 rounded-xl p-4">
            <div class="font-bold text-amber-900 text-sm mb-1">{{ __('Save your recovery codes') }}</div>
            <p class="text-xs text-amber-800 mb-3">{{ __('Keep these somewhere safe. If you lose your phone, each code lets you sign in once. They will not be shown again.') }}</p>
            <div class="grid grid-cols-2 gap-2 font-mono text-sm text-gray-900 bg-white rounded-lg p-3">
                @foreach ($shownRecoveryCodes as $recoveryCode)
                    <div>{{ $recoveryCode }}</div>
                @endforeach
            </div>
            <button type="button" wire:click="dismissCodes" class="mt-3 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2 rounded-lg">{{ __("I've saved them") }}</button>
        </div>
    @endif

    @if ($user->hasTwoFactorEnabled())
        <div class="mt-6 flex items-center gap-2 text-sm font-semibold text-green-700">
            <span class="w-2 h-2 rounded-full bg-green-600"></span>
            {{ __('Enabled') }}
        </div>
        <p class="mt-1 text-xs text-gray-500">{{ __(':count recovery codes left.', ['count' => count($user->two_factor_recovery_codes ?? [])]) }}</p>

        @if ($confirming !== '')
            <form wire:submit="{{ $confirming }}" class="mt-5 max-w-sm">
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Confirm your password') }}</label>
                <input type="password" wire:model="password" autocomplete="current-password" autofocus
                    class="w-full rounded-lg border-gray-300 focus:border-brand focus:ring-brand text-sm" />
                <x-input-error :messages="$errors->get('password')" class="mt-1.5" />
                <div class="flex gap-3 mt-3">
                    <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2 rounded-lg">
                        {{ $confirming === 'disable' ? __('Disable 2FA') : __('Generate new codes') }}
                    </button>
                    <button type="button" wire:click="stopConfirming" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2 rounded-lg">{{ __('Cancel') }}</button>
                </div>
            </form>
        @else
            <div class="mt-5 flex flex-wrap gap-3">
                <button type="button" wire:click="startConfirming('regenerate')" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2 rounded-lg">{{ __('Regenerate recovery codes') }}</button>
                <button type="button" wire:click="startConfirming('disable')" class="border border-brand text-brand hover:bg-brand-light font-bold text-sm px-4 py-2 rounded-lg">{{ __('Disable 2FA') }}</button>
            </div>
        @endif
    @elseif ($user->two_factor_secret !== null)
        {{-- Setup in progress --}}
        <div class="mt-6">
            <ol class="text-sm text-gray-600 list-decimal pl-5 space-y-1 mb-4">
                <li>{{ __('Open your authenticator app and scan this QR code.') }}</li>
                <li>{{ __('Enter the 6-digit code it shows to finish setup.') }}</li>
            </ol>

            <div class="inline-block bg-white border border-gray-200 rounded-xl p-3">{!! $this->qrSvg !!}</div>

            <div class="mt-3 text-xs text-gray-500">
                {{ __("Can't scan? Enter this key manually:") }}
                <span class="font-mono text-gray-800 break-all select-all">{{ $user->two_factor_secret }}</span>
            </div>

            <form wire:submit="confirm" class="mt-5 max-w-xs">
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Authentication code') }}</label>
                <input type="text" wire:model="code" inputmode="numeric" maxlength="7" placeholder="123456" autocomplete="one-time-code"
                    class="w-full rounded-lg border-gray-300 focus:border-brand focus:ring-brand text-sm tracking-widest" />
                <x-input-error :messages="$errors->get('code')" class="mt-1.5" />
                <div class="flex gap-3 mt-3">
                    <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2 rounded-lg">{{ __('Confirm and enable') }}</button>
                    <button type="button" wire:click="cancelSetup" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2 rounded-lg">{{ __('Cancel') }}</button>
                </div>
            </form>
        </div>
    @else
        <div class="mt-6 flex items-center gap-2 text-sm font-semibold text-gray-500">
            <span class="w-2 h-2 rounded-full bg-gray-300"></span>
            {{ __('Not enabled') }}
        </div>
        <button type="button" wire:click="enable" class="mt-4 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Enable 2FA') }}</button>
    @endif
</section>
