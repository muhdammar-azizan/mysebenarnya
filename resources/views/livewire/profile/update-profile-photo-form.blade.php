<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public $newPhoto = null;

    public function save(): void
    {
        $this->validate([
            'newPhoto' => ['required', 'image', 'max:2048'],
        ]);

        $user = Auth::user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
        }

        $path = $this->newPhoto->store('profile-photos', 'public');

        $user->forceFill(['profile_photo_path' => $path])->save();

        $this->newPhoto = null;

        $this->dispatch('photo-updated');
    }

    public function removePhoto(): void
    {
        $user = Auth::user();

        if ($user->profile_photo_path) {
            Storage::disk('public')->delete($user->profile_photo_path);
            $user->forceFill(['profile_photo_path' => null])->save();
        }
    }
}; ?>

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
                    this.$refs.cropImg.src = reader.result;
                    this.cropper = new Cropper(this.$refs.cropImg, { aspectRatio: 1, viewMode: 1, background: false });
                });
            };
            reader.readAsDataURL(file);
            e.target.value = '';
        },
        saveCrop() {
            this.cropper.getCroppedCanvas({ width: 400, height: 400 }).toBlob((blob) => {
                const file = new File([blob], 'profile.png', { type: 'image/png' });
                @this.upload('newPhoto', file, () => {
                    @this.call('save');
                    this.cropperOpen = false;
                    this.cropper.destroy();
                    this.cropper = null;
                });
            }, 'image/png');
        }
    }"
    @photo-updated.window="cropperOpen = false"
>
    @once
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js"></script>
    @endonce

    <header>
        <h2 class="text-lg font-medium text-gray-900">{{ __('Profile Photo') }}</h2>
        <p class="mt-1 text-sm text-gray-600">{{ __('Upload a photo, then crop it to a square before saving.') }}</p>
    </header>

    <div class="mt-6 flex items-center gap-4">
        @if (Auth::user()->profile_photo_path)
            <img src="{{ asset('storage/'.Auth::user()->profile_photo_path) }}" class="w-16 h-16 rounded-full object-cover" alt="{{ __('Profile photo') }}">
        @else
            <div class="w-16 h-16 rounded-full bg-brand text-white flex items-center justify-center font-bold text-lg">
                {{ collect(explode(' ', Auth::user()->name))->map(fn ($w) => $w[0] ?? '')->take(2)->implode('') }}
            </div>
        @endif

        <div class="flex flex-col gap-1.5">
            <label class="inline-block w-fit">
                <input type="file" accept="image/*" class="hidden" @change="onFileSelected">
                <span class="cursor-pointer bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 text-sm font-semibold px-4 py-1.5 rounded-md">{{ __('Change Photo') }}</span>
            </label>
            @if (Auth::user()->profile_photo_path)
                <button wire:click="removePhoto" wire:confirm="{{ __('Remove your profile photo?') }}" type="button" class="text-sm text-gray-400 hover:text-brand w-fit">{{ __('Remove') }}</button>
            @endif
        </div>
    </div>

    <div x-show="cropperOpen" x-cloak class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-6">
        <div class="bg-white rounded-xl p-5 w-full max-w-md" @click.outside="cropperOpen = false">
            <h3 class="font-bold text-gray-900 mb-3">{{ __('Crop your photo') }}</h3>
            <div class="max-h-80 overflow-hidden">
                <img x-ref="cropImg" class="max-w-full block" style="max-height: 320px;">
            </div>
            <div class="flex gap-3 mt-4">
                <button type="button" @click="saveCrop()" class="flex-1 bg-brand hover:bg-brand-dark text-white font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Save Photo') }}</button>
                <button type="button" @click="cropperOpen = false; cropper.destroy(); cropper = null;" class="flex-1 border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-4 py-2.5 rounded-lg">{{ __('Cancel') }}</button>
            </div>
        </div>
    </div>
</section>
