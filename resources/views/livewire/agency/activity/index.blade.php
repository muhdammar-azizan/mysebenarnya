<?php

use App\Concerns\GeneratesReports;
use App\Enums\InquiryStatus;
use App\Models\ClarificationMessage;
use App\Models\InquiryActivityLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('layouts.agency')] class extends Component
{
    use GeneratesReports;

    #[Url]
    public string $filter = 'all';

    public bool $exportOpen = false;

    public bool $exportJustCompleted = false;

    public string $exportFormat = 'pdf';

    public array $exportSections = ['log' => true, 'officers' => true];

    public array $exportHistory = [];

    protected function agencyId(): int
    {
        return Auth::user()->agency_id;
    }

    public function openExport(): void
    {
        $this->exportOpen = true;
        $this->exportJustCompleted = false;
    }

    public function closeExport(): void
    {
        $this->exportOpen = false;
        $this->exportJustCompleted = false;
    }

    public function exportAnother(): void
    {
        $this->exportJustCompleted = false;
    }

    public function toggleSection(string $key): void
    {
        $this->exportSections[$key] = ! ($this->exportSections[$key] ?? false);
    }

    public function toggleAllSections(): void
    {
        $allOn = ! in_array(false, $this->exportSections, true);

        foreach ($this->exportSections as $key => $value) {
            $this->exportSections[$key] = ! $allOn;
        }
    }

    protected function actionEvents(): Collection
    {
        return InquiryActivityLog::with(['inquiry', 'user'])
            ->whereHas('user', fn ($q) => $q->where('agency_id', $this->agencyId()))
            ->get()
            ->map(function ($log) {
                [$type, $text] = match ($log->action) {
                    'jurisdiction_accepted' => ['update', __('accepted jurisdiction on this inquiry')],
                    'jurisdiction_rejected' => ['update', __('rejected inquiry — outside jurisdiction')],
                    'investigation_updated' => ['comment', __('updated investigation notes')],
                    'verdict_finalized' => ['resolve', __('resolved inquiry as :status', ['status' => InquiryStatus::tryFrom((string) $log->to_status)?->label() ?? $log->to_status])],
                    default => ['update', (string) str($log->action)->headline()],
                };

                return [
                    'actor' => $log->user?->name ?? '—',
                    'text' => $text,
                    'at' => $log->created_at,
                    'inquiryTitle' => $log->inquiry?->title,
                    'type' => $type,
                ];
            });
    }

    protected function clarificationEvents(): Collection
    {
        $agencyId = $this->agencyId();

        return ClarificationMessage::with(['thread.inquiry.agency', 'user', 'consultAgency'])
            ->whereHas('thread', fn ($q) => $q->whereHas('inquiry', fn ($qq) => $qq->where('agency_id', $agencyId))
                ->orWhereHas('consults', fn ($qq) => $qq->where('consulted_agency_id', $agencyId)))
            ->get()
            ->filter(fn ($m) => $m->thread->inquiry->agency_id === $agencyId || $m->consult_agency_id === $agencyId)
            ->map(function ($m) use ($agencyId) {
                $thread = $m->thread;
                $inquiry = $thread->inquiry;
                $excerpt = (string) str($m->message)->limit(80);
                $ownCase = $inquiry->agency_id === $agencyId;

                if ($m->is_system) {
                    $text = __('closed clarification #:id — :excerpt', ['id' => $thread->id, 'excerpt' => $excerpt]);
                } elseif ($m->consult_agency_id === $agencyId) {
                    $text = $ownCase
                        ? __('sent consultation advice on clarification #:id: ":excerpt"', ['id' => $thread->id, 'excerpt' => $excerpt])
                        : __('sent consultation advice to MCMC on clarification #:id (case owned by :agency): ":excerpt"', ['id' => $thread->id, 'agency' => $inquiry->agency?->name, 'excerpt' => $excerpt]);
                } elseif ($m->consult_agency_id !== null) {
                    $text = __(':agency advised via MCMC on clarification #:id: ":excerpt"', ['agency' => $m->consultAgency?->name, 'id' => $thread->id, 'excerpt' => $excerpt]);
                } elseif ($m->user?->isMcmcStaff()) {
                    $text = __('MCMC responded to clarification #:id: ":excerpt"', ['id' => $thread->id, 'excerpt' => $excerpt]);
                } else {
                    $text = __('sent a message on clarification #:id: ":excerpt"', ['id' => $thread->id, 'excerpt' => $excerpt]);
                }

                return [
                    'actor' => $m->user?->name ?? __('MCMC'),
                    'text' => $text,
                    'at' => $m->created_at,
                    'inquiryTitle' => $inquiry->title,
                    'type' => 'clarify',
                ];
            });
    }

    protected function allEvents(): Collection
    {
        return $this->actionEvents()->concat($this->clarificationEvents())->sortByDesc('at')->values();
    }

    public function getRowsProperty(): array
    {
        return $this->allEvents()
            ->filter(fn ($e) => $this->filter === 'all' || $e['type'] === $this->filter)
            ->all();
    }

    public function iconFor(string $type): array
    {
        return match ($type) {
            'update' => ['icon' => '✎', 'bg' => 'bg-amber-100', 'color' => 'text-amber-700'],
            'comment' => ['icon' => '💬', 'bg' => 'bg-blue-100', 'color' => 'text-blue-700'],
            'resolve' => ['icon' => '✓', 'bg' => 'bg-green-100', 'color' => 'text-green-700'],
            'clarify' => ['icon' => '❓', 'bg' => 'bg-purple-100', 'color' => 'text-purple-700'],
            default => ['icon' => '•', 'bg' => 'bg-gray-100', 'color' => 'text-gray-600'],
        };
    }

    public function getFilterTabsProperty(): array
    {
        $all = $this->allEvents();

        return [
            ['key' => 'all', 'label' => __('All Activity'), 'count' => $all->count()],
            ['key' => 'update', 'label' => __('Status Updates'), 'count' => $all->where('type', 'update')->count()],
            ['key' => 'comment', 'label' => __('Comments'), 'count' => $all->where('type', 'comment')->count()],
            ['key' => 'resolve', 'label' => __('Resolutions'), 'count' => $all->where('type', 'resolve')->count()],
            ['key' => 'clarify', 'label' => __('Clarifications'), 'count' => $all->where('type', 'clarify')->count()],
        ];
    }

    protected function typeLabel(string $type): string
    {
        return match ($type) {
            'update' => 'Status Update',
            'comment' => 'Comment',
            'resolve' => 'Resolution',
            'clarify' => 'Clarification',
            default => $type,
        };
    }

    public function getExportSectionListProperty(): array
    {
        $rows = collect($this->rows);

        return [
            ['key' => 'log', 'label' => __('Activity Entries'), 'desc' => __('Timestamped audit trail of every action'), 'count' => $rows->count()],
            ['key' => 'officers', 'label' => __('Actions by Officer'), 'desc' => __('Updates, comments and resolutions per officer'), 'count' => $rows->pluck('actor')->unique()->count()],
        ];
    }

    public function getExportFiltersLabelProperty(): string
    {
        $labels = collect($this->filterTabs)->firstWhere('key', $this->filter)['label'] ?? __('All Activity');

        return $labels;
    }

    public function getExportFilenameProperty(): string
    {
        return 'SEBENARNYA_'.str(Auth::user()->agency->code)->slug().'-Activity-Log_'.now()->format('Ymd').'.'.($this->exportFormat === 'excel' ? 'xlsx' : 'pdf');
    }

    public function generateExport()
    {
        $rows = collect($this->rows);
        $sections = [];

        if ($this->exportSections['log'] ?? false) {
            $sections[] = [
                'name' => 'Activity Log',
                'kpis' => [
                    ['label' => 'Entries in Export', 'value' => $rows->count()],
                    ['label' => 'Status Updates', 'value' => $rows->where('type', 'update')->count()],
                    ['label' => 'Comments & Clarifications', 'value' => $rows->whereIn('type', ['comment', 'clarify'])->count()],
                    ['label' => 'Resolutions', 'value' => $rows->where('type', 'resolve')->count()],
                ],
                'tables' => [[
                    'heading' => 'Activity Entries',
                    'columns' => ['Date & Time', 'Officer', 'Action', 'Type', 'Inquiry'],
                    'rows' => $rows->map(fn ($e) => [
                        $e['at']->format('d M Y, H:i'),
                        $e['actor'],
                        ucfirst($e['text']),
                        $this->typeLabel($e['type']),
                        $e['inquiryTitle'],
                    ])->all(),
                ]],
            ];
        }

        if ($this->exportSections['officers'] ?? false) {
            $officers = $rows->pluck('actor')->unique()->values();
            $data = $officers->map(function ($name) use ($rows) {
                $mine = $rows->where('actor', $name);

                return [
                    $name,
                    $mine->where('type', 'update')->count(),
                    $mine->where('type', 'comment')->count(),
                    $mine->where('type', 'resolve')->count(),
                    $mine->count(),
                ];
            });

            $sections[] = [
                'name' => 'Actions by Officer',
                'tables' => [[
                    'heading' => 'Officer Activity Summary',
                    'columns' => ['Officer', 'Status Updates', 'Comments', 'Resolutions', 'Total Actions'],
                    'rows' => $data->all(),
                ]],
            ];
        }

        if (empty($sections)) {
            $this->addError('exportSections', __('Select at least one section to export.'));

            return;
        }

        $filenameBase = 'SEBENARNYA_'.str(Auth::user()->agency->code)->slug().'-Activity-Log';

        if ($this->exportFormat === 'excel') {
            $sheets = [];
            foreach ($sections as $section) {
                foreach ($section['tables'] as $table) {
                    $sheets[] = ['title' => $table['heading'], 'headings' => $table['columns'], 'rows' => $table['rows']];
                }
            }
            $response = $this->downloadExcel($filenameBase, $sheets);
        } else {
            $response = $this->downloadPdf($filenameBase, 'Agency Activity Log — '.Auth::user()->agency->name, $this->exportFiltersLabel, $sections);
        }

        $this->exportHistory[] = ['filename' => $this->exportFilename, 'meta' => now()->format('d M, H:i')];
        $this->exportJustCompleted = true;

        return $response;
    }
}; ?>

<div>
    <div class="flex items-start justify-between gap-4 mb-1">
        <div>
            <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Activity Log') }}</h1>
            <p class="text-gray-500 text-sm mt-1">{{ __('A record of actions your agency has taken on assigned inquiries.') }}</p>
        </div>
        <div class="flex-shrink-0">
            <x-export-modal
                :export-open="$exportOpen"
                :export-just-completed="$exportJustCompleted"
                :export-format="$exportFormat"
                :export-sections="$exportSections"
                :export-history="$exportHistory"
                :export-filters-label="$this->exportFiltersLabel"
                :export-filename="$this->exportFilename"
                :sections="$this->exportSectionList"
                :prepared-by="auth()->user()->name.' ('.auth()->user()->agency->name.')'"
                :intro-text="__('Export the audit trail using the activity filter currently selected.')"
            />
        </div>
    </div>

    <div class="flex flex-wrap gap-2 my-5">
        @foreach ($this->filterTabs as $tab)
            <button wire:click="$set('filter', '{{ $tab['key'] }}')" class="px-3.5 py-1.5 rounded-full text-xs font-bold {{ $filter === $tab['key'] ? 'bg-brand-light text-brand' : 'bg-gray-100 text-gray-500' }}">
                {{ $tab['label'] }} <span class="opacity-70">({{ $tab['count'] }})</span>
            </button>
        @endforeach
    </div>

    <div class="relative">
        <div class="absolute left-[15px] top-1.5 bottom-1.5 w-0.5 bg-gray-100"></div>
        <div class="flex flex-col gap-0">
            @forelse ($this->rows as $event)
                @php $icon = $this->iconFor($event['type']); @endphp
                <div class="flex gap-3.5 pb-5 relative">
                    <div class="w-8 h-8 rounded-full {{ $icon['bg'] }} {{ $icon['color'] }} flex items-center justify-center flex-shrink-0 z-10 text-sm">{{ $icon['icon'] }}</div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm text-gray-800"><strong class="text-gray-900">{{ $event['actor'] }}</strong> {{ $event['text'] }}</div>
                        <div class="text-xs text-gray-400 mt-0.5">{{ $event['at']->format('d M Y, H:i') }} &middot; {{ $event['inquiryTitle'] }}</div>
                    </div>
                </div>
            @empty
                <p class="px-1 py-10 text-center text-gray-400">{{ __('No activity in this filter.') }}</p>
            @endforelse
        </div>
    </div>
</div>
