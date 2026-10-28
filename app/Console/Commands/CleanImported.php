<?php

namespace App\Console\Commands;

use App\Enums\PublishStatus;
use App\Enums\VerificationStatus;
use App\Imports\BoilerplateDetector;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\Place;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CleanImported extends Command
{
    protected $signature = 'archive:clean-imported
        {--apply : Terapkan perubahan. Tanpa opsi ini hanya simulasi.}';

    protected $description = 'Membersihkan record hasil impor yang berisi ringkasan templat atau tautan pencarian sebagai sumber';

    public function handle(): int
    {
        $records = [];
        foreach ([Place::class, Hotel::class] as $model) {
            foreach ($model::query()->whereNotNull('external_id')->get() as $record) {
                $records[] = $record;
            }
        }

        $flagged = BoilerplateDetector::flagged(array_map(
            fn (Model $r) => ['name' => $r->name, 'summary' => (string) $r->summary],
            $records,
        ));

        $apply = (bool) $this->option('apply');
        $affected = $templated = $searchLinks = $takenDown = 0;

        foreach ($records as $record) {
            $changes = [];

            if (filled($record->summary) && BoilerplateDetector::isBoilerplate((string) $record->summary, $record->name, $flagged)) {
                $changes['summary'] = '';
                $templated++;
            }
            if (filled($record->source_url) && BoilerplateDetector::isSearchLink($record->source_url)) {
                $changes['source_url'] = null;
                $searchLinks++;
            }

            if ($changes === []) {
                continue;
            }

            // Klaim verifikasi yang bertumpu pada teks templat atau sumber palsu tidak boleh dipertahankan.
            $changes['verified_at'] = null;
            if ($record instanceof Place) {
                $changes['verification_status'] = VerificationStatus::Unknown;
            }
            // Record terbit tanpa ringkasan melanggar syarat terbit, jadi ditarik kembali menjadi draf.
            if ($record->status === PublishStatus::Published) {
                $changes['status'] = PublishStatus::Draft;
                $takenDown++;
            }

            $affected++;

            if ($apply) {
                $before = [];
                foreach (array_keys($changes) as $key) {
                    $value = $record->getAttribute($key);
                    $before[$key] = $value instanceof \BackedEnum ? $value->value : ($value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value);
                }

                $record->forceFill($changes)->save();

                AuditLog::create([
                    'action' => Str::snake(class_basename($record)).'.cleaned',
                    'entity_type' => Str::snake(class_basename($record)),
                    'entity_id' => $record->id,
                    'before_json' => $before,
                    'after_json' => array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $changes),
                ]);
            }
        }

        $this->info(($apply ? 'Dibersihkan' : 'SIMULASI, belum ada yang diubah. Akan dibersihkan').": {$affected} dari ".count($records).' record hasil impor.');
        $this->line("Ringkasan templat dikosongkan: {$templated}. Tautan pencarian dibuang: {$searchLinks}. Ditarik dari publik menjadi draf: {$takenDown}.");

        if (! $apply && $affected > 0) {
            $this->warn('Jalankan lagi dengan --apply untuk menerapkan.');
        }
        if ($apply && $affected > 0) {
            $this->line('Tulis ringkasan khusus tiap tempat di editor, isi sumber yang sebenarnya, dan tetapkan status verifikasi hanya setelah memeriksa.');
        }

        return self::SUCCESS;
    }
}
