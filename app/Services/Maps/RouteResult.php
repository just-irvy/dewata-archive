<?php

namespace App\Services\Maps;

/** Hasil rute dari penyedia. Gagal selalu berupa kode, bukan exception, agar pemanggil tidak jatuh. */
final class RouteResult
{
    /**
     * @param  array<int, array{distance_m: int, duration_s: int}>  $legs
     */
    private function __construct(
        public readonly bool $ok,
        public readonly array $legs = [],
        public readonly ?string $error = null,
    ) {}

    /** @param  array<int, array{distance_m: int, duration_s: int}>  $legs */
    public static function ok(array $legs): self
    {
        return new self(true, $legs);
    }

    public static function failed(string $code): self
    {
        return new self(false, [], $code);
    }

    /** Total jarak adalah jumlah nilai leg, bukan selisih koordinat. */
    public function distanceMeters(): int
    {
        return array_sum(array_column($this->legs, 'distance_m'));
    }

    public function durationSeconds(): int
    {
        return array_sum(array_column($this->legs, 'duration_s'));
    }
}
