<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatHistory;
use Illuminate\Console\Command;

class PurgeChatMessages extends Command
{
    protected $signature = 'archive:purge-chat';

    protected $description = 'Hapus pesan asisten yang lebih tua dari masa simpan (chatbot.retention_days)';

    public function handle(): int
    {
        $days = (int) config('chatbot.retention_days');
        $count = ChatHistory::purgeOlderThan($days);

        $this->info("{$count} pesan asisten yang lebih tua dari {$days} hari dihapus.");

        return self::SUCCESS;
    }
}
