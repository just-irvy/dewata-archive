<?php

namespace App\Content;

use App\Enums\VerificationStatus;
use App\Models\Place;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pencarian dan penyaringan tempat terbit untuk halaman Jelajah dan wilayah.
 * Hanya tempat berstatus published yang pernah muncul dari kelas ini.
 */
final class PlaceSearch
{
    public const SORTS = ['terbaru', 'nama'];

    /**
     * @param  array{q?: ?string, region?: ?string, category?: ?string, tags?: array<int, string>, access?: array<int, string>, sort?: ?string}  $filters
     */
    public function __construct(private readonly array $filters = [])
    {
    }

    public function query(): Builder
    {
        $f = $this->filters;

        $query = Place::query()->published()->with(['region', 'categories', 'media']);

        if (filled($f['q'] ?? null)) {
            $term = '%'.addcslashes(trim($f['q']), '%_\\').'%';
            $query->where(fn (Builder $w) => $w->where('name', 'like', $term)->orWhere('summary', 'like', $term)->orWhere('area_label', 'like', $term));
        }

        if (filled($f['region'] ?? null)) {
            $query->whereHas('region', fn (Builder $r) => $r->where('slug', $f['region']));
        }

        if (filled($f['category'] ?? null)) {
            $query->whereHas('categories', fn (Builder $c) => $c->where('slug', $f['category']));
        }

        if (! empty($f['tags'])) {
            // Beberapa tema berarti "salah satu", agar katalog kecil tidak langsung kosong.
            $query->whereHas('tags', fn (Builder $t) => $t->whereIn('slug', $f['tags']));
        }

        // Aksesibilitas hanya dihitung bila statusnya terverifikasi. Kunci berasal dari allowlist.
        foreach ($f['access'] ?? [] as $key) {
            if (array_key_exists($key, config('archive.accessibility_attributes'))) {
                $query->where('accessibility_status', VerificationStatus::Verified->value)
                    ->where("accessibility->{$key}", 'yes');
            }
        }

        return match ($f['sort'] ?? 'terbaru') {
            'nama' => $query->orderBy('name'),
            default => $query->orderByDesc('published_at')->orderByDesc('id'),
        };
    }
}
