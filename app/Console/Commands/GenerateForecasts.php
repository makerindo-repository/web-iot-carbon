<?php

namespace App\Console\Commands;

use App\Jobs\GenerateForecastForDevice;
use App\Models\Device;
use Illuminate\Console\Command;

class GenerateForecasts extends Command
{
    protected $signature = 'agrisense:forecast:generate {--device=} {--models=svm,xgboost,lstm}';

    protected $description = 'Menjadwalkan pembuatan prediksi forecasting (SVM/XGBoost/LSTM) untuk setiap node aktif melalui queue ai-forecast.';

    public function handle(): int
    {
        $devices = $this->option('device')
            ? Device::where('device_code', $this->option('device'))->get()
            : Device::all();

        if ($devices->isEmpty()) {
            $this->warn('Tidak ada node terdaftar untuk diproses.');

            return self::SUCCESS;
        }

        foreach ($devices as $device) {
            GenerateForecastForDevice::dispatch($device->id, $this->option('models'));
        }

        $this->info("Menjadwalkan {$devices->count()} tugas forecasting ke queue ai-forecast.");

        return self::SUCCESS;
    }
}
