{{-- Reminder shown to shared demo accounts next to anything that accepts uploads. --}}
@if (auth()->user()?->isDemo())
    <div {{ $attributes->merge(['class' => 'bg-amber-50 border border-amber-100 text-amber-800 text-sm rounded-xl px-4 py-3']) }} role="note">
        <strong>{{ __('This is a demo system.') }}</strong>
        {{ __('Everything you enter or upload is visible to other demo visitors and may be deleted at any time. Please do not upload personal or sensitive files. Use sample images or documents instead.') }}
    </div>
@endif
