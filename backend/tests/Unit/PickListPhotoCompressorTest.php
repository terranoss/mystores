<?php

namespace Tests\Unit;

use App\Services\PickListPhotoCompressor;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PickListPhotoCompressorTest extends TestCase
{
    public function test_compresses_large_image_near_100kb_target(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required.');
        }

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'pick-src-'.uniqid('', true).'.jpg';
        $img = imagecreatetruecolor(2400, 1800);
        for ($y = 0; $y < 1800; $y += 20) {
            $color = imagecolorallocate($img, ($y * 3) % 255, 80, 160);
            imagefilledrectangle($img, 0, $y, 2399, $y + 19, $color);
        }
        imagejpeg($img, $path, 95);
        imagedestroy($img);

        $this->assertGreaterThan(100 * 1024, filesize($path));

        $file = new UploadedFile($path, 'large.jpg', 'image/jpeg', null, true);
        $compressor = new PickListPhotoCompressor();
        $temp = $compressor->compressToTempJpeg($file);

        $this->assertNotNull($temp);
        $this->assertFileExists($temp);
        $size = filesize($temp);
        $this->assertNotFalse($size);
        $this->assertLessThanOrEqual(PickListPhotoCompressor::TARGET_BYTES, $size);
        $this->assertGreaterThan(5 * 1024, $size);

        @unlink($temp);
        @unlink($path);
    }

    public function test_store_writes_jpeg_under_target(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('GD extension required.');
        }

        $upload = UploadedFile::fake()->image('pick.png', 2000, 1500);
        $compressor = new PickListPhotoCompressor();
        $relative = $compressor->store($upload, 'picklist-photos/test', 'local');

        $this->assertTrue(\Illuminate\Support\Facades\Storage::disk('local')->exists($relative));
        $size = \Illuminate\Support\Facades\Storage::disk('local')->size($relative);
        $this->assertLessThanOrEqual(PickListPhotoCompressor::TARGET_BYTES, $size);
        \Illuminate\Support\Facades\Storage::disk('local')->delete($relative);
    }
}
