<?php

/*
|--------------------------------------------------------------------------
| MQTT Broker (HiveMQ) — dipakai oleh command `php artisan mqtt:listen`
|--------------------------------------------------------------------------
|
| Semua nilai dibaca dari .env. JANGAN hardcode kredensial di source code
| (lihat audit H — kredensial sebelumnya ter-commit & masuk git history).
|
*/

return [
    'host' => env('MQTT_HOST', ''),
    'port' => (int) env('MQTT_PORT', 8883),
    'username' => env('MQTT_USERNAME', ''),
    'password' => env('MQTT_PASSWORD', ''),
    'topic' => env('MQTT_TOPIC', 'agrisense/iot/readings'),

    // Verifikasi peer TLS. Default false untuk kompatibilitas broker lokal;
    // set true di produksi bila rantai sertifikat broker tepercaya.
    'tls_verify_peer' => env('MQTT_TLS_VERIFY_PEER', false),

    // URL API internal tujuan forward payload dari listener.
    'api_url' => env('MQTT_FORWARD_URL', 'http://127.0.0.1:8000/api/iot/agrisense/readings'),
];
