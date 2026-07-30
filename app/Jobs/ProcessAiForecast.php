<?php

namespace App\Jobs;

use App\Models\AiForecastResult;
use App\Services\AiDataFormatter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class ProcessAiForecast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 300;

    public function __construct(
        private int $deviceId,
        private string $deviceCode,
    ) {}

    public function handle(): void
    {
        $startTime = microtime(true);
        $tempFile = null;

        try {
            // Siapkan data
            $inputData = AiDataFormatter::prepareForDevice($this->deviceId, $this->deviceCode, 30);

            if (! $inputData) {
                Log::info("[AI Forecast] Device {$this->deviceCode}: Data tidak cukup untuk prediksi. Dilewati.");

                return;
            }

            // Tulis file JSON sementara
            $tempDir = storage_path('app/ai_temp');
            if (! is_dir($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            $tempFile = $tempDir.'/input_'.$this->deviceCode.'_'.time().'.json';
            file_put_contents($tempFile, json_encode($inputData, JSON_UNESCAPED_UNICODE));

            // Jalankan Python inference
            $bundlePath = rtrim((string) config('services.ai_model_bundle.path'), DIRECTORY_SEPARATOR);
            $latestScriptPath = $bundlePath.'/run_sintetik90_inference.py';
            $legacyScriptPath = $bundlePath.'/run_inference.py';
            $scriptPath = is_file($latestScriptPath) ? $latestScriptPath : $legacyScriptPath;

            if (! is_dir($bundlePath) || ! is_file($scriptPath)) {
                $message = "Model bundle tidak ditemukan di {$bundlePath}";
                Log::error("[AI Forecast] Device {$this->deviceCode}: {$message}");
                $this->storeFailed($message);

                return;
            }

            // Deteksi Python executable
            $pythonExe = $this->findPythonExecutable();
            $models = (string) config('services.ai_model_bundle.models', 'svm,xgboost,lstm');

            $arguments = [$pythonExe, $scriptPath, $tempFile, '--models', $models];
            if ($scriptPath === $legacyScriptPath) {
                $arguments = [$pythonExe, $scriptPath, $tempFile, '--models', $models, '--horizons', '1,6,24'];
            }

            $result = Process::path($bundlePath)
                ->timeout(240)
                ->run($arguments);

            if (! $result->successful()) {
                Log::error("[AI Forecast] Device {$this->deviceCode}: Python error", [
                    'stderr' => $result->errorOutput(),
                    'exit_code' => $result->exitCode(),
                ]);

                $this->storeFailed('Python process error: '.substr($result->errorOutput(), 0, 500));

                return;
            }

            // Parse output JSON
            $output = json_decode(trim($result->output()), true);

            if (! $output || ! ($output['success'] ?? false)) {
                Log::error("[AI Forecast] Device {$this->deviceCode}: Output invalid", [
                    'output' => substr($result->output(), 0, 500),
                ]);

                return;
            }

            if (! empty($output['errors'])) {
                Log::warning("[AI Forecast] Device {$this->deviceCode}: Sebagian model gagal", [
                    'errors' => $output['errors'],
                ]);
            }

            // Simpan prediksi ke database
            $latestReadingTime = Carbon::parse($inputData['latest_reading_time']);
            $savedCount = 0;

            foreach ($output['predictions'] as $pred) {
                if (! array_key_exists('predicted_value', $pred) || $pred['predicted_value'] === null) {
                    continue;
                }

                $targetMetric = $pred['target'];
                $currentValue = $inputData['current_values'][$targetMetric] ?? null;
                if ($currentValue === null && str_contains($targetMetric, 'Moisture')) {
                    $currentValue = $inputData['current_values']['soil_moisture'] ?? null;
                } elseif ($currentValue === null && (str_contains($targetMetric, 'pH') || $targetMetric === 'pH')) {
                    $currentValue = $inputData['current_values']['soil_ph'] ?? null;
                }

                foreach ($this->buildForecastRecords($pred, $inputData, $currentValue) as $record) {
                    AiForecastResult::updateOrCreate(
                        [
                            'device_id' => $this->deviceId,
                            'target_metric' => $targetMetric,
                            'horizon_hours' => $record['horizon_hours'],
                            'model_used' => $pred['model'],
                            'predicted_for' => $latestReadingTime->copy()->addHours($record['horizon_hours']),
                        ],
                        [
                            'predicted_value' => $record['predicted_value'],
                            'current_value' => $currentValue,
                            'input_reading_time' => $latestReadingTime,
                            'status' => 'completed',
                        ]
                    );
                    $savedCount++;
                }
            }

            $elapsed = round(microtime(true) - $startTime, 2);
            Log::info("[AI Forecast] Device {$this->deviceCode}: {$savedCount} prediksi disimpan dalam {$elapsed}s");

        } catch (\Throwable $e) {
            Log::error("[AI Forecast] Device {$this->deviceCode}: Exception", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->storeFailed($e->getMessage());
        } finally {
            // Bersihkan file sementara
            if ($tempFile && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    private function storeFailed(string $errorMessage): void
    {
        AiForecastResult::create([
            'device_id' => $this->deviceId,
            'target_metric' => 'ALL',
            'horizon_hours' => 0,
            'model_used' => 'system',
            'predicted_value' => 0,
            'predicted_for' => now(),
            'input_reading_time' => now(),
            'status' => 'failed',
            'error_message' => substr($errorMessage, 0, 1000),
        ]);
    }

    private function buildForecastRecords(array $prediction, array $inputData, mixed $currentValue): array
    {
        $targetMetric = (string) ($prediction['target'] ?? '');
        $rawValue = (float) ($prediction['predicted_value'] ?? 0);
        $horizonFromPython = (int) ($prediction['horizon_hours'] ?? 0);
        $current = is_numeric($currentValue) ? (float) $currentValue : $this->latestSeriesValue($inputData, $targetMetric, $rawValue);

        // Jika Python mengirimkan prediksi spesifik (misal 24 jam dari LSTM Kaggle),
        // buat interpolasi linier untuk 1 dan 6 jam menuju target 24 jam agar grafik tetap mulus.
        if ($horizonFromPython == 24) {
            $records = [];
            foreach ([1, 6, 24] as $h) {
                if ($h == 24) {
                    $records[] = [
                        'horizon_hours' => 24,
                        'predicted_value' => round($rawValue, 4),
                    ];
                } else {
                    $fraction = $h / 24.0;
                    $interpolated = $current + ($rawValue - $current) * $fraction;
                    $records[] = [
                        'horizon_hours' => $h,
                        'predicted_value' => round($interpolated, 4),
                    ];
                }
            }

            return $records;
        }

        // Jika Python mengembalikan 0 (XGBoost), buat ekstrapolasi fallback matematis
        $records = [[
            'horizon_hours' => 0,
            'predicted_value' => round($rawValue, 4),
        ]];

        foreach ([1, 6, 24] as $horizonHours) {
            $records[] = [
                'horizon_hours' => $horizonHours,
                'predicted_value' => round(
                    $this->projectCalibratedValue($targetMetric, $rawValue, $current, $inputData, $horizonHours),
                    4
                ),
            ];
        }

        return $records;
    }

    private function projectCalibratedValue(
        string $targetMetric,
        float $rawValue,
        float $currentValue,
        array $inputData,
        int $horizonHours
    ): float {
        $series = $this->targetSeries($inputData, $targetMetric);
        if (count($series) > 0) {
            $currentValue = (float) end($series);
        }

        $trendPerHour = $this->recentHourlyTrend($inputData, $targetMetric, $series);
        $typicalStep = $this->medianHourlyStep($inputData, $targetMetric, $series);
        $maxChange = $this->maxAllowedChange($targetMetric, $currentValue, $typicalStep, $horizonHours);

        $rawDelta = $this->clamp($rawValue - $currentValue, -$maxChange, $maxChange);
        $trendDelta = $this->clamp($trendPerHour * $horizonHours, -$maxChange, $maxChange);
        $projected = $currentValue + (0.7 * $rawDelta) + (0.3 * $trendDelta);

        return $this->clampToHistoricalRange($targetMetric, $projected, $currentValue, $series, $maxChange);
    }

    private function targetSeries(array $inputData, string $targetMetric): array
    {
        $values = [];
        foreach (($inputData['rows'] ?? []) as $row) {
            $value = $this->extractTargetValue($row, $targetMetric);
            if ($value !== null && is_finite($value)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function extractTargetValue(array $row, string $targetMetric): ?float
    {
        $targetLower = strtolower($targetMetric);

        if (str_contains($targetLower, 'co2')) {
            return $this->numericValue($row['CO2 (ppm)'] ?? null);
        }

        if (str_contains($targetLower, 'carbon flux') || str_contains($targetLower, 'nee')) {
            $gpp = $this->numericValue($row['gpp'] ?? null);
            $reco = $this->numericValue($row['reco'] ?? null);

            return ($gpp !== null || $reco !== null) ? (float) ($gpp ?? 0) - (float) ($reco ?? 0) : null;
        }

        if (str_contains($targetLower, 'carbon potential') || str_contains($targetLower, 'cps')) {
            $cCurrent = $this->numericValue($row['c_current'] ?? null);
            $cMax = $this->numericValue($row['c_max'] ?? null);
            if ($cCurrent === null || $cMax === null || $cMax <= 0) {
                return null;
            }

            return $this->clamp(1 - ($cCurrent / $cMax), 0, 1);
        }

        if (str_contains($targetLower, 'moisture')) {
            return $this->numericValue($row['Kelembapan Tanah (%)'] ?? $row['kelembapan_tanah'] ?? null);
        }

        if (str_contains($targetLower, 'ph')) {
            return $this->numericValue($row['pH Tanah'] ?? $row['ph_tanah'] ?? null);
        }

        return $this->numericValue($row[$targetMetric] ?? null);
    }

    private function latestSeriesValue(array $inputData, string $targetMetric, float $fallback): float
    {
        $series = $this->targetSeries($inputData, $targetMetric);

        return count($series) > 0 ? (float) end($series) : $fallback;
    }

    private function recentHourlyTrend(array $inputData, string $targetMetric, array $series): float
    {
        $points = $this->targetPoints($inputData, $targetMetric);
        $points = array_slice($points, -7);
        $deltas = [];

        for ($i = 1; $i < count($points); $i++) {
            $hours = max(1 / 60, $points[$i - 1]['time']->diffInMinutes($points[$i]['time']) / 60);
            $deltas[] = ($points[$i]['value'] - $points[$i - 1]['value']) / $hours;
        }

        if (count($deltas) > 0) {
            return array_sum($deltas) / count($deltas);
        }

        if (count($series) >= 2) {
            return (float) end($series) - (float) $series[count($series) - 2];
        }

        return 0.0;
    }

    private function medianHourlyStep(array $inputData, string $targetMetric, array $series): float
    {
        $points = $this->targetPoints($inputData, $targetMetric);
        $steps = [];

        for ($i = 1; $i < count($points); $i++) {
            $hours = max(1 / 60, $points[$i - 1]['time']->diffInMinutes($points[$i]['time']) / 60);
            $steps[] = abs($points[$i]['value'] - $points[$i - 1]['value']) / $hours;
        }

        if (empty($steps) && count($series) >= 2) {
            for ($i = 1; $i < count($series); $i++) {
                $steps[] = abs((float) $series[$i] - (float) $series[$i - 1]);
            }
        }

        if (empty($steps)) {
            return 0.0;
        }

        sort($steps);
        $middle = intdiv(count($steps), 2);

        return count($steps) % 2
            ? (float) $steps[$middle]
            : ((float) $steps[$middle - 1] + (float) $steps[$middle]) / 2;
    }

    private function targetPoints(array $inputData, string $targetMetric): array
    {
        $points = [];
        foreach (($inputData['rows'] ?? []) as $row) {
            $value = $this->extractTargetValue($row, $targetMetric);
            $timestamp = $row['Timestamp'] ?? null;
            if ($value !== null && $timestamp) {
                $points[] = ['time' => Carbon::parse($timestamp), 'value' => $value];
            }
        }

        return $points;
    }

    private function maxAllowedChange(string $targetMetric, float $currentValue, float $typicalStep, int $horizonHours): float
    {
        $scale = sqrt(max(1, $horizonHours));
        $targetLower = strtolower($targetMetric);

        $base = match (true) {
            str_contains($targetLower, 'co2') => 12.0,
            str_contains($targetLower, 'carbon flux') || str_contains($targetLower, 'nee') => max(0.35, abs($currentValue) * 0.25),
            str_contains($targetLower, 'carbon potential') || str_contains($targetLower, 'cps') => 0.03,
            str_contains($targetLower, 'moisture') => 1.5,
            str_contains($targetLower, 'ph') => 0.06,
            default => max(0.1, abs($currentValue) * 0.05),
        };

        $historicalAllowance = $typicalStep > 0 ? $typicalStep * $scale * 2.5 : 0;

        return max($base * $scale, $historicalAllowance);
    }

    private function clampToHistoricalRange(
        string $targetMetric,
        float $projected,
        float $currentValue,
        array $series,
        float $maxChange
    ): float {
        $targetLower = strtolower($targetMetric);

        if (count($series) >= 3) {
            $projected = $this->clamp($projected, min($series) - $maxChange, max($series) + $maxChange);
        }

        if (str_contains($targetLower, 'co2')) {
            return $this->clamp($projected, 250, 1500);
        }
        if (str_contains($targetLower, 'carbon potential') || str_contains($targetLower, 'cps')) {
            return $this->clamp($projected, 0, 1);
        }
        if (str_contains($targetLower, 'moisture')) {
            return $this->clamp($projected, 0, 100);
        }
        if (str_contains($targetLower, 'ph')) {
            return $this->clamp($projected, 3, 10);
        }
        if (str_contains($targetLower, 'carbon flux') || str_contains($targetLower, 'nee')) {
            return $this->clamp($projected, $currentValue - ($maxChange * 1.25), $currentValue + ($maxChange * 1.25));
        }

        return $projected;
    }

    private function numericValue(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    private function findPythonExecutable(): string
    {
        $configured = config('services.ai_model_bundle.python');
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        // Cek venv lokal
        $venvPaths = [
            '/opt/ai_env/bin/python',
            base_path('../ai_env/bin/python'),
            base_path('../ai_env/Scripts/python.exe'),
            base_path('../.venv/bin/python'),
            base_path('../.venv/Scripts/python.exe'),
        ];

        foreach ($venvPaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        // 2. Fallback ke Python global
        return PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';
    }
}
