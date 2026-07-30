<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

class MqttListenCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mqtt:listen';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Listen to HiveMQ Broker for incoming IoT data and forward it to Laravel API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Kredensial MQTT dibaca dari .env via config/mqtt.php — JANGAN hardcode.
        $server = config('mqtt.host');
        $port = config('mqtt.port');
        $topic = config('mqtt.topic');
        $username = config('mqtt.username');
        $password = config('mqtt.password');

        if (empty($server) || empty($username) || empty($password)) {
            $this->error('Kredensial MQTT belum diatur. Set MQTT_HOST, MQTT_USERNAME, MQTT_PASSWORD di backend/.env');

            return self::FAILURE;
        }

        while (true) {
            $clientId = 'agrisense-backend-'.uniqid();
            $this->info("Menghubungkan ke MQTT Broker: $server:$port (TLS)");

            try {
                $mqtt = new MqttClient($server, $port, $clientId, MqttClient::MQTT_3_1_1);

                $connectionSettings = (new ConnectionSettings)
                    ->setUsername($username)
                    ->setPassword($password)
                    ->setKeepAliveInterval(10)
                    ->setConnectTimeout(10)
                    ->setUseTls(true)
                    ->setTlsVerifyPeer(config('mqtt.tls_verify_peer')); // via MQTT_TLS_VERIFY_PEER

                $mqtt->connect($connectionSettings, true);
                $this->info("Berhasil terhubung! Berlangganan ke topik: $topic");

                // Subscribe to topic
                $mqtt->subscribe($topic, function (string $topic, string $message) {
                    $this->info("\n[".date('Y-m-d H:i:s').'] 📥 Menerima paket JSON dari MQTT.');

                    try {
                        $payload = json_decode($message, true);

                        if (! $payload) {
                            $this->warn('Payload bukan JSON yang valid. Diabaikan.');

                            return;
                        }

                        // Perbaikan: Port diganti menjadi 8000 sesuai dengan default `php artisan serve` Anda
                        $apiUrl = config('mqtt.api_url');
                        $this->info("Menembakkan payload ke API Internal: $apiUrl");

                        $response = Http::timeout(5)
                            ->withHeaders(['Accept' => 'application/json'])
                            ->post($apiUrl, $payload);

                        if ($response->successful()) {
                            $this->info('✅ API Success: '.$response->body());
                        } else {
                            $this->error("❌ API Error [{$response->status()}]: ".$response->body());
                        }

                    } catch (\Exception $e) {
                        $this->error('Gagal memproses/meneruskan pesan: '.$e->getMessage());
                    }
                }, 0);

                $this->info('Mulai memantau... (Tekan Ctrl+C untuk berhenti)');
                $mqtt->loop(true);

            } catch (\Exception $e) {
                $this->error('Koneksi terputus (Broker menutup koneksi): '.$e->getMessage());
                $this->info('Mencoba menyambung kembali dalam 3 detik...');
                sleep(3);
            }
        }
    }
}
