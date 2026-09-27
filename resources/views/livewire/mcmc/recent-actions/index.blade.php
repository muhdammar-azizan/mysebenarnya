<?php

use App\Models\InquiryActivityLog;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.mcmc')] class extends Component
{
    use WithPagination;

    protected const ACTION_BADGES = [
        'assigned' => ['bg' => 'bg-blue-100', 'color' => 'text-blue-700'],
        'reassigned' => ['bg' => 'bg-blue-100', 'color' => 'text-blue-700'],
        'discarded' => ['bg' => 'bg-gray-100', 'color' => 'text-gray-600'],
        'verdict_finalized' => ['bg' => 'bg-green-100', 'color' => 'text-green-700'],
    ];

    public function getRowsProperty()
    {
        return InquiryActivityLog::with('inquiry')
            ->where('user_id', Auth::id())
            ->latest()
            ->paginate(15);
    }

    public function badgeFor(string $action): array
    {
        return self::ACTION_BADGES[$action] ?? ['bg' => 'bg-gray-100', 'color' => 'text-gray-600'];
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('My Recent Actions') }}</h1>
    <p class="text-gray-500 text-sm mb-6">{{ __("A personal log of triage and assignment decisions you've made recently.") }}</p>

    <div class="bg-white border border-gray-100 rounded-lg overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-[11.5px] font-bold text-gray-400 uppercase tracking-wide border-b border-gray-100">
                    <th class="px-5 py-3">{{ __('Inquiry Title') }}</th>
                    <th class="px-5 py-3">{{ __('Action Taken') }}</th>
                    <th class="px-5 py-3">{{ __('Date/Time') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->rows as $log)
                    @php $badge = $this->badgeFor($log->action); @endphp
                    <tr wire:key="action-{{ $log->id }}" class="border-t border-gray-50">
                        <td class="px-5 py-3.5 font-semibold text-gray-900 truncate max-w-xs">{{ $log->inquiry?->title }}</td>
                        <td class="px-5 py-3.5">
                            <span class="inline-block px-2.5 py-1 rounded-full text-xs font-bold {{ $badge['bg'] }} {{ $badge['color'] }}">{{ str($log->action)->headline() }}</span>
                        </td>
                        <td class="px-5 py-3.5 text-gray-500 whitespace-nowrap">{{ $log->created_at->format('d M Y, g:i A') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-5 py-10 text-center text-gray-400">{{ __("You haven't taken any actions yet.") }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $this->rows->links() }}</div>
</div>
