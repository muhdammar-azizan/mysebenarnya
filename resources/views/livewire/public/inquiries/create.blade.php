<?php

use App\Enums\InquiryCategory;
use App\Enums\InquiryStatus;
use App\Enums\UserRole;
use App\Models\Inquiry;
use App\Models\InquiryActivityLog;
use App\Models\User;
use App\Notifications\NewInquirySubmitted;
use App\Rules\SafeImageDimensions;
use App\Support\EvidenceStorage;
use App\Support\Throttle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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

    public string $submissionError = '';

    public bool $hasDraft = false;

    public function mount(): void
    {
        $draft = Auth::user()->inquiryDraft;

        if ($draft) {
            $this->title = (string) $draft->title;
            $this->category = (string) $draft->category;
            $this->description = (string) $draft->description;
            $this->source_url = (string) $draft->source_url;
            $this->hasDraft = true;
        }
    }

    public function saveDraft(): void
    {
        $this->validate([
            'title' => 'nullable|string|max:255',
            'category' => ['nullable', 'string', Rule::in(array_map(fn ($c) => $c->value, InquiryCategory::cases()))],
            'description' => 'nullable|string|max:10000',
            'source_url' => 'nullable|url|max:2048',
        ]);

        if (blank($this->title) && blank($this->description) && blank($this->source_url) && blank($this->category)) {
            $this->addError('title', __('Fill in at least one field before saving a draft.'));

            return;
        }

        Auth::user()->inquiryDraft()->updateOrCreate([], [
            'title' => $this->title ?: null,
            'category' => $this->category ?: null,
            'description' => $this->description ?: null,
            'source_url' => $this->source_url ?: null,
        ]);

        $this->hasDraft = true;
        session()->flash('draftStatus', __('Draft saved. Evidence files are not saved, so re-attach them before submitting.'));
    }

    public function discardDraft(): void
    {
        Auth::user()->inquiryDraft()?->delete();

        $this->reset(['title', 'category', 'description', 'source_url', 'evidence']);
        $this->resetValidation();
        $this->hasDraft = false;
        session()->flash('draftStatus', __('Draft discarded.'));
    }

    public function removeEvidence(int $index): void
    {
        unset($this->evidence[$index]);
        $this->evidence = array_values($this->evidence);
    }

    public function submit(): void
    {
        $this->submissionError = '';

        $this->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'description' => 'required|string|min:20',
            'source_url' => 'nullable|url|max:2048',
            'evidence' => 'array',
            'evidence.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,pdf', new SafeImageDimensions],
        ]);

        if (count($this->evidence) === 0 && blank($this->source_url)) {
            $this->addError('evidence', __('Supporting evidence is required. Upload a document or image, or add a source link.'));

            return;
        }

        if (($wait = Throttle::limited('submit_inquiry')) !== null) {
            $this->submissionError = Throttle::message($wait);

            return;
        }

        app(EvidenceStorage::class)->assertWithinLimits(Auth::user(), count($this->evidence), 0, 'evidence');

        try {
            $inquiry = DB::transaction(function () {
                $inquiry = Inquiry::create([
                    'submitted_by' => Auth::id(),
                    'title' => $this->title,
                    'category' => $this->category,
                    'description' => $this->description,
                    'source_url' => $this->source_url ?: null,
                    'status' => InquiryStatus::Submitted,
                ]);

                foreach (array_values($this->evidence) as $index => $file) {
                    app(EvidenceStorage::class)->store($inquiry, Auth::user(), $file, $index + 1, 'evidence');
                }

                return $inquiry;
            });

            InquiryActivityLog::create([
                'inquiry_id' => $inquiry->id,
                'user_id' => Auth::id(),
                'action' => 'submitted',
                'to_status' => InquiryStatus::Submitted->value,
                'notes' => 'Inquiry submitted by public user.',
            ]);

            User::where('role', UserRole::McmcStaff)
                ->get()
                ->each(function (User $staff) use ($inquiry) {
                    if ($staff->wantsNotification('newInquiry')) {
                        $staff->notify(new NewInquirySubmitted($inquiry));
                    }
                });
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            $this->submissionError = __('Something went wrong while submitting your inquiry. Please try again, or contact support if the issue persists.');

            return;
        }

        Auth::user()->inquiryDraft()?->delete();

        session()->flash('status', __('Inquiry submitted successfully.'));

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

    @if (session('draftStatus'))
        <div class="max-w-2xl mb-4 bg-green-50 border border-green-100 text-green-700 text-sm font-semibold rounded-xl px-4 py-3">{{ session('draftStatus') }}</div>
    @endif

    @if ($hasDraft)
        <div class="max-w-2xl mb-4 bg-amber-50 border border-amber-100 text-amber-800 text-sm rounded-xl px-4 py-3 flex flex-wrap items-center justify-between gap-2">
            <span>{{ __('You are editing a saved draft.') }}</span>
            <button type="button" wire:click="discardDraft" wire:confirm="{{ __('Discard this draft?') }}" class="font-bold hover:underline">{{ __('Discard draft') }}</button>
        </div>
    @endif

    <form wire:submit="submit" class="bg-white border border-gray-100 rounded-2xl p-4 sm:p-7 max-w-2xl space-y-5">
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
                    <option value="{{ $case->value }}">{{ $case->label() }}</option>
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
            <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Source Link') }}</label>
            <input type="text" wire:model="source_url" placeholder="https://..."
                class="w-full rounded-lg border-gray-300 focus:border-brand focus:ring-brand text-sm" />
            <x-input-error :messages="$errors->get('source_url')" class="mt-1.5" />
        </div>

        <div>
            <label class="block text-sm font-bold text-gray-700 mb-1.5">{{ __('Upload Evidence') }} <span class="font-normal text-gray-400">{{ __('(upload a file or add a source link above — at least one is required)') }}</span></label>
            <x-demo-notice class="mb-3" />
            <label class="block border-2 border-dashed border-gray-300 rounded-xl p-6 text-center text-gray-500 cursor-pointer hover:border-brand bg-gray-50">
                <input type="file" wire:model="evidence" multiple class="hidden" accept=".jpg,.jpeg,.png,.pdf" />
                <div class="text-sm font-semibold text-gray-600">{{ __('Click to browse files') }}</div>
                <div class="text-xs text-gray-400 mt-1">{{ __('Images, PDFs supported — max 10MB each, up to :count files', ['count' => config('uploads.max_files_per_submission')]) }}</div>
            </label>
            <x-input-error :messages="$errors->get('evidence.*')" class="mt-1.5" />
            <x-input-error :messages="$errors->get('evidence')" class="mt-1.5" />

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

        <x-form-error-banner :message="$submissionError" retry="submit" />

        <div class="flex flex-wrap gap-3 pt-2">
            <button type="submit" class="bg-brand hover:bg-brand-dark text-white font-bold text-sm px-6 py-3 rounded-lg" wire:loading.attr="disabled" wire:target="submit">
                {{ __('Submit Inquiry') }}
            </button>
            <button type="button" wire:click="saveDraft" wire:loading.attr="disabled" wire:target="saveDraft" class="border border-brand text-brand hover:bg-brand-light font-bold text-sm px-6 py-3 rounded-lg">
                {{ __('Save as Draft') }}
            </button>
            <a href="{{ route('dashboard') }}" wire:navigate class="border border-gray-300 hover:bg-gray-50 text-gray-700 font-bold text-sm px-6 py-3 rounded-lg">
                {{ __('Cancel') }}
            </a>
        </div>
    </form>
</div>
