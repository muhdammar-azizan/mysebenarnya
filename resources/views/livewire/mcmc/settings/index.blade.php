<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.mcmc')] class extends Component
{
    public string $tab = 'account';

    /** @var array<string, bool> */
    public array $prefs = [];

    public bool $prefsSaved = false;

    public function mount(): void
    {
        $user = Auth::user();

        foreach (User::NOTIFICATION_PREFERENCE_KEYS as $key) {
            $this->prefs[$key] = $user->wantsNotification($key);
        }
    }

    public function savePreferences(): void
    {
        Auth::user()->update(['notification_preferences' => $this->prefs]);

        $this->prefsSaved = true;
    }

    public function getPreferenceRowsProperty(): array
    {
        return [
            ['key' => 'newInquiry', 'label' => __('New inquiry needs triage'), 'desc' => __('Get notified when a new public inquiry is submitted and awaiting review')],
            ['key' => 'agencyRejects', 'label' => __('Agency rejects an inquiry'), 'desc' => __('Get notified when an agency returns an inquiry for reassignment')],
            ['key' => 'agencyResolves', 'label' => __('Agency resolves an inquiry'), 'desc' => __('Get notified when an assigned agency finalizes a verdict')],
            ['key' => 'newAgency', 'label' => __('New agency registration'), 'desc' => __('Get notified when a new agency is registered in the system')],
        ];
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('Account Settings') }}</h1>
    <p class="text-gray-500 text-sm mb-6">{{ __('Manage your account information, security, and notification preferences.') }}</p>

    <div class="flex gap-6 items-start">
        <div class="w-52 flex-shrink-0 flex flex-col gap-1">
            <button type="button" wire:click="$set('tab', 'account')" class="text-left px-3.5 py-2.5 rounded-lg text-sm font-semibold {{ $tab === 'account' ? 'bg-brand-light text-brand' : 'text-gray-600 hover:bg-gray-100' }}">
                {{ __('Account Information') }}
            </button>
            <button type="button" wire:click="$set('tab', 'security')" class="text-left px-3.5 py-2.5 rounded-lg text-sm font-semibold {{ $tab === 'security' ? 'bg-brand-light text-brand' : 'text-gray-600 hover:bg-gray-100' }}">
                {{ __('Password & Security') }}
            </button>
            <button type="button" wire:click="$set('tab', 'notifications')" class="text-left px-3.5 py-2.5 rounded-lg text-sm font-semibold {{ $tab === 'notifications' ? 'bg-brand-light text-brand' : 'text-gray-600 hover:bg-gray-100' }}">
                {{ __('Notification Preferences') }}
            </button>
        </div>

        <div class="flex-1 min-w-0 flex flex-col gap-4">
            @if ($tab === 'account')
                <livewire:profile.update-profile-photo-form />
                <livewire:profile.update-profile-information-form />
            @elseif ($tab === 'security')
                <livewire:profile.update-password-form />
            @else
                @if ($prefsSaved)
                    <div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm font-semibold">
                        {{ __('✓ Notification preferences saved!') }}
                    </div>
                @endif
                <div class="bg-white border border-gray-100 rounded-2xl p-6 flex flex-col gap-5">
                    <div class="font-bold text-gray-900">{{ __('Notification Preferences') }}</div>
                    @foreach ($this->preferenceRows as $row)
                        <div class="flex items-center justify-between gap-4 pb-4 border-b border-gray-50 last:border-0 last:pb-0">
                            <div>
                                <div class="text-sm font-semibold text-gray-900 mb-0.5">{{ $row['label'] }}</div>
                                <div class="text-xs text-gray-500">{{ $row['desc'] }}</div>
                            </div>
                            <button type="button" wire:click="$set('prefs.{{ $row['key'] }}', {{ $prefs[$row['key']] ? 'false' : 'true' }})"
                                class="w-11 h-6 rounded-full flex-shrink-0 relative transition-colors {{ $prefs[$row['key']] ? 'bg-green-600' : 'bg-gray-300' }}">
                                <span class="w-[18px] h-[18px] rounded-full bg-white absolute top-[3px] transition-all {{ $prefs[$row['key']] ? 'left-[23px]' : 'left-[3px]' }}"></span>
                            </button>
                        </div>
                    @endforeach
                    <button wire:click="savePreferences" class="self-start bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">
                        {{ __('Save Preferences') }}
                    </button>
                </div>
            @endif
        </div>
    </div>
</div>
