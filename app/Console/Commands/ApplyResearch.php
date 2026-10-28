<?php

namespace App\Console\Commands;

use App\Enums\VerificationStatus;
use App\Imports\CsvReader;
use App\Imports\DatasetMapper;
use App\Imports\ImportContext;
use App\Imports\ImportFormatException;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\Place;
use App\Models\Region;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Mengisi hasil riset (ringkasan, sumber, tanggal verifikasi) ke draf yang sudah ada, menurut external_id.
 *
 * Impor biasa hanya membuat record baru. Perintah ini untuk kasus sebaliknya: record sudah ada (misalnya
 * hasil impor yang dibersihkan) dan Anda punya berkas yang lebih baik. Aturannya sengaja sempit:
 * hanya draf, hanya kolom yang masih kosong, tidak pernah menimpa isi yang sudah dikerjakan editor.
 * Berkas melewati pemeta yang sama dengan impor, sehingga ringkasan templat, tautan pencarian, dan klaim
 * "Terverifikasi" ditolak di sini juga.
 */
class ApplyResearch extends Command
{
    protected $signature = 'archive:apply-research
        {file : Berkas CSV (format v2 atau lebih baru)}
        {--apply : Terapkan perubahan. Tanpa opsi ini hanya simulasi.}';

    protected $description = 'Mengisi ringkasan, sumber, dan verifikasi ke draf yang sudah ada tanpa menimpa isi yang sudah terisi';

    public function handle(): int
    {
        $file = (string) $this->argument('file');

        if (! is_file($file)) {
            $this->error("Berkas tidak ditemukan: {$file}");

            return self::FAILURE;
        }

        try {
            $parsed = CsvReader::read($file, (int) config('archive.import.max_rows'));
        } catch (ImportFormatException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $mapper = new DatasetMapper(
            config('archive.import'),
            new ImportContext(regionSlugs: Region::query()->published()->pluck('slug')->all()),
            config('archive.bounds'),
        );

        $apply = (bool) $this->option('apply');
        $stats = ['filled' => 0, 'summary' => 0, 'source' => 0, 'verification' => 0, 'area' => 0,
            'missing' => 0, 'not_draft' => 0, 'nothing' => 0, 'error' => 0];
        $changedNames = [];

        foreach ($mapper->mapAll($parsed['rows'])['rows'] as $row) {
            if ($row->hasError() || $row->externalId === null) {
                $stats['error']++;

                continue;
            }

            $record = Place::query()->where('external_id', $row->externalId)->first()
                ?? Hotel::query()->where('external_id', $row->externalId)->first();

            if ($record === null) {
                $stats['missing']++;

                continue;
            }
            if ($record->status->value !== 'draft') {
                $stats['not_draft']++;

                continue;
            }

            $p = $row->payload;
            $patch = [];

            if (blank($record->summary) && filled($p['summary'] ?? null)) {
                $patch['summary'] = $p['summary'];
                $stats['summary']++;
            }
            if (blank($record->source_url) && filled($p['source_url'] ?? null)) {
                $patch['source_url'] = $p['source_url'];
                $stats['source']++;
            }
            if (blank($record->area_label) && filled($p['area_label'] ?? null)) {
                $patch['area_label'] = $p['area_label'];
                $stats['area']++;
            }

            $notVerifiedYet = $record->verified_at === null
                && ($record instanceof Hotel || $record->verification_status === VerificationStatus::Unknown);

            if ($notVerifiedYet && filled($p['verified_at'] ?? null)) {
                $patch['verified_at'] = $p['verified_at'];
                if ($record instanceof Place) {
                    $patch['verification_status'] = VerificationStatus::tryFrom((string) ($p['verification_status'] ?? '')) ?? VerificationStatus::Unknown;
                }
                $stats['verification']++;
            }

            if ($patch === []) {
                $stats['nothing']++;

                continue;
            }

            $stats['filled']++;
            $changedNames[] = $record->name;

            if ($apply) {
                $before = [];
                foreach (array_keys($patch) as $key) {
                    $value = $record->getAttribute($key);
                    $before[$key] = $value instanceof \BackedEnum ? $value->value : ($value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value);
                }

                $record->forceFill($patch)->save();

                AuditLog::create([
                    'action' => Str::snake(class_basename($record)).'.research_applied',
                    'entity_type' => Str::snake(class_basename($record)),
                    'entity_id' => $record->id,
                    'before_json' => $before,
                    'after_json' => array_map(fn ($v) => $v instanceof \BackedEnum ? $v->value : $v, $patch),
                ]);
            }
        }

        $this->info(($apply ? 'Diisi' : 'SIMULASI, belum ada yang diubah. Akan diisi').": {$stats['filled']} draf.");
        $this->line("Ringkasan: {$stats['summary']}. Sumber: {$stats['source']}. Tanggal dan status verifikasi: {$stats['verification']}. Label area: {$stats['area']}.");
        $this->line("Dilewati: tidak ada di database {$stats['missing']}, bukan draf {$stats['not_draft']}, tidak ada yang perlu diisi {$stats['nothing']}, baris bermasalah {$stats['error']}.");

        foreach (array_slice($changedNames, 0, 15) as $name) {
            $this->line("  - {$name}");
        }
        if (count($changedNames) > 15) {
            $this->line('  dan '.(count($changedNames) - 15).' lainnya');
        }
        if (! $apply && $stats['filled'] > 0) {
            $this->warn('Jalankan lagi dengan --apply untuk menerapkan.');
        }

        return self::SUCCESS;
    }
}
