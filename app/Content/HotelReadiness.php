<?php

namespace App\Content;

use App\Enums\VerificationStatus;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Model;

final class HotelReadiness implements Readiness
{
    public function check(Model $record): ReadinessReport
    {
        /** @var Hotel $hotel */
        $hotel = $record->loadMissing(['region', 'media']);

        $blockers = [];
        $warnings = [];

        if (blank($hotel->summary)) {
            $blockers[] = 'Ringkasan belum diisi.';
        }
        if (! $hotel->region?->is_published) {
            $blockers[] = 'Wilayah belum terbit.';
        }
        if (blank($hotel->source_url)) {
            $blockers[] = 'Tautan sumber belum diisi.';
        }
        if ($hotel->verified_at === null) {
            $blockers[] = 'Tanggal verifikasi belum diisi.';
        }
        if (abs($hotel->lat) > 90 || abs($hotel->lng) > 180 || ($hotel->lat == 0 && $hotel->lng == 0)) {
            $blockers[] = 'Koordinat tidak valid.';
        }

        if ($hotel->hasPrice()) {
            if ($hotel->price_basis === null) {
                $blockers[] = 'Harga terisi tetapi jenis sumber harga belum dipilih.';
            }
            if ($hotel->price_status === VerificationStatus::Unknown) {
                $blockers[] = 'Harga terisi tetapi status verifikasi harga belum diketahui.';
            }
        } else {
            $warnings[] = 'Harga kosong. Hotel tampil dengan keterangan "harga tidak tersedia".';
        }

        foreach ($hotel->media as $media) {
            if (! $media->isPublishable()) {
                $blockers[] = "Foto #{$media->id} belum punya alt text dan keterangan hak pakai (kredit atau lisensi).";
            }
        }
        if ($hotel->media->isEmpty()) {
            $warnings[] = 'Belum ada foto.';
        }

        return new ReadinessReport($blockers, $warnings);
    }
}
