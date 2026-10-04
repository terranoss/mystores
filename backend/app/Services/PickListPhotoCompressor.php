<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Resize and JPEG-compress pick-list photos toward a ~100 KB target.
 */
class PickListPhotoCompressor
{
    public const TARGET_BYTES = 100 * 1024;

    public const MAX_EDGE = 1280;

    /**
     * Compress an uploaded image to a local temp JPEG path.
     * Returns null if compression is unavailable or fails (caller should fall back).
     */
    public function compressToTempJpeg(UploadedFile $file): ?string
    {
        if (! extension_loaded('gd') || ! function_exists('imagecreatefromstring')) {
            return null;
        }

        $binary = @file_get_contents($file->getRealPath());
        if ($binary === false || $binary === '') {
            return null;
        }

        $source = @imagecreatefromstring($binary);
        if ($source === false) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1) {
            imagedestroy($source);

            return null;
        }

        $scaled = $this->scaleDown($source, $width, $height);
        if ($scaled !== $source) {
            imagedestroy($source);
        }

        $tempPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pick-'.Str::uuid()->toString().'.jpg';
        $quality = 75;
        $written = false;

        while ($quality >= 40) {
            if (! imagejpeg($scaled, $tempPath, $quality)) {
                break;
            }
            $written = true;
            clearstatcache(true, $tempPath);
            $size = filesize($tempPath);
            if ($size !== false && $size <= self::TARGET_BYTES) {
                break;
            }
            $quality -= 10;
        }

        imagedestroy($scaled);

        if (! $written || ! is_file($tempPath)) {
            return null;
        }

        return $tempPath;
    }

    /**
     * Store a compressed JPEG on the given disk, or fall back to the original upload.
     *
     * @return string Stored relative path
     */
    public function store(UploadedFile $file, string $directory, string $disk = 'local'): string
    {
        $tempPath = null;
        try {
            $tempPath = $this->compressToTempJpeg($file);
            if ($tempPath !== null) {
                $relative = trim($directory, '/').'/'.Str::uuid()->toString().'.jpg';
                $stream = fopen($tempPath, 'rb');
                if ($stream !== false) {
                    \Illuminate\Support\Facades\Storage::disk($disk)->put($relative, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }

                    return $relative;
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Pick list photo compression failed; storing original.', [
                'message' => $e->getMessage(),
            ]);
        } finally {
            if ($tempPath !== null && is_file($tempPath)) {
                @unlink($tempPath);
            }
        }

        return $file->store($directory, $disk);
    }

    /**
     * @param  \GdImage|resource  $source
     * @return \GdImage|resource
     */
    private function scaleDown($source, int $width, int $height)
    {
        $maxEdge = max($width, $height);
        if ($maxEdge <= self::MAX_EDGE) {
            return $source;
        }

        $scale = self::MAX_EDGE / $maxEdge;
        $newW = max(1, (int) round($width * $scale));
        $newH = max(1, (int) round($height * $scale));
        $dest = imagecreatetruecolor($newW, $newH);
        if ($dest === false) {
            return $source;
        }

        imagecopyresampled($dest, $source, 0, 0, 0, 0, $newW, $newH, $width, $height);

        return $dest;
    }
}
