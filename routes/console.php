<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler: Menjalankan sinkronisasi data BMKG / OpenWeather secara terjadwal
\Illuminate\Support\Facades\Schedule::call(function () {
    $controller = new \App\Http\Controllers\BmkgController();
    $controller->sync();
})->hourly()->timezone('Asia/Jakarta');
