<?php

namespace App\Support;

use App\Models\Inquiry;
use App\Models\InquiryEvidence;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Single place that stores evidence files and enforces the upload limits.
 */
class EvidenceStorage
{
    public function __construct(private ImageSanitizer $images) {}

    /**
     * Fail early when this upload would break a limit.
     *
     * @param  int  $incoming  files in this submission
     * @param  int  $existing  evidence files already on the inquiry
     */
    public function assertWithinLimits(User $user, int $incoming, int $existing, string $errorKey): void
    {
        if ($incoming === 0) {
            return;
        }

        if ($incoming > (int) config('uploads.max_files_per_submission')) {
            $this->fail($errorKey, __('You can upload at most :count files at a time.', ['count' => config('uploads.max_files_per_submission')]));
        }

        if ($existing + $incoming > (int) config('uploads.max_files_per_inquiry')) {
            $this->fail($errorKey, __('This inquiry can have at most :count evidence files.', ['count' => config('uploads.max_files_per_inquiry')]));
        }

        $quota = (int) config($user->isDemo() ? 'uploads.daily_file_quota_demo' : 'uploads.daily_file_quota');
        $today = InquiryEvidence::where('uploaded_by', $user->id)->where('created_at', '>=', now()->subDay())->count();

        if ($today + $incoming > $quota) {
            $this->fail($errorKey, __('Daily upload limit reached. Please try again tomorrow.'));
        }
    }

    /**
     * Store one file on the private disk and create its evidence record.
     * Demo accounts get a re-encoded image (no EXIF) and a generic file name;
     * real accounts keep the original file so investigators have all the data.
     *
     * @param  int  $position  1-based position, used for the generic demo file name
     */
    public function store(Inquiry $inquiry, User $user, UploadedFile $file, int $position, string $errorKey): InquiryEvidence
    {
        $attributes = $user->isDemo()
            ? $this->storeSanitised($file, $position, $errorKey)
            : $this->storeOriginal($file);

        return InquiryEvidence::create($attributes + [
            'inquiry_id' => $inquiry->id,
            'uploaded_by' => $user->id,
        ]);
    }

    /** @return array<string, mixed> */
    private function storeOriginal(UploadedFile $file): array
    {
        return [
            'file_path' => $file->store('evidence', 'local'),
            'file_name' => $file->getClientOriginalName(),
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
        ];
    }

    /** @return array<string, mixed> */
    private function storeSanitised(UploadedFile $file, int $position, string $errorKey): array
    {
        if (@getimagesize((string) $file->getRealPath()) === false) {
            // Not an image (a PDF): stored as-is, but under a generic name.
            return [
                'file_path' => $file->store('evidence', 'local'),
                'file_name' => 'evidence-'.$position.'.pdf',
                'file_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ];
        }

        try {
            ['path' => $path, 'extension' => $extension] = $this->images->store($file, 'local', 'evidence', (int) config('uploads.max_image_side'));
        } catch (InvalidArgumentException) {
            $this->fail($errorKey, __('This image could not be processed. Please try a different one.'));
        }

        return [
            'file_path' => $path,
            'file_name' => 'evidence-'.$position.'.'.$extension,
            'file_type' => $extension === 'jpg' ? 'image/jpeg' : 'image/png',
            'file_size' => \Illuminate\Support\Facades\Storage::disk('local')->size($path),
        ];
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
