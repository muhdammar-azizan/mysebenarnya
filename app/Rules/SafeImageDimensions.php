<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * Rejects image "bombs": files that are small on disk but expand to a huge
 * number of pixels. Non-image files (such as PDFs) pass through untouched.
 */
class SafeImageDimensions implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->getRealPath()) {
            return;
        }

        $size = @getimagesize($value->getRealPath());

        if ($size === false) {
            return;
        }

        [$width, $height] = $size;
        $side = (int) config('uploads.max_image_side');
        $pixels = (int) config('uploads.max_image_pixels');

        if ($width > $side || $height > $side || ($width * $height) > $pixels) {
            $fail(__('The image is too large. The maximum is :side pixels on each side and :megapixels megapixels.', [
                'side' => $side,
                'megapixels' => (int) round($pixels / 1_000_000),
            ]));
        }
    }
}
