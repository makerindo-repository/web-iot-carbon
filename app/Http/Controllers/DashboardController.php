<?php

namespace App\Http\Controllers;

use App\Models\ActivitySchedule;
use App\Models\Device;
use App\Models\FilteredFixStation;
use App\Models\FixStation;
use App\Models\Lecturer;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $now = Carbon::now();

        // Jadwal Kegiatan Mendatang
        $upcomingActivities = ActivitySchedule::forCurrentUser()->get()
            ->filter(function ($activity) use ($now) {
                $start = Carbon::parse($activity->date . ' ' . $activity->start_time);
                return $start >= $now;
            })->sortBy(function ($activity) {
                return Carbon::parse($activity->date . ' ' . $activity->start_time);
            })->values();

        $lecturers = Lecturer::count();
        $students = Student::count();
        $devices = Device::count();
        $activitySchedules = ActivitySchedule::forCurrentUser()->count();

        // Data Grafik Telemetri 24 Jam Terakhir
        $startTime = now()->subHours(24);
        $rawData = FixStation::where('created_at', '>=', $startTime)->orderBy('created_at')->get();
        $filteredData = FilteredFixStation::where('created_at', '>=', $startTime)->orderBy('created_at')->get();

        $n = $rawData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Nitrogen ?? 0,
        ]);

        $nFiltered = $filteredData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Nitrogen ?? 0,
        ]);

        $p = $rawData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Phosporus ?? 0,
        ]);

        $pFiltered = $filteredData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Phosporus ?? 0,
        ]);

        $k = $rawData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Kalium ?? 0,
        ]);

        $kFiltered = $filteredData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Kalium ?? 0,
        ]);

        $ec = $rawData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Ec ?? 0,
        ]);

        $ecFiltered = $filteredData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Ec ?? 0,
        ]);

        $ph = $rawData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Ph ?? 0,
        ]);

        $phFiltered = $filteredData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Ph ?? 0,
        ]);

        $temp = $rawData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Temperature ?? 0,
        ]);

        $tempFiltered = $filteredData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Temperature ?? 0,
        ]);

        $humid = $rawData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Humidity ?? 0,
        ]);

        $humidFiltered = $filteredData->map(fn($d) => [
            'x' => Carbon::parse($d->created_at)->timezone('Asia/Jakarta')->format('Y-m-d H:i:s'),
            'y' => $d->samples->Humidity ?? 0,
        ]);

        return view('pages.dashboard.index', compact('upcomingActivities', 'lecturers', 'students', 'devices', 'activitySchedules', 'n', 'nFiltered', 'p', 'pFiltered', 'k', 'kFiltered', 'ec', 'ecFiltered', 'ph', 'phFiltered', 'temp', 'tempFiltered', 'humid', 'humidFiltered'));
    }
}
