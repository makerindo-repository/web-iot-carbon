<?php

namespace App\Http\Controllers;

use App\Events\SensorData;
use App\Models\ActivitySchedule;
use App\Models\FilteredFixStation;
use App\Models\FixStation;
use App\Models\SensorThreshold;
use App\Models\SubscriptionPlan;
use App\Models\UserSubscription;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RSCDataController extends Controller
{
    public function index()
    {
        return view('pages.rsc-data.index');
    }

    public function indexMonitoring(Request $request)
    {
        $query = FixStation::query();

        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        // Filter by device ID
        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        $data = $query->latest()->take(100)->get();
        $lastUpdated = FixStation::latest()->first('created_at');

        // Get unique device IDs from the filtered data
        $uniqueDeviceIds = $data->pluck('device_id')->unique()->sort()->values();

        return view('pages.rsc-data.monitoring.raw', compact('data', 'lastUpdated', 'uniqueDeviceIds'));
    }

    public function indexFilteredMonitoring(Request $request)
    {
        $query = FilteredFixStation::query();
        $plans = SubscriptionPlan::where('price', '>', 0)->orderBy('price', 'asc')->get();
        // Filter by date range
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        // Filter by device ID
        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        $data = $query->latest()->take(100)->get();
        $lastUpdated = FilteredFixStation::latest()->first('created_at');

        // Get unique device IDs from the filtered data
        $uniqueDeviceIds = $data->pluck('device_id')->unique()->sort()->values();

        $quotaRemaining = SubscriptionService::remainingQuota(Auth::user());

        $user = Auth::user();
        $userExpires = $user->subscription->expires_at;

        return view('pages.rsc-data.monitoring.filtered', compact('data', 'lastUpdated', 'uniqueDeviceIds', 'quotaRemaining', 'plans', 'userExpires'));
    }

    public function getUniqueDeviceIds(Request $request)
    {
        $query = FixStation::query();

        // Apply same filters as the main query
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        $uniqueDeviceIds = $query->distinct()->pluck('device_id')->sort()->values();

        return response()->json($uniqueDeviceIds);
    }

    public function getFilteredUniqueDeviceIds(Request $request)
    {
        $query = FilteredFixStation::query();

        // Apply same filters as the main query
        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                $request->start_date . ' 00:00:00',
                $request->end_date . ' 23:59:59'
            ]);
        }

        $uniqueDeviceIds = $query->distinct()->pluck('device_id')->sort()->values();

        return response()->json($uniqueDeviceIds);
    }

    public function indexPenjadwalan(Request $request)
    {
        $type = $request->query('type', 'raw');
        $schedules = ActivitySchedule::get();
        return view('pages.rsc-data.schedule.index', compact('schedules', 'type'));
    }

    public function showPenjadwalan($id)
    {
        $schedule = ActivitySchedule::findOrFail($id);

        $start = Carbon::parse($schedule->date . ' ' . $schedule->start_time);
        $end = Carbon::parse($schedule->date . ' ' . $schedule->end_time);
        $now = now();

        $status = match (true) {
            $now < $start => 'belum',
            $now >= $start && $now <= $end => 'berlangsung',
            default => 'selesai'
        };

        $data = [];

        if ($status !== 'belum') {
            $data = FixStation::whereBetween('created_at', [$start, $end])
                ->orderBy('created_at', 'desc')
                ->take(100)
                ->get();
        }

        return view('pages.rsc-data.schedule.raw', compact('data', 'status', 'start', 'end', 'schedule'));
    }

    public function showFilteredPenjadwalan($id)
    {
        $schedule = ActivitySchedule::findOrFail($id);

        $start = Carbon::parse($schedule->date . ' ' . $schedule->start_time);
        $end = Carbon::parse($schedule->date . ' ' . $schedule->end_time);
        $now = now();

        $status = match (true) {
            $now < $start => 'belum',
            $now >= $start && $now <= $end => 'berlangsung',
            default => 'selesai'
        };

        $data = [];

        if ($status !== 'belum') {
            $data = FilteredFixStation::whereBetween('created_at', [$start, $end])
                ->orderBy('created_at', 'desc')
                ->take(100)
                ->get();
        }

        $plans = SubscriptionPlan::where('price', '>', 0)->orderBy('price', 'asc')->get();
        $quotaRemaining = SubscriptionService::remainingQuota(Auth::user());

        $user = Auth::user();
        $userExpires = $user->subscription->expires_at;

        return view('pages.rsc-data.schedule.filtered', compact('data', 'status', 'start', 'end', 'schedule', 'quotaRemaining', 'plans', 'userExpires'));
    }

    public function handleSensorData(Request $request)
    {
        $request->validate([
            'device_id' => 'required',
            'soilrs485.Nitrogen' => 'nullable',
            'soilrs485.Phosporus' => 'nullable',
            'soilrs485.Kalium' => 'nullable',
            'soilrs485.Ec' => 'nullable',
            'soilrs485.Ph' => 'nullable',
            'soilrs485.Temperature' => 'nullable',
            'soilrs485.Humidity' => 'nullable',
        ]);

        $data = [
            'device_id' => $request->device_id,
            'samples' => $request->soilrs485,
        ];

        $fixStation = FixStation::create($data);

        $rawSamples = $fixStation->samples; // sudah object (stdClass)
        $filteredSamples = clone $rawSamples; // clone agar tidak mengubah aslinya

        foreach ($filteredSamples as $key => $value) {
            $threshold = SensorThreshold::where('parameter', $key)->first();

            if ($threshold) {
                $min = $threshold->min;
                $max = $threshold->max;
                $filteredValue = min(max($value, $min), $max);
            } else {
                $filteredValue = $value; // tidak ada threshold = biarkan
            }

            $filteredSamples->{$key} = $filteredValue; // set nilai baru
        }

        $filteredFixStation = FilteredFixStation::create([
            'device_id' => $request->device_id,
            'samples' => $filteredSamples,
        ]);

        event(new SensorData($fixStation, $filteredFixStation));

        return response()->json([
            'status' => true,
            'message' => 'Data RSC berhasil diterima dan disimpan!',
            'data' => $data
        ], 201);
    }

    public function getRekomendasiTanaman($id)
    {
        $mode = request()->query('source', 'fix');
        $scheduleId = request()->query('scheduleId');

        $isQuotaAvailable = SubscriptionService::checkQuota(Auth::user());

        if (!$isQuotaAvailable) {
            return response()->json([
                'success' => false,
                'message' => 'Kuota habis, upgrade ke Pro untuk dapat tambahan kuota hingga 1 bulan ke depan'
            ], 402);
        }

        if ($scheduleId) {
            $jadwal = ActivitySchedule::findOrFail($scheduleId);
            if (!$jadwal) {
                return response()->json(['error' => 'Jadwal tidak ditemukan'], 404);
            }

            if ($mode === 'fix') {
                $sensorData = FixStation::findOrFail($id);
            } else {
                $sensorData = FilteredFixStation::findOrFail($id);
            }
        } else {
            if ($mode === 'fix') {
                $sensorData = FixStation::findOrFail($id);
            } else {
                $sensorData = FilteredFixStation::findOrFail($id);
            }
        }

        $n = $sensorData->samples->Nitrogen;
        $p = $sensorData->samples->Phosporus;
        $k = $sensorData->samples->Kalium;
        $ec = $sensorData->samples->Ec;
        $ph = $sensorData->samples->Ph;
        $temp = $sensorData->samples->Temperature;
        $humid = $sensorData->samples->Humidity;

        $prompt = "
        Kamu adalah sistem rekomendasi tanaman. 
        Berikan hasil dalam format JSON yang valid saja, tanpa penjelasan tambahan.
        
        Kondisi tanah:
        - pH: $ph
        - Kelembapan Tanah: $humid %
        - Nitrogen: $n mg/kg
        - Phosporus: $p mg/kg
        - Kalium: $k mg/kg
        - Ec: $ec uS/cm
        - Suhu Tanah: $temp celcius
        
        Format JSON yang harus kamu ikuti:
        {
          \"klasifikasi_tanah\": {
              \"kategori\": \"string\",
              \"deskripsi\": \"string singkat\"
            },
          \"tanaman_rekomendasi\": [
            {
              \"nama\": \"string\",
              \"kategori\": \"sayuran | buah | hias | penutup_tanah\",
              \"alasan\": \"string singkat, maksimal 1 kalimat\"
            }
          ]
        }
        
        Berikan hanya 5 tanaman rekomendasi sesuai kondisi di atas.
        ";

        $result =  Gemini::generativeModel('gemini-2.0-flash')->generateContent($prompt);
        $response = $result->text();

        $cleanedResponse = trim($response);
        // Hapus blok code fence seperti ```json dan ```
        $cleanedResponse = preg_replace('/^```json\s*|\s*```$/i', '', $cleanedResponse);
        // Bersihkan lagi spasi berlebih
        $cleanedResponse = trim($cleanedResponse);
        $decodedResponse = json_decode($cleanedResponse, true);

        return response()->json([
            'success' => true,
            'response' => $decodedResponse
        ]);
    }

    public function destroy($id)
    {
        $data = FixStation::findOrFail($id);
        $data->delete();

        activity()
            ->performedOn($data)
            ->event('delete')
            ->causedBy(Auth::user())
            ->log('Data RSC dengan timestamp' . $data->created_at . 'dihapus.');

        $pages = [
            'rm' => 'rsc-data.monitoring.index',
            'rs' => 'rsc-data.schedule.index',
            'fm' => 'rsc-data.filtered-monitoring.index',
            'fs' => 'rsc-data.filtered-schedule.index'
        ];
        $pageParam = request()->query('page', 'rm');
        $redirect = $pages[$pageParam];

        return redirect()->route($redirect)->with('success', 'Data RSC berhasil dihapus.');
    }

    public function clearSensorData()
    {
        $mode = request()->query('source', 'raw');

        if ($mode === 'raw') {
            $clear = FixStation::truncate();
        } else {
            $clear = FilteredFixStation::truncate();
        }

        $performedOn = $mode === 'raw' ? new FixStation : new FilteredFixStation;

        activity()
            ->performedOn($performedOn)
            ->event('truncate')
            ->causedBy(Auth::user())
            ->log('Data RSC di-clear oleh ' . Auth::user()->name . '.');

        return redirect()->back()->with('success', 'Data RSC berhasil di-clear.');
    }
}
