<?php

namespace App\Console\Commands;

use App\Actions\ResetCatalog;
use App\Models\User;
use Illuminate\Console\Command;

/** Hapus permanen semua tempat dan hotel dari terminal. Versi admin: Admin, Ringkasan, Zona berbahaya. */
class ResetCatalogCommand extends Command
{
    protected $signature = 'archive:reset-catalog
        {--dry-run : Hanya tampilkan jumlah yang akan dihapus}
        {--actor= : Email super-admin yang tercatat di audit log}
        {--force : Lewati pertanyaan konfirmasi (untuk skrip)}';

    protected $description = 'Hapus PERMANEN semua tempat dan hotel beserta fotonya';

    public function handle(ResetCatalog $reset): int
    {
        $preview = $reset->preview();

        $this->table(['Data', 'Jumlah'], [
            ['Tempat (termasuk draf, arsip, dan yang dihapus sementara)', $preview['places']],
            ['Hotel (termasuk draf, arsip, dan yang dihapus sementara)', $preview['hotels']],
            ['Foto tempat dan hotel', $preview['media']],
            ['Tempat di dalam rencana pengguna', $preview['plan_items']],
            ['Rencana pengguna yang terdampak (tidak dihapus)', $preview['plans_affected']],
            ['Bookmark pengguna', $preview['saved_places']],
            ['Kaitan tempat di koleksi (koleksi tidak dihapus)', $preview['collection_links']],
        ]);

        if ($this->option('dry-run')) {
            $this->info('Mode uji: tidak ada yang dihapus.');

            return self::SUCCESS;
        }

        $actor = User::query()->where('email', (string) $this->option('actor'))->first();
        if (! $actor || ! $actor->isSuperAdmin()) {
            $this->error('Sebutkan email super-admin dengan --actor=email@domain agar penghapusan tercatat di audit log.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $typed = $this->ask('Ketik '.ResetCatalog::CONFIRMATION.' untuk melanjutkan. Tindakan ini tidak bisa dibatalkan');
            if ($typed !== ResetCatalog::CONFIRMATION) {
                $this->warn('Dibatalkan. Tidak ada yang dihapus.');

                return self::FAILURE;
            }
        }

        $done = $reset($actor);
        $this->info("Selesai. {$done['places']} tempat dan {$done['hotels']} hotel dihapus permanen, beserta {$done['media']} foto.");

        return self::SUCCESS;
    }
}
