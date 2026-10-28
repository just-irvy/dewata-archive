<?php

namespace App\Console\Commands;

use App\Models\Hotel;
use App\Models\Place;
use Illuminate\Console\Command;

class PhotoList extends Command
{
    protected $signature = 'archive:photo-list
        {--path= : Folder impor foto (bawaan: storage/app/photos-import)}
        {--all : Sertakan juga yang sudah punya foto}';

    protected $description = 'Membuat folder impor foto dan daftar nama berkas yang diharapkan untuk tiap tempat dan hotel';

    public function handle(): int
    {
        $root = rtrim((string) ($this->option('path') ?: config('archive.photos.import_path')), '/\\');

        foreach (['', 'places', 'hotels'] as $sub) {
            $dir = $sub === '' ? $root : $root.DIRECTORY_SEPARATOR.$sub;
            if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
                $this->error("Tidak bisa membuat folder {$dir}");

                return self::FAILURE;
            }
        }

        $template = $root.DIRECTORY_SEPARATOR.'keterangan.csv';
        if (! is_file($template)) {
            file_put_contents($template, "file,alt_text,caption,credit,license_note\n");
        }

        $rows = [];
        $only = ! $this->option('all');

        foreach ([['tempat', 'places', Place::query()->with('region')->withCount('media')->orderBy('name')],
            ['hotel', 'hotels', Hotel::query()->with('region')->withCount('media')->orderBy('name')]] as [$label, $folder, $query]) {
            foreach ($query->get() as $record) {
                if ($only && $record->media_count > 0) {
                    continue;
                }
                $rows[] = [$label, "{$folder}/{$record->slug}.jpg", $record->name, $record->region?->name, $record->status->value, $record->media_count, $record->external_id];
            }
        }

        $listFile = $root.DIRECTORY_SEPARATOR.'daftar-nama-foto.csv';
        $handle = fopen($listFile, 'w');
        fputcsv($handle, ['jenis', 'nama_berkas', 'nama', 'wilayah', 'status', 'jumlah_foto', 'id_dataset'], ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }
        fclose($handle);

        $this->info('Folder siap: '.$root);
        $this->line(count($rows).' tempat dan hotel '.($only ? 'belum punya foto' : 'tercatat').'. Daftar nama berkas: '.$listFile);
        $this->line('Taruh foto tempat di places/ dan foto hotel di hotels/. Foto kedua dan seterusnya diberi akhiran -2, -3.');
        $this->line('Lalu jalankan: php artisan archive:import-photos --credit="..." --license="..."');

        return self::SUCCESS;
    }
}
