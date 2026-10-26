<?php

namespace App\Content;

use App\Models\Collection;
use Illuminate\Database\Eloquent\Model;

final class CollectionReadiness implements Readiness
{
    public function check(Model $record): ReadinessReport
    {
        /** @var Collection $collection */
        $collection = $record->loadMissing(['places', 'coverMedia']);

        $blockers = [];
        $warnings = [];

        if ($collection->places->isEmpty()) {
            $blockers[] = 'Koleksi belum berisi tempat.';
        }

        foreach ($collection->places->reject->isPublished() as $place) {
            $blockers[] = "Tempat \"{$place->name}\" belum terbit.";
        }

        if (blank($collection->dek)) {
            $warnings[] = 'Pengantar (dek) belum diisi.';
        }
        if ($collection->coverMedia === null) {
            $warnings[] = 'Belum ada foto sampul.';
        } elseif (! $collection->coverMedia->isPublishable()) {
            $blockers[] = 'Foto sampul belum punya alt text dan keterangan hak pakai.';
        }

        return new ReadinessReport($blockers, $warnings);
    }
}
