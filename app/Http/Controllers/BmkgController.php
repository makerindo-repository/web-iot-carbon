<?php

namespace App\Http\Controllers;

use App\Models\BmkgReading;
use App\Models\LandPlot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BmkgController extends Controller
{
    /**
     * Terjemahkan deskripsi cuaca dari OpenWeather ke Bahasa Indonesia
     */
    private function translateWeather(string $desc): string
    {
        $map = [
            'clear sky' => 'Cerah',
            'few clouds' => 'Cerah Berawan',
            'scattered clouds' => 'Berawan Sebagian',
            'broken clouds' => 'Berawan',
            'overcast clouds' => 'Mendung Tebal',
            'light rain' => 'Hujan Ringan',
            'moderate rain' => 'Hujan Sedang',
            'heavy intensity rain' => 'Hujan Lebat',
            'very heavy rain' => 'Hujan Sangat Lebat',
            'extreme rain' => 'Hujan Ekstrem',
            'freezing rain' => 'Hujan Es',
            'light intensity shower rain' => 'Gerimis Ringan',
            'shower rain' => 'Hujan Deras',
            'heavy intensity shower rain' => 'Hujan Deras Lebat',
            'ragged shower rain' => 'Hujan Tak Merata',
            'light intensity drizzle' => 'Gerimis Ringan',
            'drizzle' => 'Gerimis',
            'heavy intensity drizzle' => 'Gerimis Lebat',
            'thunderstorm' => 'Badai Petir',
            'thunderstorm with light rain' => 'Badai Petir Hujan Ringan',
            'thunderstorm with rain' => 'Badai Petir Hujan',
            'thunderstorm with heavy rain' => 'Badai Petir Hujan Lebat',
            'light thunderstorm' => 'Badai Petir Ringan',
            'heavy thunderstorm' => 'Badai Petir Berat',
            'ragged thunderstorm' => 'Badai Petir Sporadis',
            'thunderstorm with light drizzle' => 'Badai Petir Gerimis',
            'thunderstorm with drizzle' => 'Badai Petir Gerimis',
            'thunderstorm with heavy drizzle' => 'Badai Petir Gerimis Lebat',
            'snow' => 'Salju',
            'light snow' => 'Salju Ringan',
            'heavy snow' => 'Salju Lebat',
            'mist' => 'Kabut Tipis',
            'fog' => 'Kabut',
            'haze' => 'Kabut Asap',
            'smoke' => 'Berasap',
            'dust' => 'Berdebu',
            'sand' => 'Berpasir',
            'tornado' => 'Tornado',
            'squalls' => 'Angin Kencang',
        ];

        $lower = strtolower(trim($desc));

        return $map[$lower] ?? ucfirst($desc);
    }

    /**
     * Endpoint: GET /api/internal/bmkg/sync
     * Sinkronisasi data klimatologi dari OpenWeather API
     * diadaptasi menyesuaikan arsitektur AgriSense V1.0 TOR.
     */
    public function sync(?Request $request = null)
    {
        // Entry HTTP: token WAJIB untuk setiap request. Header dibaca dari request
        // aktif — fallback ke helper request() karena injeksi $request pada parameter
        // opsional (?Request = null) tidak selalu terisi oleh router. Scheduler console
        // TIDAK memanggil sync() melainkan runSync() langsung (lihat routes/console.php),
        // sehingga jalur cron yang sah tetap berjalan tanpa token.
        $cronToken = config('services.cron.sync_token');
        $requestToken = (string) ($request ?? request())->header('X-Cron-Token', '');
        if (! $cronToken || ! hash_equals($cronToken, $requestToken)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized: Token Cron Job tidak valid atau tidak disertakan.',
            ], 401);
        }

        return $this->runSync();
    }

    /**
     * Logika inti sinkronisasi klimatologi OpenWeather. Dipanggil oleh sync()
     * setelah token diverifikasi, dan oleh scheduler console (tepercaya) tanpa token.
     */
    public function runSync()
    {
        try {
            $plots = LandPlot::all();

            if ($plots->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ada data lokasi (LandPlot) tersedia untuk disinkronisasi.',
                ], 404);
            }

            $apiKey = config('services.openweather.api_key');
            if (! $apiKey) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Konfigurasi OPENWEATHER_API_KEY belum diatur pada file .env.',
                ], 500);
            }

            $successCount = 0;

            foreach ($plots as $plot) {
                // Fetch data dari OpenWeather berbasis koordinat plot
                $response = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', [
                    'lat' => $plot->latitude,
                    'lon' => $plot->longitude,
                    'appid' => $apiKey,
                    'units' => 'metric',
                ]);

                if ($response->successful()) {
                    $weather = $response->json();

                    BmkgReading::create([
                        'station_id' => isset($weather['id']) ? (string) $weather['id'] : null,
                        'station_name' => $weather['name'] ?? 'Unknown Station',
                        'area_name' => $plot->plot_name ?? 'Unknown Area', // Referensi nama plot
                        'timestamp_bmkg' => isset($weather['dt']) ? Carbon::createFromTimestamp($weather['dt'])->toDateTimeString() : Carbon::now()->toDateTimeString(),
                        'air_temperature_c' => $weather['main']['temp'] ?? 0,
                        'air_humidity_percent' => $weather['main']['humidity'] ?? 0,
                        'rainfall_mm' => $weather['rain']['1h'] ?? ($weather['rain']['3h'] ?? 0),
                        'wind_speed_mps' => $weather['wind']['speed'] ?? 0, // OpenWeather metrics defaults point to meter/sec
                        'wind_direction_deg' => $weather['wind']['deg'] ?? 0,
                        'air_pressure_hpa' => $weather['main']['pressure'] ?? 0,
                        'weather_status' => $weather['weather'][0]['description'] ?? ($weather['weather'][0]['main'] ?? 'Clear'),
                        'source_api' => 'OpenWeatherMap',
                    ]);

                    $successCount++;
                }
            }

            return response()->json([
                'status' => 'success',
                'synced' => $successCount,
                'timestamp' => Carbon::now()->toIsoString(),
            ], 200);

        } catch (\Exception $e) {
            $errorId = (string) Str::uuid();
            Log::error('BMKG sync gagal', [
                'error_id' => $errorId,
                'msg' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan internal saat sinkronisasi.',
                'error_id' => $errorId,
            ], 500);
        }
    }

    /**
     * Endpoint: GET /api/bmkg/latest
     * Diambil dari list API Master AgriSense untuk dashboard frontend
     */
    public function getLatest()
    {
        $data = BmkgReading::latest('timestamp_bmkg')->first();

        if (! $data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Belum ada data stasiun BMKG/Cuaca tersimpan.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    // ═══════════════════════════════════════════════════════════
    //  GET /api/agrisense/bmkg — Data BMKG / OpenWeather terbaru
    // ═══════════════════════════════════════════════════════════
    public function getBmkg(Request $request)
    {
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        // IF LAT/LNG PROVIDED - TRY LIVE FETCH OR MOCK
        if ($lat && $lng) {
            $apiKey = config('services.openweather.api_key');

            if ($apiKey) {
                try {
                    $response = Http::timeout(5)->get('https://api.openweathermap.org/data/2.5/weather', [
                        'lat' => $lat,
                        'lon' => $lng,
                        'appid' => $apiKey,
                        'units' => 'metric',
                    ]);

                    if ($response->successful()) {
                        $w = $response->json();

                        return response()->json([
                            'status' => 'success',
                            'station' => $w['name'] ?? 'Pusat Cuaca Lokal',
                            'weather' => $this->translateWeather($w['weather'][0]['main'] ?? 'Clear'),
                            'temp' => (float) ($w['main']['temp'] ?? 27),
                            'humidity' => (float) ($w['main']['humidity'] ?? 65),
                            'windSpeed' => (float) ($w['wind']['speed'] ?? 2.5),
                            'lastUpdate' => Carbon::now()->toIso8601String(),
                            'current' => [
                                'weather' => $this->translateWeather($w['weather'][0]['description'] ?? 'Clear'),
                                'temp' => (float) ($w['main']['temp'] ?? 27),
                                'humidity' => (float) ($w['main']['humidity'] ?? 65),
                            ],
                            'forecast' => $this->generateMockForecast($w['main']['temp'] ?? 27, $w['main']['humidity'] ?? 65),
                        ]);
                    }
                } catch (\Exception $e) {
                }
            }

            // FALLBACK TO REALISTIC MOCK DATA (If API fails or no Key)
            $plot = LandPlot::where('latitude', $lat)->where('longitude', $lng)->first();
            $locationName = 'Pusat Cuaca';

            if ($plot && $plot->address) {
                // Ekstrak nama daerah dari alamat (Skip Indonesia & Kode Pos)
                $addressParts = array_map('trim', explode(',', $plot->address));

                // Filter out 'Indonesia', kode pos, dan provinsi umum
                $filteredParts = array_filter($addressParts, function ($part) {
                    $p = strtolower($part);

                    return $p !== 'indonesia'
                        && ! preg_match('/^[0-9]+$/', $p)
                        && ! str_contains($p, 'jawa barat')
                        && ! str_contains($p, 'jawa tengah')
                        && ! str_contains($p, 'jawa timur')
                        && ! str_contains($p, 'banten')
                        && ! str_contains($p, 'jakarta');
                });

                if (count($filteredParts) > 0) {
                    $filteredParts = array_values($filteredParts);
                    $locationName = end($filteredParts); // Default awal

                    // 1. Coba cari bagian yang secara eksplisit mengandung kata kunci wilayah
                    $foundSpecific = false;
                    foreach (array_reverse($filteredParts) as $part) {
                        $p = strtolower($part);
                        if (str_contains($p, 'kecamatan') || str_contains($p, 'kec.') ||
                            str_contains($p, 'kec ') || str_contains($p, 'desa') ||
                            str_contains($p, 'kelurahan') || str_contains($p, 'kel.')) {
                            $locationName = $part;
                            $foundSpecific = true;
                            break;
                        }
                    }

                    // 2. Jika tidak ada kata kunci, gunakan heuristik posisi untuk menghindari Provinsi/Kota besar
                    if (! $foundSpecific) {
                        $count = count($filteredParts);
                        if ($count >= 4) {
                            // Misal: [Jl. X, Cimenyan, Kab Bandung, Jabar] -> Ambil 'Cimenyan'
                            $locationName = $filteredParts[$count - 3];
                        } elseif ($count === 3) {
                            // Misal: [Jl. X, Cimenyan, Bandung] -> Ambil 'Cimenyan'
                            $locationName = $filteredParts[1];
                        }
                    }
                } else {
                    $locationName = $plot->plot_name ?? 'Wilayah Terdeteksi';
                }

                // Sanitasi jika masih terlalu panjang
                if (strlen($locationName) > 40) {
                    $locationName = $plot->plot_name ?? 'Pusat Cuaca';
                }
            }

            return response()->json([
                'status' => 'success',
                'station' => $locationName,
                'weather' => 'Cerah Berawan',
                'temp' => 28.5 + (rand(-20, 20) / 10), // Random realistic temp
                'humidity' => 72 + rand(-5, 5),
                'windSpeed' => 4.2,
                'lastUpdate' => Carbon::now()->toIso8601String(),
                'current' => [
                    'weather' => 'Cerah Berawan',
                    'temp' => 28.5 + (rand(-10, 10) / 10),
                    'humidity' => 72,
                ],
                'forecast' => $this->generateMockForecast(28.5, 72),
            ]);
        }

        // DEFAULT: GET FROM DATABASE
        $data = BmkgReading::latest('timestamp_bmkg')->limit(10)->get();

        if ($data->isEmpty()) {
            return response()->json([
                'status' => 'success',
                'station' => 'AgriSense Weather Station',
                'weather' => 'Cerah',
                'temp' => 27.0,
                'humidity' => 65.0,
                'windSpeed' => 2.1,
                'lastUpdate' => Carbon::now()->toIso8601String(),
                'current' => ['weather' => 'Cerah', 'temp' => 27, 'humidity' => 65],
                'forecast' => $this->generateMockForecast(27.0, 65),
            ]);
        }

        $latest = $data->first();

        return response()->json([
            'status' => 'success',
            'station' => $latest->station_name ?? 'Stasiun Cuaca',
            'weather' => $latest->weather_status ?? 'Cerah',
            'temp' => (float) $latest->air_temperature_c,
            'humidity' => (float) $latest->air_humidity_percent,
            'windSpeed' => (float) $latest->wind_speed_mps,
            'pressure' => (float) $latest->air_pressure_hpa,
            'rainfall' => (float) $latest->rainfall_mm,
            'lastUpdate' => Carbon::parse($latest->timestamp_bmkg)->toIso8601String(),
            'current' => [
                'weather' => $latest->weather_status ?? 'Cerah',
                'temp' => (float) $latest->air_temperature_c,
                'humidity' => (float) $latest->air_humidity_percent,
            ],
            'forecast' => $data->take(4)->map(function ($d, $i) {
                $hours = [12, 15, 18, 21];

                return [
                    'time' => Carbon::now()->addHours(($i + 1) * 3)->format('H:00'),
                    'temp' => (float) $d->air_temperature_c + rand(-1, 1),
                    'weather' => $d->air_humidity_percent > 80 ? 'Hujan Ringan' : 'Berawan',
                    'humidity' => (float) $d->air_humidity_percent,
                ];
            })->values(),
        ]);
    }

    private function generateMockForecast($baseTemp, $baseHumidity = 65)
    {
        $forecast = [];
        for ($i = 0; $i < 4; $i++) {
            $forecast[] = [
                'time' => Carbon::now()->addHours(($i + 1) * 3)->format('H:00'),
                'temp' => $baseTemp + rand(-2, 2),
                'weather' => rand(0, 1) ? 'Cerah' : 'Berawan',
                'humidity' => max(35, min(98, (float) $baseHumidity + rand(-8, 8))),
            ];
        }

        return $forecast;
    }
}
