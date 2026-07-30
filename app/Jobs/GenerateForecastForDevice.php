<?php

namespace App\Jobs;

use App\Models\Device;
use App\Models\ForecastPrediction;
use App\Models\IotReading;
use App\Services\ForecastFeatureService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

/**
 * Menjalankan run_sintetik90_inference.py untuk satu node berdasarkan
 * pembacaan sensor terbarunya, lalu menyimpan hasil prediksi ke
 * forecast_predictions. Fitur input dibangun oleh ForecastFeatureService,
 * yang menandai secara eksplisit fitur mana yang berasal dari data sensor
 * riil dan mana yang memakai asumsi tetap (lihat FORECAST_LIVE_ASSUMPTIONS.md).
 *
 * Dijalankan pada queue "ai-forecast" (lihat docker-compose.yml, service
 * "queue") agar proses subprocess Python tidak memblokir scheduler.
 */
class GenerateForecastForDevice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $queue = 'ai-forecast';

    public $tries = 2;

    public $timeout = 180;

    public function __construct(
        public int $deviceId,
        public string $models = 'svm,xgboost,lstm'
    ) {}

    public function handle(): void
    {
        $device = Device::find($this->deviceId);
        if (! $device) {
            return;
        }

        $reading = IotReading::where('device_id', $device->id)
            ->orderBy('reading_time', 'desc')
            ->first();

        if (! $reading) {
            return;
        }

        $bundleDir = rtrim(env('AI_MODEL_BUNDLE_PATH', base_path('../docs/deploy_model_bundle')), '/');
        $scriptPath = $bundleDir.'/run_sintetik90_inference.py';
        $python = env('PYTHON_EXECUTABLE', 'python3');

        if (! is_file($scriptPath)) {
            Log::error("Forecast: skrip inferensi tidak ditemukan di {$scriptPath}");

            return;
        }

        $features = ForecastFeatureService::buildFeatureVector($device, $reading);

        $inputPath = storage_path('app/forecast_input_'.$device->device_code.'_'.Str::random(8).'.json');
        file_put_contents($inputPath, json_encode(['rows' => [$features]], JSON_UNESCAPED_UNICODE));

        try {
            $process = new Process([$python, $scriptPath, $inputPath, '--models', $this->models]);
            $process->setTimeout(150);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::error("Forecast: proses inferensi gagal untuk {$device->device_code}", [
                    'error_output' => $process->getErrorOutput(),
                ]);

                return;
            }

            $result = json_decode($process->getOutput(), true);

            if (! is_array($result) || empty($result['success'])) {
                Log::error("Forecast: respons inferensi tidak valid untuk {$device->device_code}", [
                    'raw_output' => $process->getOutput(),
                ]);

                return;
            }

            foreach ($result['predictions'] ?? [] as $prediction) {
                if (! isset($prediction['model'], $prediction['target'])) {
                    continue;
                }

                $horizon = (int) ($prediction['horizon_hours'] ?? 0);

                ForecastPrediction::create([
                    'device_id' => $device->id,
                    'source_reading_id' => $reading->id,
                    'model_used' => $prediction['model'],
                    'target_metric' => $prediction['target'],
                    'horizon_hours' => $horizon,
                    'predicted_value' => $prediction['predicted_value'] ?? null,
                    'predicted_for' => Carbon::parse($reading->reading_time)->addHours($horizon),
                    'assumptions_version' => ForecastFeatureService::ASSUMPTIONS_VERSION,
                    'feature_snapshot' => $features,
                    'error' => $prediction['error'] ?? null,
                ]);
            }
        } finally {
            @unlink($inputPath);
        }
    }
}
