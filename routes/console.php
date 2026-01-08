<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 🔹 Backup ΚΑΘΕ 8 ΩΡΕΣ
Schedule::command('backup:run --only-db')->cron('0 */8 * * *');

// 🔹 Καθαρισμός παλιών backups (1 φορά τη μέρα)
Schedule::command('backup:clean')->dailyAt('03:00');
