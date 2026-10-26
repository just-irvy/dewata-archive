<?php

namespace App\Content;

use App\Enums\VerificationStatus;
use App\Models\Place;
use Illuminate\Database\Eloquent\Model;

/** Syarat terbit tempat (FILE 16, langkah 2 dan 4). */
final class PlaceReadiness implements Readiness
{
    public function check(Model $record): ReadinessReport
    {
        /** @var Place $place */
        $place = $record->loadMissing(['region', 'categories', 'media']);

        $blockers = [];
        $warnings = [];

        if (blank($place->summary)) {
            $blockers[] = 'Ringkasan belum diisi.';
        }
        if (! $place->region?->is_published) {
            $blockers[] = 'Wilayah belum terbit.';
        }
        if (! $place->categories->contains(fn ($category) => $category->is_published)) {
            $blockers[] = 'Pilih minimal satu kategori yang sudah terbit.';
        }
        if (blank($place->source_url)) {
            $blockers[] = 'Tautan sumber belum diisi.';
        }
        if ($place->verified_at === null) {
            $blockers[] = 'Tanggal verifikasi belum diisi.';
        }
        if ($place->verification_status === VerificationStatus::Unknown) {
            $blockers[] = 'Status verifikasi masih "Belum diketahui".';
        }
        if (abs($place->lat) > 90 || abs($place->lng) > 180 || ($place->lat == 0 && $place->lng == 0)) {
            $blockers[] = 'Koordinat tidak valid.';
        }

        foreach ($place->media as $media) {
            if (! $media->isPublishable()) {
                $blockers[] = "Foto #{$media->id} belum punya alt text dan keterangan hak pakai (kredit atau lisensi).";
            }
        }

        if ($place->verification_status === VerificationStatus::NeedsReview) {
            $warnings[] = 'Status verifikasi masih "Perlu ditinjau".';
        }
        if ($place->media->isEmpty()) {
            $warnings[] = 'Belum ada foto.';
        }
        if (! $this->insideBounds($place->lat, $place->lng)) {
            $warnings[] = 'Koordinat berada di luar kotak batas Bali. Pastikan benar.';
        }
        if ($place->opening_hours !== null && $place->opening_hours_status === VerificationStatus::Unknown) {
            $warnings[] = 'Jam buka terisi tetapi statusnya belum diketahui. Verifikasi atau kosongkan.';
        }
        if ($place->accessibility !== null && $place->accessibility_status === VerificationStatus::Unknown) {
            $warnings[] = 'Aksesibilitas terisi tetapi statusnya belum diketahui. Verifikasi atau kosongkan.';
        }

        return new ReadinessReport($blockers, $warnings);
    }

    private function insideBounds(float $lat, float $lng): bool
    {
        [$latMin, $latMax] = config('archive.bounds.lat');
        [$lngMin, $lngMax] = config('archive.bounds.lng');

        return $lat >= $latMin && $lat <= $latMax && $lng >= $lngMin && $lng <= $lngMax;
    }
}
