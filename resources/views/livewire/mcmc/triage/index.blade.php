<?php

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\Agency;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Notifications\InquiryStatusChanged;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.mcmc')] class extends Component
{
    #[Url]
    public string $tab = 'pending';

    #[Url]
    public string $screen = 'list';

    #[Url]
    public ?int $currentId = null;

    #[Url]
    public string $search = '';

    #[Url]
    public string $category = 'All';

    #[Url]
    public string $dateRange = 'all';

    public array $selectedIds = [];

    public bool $discardModalOpen = false;

    public bool $bulkDiscardModalOpen = false;

    public string $discardNotes = '';

    public ?int $selectedAgencyId = null;

    public string $assignNotes = '';

    public string $reviewNotes = '';

    public function openReview(int $id): void
    {
        $this->currentId = $id;
        $this->reviewNotes = '';
        $this->screen = 'review';
    }

    public function validateAndAssign(int $id): void
    {
        $carriedNotes = $this->reviewNotes;
        $this->openAssign($id);
        $this->assignNotes = $carriedNotes;
    }

    public function openDiscardModal(): void
    {
        $this->discardNotes = $this->reviewNotes;
        $this->discardModalOpen = true;
    }

    public function openAssign(int $id): void
    {
        $this->currentId = $id;
        $this->selectedAgencyId = null;
        $this->assignNotes = '';
        $this->screen = 'assign';
    }

    public function backToList(): void
    {
        $this->screen = 'list';
        $this->currentId = null;
    }

    protected function applyFilters($query)
    {
        return $query
            ->when($this->search, fn ($q) => $q->where('title', 'like', '%'.$this->search.'%'))
            ->when($this->category !== 'All', fn ($q) => $q->where('category', $this->category))
            ->when($this->dateRange === '7days', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))
            ->when($this->dateRange === '30days', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)));
    }

    public function getCategoriesProperty(): array
    {
        return InquiryCategory::cases();
    }

    public function toggleSelect(int $id): void
    {
        if (in_array($id, $this->selectedIds, true)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, [$id]));
        } else {
            $this->selectedIds[] = $id;
        }
    }

    public function toggleSelectAll(): void
    {
        $ids = $this->pendingRows->pluck('id')->all();
        $allSelected = count($ids) > 0 && count(array_diff($ids, $this->selectedIds)) === 0;
        $this->selectedIds = $allSelected ? [] : array_values(array_unique(array_merge($this->selectedIds, $ids)));
    }

    public function confirmBulkDiscard(): void
    {
        foreach (Inquiry::whereIn('id', $this->selectedIds)->get() as $inquiry) {
            $this->discardInquiry($inquiry, 'Discarded via bulk action during triage.');
        }

        $this->selectedIds = [];
        $this->bulkDiscardModalOpen = false;

        session()->flash('status', __('Selected inquiries discarded.'));
    }

    public function confirmDiscard(): void
    {
        $inquiry = Inquiry::findOrFail($this->currentId);
        $this->authorize('triage', $inquiry);

        $this->discardInquiry($inquiry, $this->discardNotes ?: 'Discarded during triage — not a serious or credible claim.');

        $this->discardModalOpen = false;
        $this->discardNotes = '';
        $this->backToList();

        session()->flash('status', __('Inquiry discarded.'));
    }

    protected function discardInquiry(Inquiry $inquiry, string $notes): void
    {
        $fromStatus = $inquiry->status->value;

        $inquiry->update([
            'status' => InquiryStatus::Discarded,
            'reviewed_by' => Auth::id(),
            'reviewed_at' => now(),
        ]);

        InquiryActivityLog::create([
            'inquiry_id' => $inquiry->id,
            'user_id' => Auth::id(),
            'action' => 'discarded',
            'from_status' => $fromStatus,
            'to_status' => InquiryStatus::Discarded->value,
            'notes' => $notes,
        ]);

        $inquiry->submitter?->notify(new InquiryStatusChanged($inquiry, $fromStatus, InquiryStatus::Discarded->value));
    }

    public function confirmAssign(): void
    {
        $inquiry = Inquiry::findOrFail($this->currentId);
        $this->authorize('assign', $inquiry);

        $this->validate([
            'selectedAgencyId' => 'required|exists:agencies,id',
        ]);

        $wasReassignment = $inquiry->agency_id !== null;
        $fromStatus = $inquiry->status->value;
        $agency = Agency::findOrFail($this->selectedAgencyId);

        $inquiry->update([
            'agency_id' => $agency->id,
            'status' => InquiryStatus::UnderInvestigation,
            'jurisdiction_accepted_at' => null,
            'reviewed_by' => $inquiry->reviewed_by ?? Auth::id(),
            'reviewed_at' => $inquiry->reviewed_at ?? now(),
            'resolution_notes' => null,
        ]);

        InquiryActivityLog::create([
            'inquiry_id' => $inquiry->id,
            'user_id' => Auth::id(),
            'action' => $wasReassignment ? 'reassigned' : 'assigned',
            'from_status' => $fromStatus,
            'to_status' => InquiryStatus::UnderInvestigation->value,
            'notes' => ($this->assignNotes ?: 'Assigned to '.$agency->name.' for review.'),
        ]);

        if (! $wasReassignment) {
            $inquiry->submitter?->notify(new InquiryStatusChanged($inquiry, $fromStatus, InquiryStatus::UnderInvestigation->value));
        }

        $this->backToList();
        $this->tab = $wasReassignment ? 'reassign' : 'pending';

        session()->flash('status', __('Inquiry assigned to :agency.', ['agency' => $agency->name]));
    }

    public function getPendingRowsProperty()
    {
        return $this->applyFilters(Inquiry::where('status', InquiryStatus::Submitted)->with('submitter')->withCount('evidence'))->latest()->get();
    }

    public function getReassignRowsProperty()
    {
        return $this->applyFilters(Inquiry::where('status', InquiryStatus::Rejected)->with(['submitter', 'agency']))->latest()->get();
    }

    public function getAssignedRowsProperty()
    {
        return $this->applyFilters(Inquiry::where('status', InquiryStatus::UnderInvestigation)->with('agency'))->latest('reviewed_at')->get();
    }

    public function getReviewedRowsProperty()
    {
        return InquiryActivityLog::with(['inquiry', 'user'])
            ->whereIn('action', ['assigned', 'discarded', 'reassigned'])
            ->whereHas('user', fn ($q) => $q->where('role', 'mcmc_staff'))
            ->latest()
            ->limit(30)
            ->get();
    }

    public function getCurrentInquiryProperty(): ?Inquiry
    {
        return $this->currentId ? Inquiry::with(['submitter', 'evidence'])->find($this->currentId) : null;
    }

    public function getAgenciesProperty()
    {
        return Agency::where('is_active', true)
            ->withCount(['inquiries as active_case_count' => fn ($q) => $q->where('status', InquiryStatus::UnderInvestigation)])
            ->orderBy('name')
            ->get();
    }

    public function categoryChip(?InquiryCategory $category): array
    {
        return match ($category) {
            InquiryCategory::HealthMedical => ['bg' => 'bg-brand-light', 'color' => 'text-brand', 'code' => 'HM'],
            InquiryCategory::FinancialScams => ['bg' => 'bg-teal-50', 'color' => 'text-teal-700', 'code' => 'FS'],
            InquiryCategory::ElectoralPolitical => ['bg' => 'bg-purple-50', 'color' => 'text-purple-700', 'code' => 'EP'],
            InquiryCategory::ConsumerRights => ['bg' => 'bg-blue-50', 'color' => 'text-blue-700', 'code' => 'CR'],
            InquiryCategory::CriminalFraud => ['bg' => 'bg-gray-100', 'color' => 'text-gray-600', 'code' => 'CF'],
            InquiryCategory::DisasterEmergency => ['bg' => 'bg-amber-50', 'color' => 'text-amber-700', 'code' => 'DE'],
            InquiryCategory::TechnologyDigital => ['bg' => 'bg-green-50', 'color' => 'text-green-700', 'code' => 'TD'],
            default => ['bg' => 'bg-gray-100', 'color' => 'text-gray-500', 'code' => 'OT'],
        };
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900 mb-1">{{ __('New Inquiries / Triage') }}</h1>
    <p class="text-gray-500 text-sm mb-6">{{ __('Validate credible reports for agency assignment, or discard non-serious submissions.') }}</p>

    @if (session('status'))
        <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 font-semibold text-sm mb-5">✓ {{ session('status') }}</div>
    @endif

    @if ($screen === 'list')
        <div class="flex border-b border-gray-100 mb-5">
            <button wire:click="$set('tab', 'pending')" class="px-5 py-3 text-sm font-bold {{ $tab === 'pending' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">
                {{ __('Pending Triage') }} ({{ $this->pendingRows->count() }})
            </button>
            <button wire:click="$set('tab', 'reassign')" class="px-5 py-3 text-sm font-bold {{ $tab === 'reassign' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-400' }}">
                {{ __('Needs Reassignment') }} ({{ $this->reassignRows->count() }})
            </button>
            <button wire:click="$set('tab', 'reviewed')" class="px-5 py-3 text-sm font-bold {{ $tab === 'reviewed' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">
                {{ __('Reviewed History') }}
            </button>
            <button wire:click="$set('tab', 'assigned')" class="px-5 py-3 text-sm font-bold {{ $tab === 'assigned' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">
                {{ __('Assigned to Agency') }} ({{ $this->assignedRows->count() }})
            </button>
        </div>

        @if ($tab !== 'reviewed')
            <div class="flex flex-wrap gap-3 mb-5">
                <input type="text" wire:model.live.debounce.400ms="search" placeholder="{{ __('Search by title...') }}"
                    class="flex-1 min-w-[200px] rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
                <select wire:model.live="category" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                    <option value="All">{{ __('All Categories') }}</option>
                    @foreach ($this->categories as $case)
                        <option value="{{ $case->value }}">{{ $case->value }}</option>
                    @endforeach
                </select>
                <select wire:model.live="dateRange" class="rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                    <option value="all">{{ __('All Time') }}</option>
                    <option value="7days">{{ __('Last 7 Days') }}</option>
                    <option value="30days">{{ __('Last 30 Days') }}</option>
                </select>
            </div>
        @endif

        @if ($tab === 'pending')
            @if (count($selectedIds) > 0)
                <div class="flex items-center justify-between bg-brand-light border border-red-100 rounded-lg px-4 py-3 mb-4">
                    <span class="text-sm font-bold text-brand">{{ count($selectedIds) }} {{ __('selected') }}</span>
                    <div class="flex gap-2">
                        <button wire:click="$set('bulkDiscardModalOpen', true)" class="text-sm font-bold text-brand hover:underline">{{ __('Discard Selected') }}</button>
                        <button wire:click="$set('selectedIds', [])" class="text-sm font-semibold text-gray-500 hover:underline">{{ __('Clear') }}</button>
                    </div>
                </div>
            @endif

            <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">
                            <th class="px-5 py-3 w-8"><input type="checkbox" wire:click="toggleSelectAll" @checked(count($selectedIds) > 0 && count(array_diff($this->pendingRows->pluck('id')->all(), $selectedIds)) === 0)></th>
                            <th class="px-5 py-3">{{ __('Title') }}</th>
                            <th class="px-5 py-3">{{ __('Evidence') }}</th>
                            <th class="px-5 py-3">{{ __('Submitted') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->pendingRows as $row)
                            <tr wire:key="pending-{{ $row->id }}" class="border-t border-gray-50 hover:bg-gray-50">
                                <td class="px-5 py-3.5"><input type="checkbox" wire:click="toggleSelect({{ $row->id }})" @checked(in_array($row->id, $selectedIds))></td>
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $row->title }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $row->evidence_count }} {{ __('file'.($row->evidence_count === 1 ? '' : 's')).' attached' }}</td>
                                <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap">{{ $row->created_at->format('d M Y') }}</td>
                                <td class="px-5 py-3.5 text-right">
                                    <button wire:click="openReview({{ $row->id }})" class="text-sm font-bold text-brand hover:underline">{{ __('Review') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-10 text-center text-gray-400">{{ __('No inquiries pending triage.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif ($tab === 'reassign')
            <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">
                            <th class="px-5 py-3">{{ __('Title') }}</th>
                            <th class="px-5 py-3">{{ __('Prev. Agency') }}</th>
                            <th class="px-5 py-3">{{ __('Rejection Reason') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->reassignRows as $row)
                            @php $agencyChip = $this->categoryChip($row->agency?->specialization); @endphp
                            <tr wire:key="reassign-{{ $row->id }}" class="border-t border-gray-50 hover:bg-gray-50">
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $row->title }}</td>
                                <td class="px-5 py-3.5">
                                    <span class="inline-flex items-center gap-1.5 {{ $agencyChip['bg'] }} {{ $agencyChip['color'] }} px-2.5 py-1 rounded-full text-xs font-bold">{{ $row->agency?->name ?? '—' }}</span>
                                </td>
                                <td class="px-5 py-3.5 text-gray-600">{{ str($row->resolution_notes ?? '—')->limit(60) }}</td>
                                <td class="px-5 py-3.5 text-right">
                                    <button wire:click="openAssign({{ $row->id }})" class="text-sm font-bold text-blue-600 hover:underline">{{ __('Reassign') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-gray-400">{{ __('No inquiries awaiting reassignment.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif ($tab === 'reviewed')
            <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden divide-y divide-gray-50">
                @forelse ($this->reviewedRows as $log)
                    <div class="flex items-center justify-between px-5 py-3.5">
                        <div class="min-w-0">
                            <div class="text-sm font-semibold text-gray-900 truncate">{{ $log->inquiry?->title }}</div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $log->user?->name }} &middot; {{ $log->created_at->format('d M Y, H:i') }}</div>
                        </div>
                        <span class="flex-shrink-0 px-2.5 py-1 rounded-full text-xs font-bold {{ $log->action === 'discarded' ? 'bg-gray-100 text-gray-500' : 'bg-blue-100 text-blue-700' }}">{{ str($log->action)->headline() }}</span>
                    </div>
                @empty
                    <p class="px-5 py-10 text-center text-gray-400">{{ __('No triage decisions yet.') }}</p>
                @endforelse
            </div>
        @else
            <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 text-left text-xs font-bold text-gray-500 uppercase tracking-wide">
                            <th class="px-5 py-3">{{ __('Title') }}</th>
                            <th class="px-5 py-3">{{ __('Assigned Agency') }}</th>
                            <th class="px-5 py-3">{{ __('Assigned Date') }}</th>
                            <th class="px-5 py-3">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->assignedRows as $row)
                            <tr wire:key="assigned-{{ $row->id }}" onclick="window.location='{{ route('mcmc.inquiries.show', $row) }}'" class="border-t border-gray-50 hover:bg-gray-50 cursor-pointer">
                                <td class="px-5 py-3.5 font-semibold text-gray-900">{{ $row->title }}</td>
                                <td class="px-5 py-3.5 text-gray-600">{{ $row->agency?->name ?? '—' }}</td>
                                <td class="px-5 py-3.5 text-gray-600 whitespace-nowrap">{{ $row->reviewed_at?->format('d M Y') ?? '—' }}</td>
                                <td class="px-5 py-3.5">
                                    <x-inquiry-status-badge :status="$row->status" />
                                    @if ($row->isAwaitingJurisdiction())
                                        <span class="ml-1 inline-block px-2 py-0.5 rounded-full text-[11px] font-bold bg-gray-100 text-gray-500">{{ __('Awaiting Review') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-gray-400">{{ __('No inquiries currently assigned to an agency.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    @elseif ($screen === 'review')
        @php $inquiry = $this->currentInquiry; @endphp
        <button wire:click="backToList" class="inline-flex items-center gap-1.5 text-gray-500 hover:text-brand font-semibold text-sm mb-5">&larr; {{ __('Back to List') }}</button>

        <div class="bg-white border border-gray-100 rounded-2xl p-6 max-w-3xl">
            <div class="flex items-center gap-3 flex-wrap mb-4">
                <h2 class="font-display font-extrabold text-xl text-gray-900">{{ $inquiry->title }}</h2>
                <x-inquiry-status-badge :status="$inquiry->status" />
            </div>
            <div class="flex gap-8 flex-wrap mb-5 text-sm">
                <div><span class="text-gray-400">{{ __('Category') }}:</span> <span class="font-semibold text-gray-700">{{ $inquiry->category?->value }}</span></div>
                <div><span class="text-gray-400">{{ __('Submitted by') }}:</span> <span class="font-semibold text-gray-700">{{ $inquiry->submitter?->name }}</span></div>
                <div><span class="text-gray-400">{{ __('Prior submissions') }}:</span> <span class="font-semibold text-gray-700">{{ $inquiry->submitter?->submittedInquiries()->count() - 1 }}</span></div>
            </div>
            <div class="mb-5">
                <div class="font-bold text-gray-900 mb-1.5">{{ __('Description') }}</div>
                <p class="text-sm text-gray-600 leading-relaxed">{{ $inquiry->description }}</p>
            </div>
            @if ($inquiry->evidence->count())
                <div class="mb-5">
                    <div class="font-bold text-gray-900 mb-2">{{ __('Evidence') }}</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($inquiry->evidence as $file)
                            <span class="bg-gray-50 rounded-lg px-3 py-1.5 text-xs font-semibold text-gray-700">📎 {{ $file->file_name }}</span>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="mb-5 pt-4 border-t border-gray-100">
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Add Review Notes (optional)') }}</label>
                <textarea wire:model="reviewNotes" rows="2" placeholder="{{ __('Notes will carry over to whichever action you choose below.') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand"></textarea>
            </div>

            <div class="flex gap-3">
                <button wire:click="validateAndAssign({{ $inquiry->id }})" class="bg-green-600 hover:bg-green-700 text-white font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Validate') }}</button>
                <button wire:click="openDiscardModal" class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Discard') }}</button>
            </div>
        </div>
    @else
        @php $inquiry = $this->currentInquiry; @endphp
        <button wire:click="backToList" class="inline-flex items-center gap-1.5 text-gray-500 hover:text-brand font-semibold text-sm mb-5">&larr; {{ __('Back to List') }}</button>

        <div class="bg-white border border-gray-100 rounded-2xl p-6 max-w-2xl">
            <h2 class="font-display font-extrabold text-xl text-gray-900 mb-1">{{ __('Assign to Agency') }}</h2>
            <p class="text-sm text-gray-500 mb-5">{{ $inquiry->title }}</p>

            @if ($inquiry->status === InquiryStatus::Rejected && $inquiry->agency)
                <div class="flex items-start gap-2.5 bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 mb-5 text-sm text-amber-800">
                    <span class="flex-shrink-0">⚠️</span>
                    <div>
                        {{ __('Previously assigned to :agency — Rejected: ":reason" on :date', [
                            'agency' => $inquiry->agency->name,
                            'reason' => str($inquiry->resolution_notes ?? '—')->limit(80),
                            'date' => $inquiry->updated_at->format('d M Y'),
                        ]) }}
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-2 gap-3 mb-5">
                @foreach ($this->agencies as $agency)
                    @php $recommended = $agency->specialization === $inquiry->category; @endphp
                    <button type="button" wire:click="$set('selectedAgencyId', {{ $agency->id }})"
                        class="text-left border-2 rounded-xl p-4 {{ $selectedAgencyId === $agency->id ? 'border-brand bg-brand-light' : 'border-gray-100' }}">
                        <div class="font-bold text-sm text-gray-900">{{ $agency->name }} @if ($recommended)<span class="text-brand">({{ __('Recommended') }})</span>@endif</div>
                        <div class="text-xs text-gray-500 mt-1">{{ $agency->specialization?->value }} · {{ __(':count active cases', ['count' => $agency->active_case_count]) }}</div>
                    </button>
                @endforeach
            </div>
            <x-input-error :messages="$errors->get('selectedAgencyId')" class="mb-4" />

            <div class="mb-5">
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Notes (optional)') }}</label>
                <textarea wire:model="assignNotes" rows="3" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand"></textarea>
            </div>

            <button wire:click="confirmAssign" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-6 py-3 rounded-lg">{{ __('Assign') }}</button>
        </div>
    @endif

    @if ($discardModalOpen)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="font-bold text-gray-900 mb-2">{{ __('Discard this inquiry?') }}</h3>
                <p class="text-sm text-gray-500 mb-4">{{ __('This action is terminal and will be logged.') }}</p>
                <textarea wire:model="discardNotes" rows="3" placeholder="{{ __('Reason (optional)') }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand mb-4"></textarea>
                <div class="flex gap-3">
                    <button wire:click="confirmDiscard" class="flex-1 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Discard') }}</button>
                    <button wire:click="$set('discardModalOpen', false)" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
    @endif

    @if ($bulkDiscardModalOpen)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                <h3 class="font-bold text-gray-900 mb-2">{{ __('Discard :count inquiries?', ['count' => count($selectedIds)]) }}</h3>
                <p class="text-sm text-gray-500 mb-4">{{ __('This action is terminal for all selected inquiries and will be logged.') }}</p>
                <div class="flex gap-3">
                    <button wire:click="confirmBulkDiscard" class="flex-1 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Discard All') }}</button>
                    <button wire:click="$set('bulkDiscardModalOpen', false)" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Cancel') }}</button>
                </div>
            </div>
        </div>
    @endif
</div>
