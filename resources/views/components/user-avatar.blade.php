@props(['user' => null])

@php
    $user ??= auth()->user();
    // Agency staff without a personal photo show their agency's logo (the
    // agency's profile picture) before falling back to the placeholder icon.
    $photoPath = $user?->profile_photo_path ?: $user?->agency?->logo_path;
@endphp

@if ($photoPath)
    <img src="{{ asset('storage/'.$photoPath) }}" alt="{{ $user->name }}"
        {{ $attributes->merge(['class' => 'w-9 h-9 rounded-full object-cover bg-white flex-shrink-0']) }}>
@else
    <span {{ $attributes->merge(['class' => 'w-9 h-9 rounded-full bg-white text-brand flex items-center justify-center flex-shrink-0 overflow-hidden']) }}>
        <x-icon name="user" class="w-5 h-5" />
    </span>
@endif
