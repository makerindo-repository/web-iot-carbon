<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\ForecastPrediction;
use App\Models\IotReading;
use Illuminate\Http\Request;

class ModelPerformanceController extends Controller
{
    private function bundlePath(): string
    {
        return rtrim(env('AI_MODEL_BUNDLE_PATH', base_path('../docs/deploy_model_bundle')), '/');
    }

    /**
     * GET /api/model-performance
     *
     * Menyajikan metrik evaluasi (RMSE/MAE/MAPE/R2) hasil pelatihan model
     * LSTM/XGBoost/SVM dari comparison_payload.json pada model bundle.
     * Metrik ini adalah hasil evaluasi pada dataset sintetik_90 (FLUXNET yang
     * ditransformasikan ke iklim tropis), BUKAN akurasi operasional langsung
     * di lapangan — hal ini dijelaskan secara eksplisit pada evaluation_note.
     */
    public function index()
    {
        $path = $this->bundlePath().'/artifacts/comparison_payload.json';

        if (! is_file($path)) {
            return response()->json([
                'success' => false,
                'message' => 'Berkas metrik perbandingan model (comparison_payload.json) tidak ditemukan pada model bundle.',
            ], 404);
        }

        $payload = json_decode((string) file_get_contents($path), true);

        if (! is_array($payload) || empty($payload['comparison_rows'])) {
            return response()->json([
                'success' => false,
                'message' => 'Berkas metrik perbandingan model tidak valid atau kosong.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'comparison_rows' => $payload['comparison_rows'],
                'models' => $payload['models'] ?? [],
                'evaluation_note' => trim(sprintf(
                    '%s Dievaluasi pada dataset %s (%s), bukan pengukuran akurasi operasional langsung di lapangan.',
                    $payload['description'] ?? 'Evaluasi model dilakukan pada data uji tersimpan.',
                    $payload['source_folder'] ?? 'sintetik_90',
                    $payload['evaluation_scope'] ?? 'evaluasi historis'
                )),
            ],
        ]);
    }

    /**
     * GET /api/model-performance/node/{id}
     *
     * Evaluasi per-node dihitung dari pasangan prediksi vs aktual yang benar-benar
     * tersimpan pada tabel forecast_predictions (diisi oleh jadwal forecasting).
     * Pada node yang belum memiliki riwayat prediksi yang jatuh tempo, endpoint ini
     * tetap mengembalikan 200 OK dengan evaluation kosong dan catatan penjelasan —
     * BUKAN metrik rekaan — sehingga antarmuka otomatis kembali memakai metrik
     * agregat global dari GET /api/model-performance.
     */
    public function forNode(Request $request, string $id)
    {
        $device = Device::where('device_code', $id)->first() ?? Device::find($id);

        if (! $device) {
            return response()->json([
                'success' => false,
                'message' => "Node dengan ID/kode {$id} tidak ditemukan.",
            ], 404);
        }

        $duePredictions = ForecastPrediction::where('device_id', $device->id)
            ->whereNotNull('predicted_value')
            ->where('predicted_for', '<=', now())
            ->orderBy('predicted_for')
            ->get();

        $evaluation = [];

        if ($duePredictions->isNotEmpty()) {
            $grouped = $duePredictions->groupBy(
                fn ($p) => $p->model_used.'|'.$p->target_metric.'|'.$p->horizon_hours
            );

            foreach ($grouped as $key => $group)
            {
                [$model, $target, $horizon] = explode('|', $key);

                $pairs = [];
                foreach ($group as $prediction) {
                    $actualValue = $this->findActualValue($device->id, $target, $prediction->predicted_for);
                    if ($actualValue !== null) {
                        $pairs[] = ['predicted' => (float) $prediction->predicted_value, 'actual' => $actualValue];
                    }
                }

                if (count($pairs) === 0) {
                    continue;
                }

                $evaluation[] = array_merge([
                    'model' => strtoupper($model),
                    'target' => $target,
                    'horizon_hours' => (int) $horizon,
                    'n_pairs' => count($pairs),
                ], $this->computeMetrics($pairs));
            }
        }

        return response()->json([
            'success' => true,
            'node' => $device->device_code,
            'evaluation' => $evaluation,
            'note' => empty($evaluation)
                ? 'Belum ada pasangan data prediksi vs aktual yang matang (jatuh tempo) untuk node ini. Metrik agregat pada GET /api/model-performance tetap berlaku sebagai acuan sementara.'
                : 'Metrik dihitung dari pasangan prediksi vs aktual node ini yang tersimpan pada tabel forecast_predictions.',
        ]);
    }

    /**
     * Memetakan nama target model ke kolom sensor riil yang sebanding.
     * "Carbon Potential Score" sengaja TIDAK dipetakan karena merupakan
     * indeks komposit tanpa padanan pengukuran sensor langsung.
     */
    private function findActualValue(int $deviceId, string $target, \DateTimeInterface $predictedFor): ?float
    {
        $column = match ($target) {
            'CO2 (ppm)' => 'co2_sensor',
            'Carbon Flux (NEE AgriSense)' => 'carbon_flux',
            'Soil Moisture (%)' => 'soil_moisture',
            'pH Tanah' => 'soil_ph',
            default => null,
        };

        if ($column === null) {
            return null;
        }

        $reading = IotReading::where('device_id', $deviceId)
            ->whereBetween('reading_time', [
                (clone $predictedFor)->modify('-30 minutes'),
                (clone $predictedFor)->modify('+30 minutes'),
            ])
            ->orderByRaw('ABS(TIMESTAMPDIFF(SECOND, reading_time, ?))', [$predictedFor])
            ->first();

        if (! $reading || $reading->{$column} === null) {
            return null;
        }

        return (float) $reading->{$column};
    }

    private function computeMetrics(array $pairs): array
    {
        $n = count($pairs);
        $absErrors = [];
        $sqErrors = [];
        $pctErrors = [];
        $actuals = [];

        foreach ($pairs as $p) {
            $err = $p['predicted'] - $p['actual'];
            $absErrors[] = abs($err);
            $sqErrors[] = $err ** 2;
            $actuals[] = $p['actual'];
            if (abs($p['actual']) > 1e-6) {
                $pctErrors[] = abs($err / $p['actual']) * 100;
            }
        }

        $mae = array_sum($absErrors) / $n;
        $rmse = sqrt(array_sum($sqErrors) / $n);
        $mape = count($pctErrors) > 0 ? array_sum($pctErrors) / count($pctErrors) : null;

        $meanActual = array_sum($actuals) / $n;
        $ssTot = array_sum(array_map(fn ($a) => ($a - $meanActual) ** 2, $actuals));
        $ssRes = array_sum($sqErrors);
        $r2 = ($n > 1 && $ssTot > 1e-9) ? 1 - ($ssRes / $ssTot) : null;

        return [
            'MAE' => round($mae, 4),
            'RMSE' => round($rmse, 4),
            'MAPE_pct' => $mape !== null ? round($mape, 2) : null,
            'R2' => $r2 !== null ? round($r2, 4) : null,
        ];
    }
}
