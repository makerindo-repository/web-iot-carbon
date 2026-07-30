<?php

use App\Http\Controllers\BmkgController;
use App\Jobs\ProcessAiForecast;
use App\Models\Device;
use App\Services\AlertNotificationService;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Sync BMKG setiap jam (jalur cron tepercaya → runSync() langsung, tanpa token)
Schedule::call(function () {
    $controller = new BmkgController;
    $controller->runSync();
})->hourly()->timezone('Asia/Jakarta');

// Deteksi device offline (>65 menit)
Schedule::call(function () {
    $timeoutThreshold = Carbon::now()->subMinutes(65);

    Device::whereIn('device_status', ['online', 'warning'])
        ->where('last_seen_at', '<', $timeoutThreshold)
        ->get()
        ->each(function (Device $device) {
            $device->update(['device_status' => 'offline']);
            app(AlertNotificationService::class)->sendNodeOffline($device->fresh());
        });
})->everyFiveMinutes();

// Laporan harian Telegram
Schedule::call(function () {
    app(AlertNotificationService::class)->sendDailyTelegramReport();
})->dailyAt('07:00')->timezone('Asia/Jakarta')->name('telegram-daily-node-report')->withoutOverlapping();

// AI forecast harian
Schedule::call(function () {
    $activeDevices = Device::whereIn('device_status', ['online', 'warning'])->get();

    foreach ($activeDevices as $device) {
        ProcessAiForecast::dispatch($device->id, $device->device_code)
            ->onQueue('ai-forecast');
    }
})->dailyAt('01:00')->timezone('Asia/Jakarta')->name('ai-forecast-daily')->withoutOverlapping();

// Pruning token Sanctum expired
Schedule::command('sanctum:prune-expired --hours=48')->daily();
