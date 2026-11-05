<?php

use Illuminate\Support\Facades\Schedule;

// Jalankan `php artisan schedule:run` tiap menit lewat cron di server (lihat PANDUAN-SETUP.md).
Schedule::command('archive:purge-guest-drafts')->dailyAt('03:15');
Schedule::command('auth:clear-resets')->daily();
Schedule::command('archive:purge-chat')->dailyAt('03:30');
