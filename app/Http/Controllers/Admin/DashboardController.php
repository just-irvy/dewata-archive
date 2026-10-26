<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Hotel;
use App\Models\Place;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $days = config('archive.review_after_days');

        // Daftar tinjauan (FILE 16, langkah 5): konten terbit yang ditandai perlu ditinjau
        // atau yang verifikasinya lebih tua dari ambang.
        $stalePlaces = Place::query()->published()->with('region')
            ->where(fn ($q) => $q
                ->where('verification_status', 'needs_review')
                ->orWhereNull('verified_at')
                ->orWhere('verified_at', '<', now()->subDays($days['place'])))
            ->orderBy('verified_at')->limit(10)->get();

        $staleHotels = Hotel::query()->published()->with('region')
            ->whereNotNull('price_min')
            ->where(fn ($q) => $q
                ->where('price_status', 'needs_review')
                ->orWhereNull('verified_at')
                ->orWhere('verified_at', '<', now()->subDays($days['hotel_price'])))
            ->orderBy('verified_at')->limit(10)->get();

        return view('admin.index', [
            'counts' => [
                'Tempat' => $this->countByStatus(Place::class),
                'Hotel' => $this->countByStatus(Hotel::class),
                'Koleksi' => $this->countByStatus(Collection::class),
            ],
            'stalePlaces' => $stalePlaces,
            'staleHotels' => $staleHotels,
            'days' => $days,
        ]);
    }

    /** @return array<string, int> */
    private function countByStatus(string $model): array
    {
        $counts = $model::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return [
            'draft' => (int) ($counts['draft'] ?? 0),
            'published' => (int) ($counts['published'] ?? 0),
            'archived' => (int) ($counts['archived'] ?? 0),
        ];
    }
}
