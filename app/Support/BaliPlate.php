<?php

namespace App\Support;

/**
 * Lembar peta Pulau Bali untuk beranda, digambar dari data garis pantai Natural Earth
 * (domain publik). Proyeksi equirectangular dengan koreksi cos(lintang) di tengah pulau,
 * sehingga skala 20 km di sudut lembar akurat untuk ilustrasi.
 *
 * Titik tempat diproyeksikan dengan fungsi yang sama dengan garis pantai, jadi posisi
 * titik sesuai koordinat yang tersimpan di database.
 */
final class BaliPlate
{
    // Batas lembar, termasuk laut di sekitarnya untuk label perairan.
    private const LON = [114.30, 115.86];

    private const LAT = [-7.90, -8.98];

    private const WIDTH = 600;

    private const LAT0 = -8.42;

    private const KM_PER_DEGREE = 111.32;

    private float $k;

    private float $scale;

    public function __construct()
    {
        $this->k = cos(deg2rad(self::LAT0));
        $this->scale = self::WIDTH / ((self::LON[1] - self::LON[0]) * $this->k);
    }

    public function width(): int
    {
        return self::WIDTH;
    }

    public function height(): int
    {
        return (int) round((self::LAT[0] - self::LAT[1]) * $this->scale);
    }

    /** @return array{0: float, 1: float} x, y dalam satuan viewBox */
    public function project(float $lat, float $lng): array
    {
        return [
            round(($lng - self::LON[0]) * $this->k * $this->scale, 1),
            round((self::LAT[0] - $lat) * $this->scale, 1),
        ];
    }

    public function contains(float $lat, float $lng): bool
    {
        return $lng >= self::LON[0] && $lng <= self::LON[1] && $lat <= self::LAT[0] && $lat >= self::LAT[1];
    }

    /** Garis pantai sebagai path SVG yang dihaluskan (kurva Catmull-Rom). */
    public function coastline(): array
    {
        static $paths = null;
        if ($paths !== null) {
            return $paths;
        }

        $data = json_decode(file_get_contents(resource_path('data/bali-outline.json')), true);

        return $paths = array_map(fn (array $ring) => $this->smooth($ring), $data['rings']);
    }

    /** Garis bujur dan lintang tiap 15 menit busur, dengan label. */
    public function graticule(): array
    {
        $lines = [];
        foreach ([114.5, 114.75, 115.0, 115.25, 115.5, 115.75] as $lng) {
            [$x] = $this->project(self::LAT0, $lng);
            $lines[] = ['x1' => $x, 'y1' => 0, 'x2' => $x, 'y2' => $this->height(), 'label' => $this->dms($lng, 'BT'), 'axis' => 'lng'];
        }
        foreach ([-8.0, -8.25, -8.5, -8.75] as $lat) {
            [, $y] = $this->project($lat, self::LON[0]);
            $lines[] = ['x1' => 0, 'y1' => $y, 'x2' => self::WIDTH, 'y2' => $y, 'label' => $this->dms(abs($lat), 'LS'), 'axis' => 'lat'];
        }

        return $lines;
    }

    /** Panjang 20 km dalam satuan viewBox. */
    public function scaleBar(int $km = 20): float
    {
        return round($km / self::KM_PER_DEGREE * $this->scale, 1);
    }

    private function dms(float $deg, string $hemisphere): string
    {
        $d = (int) floor($deg);
        $m = (int) round(($deg - $d) * 60);

        return sprintf('%d°%02d′ %s', $d, $m, $hemisphere);
    }

    private function smooth(array $ring): string
    {
        $pts = array_map(fn ($c) => $this->project($c[1], $c[0]), array_slice($ring, 0, -1));
        $n = count($pts);
        $d = sprintf('M%.1f,%.1f', $pts[0][0], $pts[0][1]);

        for ($i = 0; $i < $n; $i++) {
            $p0 = $pts[($i - 1 + $n) % $n];
            $p1 = $pts[$i];
            $p2 = $pts[($i + 1) % $n];
            $p3 = $pts[($i + 2) % $n];
            $d .= sprintf(
                'C%.1f,%.1f %.1f,%.1f %.1f,%.1f',
                $p1[0] + ($p2[0] - $p0[0]) / 6, $p1[1] + ($p2[1] - $p0[1]) / 6,
                $p2[0] - ($p3[0] - $p1[0]) / 6, $p2[1] - ($p3[1] - $p1[1]) / 6,
                $p2[0], $p2[1],
            );
        }

        return $d.'Z';
    }
}
