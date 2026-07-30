<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // IoT ingestion: dual-axis rate limit.
        // Per-IP melindungi gateway/internet pipe.
        // Per-device_id melindungi dari satu node nakal yang spam,
        // walaupun IP-nya berbeda (mis. roaming melalui beberapa SIM).
        // Constraint: tidak boleh menyentuh firmware node — semua di server.
        RateLimiter::for('iot-device', function (Request $request) {
            $deviceId = (string) $request->input('device_id', 'unknown');

            return [
                Limit::perMinute(60)->by($request->ip()),
                Limit::perMinute(20)->by('device:'.$deviceId),
            ];
        });

        // Izinkan akses publik untuk dokumentasi API Scramble di Production/VPS
        Gate::define('viewApiDocs', function ($user = null) {
            return true;
        });
    }
}
