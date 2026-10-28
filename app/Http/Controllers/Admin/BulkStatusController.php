<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ChangeStatus;
use App\Enums\PublishStatus;
use App\Exceptions\NotReadyToPublish;
use App\Http\Controllers\Controller;
use App\Models\Hotel;
use App\Models\Place;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Menerbitkan banyak draf sekaligus. Setiap record tetap melewati ChangeStatus, jadi pemeriksaan
 * kesiapan, hak akses, dan audit log berlaku sama seperti menerbitkan satu per satu. Yang tidak
 * memenuhi syarat dilewati dan alasannya dilaporkan, bukan dipaksa terbit.
 */
class BulkStatusController extends Controller
{
    private const MODELS = ['places' => Place::class, 'hotels' => Hotel::class];

    public function __invoke(Request $request, string $type, ChangeStatus $change): RedirectResponse
    {
        $data = $request->validate([
            'scope' => ['required', Rule::in(['selected', 'ready'])],
            'ids' => ['required_if:scope,selected', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'region' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        /** @var class-string<Place|Hotel> $model */
        $model = self::MODELS[$type];
        $limit = (int) config('archive.bulk_publish_limit', 300);

        // Hanya draf yang diproses. Yang sudah terbit atau diarsipkan tidak disentuh.
        $query = $model::query()->where('status', PublishStatus::Draft->value)
            ->when($data['scope'] === 'selected', fn (Builder $q) => $q->whereIn('id', $data['ids'] ?? []))
            ->when($data['scope'] === 'ready' && ! empty($data['region']), fn (Builder $q) => $q->where('region_id', $data['region']))
            ->when($data['scope'] === 'ready' && filled($data['q'] ?? null), fn (Builder $q) => $q->where('name', 'like', '%'.addcslashes($data['q'], '%_\\').'%'))
            ->orderBy('id');

        $total = (clone $query)->count();
        $records = $query->limit($limit)->get();

        $published = 0;
        $reasons = [];
        $blocked = [];

        foreach ($records as $record) {
            Gate::authorize('publish', $record);

            try {
                $change($record, PublishStatus::Published);
                $published++;
            } catch (NotReadyToPublish $e) {
                foreach ($e->report->blockers as $message) {
                    // Pesan foto memuat id unik. Disatukan agar laporan tidak berulang per foto.
                    $key = preg_replace('/^Foto #\d+/', 'Foto', $message);
                    $reasons[$key] = ($reasons[$key] ?? 0) + 1;
                }
                $blocked[] = $record->name;
            }
        }

        arsort($reasons);

        return back()->with('bulk_report', [
            'type' => $type,
            'published' => $published,
            'blocked' => count($blocked),
            'reasons' => $reasons,
            'samples' => array_slice($blocked, 0, 8),
            'remaining' => max(0, $total - $records->count()),
        ]);
    }
}
