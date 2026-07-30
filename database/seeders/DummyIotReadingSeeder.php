<?php

namespace Database\Seeders;

use App\Models\Device;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummyIotReadingSeeder extends Seeder
{
    public function run()
    {
        $this->command->info('Memulai pembuatan ulang data simulasi historis lengkap (24 April - 8 Mei)...');

        $targetDevices = [
            'AGRISENSE-CC-001' => [
                'plant' => 'Cabai',
                'latitude' => -6.8411043,
                'longitude' => 107.8998966,
                'altitude_m' => 507.0,
            ],
            'AGRISENSE-CC-002' => [
                'plant' => 'Sawi',
                'latitude' => -6.9147440,
                'longitude' => 107.6098100,
                'altitude_m' => 720.0,
            ],
            'AGRISENSE-CC-003' => [
                'plant' => 'Cabai',
                'latitude' => -6.8411491,
                'longitude' => 107.8999023,
                'altitude_m' => 507.0,
            ],
        ];

        // Cari ID perangkat di database
        $devices = Device::whereIn('device_code', array_keys($targetDevices))->get()->keyBy('device_code');

        if ($devices->isEmpty()) {
            $this->command->error('Perangkat CC-001, CC-002, atau CC-003 tidak ditemukan di database. Pastikan perangkat sudah terdaftar.');

            return;
        }

        $startDate = Carbon::create(2026, 4, 24, 0, 0, 0);
        $endDate = Carbon::create(2026, 5, 8, 23, 59, 59);

        $deleted = DB::table('iot_readings')
            ->whereIn('device_id', $devices->pluck('id')->all())
            ->whereBetween('reading_time', [$startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')])
            ->where(function ($query) {
                $query->whereNull('message_id')
                    ->orWhere('message_id', 'like', 'SEED-%');
            })
            ->delete();

        $this->command->info("Menghapus {$deleted} baris data seeder lama pada rentang 24 April - 8 Mei.");

        $readings = [];
        $totalInserted = 0;

        foreach ($targetDevices as $code => $profile) {
            if (! isset($devices[$code])) {
                $this->command->warn("Device {$code} tidak ditemukan. Lewati.");

                continue;
            }

            $device = $devices[$code];
            $currentDate = clone $startDate;
            $plant = $profile['plant'];

            $this->command->info("Memproses Node: {$code} ({$plant})");

            while ($currentDate <= $endDate) {
                $hour = $currentDate->hour;
                $isDay = $hour >= 6 && $hour <= 17;
                $isNoon = $hour >= 11 && $hour <= 14;
                $isDawnOrDusk = $hour === 6 || $hour === 17;

                // Parameter default (akan di-tweak sesuai hasil audit per-node)
                $temp = 20.0;
                $humidity = 90.0;
                $soilMois = 35.0;
                $co2 = 400.0;

                if ($code === 'AGRISENSE-CC-001') {
                    $temp = $isDay ? rand(240, 285) / 10 : rand(205, 230) / 10;
                    $humidity = $isDay ? rand(67, 85) : rand(90, 97);
                    $soilMois = rand(310, 690) / 10;
                    $co2 = $isDay ? rand(550, 900) : rand(700, 1388);
                } elseif ($code === 'AGRISENSE-CC-002') {
                    $temp = $isDay ? rand(230, 277) / 10 : rand(207, 228) / 10;
                    $humidity = $isDay ? rand(70, 90) : rand(90, 98);
                    $soilMois = rand(360, 401) / 10;
                    $co2 = rand(560, 710);
                } elseif ($code === 'AGRISENSE-CC-003') {
                    $temp = $isDay ? rand(234, 282) / 10 : rand(205, 229) / 10;
                    $humidity = $isDay ? rand(68, 85) : rand(90, 97);
                    $soilMois = rand(359, 420) / 10;
                    $co2 = rand(430, 590);
                }

                $latitude = $device->latitude ?? $profile['latitude'];
                $longitude = $device->longitude ?? $profile['longitude'];
                $altitude = $device->altitude ?? $profile['altitude_m'];
                $light = $this->simulateLightLux($isDay, $isNoon, $isDawnOrDusk);
                $pressure = $this->simulatePressureHpa((float) $altitude);
                $tvoc = $isDay ? rand(15, 90) : rand(30, 130);
                $soilTemp = $temp - rand(10, 30) / 10;
                $soilPh = rand(64, 84) / 10;
                $soilEc = rand(60, 210) / 100;
                $soilN = rand(28, 80);
                $soilP = rand(14, 48);
                $soilK = rand(55, 170);
                $batteryVoltage = rand(368, 421) / 100;
                $batteryPercent = (int) max(0, min(100, round((($batteryVoltage - 3.2) / 1.0) * 100)));
                $rssi = -rand(50, 75);

                // Kalkulasi Carbon Metrics Simulasi
                $soc = rand(15, 25) / 10; // Soil Organic Carbon (1.5 - 2.5)
                $cf = ($isDay && $co2 > 450) ? (rand(1, 15) / 1000) : (rand(-5, 0) / 1000); // Carbon Flux

                $readings[] = [
                    'message_id' => 'SEED-'.$code.'-'.$currentDate->format('YmdH'),
                    'device_id' => $device->id,
                    'plot_id' => $device->plot_id ?? 1, // Fallback ke plot_id 1 jika null
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'altitude_m' => $altitude,
                    'reading_time' => $currentDate->format('Y-m-d H:i:s'),
                    'air_temperature_sensor' => $temp,
                    'air_humidity_sensor' => $humidity,
                    'air_pressure_hpa' => $pressure,
                    'light_lux' => $light,
                    'soil_temperature' => $soilTemp,
                    'soil_moisture' => $soilMois,
                    'soil_ph' => $soilPh,
                    'soil_ec_ms_cm' => $soilEc,
                    'soil_n_mg_kg' => $soilN,
                    'soil_p_mg_kg' => $soilP,
                    'soil_k_mg_kg' => $soilK,
                    'co2_sensor' => $co2,
                    'tvoc_ppb' => $tvoc,
                    'soil_organic_carbon' => $soc,
                    'carbon_flux' => $cf,
                    'battery_voltage' => $batteryVoltage,
                    'battery_percent' => $batteryPercent,
                    'signal_strength' => $rssi,
                    'network_type' => 'WiFi',
                    'node_status' => 'online',
                    'sensor_status' => 'simulated_historical',
                    'firmware_version' => '1.0.0-seed',
                    'data_valid' => true,
                    'samples' => json_encode([
                        'source' => 'dummy-seeder-v2',
                        'plant' => $plant,
                        'period' => '2026-04-24_to_2026-05-08',
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                if (count($readings) >= 200) {
                    DB::table('iot_readings')->insert($readings);
                    $totalInserted += count($readings);
                    $readings = [];
                }

                $currentDate->addHour();
            }

            if (count($readings) > 0) {
                DB::table('iot_readings')->insert($readings);
                $totalInserted += count($readings);
                $readings = [];
            }
        }

        $this->command->info("Selesai! {$totalInserted} baris data historis berhasil ditambahkan ke database.");
    }

    private function simulateLightLux(bool $isDay, bool $isNoon, bool $isDawnOrDusk): int
    {
        if (! $isDay) {
            return rand(0, 50);
        }

        if ($isDawnOrDusk) {
            return rand(250, 1800);
        }

        if ($isNoon) {
            return rand(28000, 62000);
        }

        return rand(6000, 35000);
    }

    private function simulatePressureHpa(float $altitude): float
    {
        $basePressure = 1013.25 * pow(1 - ($altitude / 44330), 5.255);

        return round($basePressure + (rand(-15, 15) / 10), 1);
    }
}
