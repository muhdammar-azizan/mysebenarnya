<?php

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\InquiryEvidence;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.public')] class extends Component
{
    use WithFileUploads;

    #[Validate('required|string|max:255')]
    public string $title = '';

    #[Validate('required|string')]
    public string $category = '';

    #[Validate('required|string|min:20')]
    public string $description = '';

    #[Validate('nullable|url|max:2048')]
    public string $source_url = '';

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $evidence = [];

    public function removeEvidence(int $index): void
    {
        unset($this->evidence[$index]);
        $this->evidence = array_values($this->evidence);
    }

    public function submit(): void
    {
        $this->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'description' => 'required|string|min:20',
            'source_url' => 'nullable|url|max:2048',
            'evidence.*' => 'file|max:10240|mimes:jpg,jpeg,png,pdf',
        ]);

        $inquiry = Inquiry::create([
            'submitted_by' => Auth::id(),
            'title' => $this->title,
            'category' => $this->category,
            'description' => $this->description,
            'source_url' => $this->source_url ?: null,
            'status' => InquiryStatus::Submitted,
        ]);

        foreach ($this->evidence as $file) {
            $path = $file->store('evidence', 'public');

            InquiryEvidence::create([
                'inquiry_id' => $inquiry->id,
                'uploaded_by' => Auth::id(),
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ]);
        }

        InquiryActivityLog::create([
            'inquiry_id' => $inquiry->id,
            'user_id' => Auth::id(),
            'action' => 'submitted',
            'to_status' => InquiryStatus::Submitted->value,
            'notes' => 'Inquiry submitted by public user.',
        ]);

        session()->flash('status', 'Inquiry submitted successfully.');

        $this->redirect(route('inquiries.show', $inquiry), navigate: true);
    }

    public function getCategoriesProperty(): array
    {
        return InquiryCategory::cases();
    }
}; ?>

<div>
    <h1 class="font-display font-extrabold text-2xl text-gray-900">{{ __('Submit a New Inquiry') }}</h1>
    <p class="text-gray-500 text-sm mt-1 mb-7">{{ __('Help us verify the news — fill in the details below.') }}</p>

    <form wire:submit="submit" class="bg-white border border-gray-100 rounded-2xl p-7 max-w-2xl space-y-5">
        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('News Title / Headline') }} *</label>
            <input type="text" wire:model="title" placeholder="{{ __('e.g. Claim about...') }}"
                class="w-full rounded-lg border-gray-300 focus:border-brand focus:ring-brand text-sm" />
            <x-input-error :messages="$errors->get('title')" class="mt-1.5" />
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Category') }} *</label>
            <select wire:model="category" class="w-full rounded-lg border-gray-300 focus:border-brand focus:ring-brand text-sm">
                <option value="">{{ __('Select a category') }}</option>
                @foreach ($this->categories as $case)
                    <option value="{{ $case->value }}">{{ $case->value }}</option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('category')" class="mt-1.5" />
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Description') }} *</label>
            <textarea wire:model="description" rows="5" placeholder="{{ __('Describe the news you want verified, including where you encountered it') }}"
                class="w-full rounded-lg border-gray-300 focus:border-brand focus:ring-brand text-sm"></textarea>
            <x-input-error :messages="$errors->get('description')" class="mt-1.5" />
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Source Link (optional)') }}</label>
            <input type="text" wire:model="source_url" placeholder="https://..."
                class="w-full rounded-lg border-gray-300 focus:border-brand focus:ring-brand text-sm" />
            <x-input-error :messages="$errors->get('source_url')" class="mt-1.5" />
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Upload Evidence') }}</label>
            <label class="block border-2 border-dashed border-gray-300 rounded-xl p-6 text-center text-gray-500 cursor-pointer hover:border-brand bg-gray-50">
                <input type="file" wire:model="evidence" multiple class="hidden" accept=".jpg,.jpeg,.png,.pdf" />
                <div class="text-sm font-semibold text-gray-600">{{ __('Click to browse files') }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ __('Images, PDFs supported — max 10MB each') }}</div>
            </label>
            <x-input-error :messages="$errors->get('evidence.*')" class="mt-1.5" />

            <div wire:loading wire:target="evidence" class="text-xs text-gray-400 mt-2">{{ __('Uploading...') }}</div>

            @if (count($evidence))
                <div class="flex flex-wrap gap-2 mt-3">
                    @foreach ($evidence as $index => $file)
                        <span class="inline-flex items-center gap-2 bg-gray-100 rounded-full px-3 py-1.5 text-xs text-gray-700">
                            {{ $file->getClientOriginalName() }}
                            <button type="button" wire:click="removeEvidence({{ $index }})" class="text-gray-400 hover:text-brand font-bold">×</button>
                        </span>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-6 py-3 rounded-lg" wire:loading.attr="disabled" wire:target="submit">
                {{ __('Submit Inquiry') }}
            </button>
            <a href="{{ route('dashboard') }}" wire:navigate class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-6 py-3 rounded-lg">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</div>
