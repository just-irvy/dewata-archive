<?php

namespace App\Console\Commands;

use App\Models\TripPlan;
use Illuminate\Console\Command;

/** Menghapus draf tamu yang masa simpannya habis (FILE 15: retensi guest drafts). */
class PurgeGuestDrafts extends Command
{
    protected $signature = 'archive:purge-guest-drafts';

    protected $description = 'Hapus draf rencana tamu yang sudah kedaluwarsa';

    public function handle(): int
    {
        $count = 0;

        TripPlan::query()
            ->whereNull('user_id')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->chunkById(200, function ($plans) use (&$count) {
                foreach ($plans as $plan) {
                    $plan->delete();
                    $count++;
                }
            });

        $this->info("{$count} draf tamu kedaluwarsa dihapus.");

        return self::SUCCESS;
    }
}
