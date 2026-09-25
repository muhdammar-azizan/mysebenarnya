<?php

use App\Enums\AgencyStaffRole;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\User;
use App\Notifications\AgencyStaffInvited;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.agency')] class extends Component
{
    use WithFileUploads;

    public string $tab = 'info';

    public string $agencyName = '';

    public string $contactEmail = '';

    public string $contactPhone = '';

    public string $description = '';

    public bool $saved = false;

    public $newLogo = null;

    public bool $inviteModalOpen = false;

    public string $inviteName = '';

    public string $inviteEmail = '';

    public string $inviteRole = 'reviewer';

    public bool $invited = false;

    public function mount(): void
    {
        $agency = Auth::user()->agency;

        $this->agencyName = $agency->name;
        $this->contactEmail = $agency->contact_email ?? '';
        $this->contactPhone = $agency->contact_phone ?? '';
        $this->description = $agency->description ?? '';
    }

    public function getAgencyProperty(): Agency
    {
        return Auth::user()->agency;
    }

    public function getStaffProperty()
    {
        return $this->agency->users()->get();
    }

    public function saveInfo(): void
    {
        if (! Auth::user()->isAgencyAdmin()) {
            abort(403);
        }

        $validated = $this->validate([
            'agencyName' => 'required|string|max:255',
            'contactEmail' => 'required|email|max:255',
            'contactPhone' => 'required|string|max:30',
        ]);

        $this->agency->update([
            'name' => $validated['agencyName'],
            'contact_email' => $validated['contactEmail'],
            'contact_phone' => $validated['contactPhone'],
            'description' => $this->description ?: null,
        ]);

        $this->saved = true;
    }

    public function saveLogo(): void
    {
        if (! Auth::user()->isAgencyAdmin()) {
            abort(403);
        }

        $this->validate([
            'newLogo' => ['required', 'image', 'max:2048'],
        ]);

        if ($this->agency->logo_path) {
            Storage::disk('public')->delete($this->agency->logo_path);
        }

        $path = $this->newLogo->store('agency-logos', 'public');

        $this->agency->update(['logo_path' => $path]);

        $this->newLogo = null;

        $this->dispatch('logo-updated');
    }

    public function removeLogo(): void
    {
        if (! Auth::user()->isAgencyAdmin()) {
            abort(403);
        }

        if ($this->agency->logo_path) {
            Storage::disk('public')->delete($this->agency->logo_path);
            $this->agency->update(['logo_path' => null]);
        }
    }

    public function openInviteModal(): void
    {
        if (! Auth::user()->isAgencyAdmin()) {
            abort(403);
        }

        $this->reset(['inviteName', 'inviteEmail', 'inviteRole', 'invited']);
        $this->inviteModalOpen = true;
    }

    public function sendInvite(): void
    {
        if (! Auth::user()->isAgencyAdmin()) {
            abort(403);
        }

        $validated = $this->validate([
            'inviteName' => 'required|string|max:255',
            'inviteEmail' => 'required|email|max:255|unique:users,email',
            'inviteRole' => 'required|in:admin,reviewer',
        ]);

        $temporaryPassword = Str::password(12);

        $staff = User::create([
            'name' => $validated['inviteName'],
            'email' => $validated['inviteEmail'],
            'password' => Hash::make($temporaryPassword),
            'role' => UserRole::AgencyStaff,
            'agency_id' => $this->agency->id,
            'agency_role' => $validated['inviteRole'] === 'admin' ? AgencyStaffRole::Admin : AgencyStaffRole::Reviewer,
            'must_change_password' => true,
            'email_verified_at' => now(),
        ]);

        $staff->notify(new AgencyStaffInvited($this->agency, $temporaryPassword, Auth::user()->name));

        $this->invited = true;
    }
}; ?>

<div>
    <div class="flex items-center gap-4 mb-6">
        @if ($this->agency->logo_path)
            <img src="{{ asset('storage/'.$this->agency->logo_path) }}" class="w-14 h-14 rounded-xl object-cover border border-gray-100" alt="{{ __('Agency logo') }}">
        @else
            <div class="w-14 h-14 rounded-xl bg-brand text-white flex items-center justify-center font-bold text-lg flex-shrink-0">
                {{ collect(explode(' ', $this->agency->name))->map(fn ($w) => $w[0] ?? '')->take(2)->implode('') }}
            </div>
        @endif
        <div>
            <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Agency Profile') }}</h1>
            <p class="text-gray-500 text-sm">{{ $this->agency->name }} ({{ $this->agency->code }})</p>
        </div>
    </div>

    <div class="flex border-b border-gray-100 mb-6">
        <button wire:click="$set('tab', 'info')" class="px-5 py-3 text-sm font-bold {{ $tab === 'info' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Agency Information') }}</button>
        <button wire:click="$set('tab', 'staff')" class="px-5 py-3 text-sm font-bold {{ $tab === 'staff' ? 'text-brand border-b-2 border-brand' : 'text-gray-400' }}">{{ __('Staff') }}</button>
    </div>

    @if ($tab === 'info')
        @if ($saved)
            <div class="bg-green-50 border border-green-200 text-green-700 rounded-lg px-4 py-3 font-semibold text-sm mb-5">✓ {{ __('Agency details saved.') }}</div>
        @endif

        @if (auth()->user()->isAgencyAdmin())
            <section
                x-data="{
                    cropperOpen: false,
                    cropper: null,
                    onFileSelected(e) {
                        const file = e.target.files[0];
                        if (!file) return;
                        const reader = new FileReader();
                        reader.onload = () => {
                            this.cropperOpen = true;
                            this.$nextTick(() => {
                                if (this.cropper) this.cropper.destroy();
                                this.$refs.cropLogoImg.src = reader.result;
                                this.cropper = new Cropper(this.$refs.cropLogoImg, { aspectRatio: 1, viewMode: 1, background: false });
                            });
                        };
                        reader.readAsDataURL(file);
                        e.target.value = '';
                    },
                    saveCrop() {
                        this.cropper.getCroppedCanvas({ width: 400, height: 400 }).toBlob((blob) => {
                            const file = new File([blob], 'logo.png', { type: 'image/png' });
                            @this.upload('newLogo', file, () => {
                                @this.call('saveLogo');
                                this.cropperOpen = false;
                                this.cropper.destroy();
                                this.cropper = null;
                            });
                        }, 'image/png');
                    }
                }"
                @logo-updated.window="cropperOpen = false"
                class="bg-white border border-gray-100 rounded-2xl p-7 max-w-xl mb-5"
            >
                @once
                    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
                    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
                @endonce

                <label class="block text-sm font-bold text-gray-700 mb-3">{{ __('Agency Logo / Profile Picture') }}</label>
                <div class="flex items-center gap-4">
                    @if ($this->agency->logo_path)
                        <img src="{{ asset('storage/'.$this->agency->logo_path) }}" class="w-16 h-16 rounded-xl object-cover" alt="{{ __('Agency logo') }}">
                    @else
                        <div class="w-16 h-16 rounded-xl bg-brand text-white flex items-center justify-center font-bold text-lg">
                            {{ collect(explode(' ', $this->agency->name))->map(fn ($w) => $w[0] ?? '')->take(2)->implode('') }}
                        </div>
                    @endif
                    <div class="flex flex-col gap-1.5">
                        <label class="inline-block w-fit">
                            <input type="file" accept="image/*" class="hidden" @change="onFileSelected">
                            <span class="cursor-pointer bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold px-4 py-1.5 rounded-md">{{ __('Change Logo') }}</span>
                        </label>
                        @if ($this->agency->logo_path)
                            <button wire:click="removeLogo" wire:confirm="{{ __('Remove your agency logo?') }}" type="button" class="text-sm text-gray-400 hover:text-brand w-fit">{{ __('Remove') }}</button>
                        @endif
                    </div>
                </div>

                <div x-show="cropperOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-6">
                    <div class="bg-white rounded-xl p-5 w-full max-w-md" @click.outside="cropperOpen = false">
                        <h3 class="font-bold text-gray-900 mb-3">{{ __('Crop your logo') }}</h3>
                        <div class="max-h-80 overflow-hidden">
                            <img x-ref="cropLogoImg" class="max-w-full block" style="max-height: 320px;">
                        </div>
                        <div class="flex gap-3 mt-4">
                            <button type="button" @click="saveCrop()" class="flex-1 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Save Logo') }}</button>
                            <button type="button" @click="cropperOpen = false; cropper.destroy(); cropper = null;" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Cancel') }}</button>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <form wire:submit="saveInfo" class="bg-white border border-gray-100 rounded-2xl p-7 max-w-xl space-y-5">
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Agency Name') }}</label>
                @if (auth()->user()->isAgencyAdmin())
                    <input type="text" wire:model="agencyName" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
                    <x-input-error :messages="$errors->get('agencyName')" class="mt-1.5" />
                @else
                    <input type="text" value="{{ $this->agency->name }}" disabled class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-500" />
                @endif
            </div>
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Jurisdiction / Specialization') }}</label>
                <input type="text" value="{{ $this->agency->specialization?->value }}" disabled class="w-full rounded-lg border-gray-200 bg-gray-50 text-sm text-gray-500" />
                <p class="text-xs text-gray-400 mt-1.5">{{ __('Locked — only MCMC can change your jurisdiction.') }}</p>
            </div>

            @if (auth()->user()->isAgencyAdmin())
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Contact Email') }}</label>
                    <input type="text" wire:model="contactEmail" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
                    <x-input-error :messages="$errors->get('contactEmail')" class="mt-1.5" />
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Contact Phone Number') }}</label>
                    <input type="text" wire:model="contactPhone" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
                    <x-input-error :messages="$errors->get('contactPhone')" class="mt-1.5" />
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Description') }}</label>
                    <textarea wire:model="description" rows="3" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand"></textarea>
                </div>
                <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-6 py-3 rounded-lg">{{ __('Save Changes') }}</button>
            @else
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Contact Email') }}</label>
                    <div class="text-sm text-gray-600">{{ $this->agency->contact_email }}</div>
                </div>
                <div>
                    <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Contact Phone Number') }}</label>
                    <div class="text-sm text-gray-600">{{ $this->agency->contact_phone }}</div>
                </div>
                <p class="text-xs text-gray-400">{{ __('Only agency Admins can edit these details.') }}</p>
            @endif
        </form>
    @else
        <div class="flex items-center justify-between mb-4 max-w-xl">
            <div class="font-bold text-gray-900">{{ __('Staff Members') }}</div>
            @if (auth()->user()->isAgencyAdmin())
                <button wire:click="openInviteModal" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2 rounded-lg">+ {{ __('Invite Staff') }}</button>
            @endif
        </div>

        <div class="bg-white border border-gray-100 rounded-2xl overflow-hidden max-w-xl">
            @foreach ($this->staff as $staffMember)
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-50 last:border-0">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-gray-900 truncate">{{ $staffMember->name }}</div>
                        <div class="text-xs text-gray-400 truncate">{{ $staffMember->email }}</div>
                    </div>
                    <span class="flex-shrink-0 px-2.5 py-1 rounded-full text-xs font-bold {{ $staffMember->agency_role?->value === 'admin' ? 'bg-brand-light text-brand' : 'bg-gray-100 text-gray-600' }}">
                        {{ str($staffMember->agency_role?->value ?? '—')->headline() }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif

    @if ($inviteModalOpen)
        <div class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-6">
            <div class="bg-white rounded-xl p-6 w-full max-w-md">
                @if (! $invited)
                    <h3 class="font-bold text-gray-900 mb-4">{{ __('Invite Staff Member') }}</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Full Name') }}</label>
                            <input type="text" wire:model="inviteName" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
                            <x-input-error :messages="$errors->get('inviteName')" class="mt-1.5" />
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Email') }}</label>
                            <input type="text" wire:model="inviteEmail" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand" />
                            <x-input-error :messages="$errors->get('inviteEmail')" class="mt-1.5" />
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Role') }}</label>
                            <select wire:model="inviteRole" class="w-full rounded-lg border-gray-300 text-sm focus:border-brand focus:ring-brand">
                                <option value="reviewer">{{ __('Reviewer') }}</option>
                                <option value="admin">{{ __('Admin') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-5">
                        <button wire:click="sendInvite" class="flex-1 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Send Invite') }}</button>
                        <button wire:click="$set('inviteModalOpen', false)" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Cancel') }}</button>
                    </div>
                @else
                    <div class="text-center">
                        <div class="w-14 h-14 rounded-full bg-green-100 text-green-600 text-2xl flex items-center justify-center mx-auto mb-4">✓</div>
                        <h3 class="font-bold text-gray-900 mb-2">{{ __('Staff Invited') }}</h3>
                        <p class="text-sm text-gray-500 mb-5">{{ __('Login credentials have been emailed to :email.', ['email' => $inviteEmail]) }}</p>
                        <button wire:click="$set('inviteModalOpen', false)" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-5 py-2.5 rounded-lg">{{ __('Done') }}</button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
