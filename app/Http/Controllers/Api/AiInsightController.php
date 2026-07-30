<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiInsightHistory;
use App\Models\CarbonDailyStock;
use App\Models\Device;
use App\Models\IotReading;
use App\Services\CarbonFluxService;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiInsightController extends Controller
{
    /**
     * Generate AI-powered agronomic insight based on aggregated sensor data.
     *
     * Implements:
     * - Time-range synchronization with frontend filter
     * - Data aggregation (AVG/MIN/MAX) to minimize token usage
     * - Cache with unique key per node+timeRange (TTL 6 hours)
     * - Outlier sanitization
     * - Graceful JSON fallback if AI returns malformed response
     * - Data sufficiency warning (not failure)
     */
    public function generateInsight(Request $request)
    {
        // Cegah timeout jika semua provider AI dipanggil secara berurutan
        if (function_exists('set_time_limit')) {
            try {
                set_time_limit(120);
            } catch (\Throwable $e) {
                Log::warning('AI Insight timeout limit could not be adjusted: '.$e->getMessage());
            }
        }

        $request->validate([
            'node_id' => $this->nodeIdRules(),
            'time_range' => 'required|string|in:24h,7d,30d',
            'force_rule_based' => 'sometimes|boolean',
        ]);

        $nodeIdInput = $request->node_id;
        $timeRange = $request->time_range;
        $forceRuleBased = filter_var($request->input('force_rule_based', false), FILTER_VALIDATE_BOOLEAN);

        try {
            $device = Device::where('device_code', $nodeIdInput)->first()
                ?? Device::find($nodeIdInput);
        } catch (\Throwable $e) {
            Log::error('AI Insight device lookup failed: '.$e->getMessage());

            $ruleBased = $this->generateRuleBasedAnalysis([
                'avg_temp' => 25,
                'avg_hum' => 70,
                'avg_co2' => 400,
                'avg_soil' => 50,
                'avg_lux' => 5000,
            ]);

            $ruleBased['data_warning'] = 'Database belum dapat diakses, sehingga AI Insight menampilkan analisis darurat berbasis aturan.';
            $ruleBased['time_range'] = $timeRange;
            $ruleBased['generated_at'] = now()->timezone('Asia/Jakarta')->format('d M Y, H:i').' WIB';
            $ruleBased['provider'] = 'rule-based';

            return $this->safeJsonResponse($ruleBased);
        }

        if (! $device) {
            return $this->safeJsonResponse([
                'analisis' => 'Node dengan ID "'.$nodeIdInput.'" tidak ditemukan di database.',
                'rekomendasi' => 'Pastikan node sudah terdaftar di menu Manajemen Perangkat.',
                'data_warning' => null,
            ]);
        }

        $nodeId = $device->id; // Numeric PK for querying iot_readings

        // ═══════════════════════════════════════════════════════
        // Check API Keys (Gemini, Groq & OpenRouter/Qwen)
        // ═══════════════════════════════════════════════════════
        $geminiKey = config('gemini.api_key');
        $groqKey = config('services.groq.key');
        $openrouterKey = config('services.openrouter.key');
        $preferredProvider = strtolower((string) config('services.ai_provider', 'gemini'));
        $configurationWarning = null;

        if ($forceRuleBased) {
            $preferredProvider = 'rule-based';
        }

        if (! $geminiKey && ! $groqKey && ! $openrouterKey) {
            Log::warning('AI Insight: all external AI keys are missing, forcing rule-based analysis.');
            $preferredProvider = 'rule-based';
            $configurationWarning = 'Layanan AI eksternal belum dikonfigurasi, sehingga analisis menggunakan sistem pakar AgriSense.';
        }

        // ═══════════════════════════════════════════════════════
        // Cache key unik per node + time_range. v5 invalidates older agronomic wording cache.
        $cacheKey = 'ai_insight_v5_'.$preferredProvider.'_'.$nodeId.'_'.$timeRange;
        try {
            if (Cache::has($cacheKey)) {
                $cachedResponse = Cache::get($cacheKey);

                if (is_array($cachedResponse) && isset($cachedResponse['analisis'], $cachedResponse['rekomendasi'])) {
                    $this->saveInsightHistory(
                        $nodeId,
                        $timeRange,
                        $cachedResponse['analisis'],
                        $cachedResponse['rekomendasi'],
                        $cachedResponse['provider'] ?? $preferredProvider
                    );
                }

                return $this->safeJsonResponse($cachedResponse);
            }
        } catch (\Throwable $cacheEx) {
            Log::warning('AI Insight cache read skipped: '.$cacheEx->getMessage());
        }

        try {
            // ═══════════════════════════════════════════════════════
            // Determine date threshold based on frontend filter
            // ═══════════════════════════════════════════════════════
            $rangeLabel = '24 jam terakhir';
            if ($timeRange === '7d') {
                $dateThreshold = now()->subDays(7);
                $rangeLabel = '7 hari terakhir';
            } elseif ($timeRange === '30d') {
                $dateThreshold = now()->subDays(30);
                $rangeLabel = '30 hari terakhir';
            } else {
                $dateThreshold = now()->subHours(24);
                $rangeLabel = '24 jam terakhir';
            }

            // Data Aggregation — AVG, MIN, MAX via SQL

            $stats = IotReading::where('device_id', $nodeId)
                ->where('reading_time', '>=', $dateThreshold)
                ->selectRaw('
                    COUNT(*) as total_readings,
                    AVG(air_temperature_sensor) as avg_temp,
                    MIN(air_temperature_sensor) as min_temp,
                    MAX(air_temperature_sensor) as max_temp,
                    AVG(air_humidity_sensor) as avg_humidity,
                    AVG(soil_moisture) as avg_soil_moisture,
                    AVG(soil_temperature) as avg_soil_temp,
                    AVG(soil_ph) as avg_ph,
                    AVG(co2_sensor) as avg_co2,
                    MIN(co2_sensor) as min_co2,
                    MAX(co2_sensor) as max_co2,
                    AVG(light_lux) as avg_lux,
                    AVG(soil_organic_carbon) as avg_soc,
                    AVG(carbon_flux) as avg_carbon_flux
                ')
                ->first();
            $latest = IotReading::where('device_id', $nodeId)
                ->orderBy('reading_time', 'desc')
                ->first();

            if (! $latest) {
                return $this->safeJsonResponse([
                    'analisis' => 'Belum ada data sensor yang tercatat untuk node ini.',
                    'rekomendasi' => 'Pastikan perangkat IoT sudah aktif dan mengirim data.',
                    'data_warning' => null,
                ]);
            }

            $latestStock = CarbonDailyStock::where('device_id', $nodeId)
                ->orderByDesc('stock_date')
                ->first();
            $socBaseline = (float) ($device->landPlot?->soc_baseline_gc_m2 ?? 0);
            if ($socBaseline <= 0) {
                $socBaseline = CarbonFluxService::DEFAULT_SOC_BASELINE_GC_M2;
            }
            $cMax = (float) ($device->landPlot?->c_max_gc_m2 ?? 0);
            if ($cMax <= 0) {
                $cMax = CarbonFluxService::estimateCMax($socBaseline);
            }
            $cumulativeNpp = (float) ($latestStock?->cumulative_npp_gc_m2 ?? 0);
            $cCurrent = $socBaseline + $cumulativeNpp;
            $cpsHeadroom = CarbonFluxService::calculateCPS($cCurrent, $cMax);

            $totalReadings = $stats->total_readings ?? 0;

            // ═══════════════════════════════════════════════════════
            // CASE 1: Data Sufficiency Check (Warning, NOT failure)
            // ═══════════════════════════════════════════════════════
            $dataWarning = null;
            if ($totalReadings < 5) {
                $dataWarning = "Data yang tersedia dalam rentang {$rangeLabel} hanya {$totalReadings} pembacaan. Hasil analisis mungkin belum sepenuhnya representatif.";
            }
            if ($configurationWarning) {
                $dataWarning = $dataWarning
                    ? $dataWarning.' '.$configurationWarning
                    : $configurationWarning;
            }

            // ═══════════════════════════════════════════════════════
            // CASE 4: Outlier Sanitization
            // Batas wajar sensor pertanian
            // ═══════════════════════════════════════════════════════
            $avgTemp = $this->sanitize($stats->avg_temp, -10, 60, null);
            $avgHumidity = $this->sanitize($stats->avg_humidity, 0, 100, null);
            $avgMoisture = $this->sanitize($stats->avg_soil_moisture, 0, 100, null);
            $avgPh = $this->sanitize($stats->avg_ph, 0, 14, null);
            $avgCo2 = $this->sanitize($stats->avg_co2, 100, 2000, null);
            $avgLux = $this->sanitize($stats->avg_lux, 0, 120000, null);

            // CCI calculation
            $cci = ($avgCo2 && $avgCo2 > 0) ? round($avgCo2 / 400, 3) : null;

            // Context Lingkungan
            $kondisiSekitar = $device->garden?->kondisi_sekitar ?? 'Tidak diketahui';
            $jarakJalan = $device->garden?->jarak_jalan_m;
            $jarakJalanLabel = $jarakJalan !== null ? "{$jarakJalan} meter" : 'Tidak diketahui';

            // Build data summary string for prompt
            $dataSummary = "Rentang analisis: {$rangeLabel} ({$totalReadings} pembacaan sensor)\n";
            $dataSummary .= $avgTemp !== null ? "- Suhu Udara: Rata-rata {$this->fmt($avgTemp)}°C (Min: {$this->fmt($stats->min_temp)}°C, Maks: {$this->fmt($stats->max_temp)}°C)\n" : '';
            $dataSummary .= $avgHumidity !== null ? "- Kelembapan Udara: Rata-rata {$this->fmt($avgHumidity)}%\n" : '';
            $dataSummary .= $avgMoisture !== null ? "- Kelembapan Tanah: Rata-rata {$this->fmt($avgMoisture)}%\n" : '';
            $dataSummary .= $avgPh !== null ? "- pH Tanah: Rata-rata {$this->fmt($avgPh)}\n" : '';
            $dataSummary .= $avgCo2 !== null ? "- CO2 Udara: Rata-rata {$this->fmt($avgCo2)} ppm (Min: {$this->fmt($stats->min_co2)}, Maks: {$this->fmt($stats->max_co2)})\n" : '';
            $dataSummary .= $avgLux !== null ? "- Intensitas Cahaya: Rata-rata {$this->fmt($avgLux)} Lux\n" : '';
            $dataSummary .= $cci !== null ? "- Carbon Capture Index (CCI): {$cci}\n" : '';
            $dataSummary .= "- Konteks Lingkungan Sekitar: {$kondisiSekitar}\n";
            $dataSummary .= "- Jarak ke Jalan Raya Terdekat: {$jarakJalanLabel}\n";
            $dataSummary .= "- SOC Baseline SoilGrids/fallback: {$this->fmt($socBaseline)} gC/m2\n";
            $dataSummary .= "- Akumulasi NPP/Biomassa: {$this->fmt($cumulativeNpp)} gC/m2\n";
            $dataSummary .= "- C_current = SOC_baseline + C_biomass,acc: {$this->fmt($cCurrent)} gC/m2\n";
            $dataSummary .= "- C_max = SOC_baseline x 2.0: {$this->fmt($cMax)} gC/m2\n";
            $dataSummary .= '- Carbon Potential Score (CPS Headroom): '.round($cpsHeadroom * 100, 1)."%\n";
            $rangeLabel = ($timeRange === '7d') ? '7 hari terakhir' : (($timeRange === '30d') ? '30 hari terakhir' : '24 jam terakhir');

            // ═══════════════════════════════════════════════════════
            // 2. Generate Insight (AI with Multiple Fallbacks)
            //    Chain: Gemini → Groq → OpenRouter/Qwen → Rule-Based
            // ═══════════════════════════════════════════════
            $aiResponseText = null;
            $usedProvider = 'gemini';
            $prompt = "Anda adalah analis siklus karbon untuk AgriSense. AgriSense berfokus pada carbon flux/NEE, CO₂ lokal, SOC, NPP/GPP, CCI, CPS, respirasi tanah, emisi karbon, dan potensi sequestration lahan.\n\n"
                ."Tugas Anda: tafsirkan data berikut HANYA dari perspektif dinamika karbon lahan, bukan dari perspektif budidaya, produktivitas, kesehatan tanaman, panen, pupuk hasil, atau pertumbuhan tanaman.\n\n"
                .$dataSummary
                ."\nAturan wajib:\n"
                ."1. Jangan gunakan frasa seperti kesehatan tanaman, pertumbuhan tanaman, hasil panen, pemupukan tanaman, akar sehat, atau rekomendasi budidaya.\n"
                ."2. Jika membahas kelembapan tanah, pH, cahaya, atau suhu, jelaskan dampaknya terhadap respirasi tanah, mineralisasi SOC, dekomposisi bahan organik, GPP/NPP, NEE, dan sequestration karbon.\n"
                ."3. Jika CO₂ meningkat, bedakan antara aktivitas respirasi/dekomposisi lokal, kemungkinan akumulasi udara di sekitar sensor, dan potensi anomali sensor. Jangan otomatis menyebut kondisi baik untuk tanaman.\n"
                ."4. Rekomendasi harus berupa aksi pengelolaan karbon lahan: validasi sensor, evaluasi ventilasi/posisi sensor, peningkatan input karbon organik, mulsa/biochar/kompos sebagai input SOC, pengaturan kelembapan untuk menekan mineralisasi berlebih, dan pemantauan NEE/CPS.\n"
                .'5. Berikan respon MURNI JSON valid tanpa markdown dengan format: {"analisis":"...","rekomendasi":"..."}.';

            try {
                if ($forceRuleBased || in_array($preferredProvider, ['rule-based', 'rule_based', 'local', 'off'], true)) {
                    $geminiKey = null;
                    $groqKey = null;
                    $openrouterKey = null;
                }

                // Tahap 1: Gemini
                if ($geminiKey) {
                    $result = Gemini::generativeModel('gemini-2.0-flash')->generateContent($prompt);
                    $aiResponseText = $result->text();
                    $usedProvider = 'gemini';
                } else {
                    throw new \Exception('Gemini Key Missing');
                }
            } catch (\Exception $e) {
                Log::warning('Gemini Failed, Trying Groq: '.$e->getMessage());
                try {
                    // Tahap 2: Groq
                    if ($groqKey) {
                        $response = Http::timeout(8)->withToken($groqKey)
                            ->post('https://api.groq.com/openai/v1/chat/completions', [
                                'model' => 'llama-3.3-70b-versatile',
                                'messages' => [
                                    ['role' => 'system', 'content' => 'Respon MURNI JSON.'],
                                    ['role' => 'user', 'content' => $prompt],
                                ],
                                'temperature' => 0.4,
                            ]);

                        if ($response->successful()) {
                            $aiResponseText = $response->json('choices.0.message.content');
                            $usedProvider = 'groq';
                        } else {
                            throw new \Exception('Groq Failed');
                        }
                    } else {
                        throw new \Exception('Groq Key Missing');
                    }
                } catch (\Throwable $e2) {
                    Log::warning('Groq Failed, Trying OpenRouter: '.$e2->getMessage());
                    try {
                        // Tahap 3: OpenRouter
                        if ($openrouterKey) {
                            $openrouterModel = config('services.openrouter.model', 'openrouter/free');
                            $response = Http::timeout(10)->withHeaders([
                                'Authorization' => 'Bearer '.$openrouterKey,
                                'HTTP-Referer' => config('app.url', 'https://agrisense.web.id'),
                                'X-Title' => 'AgriSense Carbon Monitor',
                            ])->post('https://openrouter.ai/api/v1/chat/completions', [
                                'model' => $openrouterModel,
                                'messages' => [
                                    ['role' => 'system', 'content' => 'Respon MURNI JSON. Jangan gunakan markdown atau code block.'],
                                    ['role' => 'user', 'content' => $prompt],
                                ],
                                'temperature' => 0.4,
                                'max_tokens' => 1024,
                            ]);

                            if ($response->successful()) {
                                $aiResponseText = $response->json('choices.0.message.content');
                                $usedProvider = 'openrouter:'.$openrouterModel;
                            } else {
                                Log::error('OpenRouter Response Error: '.$response->body());
                                throw new \Exception('OpenRouter Failed: '.$response->status());
                            }
                        } else {
                            throw new \Exception('OpenRouter Key Missing');
                        }
                    } catch (\Throwable $e3) {
                        Log::error('All AI failed, using Expert System: '.$e3->getMessage());
                        // Tahap 4: Rule-Based (Expert System)
                        $ruleBased = $this->generateRuleBasedAnalysis([
                            'avg_temp' => $avgTemp ?? 25,
                            'avg_hum' => $avgHumidity ?? 70,
                            'avg_co2' => $avgCo2 ?? 400,
                            'avg_soil' => $avgMoisture ?? 50,
                            'avg_lux' => $avgLux ?? 5000,
                            'soc_baseline' => $socBaseline,
                            'c_max' => $cMax,
                            'cumulative_npp' => $cumulativeNpp,
                            'c_current' => $cCurrent,
                            'cps_headroom' => $cpsHeadroom,
                        ]);
                        $aiResponseText = json_encode($ruleBased);
                        $usedProvider = 'rule-based';
                    }
                }
            }

            // ═══════════════════════════════════════════════════════
            // 3. Parsing & Formatting
            // ═══════════════════════════════════════════════════════
            $parsedResponse = $this->parseAiResponse($aiResponseText);
            if ($usedProvider === 'rule-based') {
                $parsedResponse['analisis'] .= "\n\n(Catatan: Menggunakan Sistem Pakar AgriSense karena layanan AI eksternal sibuk.)";
            }

            $parsedResponse['data_warning'] = $dataWarning;
            $parsedResponse['time_range'] = $rangeLabel;
            $parsedResponse['generated_at'] = now()->timezone('Asia/Jakarta')->format('d M Y, H:i').' WIB';
            $parsedResponse['provider'] = $usedProvider;

            // ═══════════════════════════════════════════════════════
            $this->saveInsightHistory(
                $nodeId,
                $timeRange,
                $parsedResponse['analisis'],
                $parsedResponse['rekomendasi'],
                $usedProvider
            );

            // Simpan di Cache selama 6 jam. Cache bersifat opsional agar
            // gangguan Redis tidak membuat AI Insight gagal total.
            try {
                Cache::put($cacheKey, $parsedResponse, now()->addMinutes(360));
            } catch (\Throwable $cacheEx) {
                Log::warning('AI Insight cache write skipped: '.$cacheEx->getMessage());
            }

            return $this->safeJsonResponse($parsedResponse);

        } catch (\Throwable $e) {
            Log::error('Critical AI Insight Error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());

            // Jika benar-benar gagal total, paksa keluarkan Rule-Based di sini juga
            $ruleBased = $this->generateRuleBasedAnalysis([
                'avg_temp' => $avgTemp ?? 25,
                'avg_hum' => $avgHumidity ?? 70,
                'avg_co2' => $avgCo2 ?? 400,
                'avg_soil' => $avgMoisture ?? 50,
                'avg_lux' => 5000,
                'soc_baseline' => $socBaseline ?? CarbonFluxService::DEFAULT_SOC_BASELINE_GC_M2,
                'c_max' => $cMax ?? CarbonFluxService::estimateCMax(CarbonFluxService::DEFAULT_SOC_BASELINE_GC_M2),
                'cumulative_npp' => $cumulativeNpp ?? 0,
                'c_current' => $cCurrent ?? CarbonFluxService::DEFAULT_SOC_BASELINE_GC_M2,
                'cps_headroom' => $cpsHeadroom ?? 0.5,
            ]);
            $ruleBased['analisis'] .= "\n\n(Catatan: Terjadi error sistem kritis, menampilkan analisis darurat.)";
            $ruleBased['data_warning'] = $configurationWarning ?? null;
            $ruleBased['time_range'] = $timeRange;
            $ruleBased['generated_at'] = now()->timezone('Asia/Jakarta')->format('d M Y, H:i').' WIB';
            $ruleBased['provider'] = 'rule-based';

            if (isset($nodeId, $timeRange)) {
                $this->saveInsightHistory(
                    $nodeId,
                    $timeRange,
                    $ruleBased['analisis'],
                    $ruleBased['rekomendasi'],
                    $ruleBased['provider'] ?? 'rule-based'
                );
            }

            return $this->safeJsonResponse($ruleBased);
        }
    }

    /**
     * Aturan validasi node_id: wajib ada, dan harus string (device_code) ATAU
     * integer (PK device — lihat `Device::find($nodeIdInput)`). Menolak
     * array/bool/objek yang sebelumnya memicu QueryException 500 saat lookup.
     * Dipakai konsisten oleh generateInsight() dan getHistory().
     */
    private function nodeIdRules(): array
    {
        return ['required', function ($attribute, $value, $fail) {
            if (! is_string($value) && ! is_int($value)) {
                $fail('Node ID harus berupa teks (device_code) atau angka (ID perangkat).');
            }
        }];
    }

    private function safeJsonResponse($payload, int $status = 200)
    {
        $flags = JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
            | JSON_PARTIAL_OUTPUT_ON_ERROR;

        try {
            return response()->json($this->sanitizeForJson($payload), $status, [], $flags);
        } catch (\Throwable $e) {
            Log::error('AI Insight JSON response failed: '.$e->getMessage());

            return response()->json([
                'analisis' => 'Analisis berhasil diproses, tetapi respons tidak dapat diformat sepenuhnya.',
                'rekomendasi' => 'Coba ulangi permintaan. Jika masih terjadi, periksa data sensor terbaru untuk nilai kosong atau tidak valid.',
                'data_warning' => 'Respons disederhanakan karena payload mengandung nilai yang tidak valid untuk JSON.',
                'provider' => 'rule-based',
            ], 200, [], $flags);
        }
    }

    private function sanitizeForJson($value)
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->sanitizeForJson($item), $value);
        }

        if ($value instanceof Arrayable) {
            return $this->sanitizeForJson($value->toArray());
        }

        if ($value instanceof \JsonSerializable) {
            return $this->sanitizeForJson($value->jsonSerialize());
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_float($value)) {
            return is_finite($value) ? $value : null;
        }

        if (is_string($value) && function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($value, 'UTF-8', 'UTF-8');
        }

        if (is_scalar($value) || $value === null) {
            return $value;
        }

        return method_exists($value, '__toString') ? (string) $value : null;
    }

    private function saveInsightHistory(int $deviceId, string $timeRange, string $analysis, string $recommendation, ?string $provider = null): void
    {
        try {
            AiInsightHistory::create([
                'device_id' => $deviceId,
                'time_range' => $timeRange,
                'provider' => $provider,
                'analysis_text' => $analysis,
                'recommendation_json' => $recommendation,
            ]);
        } catch (\Throwable $dbEx) {
            Log::error('Database Error (History Save): '.$dbEx->getMessage());
        }
    }

    /**
     * ══════════════════════════════════════════════════════════════
     * Scientific Rule-Based Expert System V4 — AgriSense
     * ══════════════════════════════════════════════════════════════
     * 8 Modul Analisis Ilmiah:
     * 1. VPD (Vapor Pressure Deficit) — Ref: Monteith & Unsworth (2013)
     * 2. Kelembapan Tanah (Optimality Trapezoid) — Ref: FAO Paper 56
     * 3. Stres Suhu (Kurva Parabola RuBisCO) — Ref: Farquhar et al. (1980)
     * 4. Aktivitas CO₂ & Respirasi Tanah — Ref: Ryan & Law (2005)
     * 5. pH Tanah — Ref: Brady & Weil (2017)
     * 6. Cahaya & Potensi Fotosintesis — Ref: Monteith (1972)
     * 7. Carbon Potential Score (CPS Headroom) — SOC baseline + akumulasi NPP
     * 8. SOC Proxy Status — Ref: Viscarra Rossel et al. (2006)
     */
    private function generateRuleBasedAnalysis(array $data): array
    {
        $temp = round(floatval($data['avg_temp'] ?? 25), 2);
        $hum = round(floatval($data['avg_hum'] ?? 70), 2);
        $co2 = round(floatval($data['avg_co2'] ?? 420), 2);
        $soil = round(floatval($data['avg_soil'] ?? 50), 2);
        $lux = round(floatval($data['avg_lux'] ?? 5000), 2);

        $sections = [];
        $recommendations = [];
        $overallStatus = 'OPTIMAL';
        $riskLevel = 0; // 0=optimal, 1=waspada, 2=peringatan, 3=bahaya

        // ═══════════════════════════════════════════════════════
        // MODUL 1: VPD (Vapor Pressure Deficit)
        // Ref: Monteith & Unsworth (2013), "Principles of Environmental Physics"
        // Rumus Tetens: es = 0.6108 × exp((17.27×T)/(T+237.3))
        // ═══════════════════════════════════════════════════════
        $es = 0.6108 * exp((17.27 * $temp) / ($temp + 237.3));
        $ea = $es * ($hum / 100);
        $vpd = round($es - $ea, 2);

        $sections[] = 'ANALISIS VPD (Vapor Pressure Deficit)';
        if ($vpd > 2.0) {
            $sections[] = "VPD terukur {$vpd} kPa — KRITIS. Udara terlalu kering pada suhu {$temp}°C, sehingga potensi fiksasi CO₂ dan akumulasi NPP dapat turun sementara respirasi ekosistem tetap berjalan. Kondisi ini menurunkan peluang lahan bertindak sebagai carbon sink.";
            $recommendations[] = 'URGENT: Stabilkan mikroklimat dan kelembapan lahan untuk menjaga pertukaran CO₂ tetap terukur. Verifikasi posisi sensor agar pembacaan tidak bias oleh area terlalu kering atau radiasi langsung.';
            $riskLevel = max($riskLevel, 3);
        } elseif ($vpd > 1.5) {
            $sections[] = "VPD terukur {$vpd} kPa — TINGGI. Tekanan atmosfer terhadap kelembapan dapat mengurangi efisiensi fiksasi CO₂ dan menurunkan GPP/NPP harian.";
            $recommendations[] = 'Pertahankan kelembapan mikroklimat pada jam panas agar NEE tidak bergeser ke carbon source. Pantau ulang CO₂ siang-malam untuk membedakan serapan dan respirasi.';
            $riskLevel = max($riskLevel, 2);
        } elseif ($vpd < 0.4) {
            $sections[] = "VPD terukur {$vpd} kPa — TERLALU RENDAH. Udara sangat lembap dapat memperlambat pertukaran gas dan membuat CO₂ lokal lebih mudah terakumulasi di sekitar sensor.";
            $recommendations[] = 'Periksa ventilasi dan pencampuran udara di sekitar node agar sinyal CO₂ merepresentasikan dinamika karbon lahan, bukan akumulasi lokal.';
            $riskLevel = max($riskLevel, 2);
        } else {
            $sections[] = "VPD terukur {$vpd} kPa — OPTIMAL. Kondisi mikroklimat mendukung pertukaran CO₂ yang stabil dan interpretasi NEE yang lebih andal.";
        }

        // ═══════════════════════════════════════════════════════
        // MODUL 2: Kelembapan Tanah (Optimality Trapezoid)
        // Ref: FAO Irrigation & Drainage Paper No. 56
        // Optimal: 40-60% VWC (kapasitas lapang)
        // ═══════════════════════════════════════════════════════
        $sections[] = "\nANALISIS KELEMBAPAN TANAH";
        if ($soil < 20) {
            $sections[] = "Kelembapan tanah {$soil}% — KRITIS. Aktivitas mikroba dan dekomposisi bahan organik dapat turun tajam, sehingga siklus karbon tanah menjadi tidak stabil dan data NEE berisiko tidak representatif.";
            $recommendations[] = 'Pulihkan kelembapan tanah menuju rentang 40-60% untuk menjaga respirasi tanah, dekomposisi, dan akumulasi SOC tetap berada pada kondisi terukur.';
            $riskLevel = max($riskLevel, 3);
        } elseif ($soil < 40) {
            $sections[] = "Kelembapan tanah {$soil}% — RENDAH. Difusi CO₂ dari tanah dapat terganggu dan aktivitas mikroba pengurai bahan organik cenderung melemah, sehingga estimasi respirasi tanah perlu dibaca hati-hati.";
            $recommendations[] = 'Jaga kelembapan tanah pada 45-55% dan tambahkan input karbon organik seperti mulsa, kompos, atau biochar untuk meningkatkan retensi air sekaligus cadangan SOC.';
            $riskLevel = max($riskLevel, 2);
        } elseif ($soil > 85) {
            $sections[] = "Kelembapan tanah {$soil}% — JENUH AIR. Pori tanah yang terlalu basah dapat memicu kondisi anaerobik, mengubah jalur dekomposisi, dan meningkatkan risiko emisi karbon tanah yang tidak efisien.";
            $recommendations[] = 'Perbaiki drainase dan kurangi genangan agar respirasi tanah kembali aerobik dan pembacaan CO₂/NEE lebih stabil.';
            $riskLevel = max($riskLevel, 2);
        } elseif ($soil >= 40 && $soil <= 60) {
            $sections[] = "Kelembapan tanah {$soil}% — OPTIMAL (kapasitas lapang tercapai). Keseimbangan antara air dan udara di pori tanah sangat baik. Aktivitas mikroba tanah pada puncaknya, mendukung siklus karbon dan nutrisi secara maksimal.";
        } else {
            $sections[] = "Kelembapan tanah {$soil}% — CUKUP BAIK. Kondisi ini masih mendukung aktivitas mikroba dan pertukaran CO₂ tanah, meski perlu dipantau agar tidak bergeser ke jenuh air.";
        }

        // ═══════════════════════════════════════════════════════
        // MODUL 3: Stres Suhu (Kurva Parabola RuBisCO)
        // Ref: Farquhar, von Caemmerer & Berry (1980)
        // T_opt=28°C untuk efisiensi fiksasi karbon C3 tropis, T_min=10°C, T_max=40°C
        // ═══════════════════════════════════════════════════════
        $sections[] = "\nANALISIS STRES SUHU";
        if ($temp <= 10) {
            $tScalar = 0;
            $sections[] = "Suhu udara {$temp}°C — TERLALU DINGIN. Efisiensi fiksasi CO₂ dan GPP dapat turun kuat, sementara interpretasi carbon sink/source perlu dibandingkan dengan respirasi tanah.";
            $recommendations[] = 'Pantau perubahan suhu siang-malam dan bandingkan dengan NEE untuk memastikan apakah lahan tetap berfungsi sebagai sink atau bergeser menjadi source.';
            $riskLevel = max($riskLevel, 2);
        } elseif ($temp >= 40) {
            $tScalar = 0;
            $sections[] = "Suhu udara {$temp}°C — BERBAHAYA. Efisiensi protein fotosintesis dapat turun drastis, sementara respirasi ekosistem meningkat dan berpotensi membuat neraca karbon menjadi source.";
            $recommendations[] = 'Kurangi tekanan panas mikroklimat dan jaga kelembapan tanah agar mineralisasi SOC tidak meningkat berlebihan.';
            $riskLevel = max($riskLevel, 3);
        } elseif ($temp >= 24 && $temp <= 32) {
            $num = ($temp - 10) * (40 - $temp);
            $den = (28 - 10) * (40 - 28);
            $tScalar = round(min(1.0, $num / $den), 2);
            $sections[] = "Suhu udara {$temp}°C — OPTIMAL (efisiensi enzim: {$tScalar}). Kondisi ini mendukung fiksasi CO₂ dan potensi akumulasi NPP yang baik.";
        } else {
            $num = ($temp - 10) * (40 - $temp);
            $den = (28 - 10) * (40 - 28);
            $tScalar = round(max(0, min(1.0, $num / $den)), 2);
            $sections[] = "Suhu udara {$temp}°C — SUBOPTIMAL (efisiensi enzim: {$tScalar}). Potensi GPP/NPP turun sekitar ".round((1 - $tScalar) * 100).'% dari kapasitas model, sehingga carbon sink harian dapat melemah.';
        }

        // ═══════════════════════════════════════════════════════
        // MODUL 4: Aktivitas CO₂ & Respirasi Tanah
        // Ref: Ryan & Law (2005), "Interpreting soil respiration"
        // CO₂ tinggi = aktivitas biologis POSITIF (bukan negatif)
        // ═══════════════════════════════════════════════════════
        $co2Baseline = 420; // ppm NOAA 2024
        $co2Ratio = round($co2 / $co2Baseline, 2);

        $sections[] = "\nANALISIS AKTIVITAS CO₂ & RESPIRASI TANAH";
        if ($co2 < 350) {
            $sections[] = "CO₂ terukur {$co2} ppm (rasio: {$co2Ratio}× baseline) — RENDAH. Aktivitas biologis tanah sangat minim. Mikroba tanah kurang aktif, yang mengindikasikan rendahnya dekomposisi bahan organik. Kemungkinan penyebab: tanah terlalu kering, pH ekstrem, atau kurangnya bahan organik segar.";
            $recommendations[] = 'Tambahkan bahan organik segar (kompos, serasah, atau pupuk kandang) untuk merangsang aktivitas mikroba tanah.';
        } elseif ($co2 > 800) {
            $sections[] = "CO₂ terukur {$co2} ppm (rasio: {$co2Ratio}× baseline) — SANGAT TINGGI/ANOMALI. Lonjakan CO₂ melebihi 2× baseline atmosfer menunjukkan kemungkinan: (a) pengolahan tanah baru-baru ini yang melepas CO₂ terperangkap, (b) dekomposisi masif bahan organik segar, atau (c) sensor perlu dikalibrasi ulang.";
            $recommendations[] = 'Verifikasi pembacaan sensor CO₂. Jika valid, investigasi apakah ada pengolahan tanah atau penambahan bahan organik besar baru-baru ini.';
            $riskLevel = max($riskLevel, 1);
        } elseif ($co2 >= 400 && $co2 <= 600) {
            $sections[] = "CO₂ terukur {$co2} ppm (rasio: {$co2Ratio}× baseline) — ELEVASI TERKENDALI. Konsentrasi di atas baseline atmosfer global (420 ppm) dapat menunjukkan respirasi tanah/dekomposisi aktif atau akumulasi udara lokal di sekitar sensor. Nilai ini perlu dibaca bersama cahaya, kelembapan, dan NEE.";
        } else {
            $sections[] = "CO₂ terukur {$co2} ppm (rasio: {$co2Ratio}× baseline) — NORMAL. Aktivitas biologis tanah dalam rentang wajar.";
        }

        // ═══════════════════════════════════════════════════════
        // MODUL 5: Cahaya & Potensi Fotosintesis
        // Ref: Monteith (1972), "Solar Radiation and Productivity"
        // ═══════════════════════════════════════════════════════
        $sections[] = "\nANALISIS CAHAYA & POTENSI FOTOSINTESIS";
        $par = round($lux * 0.0185, 1); // Lux → µmol m⁻² s⁻¹
        if ($lux < 500) {
            $sections[] = "Intensitas cahaya {$lux} Lux (PAR: ~{$par} µmol/m²/s) — GELAP/MALAM HARI. Fiksasi CO₂ hampir berhenti, sementara respirasi ekosistem tetap melepas CO₂. Ini normal untuk siklus diurnal.";
        } elseif ($lux < 5000) {
            $sections[] = "Intensitas cahaya {$lux} Lux (PAR: ~{$par} µmol/m²/s) — RENDAH. Potensi GPP dan serapan CO₂ berada di bawah kapasitas model, sehingga NEE lebih mudah mendekati source.";
        } elseif ($lux > 50000) {
            $sections[] = "Intensitas cahaya {$lux} Lux (PAR: ~{$par} µmol/m²/s) — SANGAT TERANG. PAR tinggi dapat mendukung GPP, tetapi jika disertai suhu/VPD tinggi efisiensi fiksasi karbon dapat turun.";
            $recommendations[] = 'Bandingkan PAR tinggi dengan suhu, VPD, dan NEE; jika serapan tidak naik, evaluasi tekanan mikroklimat atau anomali sensor cahaya.';
        } else {
            $sections[] = "Intensitas cahaya {$lux} Lux (PAR: ~{$par} µmol/m²/s) — BAIK. Cahaya cukup untuk mendukung fiksasi CO₂ dan pembentukan NPP pada model LUE.";
        }

        // ═══════════════════════════════════════════════════════
        // MODUL 6: Carbon Potential Score (CPS) - Sequestration Headroom
        // ═══════════════════════════════════════════════════════
        // Hitung subscore lingkungan pendukung sebagai konteks naratif.
        $moistureScore = ($soil >= 40 && $soil <= 60) ? 1.0 : (($soil < 40) ? max(0, $soil / 40) : max(0, 1 - ($soil - 60) / 40));
        $tempScore = ($temp <= 5 || $temp >= 45) ? 0.0 : max(0, min(1, (($temp - 5) * (45 - $temp)) / ((25 - 5) * (45 - 25))));
        $co2Score = ($co2Ratio >= 0.9 && $co2Ratio <= 1.4) ? min(1.0, 0.5 + $co2Ratio * 0.35) : (($co2Ratio > 1.4) ? max(0.3, 1.0 - ($co2Ratio - 1.4) * 0.7) : max(0.2, $co2Ratio * 0.6));
        $lightScore = ($lux <= 0) ? 0.3 : min(1.0, $lux / 50000);

        $socBaseline = (float) ($data['soc_baseline'] ?? CarbonFluxService::DEFAULT_SOC_BASELINE_GC_M2);
        $cumulativeNpp = (float) ($data['cumulative_npp'] ?? 0);
        $cCurrent = (float) ($data['c_current'] ?? ($socBaseline + $cumulativeNpp));
        $cMax = (float) ($data['c_max'] ?? CarbonFluxService::estimateCMax($socBaseline));
        $cpsRaw = (float) ($data['cps_headroom'] ?? CarbonFluxService::calculateCPS($cCurrent, $cMax));
        $cps = round(min(100, max(0, $cpsRaw * 100)), 1);

        $cpsClass = ($cps >= 70) ? 'TINGGI' : (($cps >= 40) ? 'SEDANG' : 'RENDAH');

        $sections[] = "\nCARBON POTENTIAL SCORE (CPS)";
        $sections[] = "Skor CPS: {$cps}/100 ({$cpsClass}). CPS dihitung sebagai Sequestration Headroom: CPS = 1 - (C_current / C_max), dengan C_current = SOC_baseline + C_biomass,acc. Nilai saat ini: SOC baseline {$this->fmt($socBaseline)} gC/m2, akumulasi NPP {$this->fmt($cumulativeNpp)} gC/m2, C_current {$this->fmt($cCurrent)} gC/m2, dan C_max {$this->fmt($cMax)} gC/m2.";
        $sections[] = 'Konteks lingkungan pendukung: Kelembapan Tanah '.round($moistureScore * 100).'%, Suhu '.round($tempScore * 100).'%, Aktivitas CO2 '.round($co2Score * 100).'%, Cahaya '.round($lightScore * 100).'%.';

        if ($cps >= 70) {
            $sections[] = 'Ruang penyimpanan karbon model masih besar. Dalam konvensi dashboard AgriSense, carbon flux/NEE positif menunjukkan potensi serapan bersih.';
        } elseif ($cps < 40) {
            $sections[] = 'Ruang penyimpanan karbon model mulai terbatas atau C_current mendekati C_max. Perlu validasi stok karbon dan strategi peningkatan input biomassa.';
            $recommendations[] = 'Tingkatkan tutupan vegetasi, perbaiki irigasi, dan tambahkan mulsa organik untuk memperbaiki siklus karbon lahan.';
        } else {
            $sections[] = 'Ruang penyimpanan karbon model berada pada tingkat sedang. Perbaikan lingkungan tumbuh tetap berguna untuk menjaga akumulasi NPP.';
        }

        // ═══════════════════════════════════════════════════════
        // MODUL 7: SOC Proxy Status
        // ═══════════════════════════════════════════════════════
        $sections[] = "\nSTATUS KARBON ORGANIK TANAH (SOC PROXY)";
        if ($lightScore > 0.5 && $moistureScore > 0.6 && $co2Score > 0.5) {
            $socProxy = 'POTENSI PENINGKATAN';
            $sections[] = "SOC Proxy: {$socProxy}. Cahaya cukup, kelembapan optimal, dan aktivitas biologis aktif mengindikasikan potensi akumulasi karbon organik tanah dalam jangka menengah-panjang.";
        } elseif ($temp > 35 && $soil < 30) {
            $socProxy = 'RISIKO KEHILANGAN';
            $sections[] = "SOC Proxy: {$socProxy}. Suhu tinggi dengan tanah kering mempercepat mineralisasi karbon organik. Laju dekomposisi melebihi input bahan organik baru, menyebabkan penurunan stok karbon tanah.";
            $recommendations[] = 'Tambahkan bahan organik (kompos, biochar) dan tingkatkan irigasi untuk menekan laju mineralisasi SOC.';
        } else {
            $socProxy = 'STABIL';
            $sections[] = "SOC Proxy: {$socProxy}. Input dan output karbon organik tanah relatif seimbang pada kondisi saat ini.";
        }

        $sections[] = "\nCATATAN ILMIAH: SOC Proxy ini adalah estimasi kualitatif dari indikator tidak langsung (suhu, kelembapan, CO₂). Nilai numerik SOC absolut memerlukan analisis laboratorium (metode Walkley-Black atau Loss-on-Ignition). CPS adalah indeks internal AgriSense, bukan standar resmi karbon.";

        // ═══════════════════════════════════════════════════════
        // FINAL ASSEMBLY
        // ═══════════════════════════════════════════════════════
        $statusMap = ['OPTIMAL', 'WASPADA', 'PERINGATAN', 'BAHAYA'];
        $overallStatus = 'STATUS KESELURUHAN: '.$statusMap[$riskLevel];

        $finalAnalisis = $overallStatus."\n\n".implode("\n", $sections);

        if (empty($recommendations)) {
            $recommendations[] = 'Seluruh parameter lingkungan berada dalam kondisi ideal. Pertahankan manajemen lahan saat ini dan lakukan pemantauan rutin setiap 6-12 jam.';
        }

        // Format recommendations as bullet points
        $formattedRecs = array_map(fn ($r) => '• '.$r, array_unique($recommendations));

        return [
            'analisis' => $finalAnalisis,
            'rekomendasi' => implode("\n", $formattedRecs),
        ];
    }

    /**
     * Sanitize sensor value: if outside reasonable bounds, return fallback.
     */
    private function sanitize($value, $min, $max, $fallback)
    {
        if ($value === null) {
            return $fallback;
        }
        $v = floatval($value);

        return ($v >= $min && $v <= $max) ? $v : $fallback;
    }

    /**
     * Format number to 1 decimal place.
     */
    private function fmt($value): string
    {
        return number_format(floatval($value), 1);
    }

    /**
     * Parse AI response text into structured JSON.
     * Handles markdown code blocks, malformed JSON, and plain text fallback.
     */
    private function parseAiResponse(?string $text): array
    {
        if (empty($text)) {
            return [
                'analisis' => 'Tidak ada respons dari layanan AI.',
                'rekomendasi' => 'Sistem pakar akan otomatis mengambil alih pada percobaan berikutnya.',
            ];
        }

        // Strip markdown code fences
        $cleaned = preg_replace('/```(?:json)?\s*/', '', $text);
        $cleaned = trim($cleaned);

        // Try direct JSON parse
        $parsed = json_decode($cleaned, true);
        if ($parsed && isset($parsed['analisis'])) {
            return $parsed;
        }

        // Try extracting JSON from surrounding text
        if (preg_match('/\{[^{}]*"analisis"\s*:\s*"[^"]*"[^{}]*\}/s', $cleaned, $matches)) {
            $parsed = json_decode($matches[0], true);
            if ($parsed && isset($parsed['analisis'])) {
                return $parsed;
            }
        }

        // Ultimate fallback: use raw text
        return [
            'analisis' => $cleaned,
            'rekomendasi' => 'Silakan tekan tombol analisis lagi untuk mendapatkan rekomendasi yang lebih terstruktur.',
        ];
    }

    /**
     * Get AI Insight history for a specific node.
     */
    public function getHistory(Request $request)
    {
        $request->validate([
            'node_id' => $this->nodeIdRules(),
        ]);

        $nodeIdInput = $request->node_id;

        $device = Device::where('device_code', $nodeIdInput)->first()
            ?? Device::find($nodeIdInput);

        if (! $device) {
            return $this->safeJsonResponse([
                'analisis' => 'Gagal menemukan data alat (Device tidak terdaftar).',
                'rekomendasi' => 'Pastikan alat Anda sudah terdaftar di sistem AgriSense.',
                'error' => 'Device not found',
            ], 404);
        }

        $histories = AiInsightHistory::where('device_id', $device->id)
            ->orderBy('created_at', 'desc')
            ->limit(20) // Limit to last 20 for performance
            ->get();

        return $this->safeJsonResponse([
            'success' => true,
            'data' => $histories,
        ]);
    }
}
