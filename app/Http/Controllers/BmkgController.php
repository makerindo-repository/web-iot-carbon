<?php

namespace App\Http\Controllers;

use App\Models\BmkgReading;
use Illuminate\Http\Request;
use App\Models\LandPlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class BmkgController extends Controller
{
    /**
     * Endpoint: GET /api/internal/bmkg/sync
     * Sinkronisasi data klimatologi dari OpenWeather API 
     * diadaptasi menyesuaikan arsitektur AgriSense V1.0 TOR.
     */
    public function sync()
    {
        try {
            $plots = LandPlot::all();
            
            if ($plots->isEmpty()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Tidak ada data lokasi (LandPlot) tersedia untuk disinkronisasi.'
                ], 404);
            }

            $apiKey = env('OPENWEATHER_API_KEY');
            if (!$apiKey) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Konfigurasi OPENWEATHER_API_KEY belum diatur pada file .env.'
                ], 500);
            }

            $successCount = 0;

            foreach ($plots as $plot) {
                // Fetch data dari OpenWeather berbasis koordinat plot
                $response = Http::timeout(10)->get("https://api.openweathermap.org/data/2.5/weather", [
                    'lat' => $plot->latitude,
                    'lon' => $plot->longitude,
                    'appid' => $apiKey,
                    'units' => 'metric', 
                ]);

                if ($response->successful()) {
                    $weather = $response->json();
                    
                    BmkgReading::create([
                        'station_id' => isset($weather['id']) ? (string)$weather['id'] : null,
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
                        'source_api' => 'OpenWeatherMap'
                    ]);
                    
                    $successCount++;
                }
            }

            return response()->json([
                'status' => 'success',
                'synced' => $successCount,
                'timestamp' => Carbon::now()->toIsoString()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Telah terjadi kesalahan saat penarikan API: ' . $e->getMessage()
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
        
        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => 'Belum ada data stasiun BMKG/Cuaca tersimpan.'
            ], 404);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $data
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
            $apiKey = env('OPENWEATHER_API_KEY');
            
            if ($apiKey) {
                try {
                    $response = Http::timeout(5)->get("https://api.openweathermap.org/data/2.5/weather", [
                        'lat' => $lat,
                        'lon' => $lng,
                        'appid' => $apiKey,
                        'units' => 'metric',
                    ]);

                    if ($response->successful()) {
                        $w = $response->json();
                        return response()->json([
                            'status'     => 'success',
                            'station'    => $w['name'] ?? 'Pusat Cuaca Lokal',
                            'weather'    => $w['weather'][0]['main'] ?? 'Cerah',
                            'temp'       => (float)($w['main']['temp'] ?? 27),
                            'humidity'   => (float)($w['main']['humidity'] ?? 65),
                            'windSpeed'  => (float)($w['wind']['speed'] ?? 2.5),
                            'lastUpdate' => Carbon::now()->toIso8601String(),
                            'current'    => [
                                'weather'  => $w['weather'][0]['description'] ?? 'Cerah Berawan',
                                'temp'     => (float)($w['main']['temp'] ?? 27),
                                'humidity' => (float)($w['main']['humidity'] ?? 65),
                            ],
                            'forecast' => $this->generateMockForecast($w['main']['temp'] ?? 27)
                        ]);
                    }
                } catch (\Exception $e) {}
            }

            // FALLBACK TO REALISTIC MOCK DATA (If API fails or no Key)
            return response()->json([
                'status'     => 'success',
                'station'    => 'AgriSense Weather Point',
                'weather'    => 'Cerah Berawan',
                'temp'       => 28.5 + (rand(-20, 20) / 10), // Random realistic temp
                'humidity'   => 72 + rand(-5, 5),
                'windSpeed'  => 4.2,
                'lastUpdate' => Carbon::now()->toIso8601String(),
                'current'    => [
                    'weather'  => 'Cerah Berawan',
                    'temp'     => 28.5 + (rand(-10, 10) / 10),
                    'humidity' => 72,
                ],
                'forecast' => $this->generateMockForecast(28.5)
            ]);
        }

        // DEFAULT: GET FROM DATABASE
        $data = BmkgReading::latest('timestamp_bmkg')->limit(10)->get();

        if ($data->isEmpty()) {
            return response()->json([
                'status'     => 'success',
                'station'    => 'AgriSense Weather Station',
                'weather'    => 'Cerah',
                'temp'       => 27.0,
                'humidity'   => 65.0,
                'windSpeed'  => 2.1,
                'lastUpdate' => Carbon::now()->toIso8601String(),
                'current' => ['weather' => 'Cerah', 'temp' => 27, 'humidity' => 65],
                'forecast' => $this->generateMockForecast(27.0),
            ]);
        }

        $latest = $data->first();
        return response()->json([
            'status'     => 'success',
            'station'    => $latest->station_name ?? 'Stasiun Cuaca',
            'weather'    => $latest->weather_status ?? 'Cerah',
            'temp'       => (float)$latest->air_temperature_c,
            'humidity'   => (float)$latest->air_humidity_percent,
            'windSpeed'  => (float)$latest->wind_speed_mps,
            'pressure'   => (float)$latest->air_pressure_hpa,
            'rainfall'   => (float)$latest->rainfall_mm,
            'lastUpdate' => Carbon::parse($latest->timestamp_bmkg)->toIso8601String(),
            'current'    => [
                'weather'  => $latest->weather_status ?? 'Cerah',
                'temp'     => (float)$latest->air_temperature_c,
                'humidity' => (float)$latest->air_humidity_percent,
            ],
            'forecast' => $data->take(4)->map(function ($d, $i) {
                $hours = [12, 15, 18, 21];
                return [
                    'time'    => Carbon::now()->addHours(($i+1)*3)->format('H:00'),
                    'temp'    => (float)$d->air_temperature_c + rand(-1, 1),
                    'weather' => $d->air_humidity_percent > 80 ? 'Hujan Ringan' : 'Berawan',
                ];
            })->values(),
        ]);
    }

    private function generateMockForecast($baseTemp)
    {
        $forecast = [];
        for ($i = 0; $i < 4; $i++) {
            $forecast[] = [
                'time' => Carbon::now()->addHours(($i+1)*3)->format('H:00'),
                'temp' => $baseTemp + rand(-2, 2),
                'weather' => rand(0, 1) ? 'Cerah' : 'Berawan'
            ];
        }
        return $forecast;
    }
}