<?php

namespace App\Console\Commands;

use App\Imports\DatasetMapper;
use App\Models\AuditLog;
use App\Models\Hotel;
use App\Models\Media;
use App\Models\Place;
use App\Photos\ImageProcessor;
use App\Photos\PhotoMatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class ImportPhotos extends Command
{
    protected $signature = 'archive:import-photos
        {--path= : Folder berisi places/ dan hotels/ (bawaan: storage/app/photos-import)}
        {--credit= : Kredit untuk semua foto, misalnya nama fotografer atau pemilik}
        {--license= : Lisensi atau izin, misalnya "izin tertulis" atau "CC BY 4.0"}
        {--alt= : Template alt text dengan token nama yang diganti nama tempat}
        {--max-width=2000 : Foto yang lebih lebar diperkecil ke lebar ini}
        {--dry-run : Hanya menampilkan rencana, tidak menulis apa pun}';

    protected $description = 'Memasang foto dari folder ke tempat dan hotel menurut nama berkas';

    public function handle(): int
    {
        $root = rtrim((string) ($this->option('path') ?: config('archive.photos.import_path')), '/\\');
        $dry = (bool) $this->option('dry-run');

        if (! is_dir($root)) {
            $this->error("Folder tidak ditemukan: {$root}");
            $this->line('Buat dulu dengan: php artisan archive:photo-list');

            return self::FAILURE;
        }

        $sidecar = $this->readSidecar($root);
        $credit = trim((string) $this->option('credit'));
        $license = trim((string) $this->option('license'));
        $template = trim((string) $this->option('alt')) ?: 'Foto {nama}';
        $maxWidth = max(400, (int) $this->option('max-width'));

        // Hak pakai tidak pernah diisi diam-diam. Anda yang menyatakannya, sekali, di sini atau di keterangan.csv.
        if (! $dry && $credit === '' && $license === '' && $sidecar === []) {
            $this->error('Nyatakan hak pakai foto lebih dulu.');
            $this->line('Contoh: php artisan archive:import-photos --credit="Tim The Dewata Archive" --license="Foto milik sendiri"');
            $this->line('Atau isi kolom credit atau license_note per berkas di keterangan.csv.');

            return self::FAILURE;
        }

        $done = $skippedDuplicate = 0;
        $unmatched = $ambiguous = $invalid = $noRights = [];

        foreach (['places' => Place::class, 'hotels' => Hotel::class] as $folder => $model) {
            $dir = $root.DIRECTORY_SEPARATOR.$folder;

            if (! is_dir($dir)) {
                continue;
            }

            $records = $model::query()->get(['id', 'name', 'slug']);
            $byId = $records->keyBy('id');
            $index = [];
            foreach ($records as $record) {
                $index[$record->slug][] = $record->id;
                $index[DatasetMapper::slug($record->name)][] = $record->id;
            }

            $files = array_values(array_filter(scandir($dir), fn ($f) => is_file($dir.DIRECTORY_SEPARATOR.$f)));
            sort($files);

            foreach ($files as $file) {
                $label = "{$folder}/{$file}";
                $full = $dir.DIRECTORY_SEPARATOR.$file;

                if (! preg_match('/\.(jpe?g|png|webp)$/i', $file)) {
                    $invalid[] = "{$label}: ekstensi tidak didukung (hanya jpg, png, webp)";

                    continue;
                }

                $match = PhotoMatcher::match($file, $index);

                if ($match['status'] === 'none') {
                    $unmatched[] = $label;

                    continue;
                }
                if ($match['status'] === 'ambiguous') {
                    $ambiguous[] = $label;

                    continue;
                }

                $owner = $byId[$match['id']];
                $meta = $sidecar[$file] ?? $sidecar["{$folder}/{$file}"] ?? [];
                $fileCredit = trim((string) ($meta['credit'] ?? '')) ?: $credit;
                $fileLicense = trim((string) ($meta['license_note'] ?? '')) ?: $license;

                if ($fileCredit === '' && $fileLicense === '') {
                    $noRights[] = $label;

                    continue;
                }

                try {
                    ImageProcessor::inspect($full);
                } catch (InvalidArgumentException $e) {
                    $invalid[] = "{$label}: {$e->getMessage()}";

                    continue;
                }

                $hash = hash_file('sha256', $full);

                if (Media::query()->where('mediable_type', $model)->where('mediable_id', $owner->id)->where('source_hash', $hash)->exists()) {
                    $skippedDuplicate++;

                    continue;
                }

                if ($dry) {
                    $done++;
                    $this->line("  akan dipasang: {$label} -> {$owner->name}");

                    continue;
                }

                try {
                    $this->install($model, $owner, $full, $hash, [
                        'alt_text' => trim((string) ($meta['alt_text'] ?? '')) ?: str_replace('{nama}', $owner->name, $template),
                        'caption' => trim((string) ($meta['caption'] ?? '')) ?: null,
                        'credit' => $fileCredit ?: null,
                        'license_note' => $fileLicense ?: null,
                        'sort_order' => $match['order'],
                    ], $maxWidth, $file);
                    $done++;
                } catch (Throwable $e) {
                    $invalid[] = "{$label}: {$e->getMessage()}";
                }
            }
        }

        $this->report($dry, $done, $skippedDuplicate, $unmatched, $ambiguous, $invalid, $noRights);

        return self::SUCCESS;
    }

    /** @param  array<string, mixed>  $attributes */
    private function install(string $model, object $owner, string $source, string $hash, array $attributes, int $maxWidth, string $originalName): void
    {
        $disk = (string) config('archive.media.disk');
        $tmp = tempnam(sys_get_temp_dir(), 'foto');

        try {
            $meta = ImageProcessor::process($source, $tmp, $maxWidth);
            $path = 'media/'.now()->format('Y/m').'/'.Str::random(40).'.'.$meta['ext'];

            $stream = fopen($tmp, 'rb');
            Storage::disk($disk)->put($path, $stream);
            fclose($stream);
        } finally {
            @unlink($tmp);
        }

        try {
            $media = new Media($attributes + ['path' => $path, 'width' => $meta['width'], 'height' => $meta['height']]);
            $media->forceFill(['source_hash' => $hash]);
            $owner->media()->save($media);

            AuditLog::create([
                'action' => 'media.imported',
                'entity_type' => 'media',
                'entity_id' => $media->id,
                'after_json' => ['path' => $path, 'owner' => class_basename($model).'#'.$owner->id, 'file' => $originalName] + ($meta['note'] ? ['note' => $meta['note']] : []),
            ]);
        } catch (Throwable $e) {
            Storage::disk($disk)->delete($path);

            throw $e;
        }
    }

    /** @return array<string, array<string, string>> */
    private function readSidecar(string $root): array
    {
        $file = $root.DIRECTORY_SEPARATOR.'keterangan.csv';

        if (! is_file($file)) {
            return [];
        }

        $handle = fopen($file, 'r');
        $header = fgetcsv($handle, 0, ',', '"', '');
        $rows = [];

        if (is_array($header) && in_array('file', $header, true)) {
            $header = array_map(fn ($h) => trim((string) $h), $header);

            while (($fields = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($fields === [null] || count($fields) !== count($header)) {
                    continue;
                }
                $row = array_combine($header, $fields);
                if (filled($row['file'] ?? null)) {
                    $rows[trim($row['file'])] = $row;
                }
            }
        }

        fclose($handle);

        return $rows;
    }

    private function report(bool $dry, int $done, int $duplicate, array $unmatched, array $ambiguous, array $invalid, array $noRights): void
    {
        $this->newLine();
        $this->info(($dry ? 'SIMULASI, tidak ada yang ditulis. Akan dipasang: ' : 'Foto dipasang: ').$done);

        if ($duplicate > 0) {
            $this->line("Dilewati karena sudah ada: {$duplicate}");
        }

        foreach (
            [
                'Tidak cocok dengan tempat atau hotel mana pun (periksa ejaan nama berkas)' => $unmatched,
                'Nama ambigu, cocok dengan lebih dari satu record' => $ambiguous,
                'Dilewati karena tanpa kredit atau lisensi' => $noRights,
                'Tidak valid' => $invalid,
            ] as $title => $items
        ) {
            if ($items !== []) {
                $this->warn($title.': '.count($items));
                foreach (array_slice($items, 0, 25) as $item) {
                    $this->line("  - {$item}");
                }
                if (count($items) > 25) {
                    $this->line('  dan '.(count($items) - 25).' lainnya');
                }
            }
        }

        $this->newLine();
        $this->line(sprintf(
            'Tempat tanpa foto: %d dari %d. Hotel tanpa foto: %d dari %d.',
            Place::query()->doesntHave('media')->count(),
            Place::query()->count(),
            Hotel::query()->doesntHave('media')->count(),
            Hotel::query()->count(),
        ));
    }
}
