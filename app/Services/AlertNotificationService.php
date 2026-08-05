<?php

namespace App\Services;

use App\Models\AgrisenseSetting;
use App\Models\Device;
use App\Models\IotReading;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AlertNotificationService
{
    public function sendNodeOffline(Device $device): void
    {
        $subject = '[AgriSense] Node Offline: '.($device->device_code ?? $device->id);
        $message = $this->buildOfflineMessage($device);

        if ($this->settingEnabled('emailAlert', true)) {
            $this->sendEmailToAdmins($subject, $message);
        }

        if ($this->settingEnabled('telegramBot', false)) {
            $this->sendTelegram($message);
        }
    }

    public function sendNodeWarning(IotReading $reading, float $co2Threshold, float $tempMax, float $humidityMin): void
    {
        if (! $this->settingEnabled('telegramBot', false) && ! $this->settingEnabled('emailAlert', true)) {
            return;
        }

        $device = $reading->device;
        $nodeName = $device->landPlot->plot_name ?? 'Lahan Tidak Diketahui';
        $time = now()->timezone('Asia/Jakarta')->format('d M Y, H:i').' WIB';

        $alerts = [];
        if ($reading->co2_sensor > $co2Threshold) {
            $alerts[] = " CO2 Tinggi: {$reading->co2_sensor} ppm (Batas: {$co2Threshold})";
        }
        if ($reading->air_temperature_sensor > $tempMax) {
            $alerts[] = " Suhu Panas: {$reading->air_temperature_sensor}°C (Batas: {$tempMax}°C)";
        }
        if ($reading->air_humidity_sensor !== null && $reading->air_humidity_sensor > 0 && $reading->air_humidity_sensor < $humidityMin) {
            $alerts[] = " Kelembapan Rendah: {$reading->air_humidity_sensor}% (Batas: {$humidityMin}%)";
        }

        if (empty($alerts)) {
            return;
        }

        $message = implode("\n", array_merge([
            '🚨 AgriSense Alert - Ambang Batas Terlewati!',
            'Waktu: '.$time,
            '',
            'ID Perangkat: '.($device->device_code ?? $device->id),
            'Lokasi/Lahan: '.$nodeName,
            '',
            'Rincian Peringatan:',
        ], $alerts, [
            '',
            'Harap segera periksa kondisi di lapangan.',
        ]));

        $subject = '[AgriSense Alert] Ambang Batas Terlewati pada '.($device->device_code ?? $device->id);

        if ($this->settingEnabled('emailAlert', true)) {
            $this->sendEmailToAdmins($subject, $message);
        }

        if ($this->settingEnabled('telegramBot', false)) {
            $this->sendTelegram($message);
        }
    }

    public function sendDailyTelegramReport(): void
    {
        if (! $this->settingEnabled('telegramBot', false)) {
            return;
        }

        $total = Device::count();
        $online = Device::where('device_status', 'online')->count();
        $warning = Device::where('device_status', 'warning')->count();
        $offline = Device::where('device_status', 'offline')->count();

        $offlineDevices = Device::with('landPlot')->where('device_status', 'offline')
            ->orderBy('device_code')
            ->limit(8)
            ->get(['id', 'device_code', 'plot_id', 'last_seen_at']);

        $lines = [
            'AgriSense - Laporan Harian Node',
            'Waktu: '.now()->timezone('Asia/Jakarta')->format('d M Y, H:i').' WIB',
            '',
            "Total node: {$total}",
            "Online: {$online}",
            "Warning: {$warning}",
            "Offline: {$offline}",
        ];

        if ($offlineDevices->isNotEmpty()) {
            $lines[] = '';
            $lines[] = 'Node offline:';
            foreach ($offlineDevices as $device) {
                $lastSeen = $this->formatJakartaTime($device->last_seen_at);
                $nodeName = $device->landPlot->plot_name ?? 'Lahan Tidak Diketahui';
                $lines[] = '- '.($device->device_code ?? $device->id)." ($nodeName) terakhir: ".$lastSeen;
            }
        }

        $this->sendTelegram(implode("\n", $lines));
    }

    private function buildOfflineMessage(Device $device): string
    {
        $lastSeen = $this->formatJakartaTime($device->last_seen_at);
        $nodeName = $device->landPlot->plot_name ?? 'Lahan Tidak Diketahui';

        return implode("\n", [
            'AgriSense Alert - Node Offline',
            'Waktu: '.now()->timezone('Asia/Jakarta')->format('d M Y, H:i').' WIB',
            '',
            'ID Perangkat: '.($device->device_code ?? $device->id),
            'Lokasi/Lahan: '.$nodeName,
            'Status: offline',
            'Terakhir terlihat: '.$lastSeen,
            '',
            'Mohon cek koneksi daya, WiFi/sinyal, dan jadwal pengiriman data node.',
        ]);
    }

    private function sendEmailToAdmins(string $subject, string $message): void
    {
        // Prioritas 1: Ambil daftar email subscriber dari pengaturan sistem
        $subscriberJson = AgrisenseSetting::where('key', 'notificationEmails')->value('value');
        $recipients = collect(json_decode($subscriberJson ?? '[]', true))->filter()->unique()->values();

        // Prioritas 2: Jika kosong, fallback ke semua admin
        if ($recipients->isEmpty()) {
            $recipients = User::where('role', 'admin')
                ->whereNotNull('email')
                ->pluck('email')
                ->filter()
                ->unique()
                ->values();
        }

        if ($recipients->isEmpty()) {
            Log::warning('Email alert skipped: no recipients found.');

            return;
        }

        try {
            Mail::raw($message, function ($mail) use ($recipients, $subject) {
                $mail->to($recipients->all())->subject($subject);
            });
        } catch (\Throwable $e) {
            Log::warning('Email alert failed: '.$e->getMessage());
        }
    }

    private function sendTelegram(string $message): void
    {
        $token = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        if (! $token || ! $chatId) {
            Log::warning('Telegram alert skipped: TELEGRAM_BOT_TOKEN or TELEGRAM_CHAT_ID is missing.');

            return;
        }

        try {
            $response = Http::timeout(10)->post("https://api.telegram.org/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'disable_web_page_preview' => true,
            ]);

            if (! $response->successful()) {
                Log::warning('Telegram alert failed: '.$response->body());
            }
        } catch (\Throwable $e) {
            Log::warning('Telegram alert failed: '.$e->getMessage());
        }
    }

    private function settingEnabled(string $key, bool $default): bool
    {
        $value = AgrisenseSetting::where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private function formatJakartaTime(mixed $value): string
    {
        if (! $value) {
            return 'belum pernah terlihat';
        }

        try {
            return Carbon::parse($value)->timezone('Asia/Jakarta')->format('d M Y, H:i').' WIB';
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
