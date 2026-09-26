<div class="min-h-screen box-border relative overflow-hidden bg-[linear-gradient(160deg,#FBEEF0_0%,#F3F1EF_55%,#EAEBED_100%)] px-6 py-8 flex flex-col items-center justify-center">

    <div class="absolute left-0 right-0 bottom-0 h-1/2 bg-cover bg-bottom opacity-45 z-0"
        style="background-image: url('{{ asset('images/signup-newspaper-footer.jpg') }}'); -webkit-mask-image: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.5) 50%, transparent 100%); mask-image: linear-gradient(to top, rgba(0,0,0,0.9) 0%, rgba(0,0,0,0.5) 50%, transparent 100%);">
    </div>

    <div class="relative z-10 flex items-center gap-2.5 mb-6">
        <x-brand-mark class="w-[34px] h-[34px] flex-shrink-0" />
        <div class="font-display font-extrabold text-base tracking-tight">
            <span class="text-gray-900">SE</span><span class="text-brand">BENAR</span><span class="text-gray-900">NYA.MY</span>
        </div>
    </div>

    <div class="relative z-10 w-full max-w-[560px] max-h-[calc(100vh-140px)] overflow-y-auto box-border bg-white/95 border border-white/70 rounded-[20px] shadow-2xl p-8">
        <div class="w-[52px] h-[52px] mx-auto mb-5">
            <x-brand-mark class="w-[52px] h-[52px]" />
        </div>

        {{ $slot }}
    </div>
</div>
