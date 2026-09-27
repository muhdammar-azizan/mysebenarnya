{{--
    Shared "Export Report" modal. This is a plain Blade component (not a
    nested Livewire component), so it does NOT automatically see the
    enclosing Livewire component's properties — everything it displays must
    be passed in explicitly as a prop. Only the wire:click/wire:model
    attribute strings below are resolved against the enclosing Livewire
    component, since those are handled client-side by Livewire's JS runtime
    rather than needing PHP variable access here.

    The parent Livewire component must expose this exact method contract
    (the wire:click targets below are hardcoded to these names):

    - openExport(): void
    - closeExport(): void                    (also resets exportJustCompleted)
    - exportAnother(): void                  (resets exportJustCompleted only, keeps modal open)
    - toggleSection(string $key): void
    - toggleAllSections(): void
    - generateExport(): BinaryFileResponse    (also appends to exportHistory and sets exportJustCompleted = true)
    - a public string $exportFormat property ('pdf' | 'excel'), set via $set('exportFormat', ...)
    - a public array $exportSections property [key => bool], set via toggleSection()
    - if showPeriod, a public property named by periodProperty, bound via wire:model.live

    Props:
    - exportOpen: bool
    - exportJustCompleted: bool
    - exportFormat: string ('pdf' | 'excel')
    - exportSections: array<string, bool>
    - exportHistory: array<int, array{filename:string, meta:string}>
    - exportFiltersLabel: string
    - exportFilename: string
    - sections: array<int, array{key:string,label:string,desc:string,count:int}>
    - showPeriod: bool — whether to render the period <select>
    - periodProperty: string|null — the parent's wire:model property name for the period select (e.g. 'dateRange')
    - periodOptions: array<string,string> value=>label, required when showPeriod is true
    - preparedBy: string
    - introText: string
--}}
@props([
    'exportOpen' => false,
    'exportJustCompleted' => false,
    'exportFormat' => 'pdf',
    'exportSections' => [],
    'exportHistory' => [],
    'exportFiltersLabel' => '',
    'exportFilename' => '',
    'sections' => [],
    'showPeriod' => false,
    'periodProperty' => null,
    'periodOptions' => [],
    'preparedBy' => '',
    'introText' => '',
])

<button wire:click="openExport" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg whitespace-nowrap flex items-center gap-2">
    📄 {{ __('Export Report') }}
</button>

@if ($exportOpen)
    <div wire:click="closeExport" class="fixed inset-0 bg-black/45 z-[60] flex items-center justify-center p-6">
        <div wire:click.stop class="w-[560px] max-w-full max-h-[calc(100vh-48px)] bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 flex items-start justify-between gap-4">
                <div>
                    <div class="font-display font-bold text-lg text-gray-900 mb-0.5">{{ __('Export Report') }}</div>
                    <div class="text-xs text-gray-500">{{ $introText }}</div>
                </div>
                <button wire:click="closeExport" class="text-gray-400 hover:text-brand text-lg leading-none flex-shrink-0">✕</button>
            </div>

            <div class="flex-1 min-h-0 overflow-auto">
                @unless ($exportJustCompleted)
                <div class="p-6 flex flex-col gap-5">
                    <div>
                        <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide mb-2.5">{{ __('File Format') }}</div>
                        <div class="grid grid-cols-2 gap-3">
                            <button type="button" wire:click="$set('exportFormat', 'pdf')" class="text-left rounded-lg p-3 {{ $exportFormat === 'pdf' ? 'border-brand bg-brand-light' : 'border-gray-200' }}" style="border-width: 1.5px;">
                                <div class="font-bold text-sm text-gray-900">{{ __('PDF Document') }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ __('Formatted report for printing or sharing') }}</div>
                            </button>
                            <button type="button" wire:click="$set('exportFormat', 'excel')" class="text-left rounded-lg p-3 {{ $exportFormat === 'excel' ? 'border-brand bg-brand-light' : 'border-gray-200' }}" style="border-width: 1.5px;">
                                <div class="font-bold text-sm text-gray-900">{{ __('Excel Spreadsheet') }}</div>
                                <div class="text-xs text-gray-500 mt-0.5">{{ __('Raw data tables for further analysis') }}</div>
                            </button>
                        </div>
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-2.5">
                            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide">{{ __('Include Sections') }}</div>
                            <button type="button" wire:click="toggleAllSections" class="text-xs font-semibold text-brand">{{ __('Toggle All') }}</button>
                        </div>
                        <div class="flex flex-col gap-2">
                            @foreach ($sections as $section)
                                <button type="button" wire:click="toggleSection('{{ $section['key'] }}')" class="flex items-center gap-3 px-3.5 py-3 border rounded-lg text-left {{ ($exportSections[$section['key']] ?? false) ? 'border-brand bg-brand-light/40' : 'border-gray-200' }}">
                                    <span class="w-[18px] h-[18px] rounded flex items-center justify-center flex-shrink-0 {{ ($exportSections[$section['key']] ?? false) ? 'bg-brand' : 'border border-gray-300 bg-white' }}">
                                        @if ($exportSections[$section['key']] ?? false)
                                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none"><path d="M5 12.5l4.5 4.5L19 7.5" stroke="#fff" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        @endif
                                    </span>
                                    <span class="flex-1 min-w-0">
                                        <span class="block text-sm font-bold text-gray-900">{{ $section['label'] }}</span>
                                        <span class="block text-xs text-gray-500 mt-0.5">{{ $section['desc'] }}</span>
                                    </span>
                                    <span class="text-xs font-semibold text-gray-500 bg-gray-100 px-2.5 py-0.5 rounded-full whitespace-nowrap">{{ $section['count'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @if ($showPeriod)
                        <div>
                            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide mb-2">{{ __('Reporting Period') }}</div>
                            <select wire:model.live="{{ $periodProperty }}" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                                @foreach ($periodOptions as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="bg-gray-50 rounded-lg p-3.5 flex flex-col gap-1.5 text-xs">
                        <div class="flex gap-2.5"><span class="w-20 text-gray-400 font-semibold flex-shrink-0">{{ __('Filters') }}</span><span class="text-gray-900">{{ $exportFiltersLabel }}</span></div>
                        <div class="flex gap-2.5"><span class="w-20 text-gray-400 font-semibold flex-shrink-0">{{ __('File name') }}</span><span class="text-gray-900 font-mono break-all">{{ $exportFilename }}</span></div>
                        <div class="flex gap-2.5"><span class="w-20 text-gray-400 font-semibold flex-shrink-0">{{ __('Prepared by') }}</span><span class="text-gray-900">{{ $preparedBy }}</span></div>
                    </div>

                    @if (count($exportHistory))
                        <div>
                            <div class="text-[11px] font-bold text-gray-400 uppercase tracking-wide mb-2">{{ __('Recent Exports (this session)') }}</div>
                            <div class="flex flex-col gap-1.5">
                                @foreach (array_reverse($exportHistory) as $entry)
                                    <div class="flex items-center justify-between gap-3 text-xs px-2.5 py-2 border border-gray-100 rounded-lg">
                                        <span class="font-mono text-gray-700 truncate">{{ $entry['filename'] }}</span>
                                        <span class="text-gray-400 whitespace-nowrap">{{ $entry['meta'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button wire:click="closeExport" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold text-sm py-2.5 rounded-lg">{{ __('Cancel') }}</button>
                    <button wire:click="generateExport" wire:loading.attr="disabled" wire:target="generateExport" class="flex-1 bg-brand hover:bg-brand-dark disabled:opacity-60 text-white font-bold text-sm py-2.5 rounded-lg flex items-center justify-center gap-2">
                        <span wire:loading wire:target="generateExport" class="inline-block w-3.5 h-3.5 border-2 border-white/40 border-t-white rounded-full animate-spin"></span>
                        <span wire:loading.remove wire:target="generateExport">{{ __('Generate Export') }}</span>
                        <span wire:loading wire:target="generateExport">{{ __('Generating…') }}</span>
                    </button>
                </div>
                @else
                <div class="p-8 text-center">
                    <div class="w-14 h-14 rounded-full bg-green-100 text-green-600 text-2xl flex items-center justify-center mx-auto mb-4">✓</div>
                    <div class="font-display font-bold text-lg text-gray-900 mb-1.5">{{ __('Report Exported') }}</div>
                    <p class="text-sm text-gray-500">{{ __('Your download should begin automatically.') }}</p>
                </div>

                <div class="flex gap-3 px-6 py-4 border-t border-gray-100 flex-shrink-0">
                    <button wire:click="exportAnother" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-semibold text-sm py-2.5 rounded-lg">{{ __('Export Another') }}</button>
                    <button wire:click="closeExport" class="flex-1 bg-brand hover:bg-brand-dark text-white font-bold text-sm py-2.5 rounded-lg">{{ __('Done') }}</button>
                </div>
                @endunless
            </div>
        </div>
    </div>
@endif
