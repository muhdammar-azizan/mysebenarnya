@php
    $items = [
        ['route' => 'dashboard', 'label' => __('Home')],
        ['route' => 'inquiries.create', 'label' => __('Submit Inquiry')],
        ['route' => 'inquiries.index', 'label' => __('My Inquiries')],
        ['route' => 'profile', 'label' => __('Profile Settings')],
    ];
@endphp

<nav class="w-56 flex-shrink-0 bg-white rounded-2xl shadow-sm overflow-auto py-4">
    <div class="px-4 pb-2 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ __('Menu') }}</div>
    <div class="px-2.5 flex flex-col gap-0.5">
        @foreach ($items as $item)
            @php $active = request()->routeIs($item['route']); @endphp
            <a href="{{ route($item['route']) }}" wire:navigate
                class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm font-semibold {{ $active ? 'bg-brand text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                <span class="w-1.5 h-1.5 rounded-sm {{ $active ? 'bg-white' : 'bg-gray-400' }}"></span>
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</nav>
