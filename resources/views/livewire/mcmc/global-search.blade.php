<?php

use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\User;
use Livewire\Volt\Component;

new class extends Component
{
    public string $query = '';

    protected function term(): string
    {
        return trim($this->query);
    }

    public function getResultsProperty(): array
    {
        $term = $this->term();

        if (mb_strlen($term) < 2) {
            return ['inquiries' => collect(), 'agencies' => collect(), 'users' => collect()];
        }

        $like = '%'.$term.'%';

        return [
            'inquiries' => Inquiry::query()
                ->where(fn ($q) => $q->where('title', 'like', $like)->orWhere('reference_no', 'like', $like))
                ->latest()
                ->limit(5)
                ->get(['id', 'reference_no', 'title', 'status']),

            'agencies' => Agency::query()
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like))
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'code']),

            // Only public accounts have a page MCMC can open (Registered Users).
            'users' => User::query()
                ->where('role', UserRole::Public)
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'email']),
        ];
    }

    public function getHasResultsProperty(): bool
    {
        return collect($this->results)->contains(fn ($group) => $group->isNotEmpty());
    }

    public function searchAll(): void
    {
        $term = $this->term();

        if ($term === '') {
            return;
        }

        $this->redirect(route('mcmc.inquiries.index', ['search' => $term]), navigate: true);
    }
}; ?>

<div
    x-data="{
        open: false,
        show() { this.open = true; this.$nextTick(() => this.$refs.q.focus()); },
        hide() { this.open = false; },
        move(step) {
            const items = Array.from(this.$refs.panel.querySelectorAll('[data-result]'));
            if (!items.length) return;
            const at = items.indexOf(document.activeElement);
            const next = at === -1 ? (step > 0 ? 0 : items.length - 1) : at + step;
            if (next < 0) { this.$refs.q.focus(); return; }
            items[Math.min(next, items.length - 1)].focus();
        },
    }"
    @keydown.window="if (($event.ctrlKey || $event.metaKey) && $event.key.toLowerCase() === 'k') { $event.preventDefault(); show(); }"
    @keydown.escape.window="hide()"
>
    {{-- Header triggers --}}
    <button type="button" @click="show()" class="hidden md:flex items-center gap-2 w-64 h-9 px-3 rounded-lg bg-white text-left text-sm text-gray-400 hover:ring-2 hover:ring-white/50">
        <x-icon name="search" class="w-4 h-4 flex-shrink-0" />
        <span class="flex-1 truncate">{{ __('Search inquiries, agencies...') }}</span>
        <kbd class="hidden lg:inline text-[10px] font-semibold text-gray-400 border border-gray-200 rounded px-1.5 py-0.5">Ctrl K</kbd>
    </button>
    <button type="button" @click="show()" class="md:hidden w-9 h-9 flex-shrink-0 rounded-lg bg-white/15 hover:bg-white/25 text-white flex items-center justify-center" aria-label="{{ __('Search') }}">
        <x-icon name="search" class="w-[18px] h-[18px]" />
    </button>

    {{-- Search palette --}}
    <div x-show="open" x-cloak x-transition.opacity class="fixed inset-0 z-50 bg-black/40 flex items-start justify-center px-3 sm:px-6 pt-[10vh]" @click.self="hide()">
        <div x-ref="panel" class="w-full max-w-xl bg-white rounded-2xl shadow-2xl overflow-hidden" @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)">
            <form wire:submit="searchAll" class="flex items-center gap-3 px-4 border-b border-gray-100">
                <x-icon name="search" class="w-5 h-5 text-gray-400 flex-shrink-0" />
                <input x-ref="q" type="text" wire:model.live.debounce.250ms="query" autocomplete="off"
                    placeholder="{{ __('Search inquiries, agencies, users...') }}"
                    class="flex-1 h-14 border-0 focus:ring-0 text-sm text-gray-900 placeholder-gray-400" />
                <kbd class="text-[10px] font-semibold text-gray-400 border border-gray-200 rounded px-1.5 py-0.5">Esc</kbd>
            </form>

            <div class="max-h-[60vh] overflow-y-auto">
                @if (mb_strlen(trim($query)) < 2)
                    <p class="px-5 py-8 text-center text-sm text-gray-400">{{ __('Type at least 2 characters to search.') }}</p>
                @elseif (! $this->hasResults)
                    <p class="px-5 py-8 text-center text-sm text-gray-400">{{ __('No results for ":q".', ['q' => trim($query)]) }}</p>
                @else
                    @if ($this->results['inquiries']->isNotEmpty())
                        <div class="px-5 pt-4 pb-1 text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Inquiries') }}</div>
                        @foreach ($this->results['inquiries'] as $inquiry)
                            <a data-result href="{{ route('mcmc.inquiries.show', $inquiry) }}" wire:navigate wire:key="gs-i-{{ $inquiry->id }}"
                                class="flex items-center justify-between gap-3 px-5 py-2.5 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-gray-900 truncate">{{ $inquiry->title }}</span>
                                    <span class="block text-xs text-gray-400">{{ $inquiry->reference_no }}</span>
                                </span>
                                <x-inquiry-status-badge :status="$inquiry->status" />
                            </a>
                        @endforeach
                    @endif

                    @if ($this->results['agencies']->isNotEmpty())
                        <div class="px-5 pt-4 pb-1 text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Agencies') }}</div>
                        @foreach ($this->results['agencies'] as $agency)
                            <a data-result href="{{ route('mcmc.agencies.edit', $agency) }}" wire:navigate wire:key="gs-a-{{ $agency->id }}"
                                class="flex items-center justify-between gap-3 px-5 py-2.5 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                <span class="text-sm font-semibold text-gray-900 truncate">{{ $agency->name }}</span>
                                <span class="text-xs text-gray-400">{{ $agency->code }}</span>
                            </a>
                        @endforeach
                    @endif

                    @if ($this->results['users']->isNotEmpty())
                        <div class="px-5 pt-4 pb-1 text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Registered Users') }}</div>
                        @foreach ($this->results['users'] as $user)
                            <a data-result href="{{ route('mcmc.users.index', ['search' => $user->email]) }}" wire:navigate wire:key="gs-u-{{ $user->id }}"
                                class="flex items-center justify-between gap-3 px-5 py-2.5 hover:bg-gray-50 focus:bg-gray-50 focus:outline-none">
                                <span class="text-sm font-semibold text-gray-900 truncate">{{ $user->name }}</span>
                                <span class="text-xs text-gray-400 truncate">{{ $user->email }}</span>
                            </a>
                        @endforeach
                    @endif
                @endif

                @if (trim($query) !== '')
                    <button type="button" wire:click="searchAll" class="w-full text-left px-5 py-3 border-t border-gray-100 text-xs font-semibold text-brand hover:bg-gray-50">
                        {{ __('Press Enter to search all inquiries for ":q"', ['q' => trim($query)]) }} &rarr;
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
