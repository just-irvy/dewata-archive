<?php

namespace App\Photos;

use InvalidArgumentException;

/**
 * Memeriksa dan menyiapkan foto: hanya JPG, PNG, dan WebP asli (dicek dari isi berkas, bukan ekstensi),
 * diperkecil bila terlalu lebar, dan metadata (termasuk lokasi GPS) ikut terbuang karena gambar disimpan ulang.
 * Orientasi EXIF pada JPG diterapkan sebelum metadata dibuang, agar foto tidak berputar.
 */
final class ImageProcessor
{
    public const MAX_SIDE = 8000;

    private const TYPES = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    /**
     * @return array{width: int, height: int, ext: string}
     *
     * @throws InvalidArgumentException
     */
    public static function inspect(string $path): array
    {
        $info = @getimagesize($path);

        if ($info === false || ! isset(self::TYPES[$info[2]])) {
            throw new InvalidArgumentException('bukan gambar JPG, PNG, atau WebP');
        }
        if ($info[0] > self::MAX_SIDE || $info[1] > self::MAX_SIDE) {
            throw new InvalidArgumentException('sisi gambar melebihi '.self::MAX_SIDE.' piksel');
        }

        return ['width' => $info[0], 'height' => $info[1], 'ext' => self::TYPES[$info[2]]];
    }

    /**
     * @return array{width: int, height: int, ext: string, note: ?string}
     *
     * @throws InvalidArgumentException
     */
    public static function process(string $source, string $destination, int $maxWidth = 2000): array
    {
        $meta = self::inspect($source);

        // GD membuka gambar sebagai bitmap penuh (sekitar 5 byte per piksel, lebih saat diperkecil). Gambar
        // 8000x8000 butuh ratusan MB; melebihi memory_limit menghasilkan fatal error yang tidak bisa ditangkap
        // dan tampil sebagai halaman 500. Ditolak lebih dulu dengan pesan yang jelas.
        if (! self::fitsInMemory($meta['width'], $meta['height'])) {
            throw new InvalidArgumentException('gambar terlalu besar untuk diproses server ini, perkecil ke lebar sekitar 4000 piksel');
        }

        // Tanpa dukungan yang dibutuhkan, berkas disalin apa adanya. Itu lebih baik daripada merusak orientasi.
        $canRecode = function_exists('imagecreatetruecolor')
            && ! ($meta['ext'] === 'jpg' && ! function_exists('exif_read_data'))
            && ! ($meta['ext'] === 'webp' && ! function_exists('imagewebp'));

        if (! $canRecode) {
            copy($source, $destination);

            return $meta + ['note' => 'berkas disalin apa adanya (ekstensi PHP gd atau exif tidak lengkap), metadata tidak dibuang'];
        }

        $image = match ($meta['ext']) {
            'jpg' => imagecreatefromjpeg($source),
            'png' => imagecreatefrompng($source),
            'webp' => imagecreatefromwebp($source),
        };

        if ($image === false) {
            throw new InvalidArgumentException('gambar rusak dan tidak bisa dibaca');
        }

        if ($meta['ext'] === 'jpg') {
            $image = self::applyOrientation($image, $source);
        }

        $width = imagesx($image);
        $height = imagesy($image);

        if ($width > $maxWidth) {
            $newHeight = (int) round($height * $maxWidth / $width);
            $resized = imagecreatetruecolor($maxWidth, $newHeight);

            if ($meta['ext'] !== 'jpg') {
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
            }

            imagecopyresampled($resized, $image, 0, 0, 0, 0, $maxWidth, $newHeight, $width, $height);
            $image = $resized;
            $width = $maxWidth;
            $height = $newHeight;
        }

        match ($meta['ext']) {
            'jpg' => (function () use ($image, $destination) {
                imageinterlace($image, true);
                imagejpeg($image, $destination, 85);
            })(),
            'png' => (function () use ($image, $destination) {
                imagesavealpha($image, true);
                imagepng($image, $destination, 6);
            })(),
            'webp' => imagewebp($image, $destination, 85),
        };

        return ['width' => $width, 'height' => $height, 'ext' => $meta['ext'], 'note' => null];
    }

    private static function applyOrientation(\GdImage $image, string $source): \GdImage
    {
        $exif = @exif_read_data($source);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        return imagerotate($image, $angle, 0) ?: $image;
    }

    /** Perkiraan apakah bitmap gambar (ditambah salinan saat diperkecil) muat di sisa memory_limit. */
    public static function fitsInMemory(int $width, int $height, ?string $limit = null): bool
    {
        $bytes = self::bytes($limit ?? (string) ini_get('memory_limit'));

        if ($bytes <= 0) {
            return true;
        }

        $needed = (int) ($width * $height * 5 * 1.8);

        return memory_get_usage(true) + $needed < $bytes;
    }

    private static function bytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;

        return match ($unit) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => (int) $value,
        };
    }
}
