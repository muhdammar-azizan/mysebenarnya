<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    /** @var array<string, bool> */
    public array $prefs = [];

    public bool $saved = false;

    public function mount(): void
    {
        $user = Auth::user();

        foreach ($user->notificationPreferenceKeys() as $key) {
            $this->prefs[$key] = $user->wantsNotification($key);
        }
    }

    public function toggle(string $key): void
    {
        if (! array_key_exists($key, $this->prefs)) {
            return;
        }

        $this->prefs[$key] = ! $this->prefs[$key];
        $this->saved = false;
    }

    public function save(): void
    {
        $user = Auth::user();

        // Only keys that belong to this role are written, merged over whatever is stored.
        $allowed = array_intersect_key($this->prefs, array_flip($user->notificationPreferenceKeys()));

        $user->update([
            'notification_preferences' => array_merge($user->notification_preferences ?? [], array_map('boolval', $allowed)),
        ]);

        $this->saved = true;
    }

    public function getRowsProperty(): array
    {
        $user = Auth::user();

        $all = [
            'statusChanged' => [__('Inquiry status updates'), __('Get notified when the status of one of your inquiries changes')],
            'inquiryAssigned' => [__('New inquiry assigned'), __('Get notified when MCMC assigns an inquiry to your agency')],
            'consultRequested' => [__('Consultation requested'), __('Get notified when MCMC asks your agency for input on a clarification')],
        ];

        return collect($user->notificationPreferenceKeys())
            ->filter(fn ($key) => isset($all[$key]))
            ->map(fn ($key) => ['key' => $key, 'label' => $all[$key][0], 'desc' => $all[$key][1]])
            ->values()
            ->all();
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">{{ __('Notification Preferences') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('Choose which in-app notifications you want to receive.') }}</p>
    </header>

    @if ($saved)
        <div class="mt-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm font-semibold">
            {{ __('Notification preferences saved!') }}
        </div>
    @endif

    <div class="mt-6 flex flex-col gap-5">
        @foreach ($this->rows as $row)
            <div class="flex items-center justify-between gap-4 pb-4 border-b border-gray-50 last:border-0 last:pb-0">
                <div>
                    <div class="text-sm font-semibold text-gray-900 mb-0.5">{{ $row['label'] }}</div>
                    <div class="text-xs text-gray-500">{{ $row['desc'] }}</div>
                </div>
                <button type="button" wire:click="toggle('{{ $row['key'] }}')" role="switch" aria-checked="{{ $prefs[$row['key']] ? 'true' : 'false' }}"
                    class="w-11 h-6 rounded-full flex-shrink-0 relative transition-colors {{ $prefs[$row['key']] ? 'bg-green-600' : 'bg-gray-300' }}">
                    <span class="w-[18px] h-[18px] rounded-full bg-white absolute top-[3px] transition-all {{ $prefs[$row['key']] ? 'left-[23px]' : 'left-[3px]' }}"></span>
                </button>
            </div>
        @endforeach
    </div>

    <button wire:click="save" type="button" class="mt-6 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">
        {{ __('Save Preferences') }}
    </button>
</section>
