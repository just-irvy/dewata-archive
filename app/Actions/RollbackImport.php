<?php

namespace App\Actions;

use App\Models\Hotel;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Place;
use App\Models\TripPlanItem;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Tahap 6 FILE 16: strategi pemulihan. Hanya record yang masih draf, belum pernah diubah sejak
 * diimpor, tanpa foto, dan belum dipakai di rencana atau koleksi yang dihapus permanen.
 * Selebihnya dibiarkan dan dilaporkan, karena sudah menjadi pekerjaan editor.
 */
final class RollbackImport
{
    public function __construct(private readonly RecordAudit $audit) {}

    /** @return array{removed: int, kept: array<int, array{name: string, reason: string}>} */
    public function __invoke(ImportBatch $batch, User $actor): array
    {
        if (! $batch->isCommitted()) {
            throw new DomainException('Hanya batch yang sudah di-commit yang bisa di-rollback.');
        }

        return DB::transaction(function () use ($batch, $actor) {
            $removed = 0;
            $kept = [];

            $rows = $batch->rows()->where('action', 'created')->whereNotNull('record_id')->get();

            foreach ($rows as $row) {
                $record = $this->find($row);

                if ($record === null) {
                    $row->update(['action' => 'rolled_back']);

                    continue;
                }

                $reason = $this->blocker($record);
                if ($reason !== null) {
                    $kept[] = ['name' => $record->name, 'reason' => $reason];

                    continue;
                }

                $record->forceDelete();
                $row->update(['action' => 'rolled_back']);
                $removed++;
            }

            $batch->forceFill([
                'status' => 'rolled_back',
                'rolled_back_by' => $actor->id,
                'rolled_back_at' => now(),
            ])->save();

            ($this->audit)('import_batch.rolled_back', $batch, ['status' => 'committed'], [
                'status' => 'rolled_back', 'removed' => $removed, 'kept' => count($kept),
            ]);

            return ['removed' => $removed, 'kept' => $kept];
        });
    }

    private function find(ImportRow $row): Place|Hotel|null
    {
        $model = $row->entity === 'hotel' ? Hotel::class : Place::class;

        return $model::withTrashed()->find($row->record_id);
    }

    private function blocker(Place|Hotel $record): ?string
    {
        if ($record->status->value !== 'draft') {
            return 'Sudah bukan draf.';
        }
        if (! $record->updated_at->equalTo($record->created_at)) {
            return 'Sudah diubah editor sejak diimpor.';
        }
        if ($record->media()->exists()) {
            return 'Sudah punya foto.';
        }
        if ($record instanceof Place) {
            if (TripPlanItem::query()->where('place_id', $record->id)->exists()) {
                return 'Dipakai di rencana pengguna.';
            }
            if ($record->collections()->exists()) {
                return 'Ada di koleksi.';
            }
        }

        return null;
    }
}
