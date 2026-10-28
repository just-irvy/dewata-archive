<?php

namespace Tests\Feature;

use App\Photos\ImageProcessor;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ImageProcessorTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/proc-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*'));
        rmdir($this->dir);
        parent::tearDown();
    }

    private function make(string $name, int $w, int $h, string $type = 'jpg'): string
    {
        $path = "{$this->dir}/{$name}";
        $im = imagecreatetruecolor($w, $h);
        imagefilledrectangle($im, 0, 0, intdiv($w, 2), $h, imagecolorallocate($im, 200, 30, 30));
        match ($type) {
            'jpg' => imagejpeg($im, $path, 90),
            'png' => imagepng($im, $path),
            'webp' => imagewebp($im, $path),
        };

        return $path;
    }

    private function withOrientation(string $jpeg, int $orientation): void
    {
        $data = file_get_contents($jpeg);
        $tiff = "MM\x00\x2A\x00\x00\x00\x08\x00\x01\x01\x12\x00\x03\x00\x00\x00\x01".pack('n', $orientation)."\x00\x00\x00\x00\x00\x00";
        $exif = "Exif\x00\x00".$tiff;
        file_put_contents($jpeg, substr($data, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($data, 2));
    }

    public function test_wide_photos_are_scaled_down_proportionally(): void
    {
        $result = ImageProcessor::process($this->make('b.jpg', 3000, 1500), "{$this->dir}/out.jpg", 2000);

        $this->assertSame([2000, 1000], [$result['width'], $result['height']]);
        $this->assertSame(2000, getimagesize("{$this->dir}/out.jpg")[0]);
    }

    public function test_small_photos_keep_their_size(): void
    {
        $result = ImageProcessor::process($this->make('s.png', 400, 300, 'png'), "{$this->dir}/out.png", 2000);

        $this->assertSame([400, 300, 'png'], [$result['width'], $result['height'], $result['ext']]);
    }

    public function test_phone_photos_are_rotated_by_their_exif_orientation(): void
    {
        $source = $this->make('p.jpg', 300, 200);
        $this->withOrientation($source, 6);

        $result = ImageProcessor::process($source, "{$this->dir}/out.jpg", 2000);

        $this->assertSame([200, 300], [$result['width'], $result['height']]);
    }

    public function test_metadata_is_dropped_when_the_photo_is_saved_again(): void
    {
        $source = $this->make('m.jpg', 300, 200);
        $this->withOrientation($source, 6);

        ImageProcessor::process($source, "{$this->dir}/out.jpg", 2000);

        $this->assertEmpty(@exif_read_data("{$this->dir}/out.jpg")['Orientation'] ?? null);
    }

    public function test_only_real_jpg_png_and_webp_files_are_accepted(): void
    {
        file_put_contents("{$this->dir}/palsu.jpg", 'ini bukan gambar');
        file_put_contents("{$this->dir}/x.svg", '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>');
        imagegif(imagecreatetruecolor(4, 4), "{$this->dir}/g.gif");

        foreach (['palsu.jpg', 'x.svg', 'g.gif'] as $file) {
            try {
                ImageProcessor::inspect("{$this->dir}/{$file}");
                $this->fail("{$file} seharusnya ditolak");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
