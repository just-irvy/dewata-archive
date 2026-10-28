<?php

namespace App\Content;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Builder;

final class HotelSearch
{
    public const SORTS = ['terbaru', 'nama', 'harga'];

    /**
     * @param  array{region?: ?string, type?: ?string, min?: ?int, max?: ?int, sort?: ?string}  $filters
     */
    public function __construct(private readonly array $filters = [])
    {
    }

    public function query(): Builder
    {
        $f = $this->filters;

        $query = Hotel::query()->published()->with(['region', 'media']);

        if (filled($f['region'] ?? null)) {
            $query->whereHas('region', fn (Builder $r) => $r->where('slug', $f['region']));
        }

        if (filled($f['type'] ?? null)) {
            $query->where('type', $f['type']);
        }

        // Filter harga memakai kisaran yang tumpang tindih dengan rentang pengguna.
        // Hotel tanpa data harga tidak ikut, karena tidak ada dasar menyatakan cocok.
        $min = $f['min'] ?? null;
        $max = $f['max'] ?? null;

        if ($min !== null || $max !== null) {
            $query->whereNotNull('price_min');

            if ($max !== null) {
                $query->where('price_min', '<=', $max);
            }
            if ($min !== null) {
                $query->whereRaw('coalesce(price_max, price_min) >= ?', [$min]);
            }
        }

        return match ($f['sort'] ?? 'terbaru') {
            'nama' => $query->orderBy('name'),
            'harga' => $query->orderByRaw('price_min is null')->orderBy('price_min')->orderBy('name'),
            default => $query->orderByDesc('id'),
        };
    }
}
