<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\LandPlot;
use App\Models\Garden;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateHistoricalData extends Command
{
    protected $signature = 'agrisense:generate-historical {--start=2026-05-01} {--end=2026-07-31} {--interval=1}';
    protected $description = 'Generasi/pemulihan data historis telemetri sensor dari Mei s/d Juli 2026 untuk seluruh node';

    public function handle()
    {
        $startInput = $this->option('start');
        $endInput = $this->option('end');
        $intervalHours = (int) $this->option('interval');

        $startDate = Carbon::parse($startInput)->startOfDay();
        $endDate = Carbon::parse($endInput)->endOfDay();

        $this->info("Memulai regenerasi data historis ({$startDate->format('d M Y')} s/d {$endDate->format('d M Y')})...");

        // 1. Memastikan minimal ada Node terdaftar jika sebelumnya sempat terhapus
        $devices = Device::all();
        if ($devices->isEmpty()) {
            $this->warn("TIDAK ADA NODE TERDAFTAR! Membuat otomatis node standar...");
            
            $plot = LandPlot::firstOrCreate(
                ['plot_name' => 'Lahan Utama AgriSense'],
                ['latitude' => -6.830000, 'longitude' => 107.910000, 'altitude' => 720]
            );

            $garden = Garden::firstOrCreate(
                ['garden_name' => 'Kebun Hortikultura Utama'],
                ['plot_id' => $plot->id, 'plant_types' => 'Cabai & Hortikultura', 'latitude' => -6.830000, 'longitude' => 107.910000]
            );

            $defaultNodes = [
                ['device_code' => 'AGRISENSE-CC-001', 'name' => 'NODE AGRISENSE-CC-001', 'latitude' => -6.841104, 'longitude' => 107.899896, 'altitude' => 507, 'status' => 'online'],
                ['device_code' => 'AGRISENSE-CC-002', 'name' => 'NODE AGRISENSE-CC-002', 'latitude' => -6.914744, 'longitude' => 107.609810, 'altitude' => 720, 'status' => 'online'],
                ['device_code' => 'AGRISENSE-CC-003', 'name' => 'NODE AGRISENSE-CC-003', 'latitude' => -6.841149, 'longitude' => 107.899902, 'altitude' => 507, 'status' => 'online'],
            ];

            foreach ($defaultNodes as $n) {
                Device::firstOrCreate(
                    ['device_code' => $n['device_code']],
                    [
                        'name' => $n['name'],
                        'plot_id' => $plot->id,
                        'garden_id' => $garden->id,
                        'latitude' => $n['latitude'],
                        'longitude' => $n['longitude'],
                        'altitude' => $n['altitude'],
                        'status' => $n['status'],
                        'firmware_version' => '1.0.0',
                    ]
                );
            }

            $devices = Device::all();
        }

        $this->info("Ditemukan {$devices->count()} node aktif di database.");

        $totalInserted = 0;
        $chunk = [];

        foreach ($devices as $d) {
            $this->info("Menghasilkan telemetri untuk Node: {$d->device_code} ({$d->name})");
            $curr = clone $startDate;

            while ($curr <= $endDate) {
                $hour = $curr->hour;
                $isDay = ($hour >= 6 && $hour <= 17);
                $isNoon = ($hour >= 11 && $hour <= 14);

                // Fluktuasi realistis
                $temp = $isDay ? (25.0 + ($hour - 6) * 0.7 + (rand(-10, 10) / 10)) : (20.0 + (rand(-8, 8) / 10));
                $humidity = $isDay ? (80.0 - ($hour - 6) * 2.5 + (rand(-15, 15) / 10)) : (90.0 + (rand(-5, 5) / 10));
                $humidity = max(35, min(99, $humidity));
                
                $co2 = $isDay ? rand(410, 520) : rand(480, 750);
                $ch4 = round(rand(12, 35) / 10, 1); // 1.2 - 3.5 ppm
                $no2 = round(rand(1, 15) / 10, 1);  // 0.1 - 1.5 ppb
                $windSpeed = $isDay ? round(rand(2, 18) / 10, 1) : round(rand(0, 8) / 10, 1);
                $windDir = rand(0, 7) * 45; // 0, 45, 90, dst

                $batVolt = round(12.4 + (rand(0, 14) / 10), 2); // 12.4 - 13.8V
                $batPercent = min(100, max(75, (int) round((($batVolt - 11.5) / 2.3) * 100)));

                $chunk[] = [
                    'message_id' => 'GEN-' . $d->device_code . '-' . $curr->format('YmdHi'),
                    'device_id' => $d->id,
                    'plot_id' => $d->plot_id ?? 1,
                    'reading_time' => $curr->format('Y-m-d H:i:s'),
                    'latitude' => $d->latitude ?? -6.830000,
                    'longitude' => $d->longitude ?? 107.910000,
                    'altitude_m' => $d->altitude ?? 720,
                    'co2_sensor' => $co2,
                    'tvoc_ppb' => rand(15, 85),
                    'ch4_ppm' => $ch4,
                    'no2_ppb' => $no2,
                    'n2o_ppb' => 0.3,
                    'air_temperature_sensor' => round($temp, 1),
                    'air_humidity_sensor' => round($humidity, 1),
                    'air_pressure_hpa' => 1011.5,
                    'wind_speed_kmh' => $windSpeed,
                    'wind_direction_deg' => $windDir,
                    'light_lux' => $isDay ? rand(15000, 55000) : rand(0, 10),
                    'battery_voltage' => $batVolt,
                    'battery_percent' => $batPercent,
                    'signal_strength' => -72,
                    'network_type' => 'WiFi',
                    'node_status' => 'online',
                    'sensor_status' => 'normal',
                    'firmware_version' => $d->firmware_version ?? '1.0.0',
                    'data_valid' => true,
                    'created_at' => $curr->format('Y-m-d H:i:s'),
                    'updated_at' => $curr->format('Y-m-d H:i:s'),
                ];

                if (count($chunk) >= 500) {
                    DB::table('iot_readings')->insert($chunk);
                    $totalInserted += count($chunk);
                    $chunk = [];
                }

                $curr->addHours($intervalHours);
            }
        }

        if (count($chunk) > 0) {
            DB::table('iot_readings')->insert($chunk);
            $totalInserted += count($chunk);
        }

        $this->info("SELESAI! Berhasil menghasilkan {$totalInserted} baris telemetri historis dari Mei s/d Juli 2026.");
        return 0;
    }
}
