<?php

use App\Http\Controllers\BmkgController;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduler: Menjalankan sinkronisasi data BMKG / OpenWeather secara terjadwal
Schedule::call(function () {
    $controller = new BmkgController;
    $controller->sync();
})->hourly()->timezone('Asia/Jakarta');

// Scheduler: Menjadwalkan pembuatan prediksi forecasting (SVM/XGBoost/LSTM)
// untuk setiap node ke queue "ai-forecast" (lihat docker-compose.yml, service
// "queue"). Dijalankan setiap jam mengikuti pembacaan sensor node terbaru.
Schedule::command('agrisense:forecast:generate')->hourly()->timezone('Asia/Jakarta');
