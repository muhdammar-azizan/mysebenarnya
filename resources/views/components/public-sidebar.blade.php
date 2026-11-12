@php
    $items = [
        ['route' => 'dashboard', 'icon' => 'home', 'label' => __('Home')],
        ['route' => 'inquiries.create', 'icon' => 'plus-circle', 'label' => __('Submit Inquiry')],
        ['route' => 'inquiries.index', 'icon' => 'folder', 'label' => __('My Inquiries')],
        ['route' => 'profile', 'icon' => 'user', 'label' => __('Profile Settings')],
    ];
@endphp

<nav class="w-[216px] flex-shrink-0 bg-white border-r border-gray-200 flex flex-col overflow-y-auto pt-4">
    <div class="px-4 pb-2 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ __('Menu') }}</div>
    <div class="px-2.5 flex flex-col gap-0.5">
        @foreach ($items as $item)
            @php $active = request()->routeIs($item['route']); @endphp
            <a href="{{ route($item['route']) }}" wire:navigate
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold {{ $active ? 'bg-brand text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                <x-icon :name="$item['icon']" class="w-5 h-5 flex-shrink-0" />
                <span class="flex-1">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
