@php
    $unreadClarifications = \App\Models\ClarificationThread::where('unread_by_mcmc', true)->count();

    $items = [
        ['route' => 'dashboard', 'label' => __('Dashboard')],
        ['route' => 'mcmc.triage.index', 'label' => __('New Inquiries / Triage')],
        ['route' => 'mcmc.inquiries.index', 'label' => __('All Inquiries')],
        ['route' => 'mcmc.clarifications.index', 'label' => __('Clarifications'), 'badge' => $unreadClarifications],
        ['route' => 'mcmc.agencies.index', 'label' => __('Agency Management')],
        ['route' => 'mcmc.users.index', 'label' => __('Registered Users')],
        ['route' => 'mcmc.reports.index', 'label' => __('Reports')],
    ];
@endphp

<nav class="w-56 flex-shrink-0 bg-white rounded-2xl shadow-sm overflow-auto py-4">
    <div class="px-4 pb-2 text-xs font-bold text-gray-400 uppercase tracking-wider">{{ __('Menu') }}</div>
    <div class="px-2.5 flex flex-col gap-0.5">
        @foreach ($items as $item)
            @php $active = request()->routeIs($item['route']) || request()->routeIs($item['route'].'.*'); @endphp
            <a href="{{ route($item['route']) }}" wire:navigate
                class="flex items-center gap-2.5 px-2.5 py-2 rounded-lg text-sm font-semibold {{ $active ? 'bg-brand text-white' : 'text-gray-600 hover:bg-gray-100' }}">
                <span class="w-1.5 h-1.5 rounded-sm {{ $active ? 'bg-white' : 'bg-gray-400' }}"></span>
                <span class="flex-1">{{ $item['label'] }}</span>
                @if (! empty($item['badge']))
                    <span class="text-[10px] font-bold rounded-full px-1.5 {{ $active ? 'bg-white text-brand' : 'bg-brand text-white' }}">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </div>
</nav>
