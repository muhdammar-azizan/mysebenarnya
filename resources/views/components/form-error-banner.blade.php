@props(['message' => null, 'retry' => null])

@if ($message)
    <div {{ $attributes->merge(['class' => 'bg-brand-light border border-[#F3AEB6] rounded-lg px-3.5 py-3 flex items-start gap-2.5']) }}>
        <span class="text-[15px] flex-shrink-0">⚠️</span>
        <div class="flex-1">
            <div class="text-[12.5px] text-[#8A2530] leading-relaxed mb-2">{{ $message }}</div>
            @if ($retry)
                <button type="button" wire:click="{{ $retry }}" class="bg-white text-brand border-[1.5px] border-brand hover:bg-brand-light font-bold text-xs px-3.5 py-1.5 rounded-md">
                    {{ __('Retry') }}
                </button>
            @endif
        </div>
    </div>
@endif
