<?php

namespace App\Services;

use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiInsightService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
Anda adalah asisten analisis agronomi pada sistem AgriSense. Berdasarkan data
sensor lahan berikut, berikan analisis kondisi lahan dan rekomendasi tindakan
yang relevan bagi peneliti/petani. Jawab HANYA dalam format JSON valid tanpa
markdown maupun teks tambahan, persis dengan struktur:
{"analisis": "...", "rekomendasi": "..."}
Gunakan Bahasa Indonesia baku serta istilah agronomi/karbon yang tepat.
Analisis maksimal 4 kalimat, rekomendasi maksimal 3 kalimat.
PROMPT;

    /**
     * Urutan provider yang dicoba: provider pilihan (AI_PROVIDER) lebih dulu,
     * diikuti provider lain sebagai fallback jika provider utama gagal/tidak
     * terkonfigurasi. Rule-based selalu menjadi fallback terakhir di luar chain ini.
     */
    public static function providerChain(): array
    {
        $primary = strtolower((string) env('AI_PROVIDER', 'gemini'));
        $all = ['gemini', 'groq', 'openrouter'];

        return array_values(array_unique(array_merge(
            in_array($primary, $all, true) ? [$primary] : [],
            $all
        )));
    }

    /**
     * Mencoba tiap provider LLM yang terkonfigurasi secara berurutan.
     * Mengembalikan [analisis, rekomendasi, provider] pada percobaan pertama
     * yang berhasil, atau null jika seluruh provider gagal/tidak terkonfigurasi
     * (di luar kendali kode ini — misalnya kuota habis atau jaringan terputus).
     */
    public static function generateViaLlm(array $context): ?array
    {
        $prompt = self::buildPrompt($context);

        foreach (self::providerChain() as $provider) {
            try {
                $text = match ($provider) {
                    'gemini' => self::callGemini($prompt),
                    'groq' => self::callGroq($prompt),
                    'openrouter' => self::callOpenRouter($prompt),
                    default => null,
                };

                if ($text === null) {
                    continue;
                }

                $parsed = self::parseJsonResponse($text);
                if ($parsed !== null) {
                    $providerLabel = $provider === 'openrouter'
                        ? 'openrouter:'.env('OPENROUTER_MODEL', 'auto')
                        : $provider;

                    return [$parsed['analisis'], $parsed['rekomendasi'], $providerLabel];
                }

                Log::warning("AI Insight: respons provider [{$provider}] tidak dapat diuraikan sebagai JSON.");
            } catch (\Throwable $e) {
                Log::warning("AI Insight: provider [{$provider}] gagal dihubungi.", ['error' => $e->getMessage()]);
            }
        }

        return null;
    }

    private static function callGemini(string $prompt): ?string
    {
        if (! env('GEMINI_API_KEY')) {
            return null;
        }

        $result = Gemini::generativeModel(model: 'gemini-2.0-flash')->generateContent($prompt);

        return $result->text();
    }

    private static function callGroq(string $prompt): ?string
    {
        $key = env('GROQ_API_KEY');
        if (! $key) {
            return null;
        }

        $response = Http::withToken($key)
            ->timeout((int) env('GEMINI_REQUEST_TIMEOUT', 20))
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => 'llama-3.1-8b-instant',
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 500,
                'temperature' => 0.4,
            ]);

        return $response->successful() ? $response->json('choices.0.message.content') : null;
    }

    private static function callOpenRouter(string $prompt): ?string
    {
        $key = env('OPENROUTER_API_KEY');
        if (! $key) {
            return null;
        }

        $response = Http::withToken($key)
            ->timeout(20)
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => env('OPENROUTER_MODEL', 'openrouter/auto'),
                'messages' => [['role' => 'user', 'content' => $prompt]],
                'max_tokens' => 500,
            ]);

        return $response->successful() ? $response->json('choices.0.message.content') : null;
    }

    private static function buildPrompt(array $context): string
    {
        $lines = [];
        foreach ($context as $label => $value) {
            $lines[] = "- {$label}: {$value}";
        }

        return self::SYSTEM_PROMPT."\n\nData kondisi lahan terkini:\n".implode("\n", $lines);
    }

    private static function parseJsonResponse(string $text): ?array
    {
        $clean = trim($text);
        $clean = preg_replace('/^```(?:json)?/i', '', $clean) ?? $clean;
        $clean = preg_replace('/```$/', '', trim($clean)) ?? $clean;

        $decoded = json_decode(trim($clean), true);

        if (! is_array($decoded) || empty($decoded['analisis']) || empty($decoded['rekomendasi'])) {
            return null;
        }

        return [
            'analisis' => (string) $decoded['analisis'],
            'rekomendasi' => (string) $decoded['rekomendasi'],
        ];
    }

    /**
     * Analisis deterministik berbasis aturan ("Sistem Pakar AgriSense") — tidak
     * bergantung pada layanan LLM eksternal, sehingga selalu tersedia sebagai
     * fallback yang jujur, bukan simulasi acak. Logika sejajar dengan heuristik
     * sisi klien pada src/pages/AnalyticsView.tsx (heuristicInsights) agar nada
     * dan istilah analisis tetap konsisten antara mode viewer dan mode LLM.
     */
    public static function generateRuleBased(array $context): array
    {
        $alt = (float) ($context['altitude_m'] ?? 0);
        $temp = (float) ($context['air_temperature_c'] ?? 25);
        $moist = (float) ($context['soil_moisture_percent'] ?? 50);
        $ph = (float) ($context['soil_ph'] ?? 7);
        $n = (float) ($context['soil_n_mg_kg'] ?? 0);
        $light = (float) ($context['light_lux'] ?? 0);
        $co2 = (float) ($context['co2_ppm'] ?? 400);
        $pressure = (float) ($context['air_pressure_hpa'] ?? 1013);

        $analysis = sprintf('Elevasi %.0f m dpl dengan tekanan udara %.0f hPa. ', $alt, $pressure);
        if ($alt > 800) {
            $analysis .= 'Suhu sejuk pada ketinggian ini memperlambat sebagian proses respirasi tanah dan cenderung menjaga kestabilan karbon organik. ';
        } elseif ($alt < 100) {
            $analysis .= 'Lahan dataran rendah perlu dipantau karena kelembapan dan salinitas dapat memengaruhi emisi karbon tanah. ';
        }

        if ($temp > 32 && $moist < 40) {
            $analysis .= sprintf('Kombinasi suhu tinggi (%.1f°C) dan tanah kering menekan efisiensi fotosintesis (GPP) serta meningkatkan risiko mineralisasi karbon organik tanah (SOC).', $temp);
        } elseif ($temp < 20 && $moist > 80) {
            $analysis .= 'Kondisi dingin dan lembap memperlambat aktivitas enzimatik pada tanaman, namun dapat meningkatkan respirasi tanah dari dekomposisi bahan organik.';
        } else {
            $analysis .= 'Iklim mikro pada lokasi ini relatif mendukung pertukaran CO2 yang stabil, sehingga interpretasi neraca karbon (NEE) lebih dapat diandalkan.';
        }

        $recommendations = [];
        if ($ph < 5.5) {
            $recommendations[] = sprintf('pH tanah tergolong sangat masam (%.1f); aktivitas mikroba dekomposer berpotensi menurun sehingga perputaran karbon organik tanah melambat.', $ph);
        } elseif ($ph > 7.5) {
            $recommendations[] = sprintf('pH tanah tergolong basa (%.1f); mineralisasi karbon organik dapat melambat, namun tetap perlu dikaitkan dengan data kelembapan dan CO2 setempat.', $ph);
        }

        if ($n < 40 && $moist > 70) {
            $recommendations[] = sprintf('Kadar nitrogen rendah (%.0f mg/kg) pada tanah yang jenuh air meningkatkan risiko denitrifikasi dan emisi N2O; disarankan pemantauan neraca gas rumah kaca lebih lanjut.', $n);
        } elseif ($n < 40) {
            $recommendations[] = sprintf('Kadar nitrogen rendah (%.0f mg/kg) berpotensi membatasi pembentukan biomassa dan input karbon organik baru ke tanah.', $n);
        }

        if ($light > 20000 && $moist < 30) {
            $recommendations[] = sprintf('Intensitas cahaya tinggi (%.0f lux) dengan tanah kering berisiko menekan GPP sementara respirasi tetap berlangsung; disarankan memantau NEE dan kelembapan tanah secara berkala.', $light);
        } elseif ($light < 5000 && $co2 > 600) {
            $recommendations[] = 'Fase respirasi tampak dominan: intensitas cahaya rendah menekan serapan CO2 sementara konsentrasi CO2 terakumulasi dari respirasi tanah dan biomassa.';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Kondisi mikroklimat saat ini mendukung siklus karbon yang stabil. Disarankan mempertahankan pemantauan rutin terhadap CO2, kelembapan tanah, dan suhu untuk menjaga potensi sekuestrasi karbon.';
        }

        return [trim($analysis), implode(' ', $recommendations)];
    }
}
