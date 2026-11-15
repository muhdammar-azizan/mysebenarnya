@props(['tone' => 'light'])

@php
    $current = app()->getLocale();
    $wrap = $tone === 'dark' ? 'bg-white/15' : 'bg-gray-100';
    $on = $tone === 'dark' ? 'bg-white text-brand' : 'bg-brand text-white';
    $off = $tone === 'dark' ? 'text-white/80 hover:text-white' : 'text-gray-500 hover:text-gray-800';
@endphp

<div {{ $attributes->merge(['class' => "inline-flex items-center rounded-lg p-0.5 text-xs font-bold {$wrap}"]) }} role="group" aria-label="{{ __('Language') }}">
    @foreach (config('app.available_locales') as $code => $label)
        <form method="POST" action="{{ route('locale.update', $code) }}">
            @csrf
            <button type="submit" @if ($current === $code) aria-current="true" @endif
                class="px-2.5 py-1.5 rounded-md {{ $current === $code ? $on : $off }}">{{ $label }}</button>
        </form>
    @endforeach
</div>
