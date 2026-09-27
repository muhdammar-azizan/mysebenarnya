@php
    $items = [
        ['route' => 'dashboard', 'label' => __('Dashboard')],
        ['route' => 'mcmc.triage.index', 'label' => __('New Inquiries / Triage'), 'excludeTabs' => ['assigned']],
        ['route' => 'mcmc.triage.index', 'label' => __('Assign to Agency'), 'query' => ['tab' => 'assigned'], 'requireTab' => 'assigned'],
        ['route' => 'mcmc.inquiries.index', 'label' => __('All Inquiries')],
        ['route' => 'mcmc.agencies.index', 'label' => __('Agency Management')],
        ['route' => 'mcmc.users.index', 'label' => __('Registered Users')],
        ['route' => 'mcmc.reports.index', 'label' => __('Reports')],
    ];
@endphp

<nav class="w-56 flex-shrink-0 bg-white rounded-2xl shadow-sm overflow-auto py-4">
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
                class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm font-semibold {{ $active ? 'bg-brand text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                <span class="w-1.5 h-1.5 rounded-sm {{ $active ? 'bg-white' : 'bg-gray-400' }}"></span>
                <span class="flex-1">{{ $item['label'] }}</span>
                @if (! empty($item['badge']))
                    <span class="text-[10px] font-bold rounded-full px-1.5 {{ $active ? 'bg-white text-brand' : 'bg-brand text-white' }}">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <div class="px-2.5 mt-2 pt-2 border-t border-gray-100 flex flex-col gap-0.5">
        <a href="{{ route('mcmc.settings.index') }}" wire:navigate
            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm font-semibold {{ request()->routeIs('mcmc.settings.index') ? 'bg-brand text-white' : 'text-gray-600 hover:bg-gray-100' }}">
            <span class="w-1.5 h-1.5 rounded-sm {{ request()->routeIs('mcmc.settings.index') ? 'bg-white' : 'bg-gray-400' }}"></span>
            <span class="flex-1">{{ __('Settings') }}</span>
        </a>
        <a href="{{ route('mcmc.recent-actions.index') }}" wire:navigate
            class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm font-semibold {{ request()->routeIs('mcmc.recent-actions.index') ? 'bg-brand text-white' : 'text-gray-600 hover:bg-gray-100' }}">
            <span class="w-1.5 h-1.5 rounded-sm {{ request()->routeIs('mcmc.recent-actions.index') ? 'bg-white' : 'bg-gray-400' }}"></span>
            <span class="flex-1">{{ __('My Recent Actions') }}</span>
        </a>
    </div>

    <livewire:mcmc.monthly-target-widget />
</nav>
