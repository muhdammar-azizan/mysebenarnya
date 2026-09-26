@php
    $sidebarPortal = auth()->user()->isPublic() || auth()->user()->isMcmcStaff() || auth()->user()->isAgencyStaff();
    $layoutComponent = match (true) {
        auth()->user()->isPublic() => 'public-layout',
        auth()->user()->isMcmcStaff() => 'mcmc-layout',
        auth()->user()->isAgencyStaff() => 'agency-layout',
        default => 'app-layout',
    };
@endphp
<x-dynamic-component :component="$layoutComponent">
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Profile') }}
        </h2>
    </x-slot>

    <div @if ($sidebarPortal) class="max-w-2xl" @else class="py-12" @endif x-data="{ tab: 'info' }">
        <div @unless ($sidebarPortal) class="max-w-7xl mx-auto sm:px-6 lg:px-8" @endunless>

            <div class="flex border-b border-gray-200 mb-6">
                <button type="button" @click="tab = 'info'" :class="tab === 'info' ? 'text-brand border-b-2 border-brand' : 'text-gray-400'" class="px-5 py-3 text-sm font-bold">
                    {{ __('Profile Information') }}
                </button>
                <button type="button" @click="tab = 'security'" :class="tab === 'security' ? 'text-brand border-b-2 border-brand' : 'text-gray-400'" class="px-5 py-3 text-sm font-bold">
                    {{ __('Password & Security') }}
                </button>
            </div>

            <div x-show="tab === 'info'" class="space-y-6">
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        <livewire:profile.update-profile-photo-form />
                    </div>
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        <livewire:profile.update-profile-information-form />
                    </div>
                </div>
            </div>

            <div x-show="tab === 'security'" x-cloak class="space-y-6">
                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        <livewire:profile.update-password-form />
                    </div>
                </div>

                <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
                    <div class="max-w-xl">
                        <livewire:profile.delete-user-form />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-dynamic-component>
