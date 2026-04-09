<?php

namespace App\Http\Controllers;

use App\Models\BmkgReading;
use Illuminate\Http\Request;
use App\Models\LandPlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class BmkgController extends Controller
    {
        public function index()
        {
            $data = BmkgReading::latest('reference_time')->get();
            return view('pages.bmkg.index', compact('data'));
        }
        
        public function destroy($id)
        {    
            $bmkg = BmkgReading::findOrFail($id);
            $waktu = $bmkg->reference_time;
            
            $bmkg->delete();

            activity()
                ->performedOn($bmkg)
                ->event('delete')
                ->causedBy(Auth::user())
                ->log('Menghapus historis data cuaca BMKG pada tanggal: ' . $waktu);

            return redirect()->back()->with('success', "Data Cuaca ($waktu) berhasil dihapus");
        }

        // Clear Semua Data BMKG
        public function clearBmkgData()
        {       
            BmkgReading::truncate();

            activity()
                ->event('clear')
                ->causedBy(Auth::user())
                ->log('Membersihkan KESELURUHAN tabel data cuaca BMKG');

            return redirect()->back()->with('success', 'Seluruh Data Cuaca BMKG berhasil direset total');
        }

        public function fetchWeatherData()
        {
            try {
                $plots = LandPlot::all();
                
                if ($plots->isEmpty()) {
                    return redirect()->back()->with('warning', 'Tidak ada data lahan yang terdaftar. Silakan tambahkan lahan terlebih dahulu.');
                }

                $apiKey = config('services.openweather.api_key');
                if (!$apiKey) {
                    return redirect()->back()->with('error', 'API Key OpenWeather belum dikonfigurasi di file .env');
                }

                $successCount = 0;

                foreach ($plots as $plot) {
                    $response = Http::timeout(10)->get("https://api.openweathermap.org/data/2.5/weather", [
                        'lat' => $plot->latitude,
                        'lon' => $plot->longitude,
                        'appid' => $apiKey,
                        'units' => 'metric', 
                    ]);

                    if ($response->successful()) {
                        $weather = $response->json();
                        BmkgReading::create([
                            'plot_id' => $plot->id,
                            'reference_time' => Carbon::now(),
                            'air_temperature_bmkg' => $weather['main']['temp'] ?? 0,
                            'air_humidity_bmkg' => $weather['main']['humidity'] ?? 0,
                            'rainfall_mm' => $weather['rain']['1h'] ?? ($weather['rain']['3h'] ?? 0), 
                            'wind_speed_bmkg' => ($weather['wind']['speed'] ?? 0) * 3.6, // m/s ke km/h
                            'wind_direction' => $weather['wind']['deg'] ?? 0,
                            'air_pressure' => $weather['main']['pressure'] ?? 0,
                        ]);
                        
                        $successCount++;
                    }
                }

            return redirect()->back()->with('success', "Berhasil menarik data cuaca terbaru untuk $successCount lahan.");

            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Gagal mengambil data cuaca: ' . $e->getMessage());
        }

    }
}