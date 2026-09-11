<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\AutoExpireLoHangJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Tự động hủy lô hàng hết hạn mỗi ngày lúc 00:01
// Chạy: php artisan schedule:run (hoặc set Task Scheduler / cron)
Schedule::job(new AutoExpireLoHangJob)->dailyAt('00:01')->name('auto-expire-lo-hang')->withoutOverlapping();
