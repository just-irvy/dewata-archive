<?php

namespace App\Actions;

use App\Imports\CsvReader;
use App\Imports\DatasetMapper;
use App\Imports\ImportContext;
use App\Imports\MappedRow;
use App\Models\Hotel;
use App\Models\ImportBatch;
use App\Models\Place;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Tahap 2 dan 3 FILE 16: parse ke staging lalu validasi. Tidak ada record Place atau Hotel
 * yang dibuat di sini. Tabel live tidak tersentuh sampai admin menyetujui (CommitImport).
 */
final class StageImport
{
    public function __construct(private readonly RecordAudit $audit)
    {
    }

    /** @throws \App\Imports\ImportFormatException */
    public function __invoke(UploadedFile $file, User $actor): ImportBatch
    {
        $config = config('archive.import');
        $parsed = CsvReader::read($file->getRealPath(), $config['max_rows']);

        $result = (new DatasetMapper($config, $this->context(), config('archive.bounds')))->mapAll($parsed['rows']);
        $rows = $result['rows'];

        return DB::transaction(function () use ($file, $actor, $config, $rows, $result) {
            $batch = ImportBatch::create([
                'uploaded_by' => $actor->id,
                'source' => $config['source'],
                'original_filename' => Str::limit(preg_replace('/[^\w.\- ]+/u', '_', $file->getClientOriginalName()), 200, ''),
                'file_hash' => hash_file('sha256', $file->getRealPath()),
                'status' => 'staged',
                'totals' => $this->totals($rows),
                'notes' => $result['notes'],
            ]);

            foreach (array_chunk($rows, 200) as $chunk) {
                $batch->rows()->insert(array_map(fn (MappedRow $row) => [
                    'import_batch_id' => $batch->id,
                    'row_number' => $row->rowNumber,
                    'external_id' => $row->externalId,
                    'entity' => $row->entity,
                    'status' => $row->status(),
                    'action' => $row->action,
                    'payload' => json_encode($row->payload, JSON_UNESCAPED_UNICODE),
                    'issues' => json_encode($row->issues, JSON_UNESCAPED_UNICODE),
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $chunk));
            }

            ($this->audit)('import_batch.staged', $batch, null, ['rows' => count($rows), 'file' => $batch->original_filename]);

            return $batch;
        });
    }

    private function context(): ImportContext
    {
        return new ImportContext(
            regionSlugs: Region::query()->published()->pluck('slug')->all(),
            existingExternalIds: array_merge(
                Place::withTrashed()->whereNotNull('external_id')->pluck('external_id')->all(),
                Hotel::withTrashed()->whereNotNull('external_id')->pluck('external_id')->all(),
            ),
            existingPlaceSlugs: Place::withTrashed()->pluck('slug')->all(),
            existingHotelSlugs: Hotel::withTrashed()->pluck('slug')->all(),
        );
    }

    /** @param  array<int, MappedRow>  $rows */
    private function totals(array $rows): array
    {
        $count = fn (callable $fn) => count(array_filter($rows, $fn));

        return [
            'rows' => count($rows),
            'valid' => $count(fn (MappedRow $r) => $r->status() === 'valid'),
            'warning' => $count(fn (MappedRow $r) => $r->status() === 'warning'),
            'error' => $count(fn (MappedRow $r) => $r->status() === 'error'),
            'places' => $count(fn (MappedRow $r) => $r->entity === 'place' && ! $r->hasError() && $r->action === 'create'),
            'hotels' => $count(fn (MappedRow $r) => $r->entity === 'hotel' && ! $r->hasError() && $r->action === 'create'),
            'skipped' => $count(fn (MappedRow $r) => ! $r->hasError() && $r->action === 'skip'),
        ];
    }
}
