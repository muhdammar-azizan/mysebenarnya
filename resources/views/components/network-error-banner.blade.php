<div wire:offline class="fixed top-0 inset-x-0 z-[100] bg-brand-light border-b border-[#F3AEB6] px-4 py-3 flex items-center gap-3 flex-wrap justify-center">
    <span class="text-lg">⚠️</span>
    <div class="text-sm text-[#8A2530] font-medium">{{ __('Unable to connect. Please check your internet connection and try again.') }}</div>
    <button type="button" onclick="window.location.reload()" class="bg-brand hover:bg-brand-dark text-white font-bold text-xs px-4 py-2 rounded-md flex-shrink-0">
        {{ __('Retry') }}
    </button>
</div>
