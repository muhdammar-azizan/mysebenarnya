@props(['user' => null])

@php
    $user ??= auth()->user();
@endphp

@if ($user?->profile_photo_path)
    <img src="{{ asset('storage/'.$user->profile_photo_path) }}" alt="{{ $user->name }}"
        {{ $attributes->merge(['class' => 'w-9 h-9 rounded-full object-cover bg-white flex-shrink-0']) }}>
@else
    <span {{ $attributes->merge(['class' => 'w-9 h-9 rounded-full bg-white text-brand flex items-center justify-center flex-shrink-0 overflow-hidden']) }}>
        <x-icon name="user" class="w-5 h-5" />
    </span>
@endif
