@php
    $items = [
        ['route' => 'dashboard', 'icon' => 'dashboard', 'label' => __('Dashboard')],
        ['route' => 'mcmc.triage.index', 'icon' => 'inbox', 'label' => __('New Inquiries / Triage'), 'excludeTabs' => ['assigned']],
        ['route' => 'mcmc.triage.index', 'icon' => 'share', 'label' => __('Assign to Agency'), 'query' => ['tab' => 'assigned'], 'requireTab' => 'assigned'],
        ['route' => 'mcmc.inquiries.index', 'icon' => 'folder', 'label' => __('All Inquiries')],
        ['route' => 'mcmc.agencies.index', 'icon' => 'building', 'label' => __('Agency Management')],
        ['route' => 'mcmc.users.index', 'icon' => 'users', 'label' => __('Registered Users')],
        ['route' => 'mcmc.reports.index', 'icon' => 'chart', 'label' => __('Reports')],
    ];

    $bottomItems = [
        ['route' => 'mcmc.settings.index', 'icon' => 'settings', 'label' => __('Settings')],
        ['route' => 'mcmc.recent-actions.index', 'icon' => 'history', 'label' => __('My Recent Actions')],
    ];
@endphp

<nav class="w-[216px] flex-shrink-0 bg-white border-r border-gray-200 flex flex-col overflow-y-auto pt-4">
    <div class="px-4 pb-2 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ __('Menu') }}</div>
    <div class="px-2.5 flex flex-col gap-0.5">
        @foreach ($items as $item)
            @php
                $onRoute = request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*');
                $currentTab = request()->query('tab');
                $active = $onRoute
                    && (empty($item['requireTab']) || $currentTab === $item['requireTab'])
                    && (empty($item['excludeTabs']) || ! in_array($currentTab, $item['excludeTabs'], true));
            @endphp
            <a href="{{ route($item['route'], $item['query'] ?? []) }}" wire:navigate
                class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold {{ $active ? 'bg-brand text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                <x-icon :name="$item['icon']" class="w-5 h-5 flex-shrink-0" />
                <span class="flex-1">{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="mt-auto">
        <div class="px-2.5 pt-2 border-t border-gray-100 flex flex-col gap-0.5">
            @foreach ($bottomItems as $item)
                @php $active = request()->routeIs($item['route']); @endphp
                <a href="{{ route($item['route']) }}" wire:navigate
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-semibold {{ $active ? 'bg-brand text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                    <x-icon :name="$item['icon']" class="w-5 h-5 flex-shrink-0" />
                    <span class="flex-1">{{ $item['label'] }}</span>
                </a>
            @endforeach
        </div>

        <livewire:mcmc.monthly-target-widget />
    </div>
</nav>
