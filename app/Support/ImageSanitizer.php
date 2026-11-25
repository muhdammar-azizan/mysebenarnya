<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Re-encodes an uploaded image into a brand-new file. GD never copies the
 * original metadata, so EXIF data (GPS location, camera model, timestamps)
 * and anything hidden inside the file is dropped.
 */
class ImageSanitizer
{
    /**
     * Re-encode the image and store it. Returns the stored path and extension.
     *
     * @return array{path: string, extension: string}
     */
    public function store(UploadedFile $file, string $disk, string $directory, ?int $maxSide = null): array
    {
        ['data' => $data, 'extension' => $extension] = $this->clean((string) $file->getRealPath(), $maxSide);

        $path = trim($directory, '/').'/'.Str::random(40).'.'.$extension;

        Storage::disk($disk)->put($path, $data);

        return ['path' => $path, 'extension' => $extension];
    }

    /**
     * @return array{data: string, extension: string}
     */
    public function clean(string $path, ?int $maxSide = null): array
    {
        $info = @getimagesize($path);

        if ($info === false) {
            throw new InvalidArgumentException('The file is not an image.');
        }

        [$width, $height, $type] = $info;

        if (($width * $height * 5) > $this->memoryBudget()) {
            throw new InvalidArgumentException('The image is too large to process safely.');
        }

        $image = match ($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_WEBP => @imagecreatefromwebp($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            default => false,
        };

        if ($image === false) {
            throw new InvalidArgumentException('The image could not be read.');
        }

        // Phones store the rotation in EXIF, so apply it before the metadata is lost.
        if ($type === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
            $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

            if ($angle !== 0 && ($rotated = imagerotate($image, $angle, 0)) !== false) {
                $image = $rotated;
            }
        }

        $isJpeg = $type === IMAGETYPE_JPEG;
        $image = $this->resize($image, $maxSide, ! $isJpeg);

        ob_start();
        $isJpeg ? imagejpeg($image, null, 88) : imagepng($image, null, 6);
        $data = (string) ob_get_clean();

        return ['data' => $data, 'extension' => $isJpeg ? 'jpg' : 'png'];
    }

    private function resize(\GdImage $image, ?int $maxSide, bool $keepAlpha): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = $maxSide ? min(1, $maxSide / max($width, $height)) : 1;

        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        if ($keepAlpha) {
            imagealphablending($canvas, false);
            imagesavealpha($canvas, true);
            imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        }

        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        return $canvas;
    }

    /** Bytes we are willing to spend decoding one image (60% of PHP's memory limit). */
    private function memoryBudget(): int
    {
        $limit = ini_get('memory_limit');

        if ($limit === '-1' || $limit === false) {
            return PHP_INT_MAX;
        }

        $bytes = (int) $limit;

        $bytes *= match (strtoupper(substr(trim($limit), -1))) {
            'G' => 1024 ** 3,
            'M' => 1024 ** 2,
            'K' => 1024,
            default => 1,
        };

        return (int) ($bytes * 0.6);
    }
}
