<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgrisenseSetting;
use App\Models\Device;
use App\Models\IotReading;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Spatie\Activitylog\Models\Activity;

class SystemController extends Controller
{
    // Ambil activity logs
    public function getLogs()
    {
        try {
            $logs = Activity::with('causer')->latest()->limit(100)->get();

            return response()->json($logs->map(function ($l) {
                return [
                    'id' => 'LOG-'.str_pad($l->id, 3, '0', STR_PAD_LEFT),
                    'user' => $l->causer?->name ?? 'System',
                    'action' => $l->description,
                    'module' => $l->log_name ?? 'System',
                    'timestamp' => $l->created_at->toIso8601String(),
                    'status' => 'success',
                    'ip' => $l->properties['ip'] ?? '127.0.0.1',
                ];
            }));
        } catch (\Exception $e) {
            Log::error('Failed to fetch logs: '.$e->getMessage());

            return response()->json([], 500);
        }
    }

    public function recordLog(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string|max:200',
            'module' => 'sometimes|string|in:Node,Lahan,Garden,Komoditi,User,Settings,Report,Auth',
        ]);

        // Strip control chars, prefix user-action
        $action = preg_replace('/[\x00-\x1F]/', '', $validated['action']);
        activity()
            ->useLog($validated['module'] ?? 'User')
            ->log('[user-action] '.$action);

        return response()->json(['status' => 'success']);
    }

    // Daftar pengguna
    public function getUsers()
    {
        try {
            $users = User::all();

            return response()->json($users->map(function ($u) {
                return [
                    'id' => 'USR-'.str_pad($u->id, 3, '0', STR_PAD_LEFT),
                    'real_id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $u->role ?? 'viewer',
                    'status' => $u->status ?? 'active',
                    'created_at' => $u->created_at ? $u->created_at->toIso8601String() : null,
                    'lastLogin' => $u->updated_at ? $u->updated_at->toIso8601String() : null,
                ];
            }));
        } catch (\Throwable $e) {
            Log::error('Gagal fetch users', ['msg' => $e->getMessage()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengambil data pengguna.',
            ], 500);
        }
    }

    // Buat pengguna baru
    public function createUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|string|min:8|max:64',
            'role' => 'required|in:admin,operator,viewer',
            'status' => 'sometimes|in:active,inactive',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'status' => $validated['status'] ?? 'active',
        ]);

        // Sync role ke frontend
        $user->role = $validated['role'];

        return response()->json(['status' => 'success', 'user' => $user]);
    }

    // Update pengguna
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,'.$id,
            'role' => 'sometimes|in:admin,operator,viewer',
            'status' => 'sometimes|in:active,inactive',
        ]);

        // Cegah admin mengubah peran akunnya sendiri (hindari lockout admin terakhir)
        if ($user->id === auth()->id() && isset($validated['role']) && $validated['role'] !== $user->role) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak dapat mengubah peran akun Anda sendiri.',
            ], 403);
        }

        // Cegah admin menonaktifkan akunnya sendiri
        if ($user->id === auth()->id() && isset($validated['status']) && $validated['status'] === 'inactive') {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak dapat menonaktifkan akun Anda sendiri.',
            ], 403);
        }

        $user->update($validated);

        return response()->json(['status' => 'success', 'user' => $user]);
    }

    // Hapus pengguna
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);

        // Cegah hapus diri sendiri
        if ($user->id === auth()->id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri!',
            ], 403);
        }

        $user->delete();

        return response()->json(['status' => 'success']);
    }

    // Ambil pengaturan sistem
    public function getSettings(Request $request)
    {
        $settings = AgrisenseSetting::pluck('value', 'key')->toArray();

        // Nilai default
        $defaults = [
            'appName' => 'AgriSense V1.0',
            'appLogo' => '/logo_utama.png',
            'co2Threshold' => '1000',
            'tempMax' => '35',
            'humidityMin' => '40',
            'samplingInterval' => '60',
            'mqttUrl' => 'wss://47236b5730574b438d2e060b7756448f.s1.eu.hivemq.cloud:8884/mqtt',
            'aiEngineKey' => '',
            'emailAlert' => '1',
            'telegramBot' => '0',
            'telegramInviteLink' => '',
            'notificationEmails' => '[]',
        ];

        $merged = array_merge($defaults, $settings);
        $role = $request->user()?->role;
        $isAdmin = $role === 'admin';

        // Sembunyikan secret dari non-admin
        if (! $isAdmin) {
            $merged['aiEngineKey'] = '';
        }

        return response()->json($merged);
    }

    // Update pengaturan sistem
    public function updateSettings(Request $request)
    {
        try {
            // Whitelist key
            $allowedKeys = [
                'appName', 'appLogo', 'co2Threshold', 'tempMax', 'humidityMin',
                'samplingInterval', 'mqttUrl', 'aiEngineKey', 'emailAlert', 'telegramBot', 'telegramInviteLink',
                'notificationEmails',
            ];

            $data = $request->all();
            foreach ($data as $key => $value) {
                // Skip key tidak valid
                if (! in_array($key, $allowedKeys)) {
                    continue;
                }

                // Upload logo base64 (maks 1MB)
                if ($key === 'appLogo' && is_string($value) && preg_match('/^data:image\/(\w+);base64,/', $value, $type)) {
                    // Cek ukuran base64 (~1MB decoded)
                    if (strlen($value) > 1 * 1024 * 1024 * 1.37) {
                        continue;
                    }

                    $extension = strtolower($type[1]);
                    if (in_array($extension, ['jpeg', 'jpg', 'png', 'webp'])) {
                        $image = substr($value, strpos($value, ',') + 1);
                        $image = base64_decode($image);

                        // Verifikasi MIME aktual
                        $finfo = new \finfo(FILEINFO_MIME_TYPE);
                        $realMime = $finfo->buffer($image);
                        if (! in_array($realMime, ['image/jpeg', 'image/png', 'image/webp'])) {
                            continue;
                        }

                        // Verifikasi dimensi gambar
                        $imgInfo = @getimagesizefromstring($image);
                        if (! $imgInfo || $imgInfo[0] > 2048 || $imgInfo[1] > 2048) {
                            continue;
                        }

                        $filename = 'logo_'.time().'.'.$extension;

                        $path = public_path('uploads');
                        if (! File::exists($path)) {
                            File::makeDirectory($path, 0755, true);
                        }

                        File::put($path.'/'.$filename, $image);

                        $value = url('uploads/'.$filename);
                    }
                }

                // Boolean ke 1/0
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                }

                // Tambahan: Pastikan kita menangkap error database secara spesifik
                AgrisenseSetting::updateOrCreate(
                    ['key' => $key],
                    ['value' => (string) $value]
                );
            }

            // Re-evaluasi status device
            $this->reEvaluateAllDeviceStatuses();

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Settings Update Failed: '.$e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan pengaturan. Silakan periksa kembali atau hubungi administrator.',
            ], 500);
        }
    }

    // Re-evaluasi status device
    private function reEvaluateAllDeviceStatuses()
    {
        $co2Threshold = (float) (AgrisenseSetting::where('key', 'co2Threshold')->value('value') ?? 1000);
        $tempMax = (float) (AgrisenseSetting::where('key', 'tempMax')->value('value') ?? 35);
        $humidityMin = (float) (AgrisenseSetting::where('key', 'humidityMin')->value('value') ?? 40);

        // Latest reading per device
        $latestIds = IotReading::selectRaw('MAX(id) as id')
            ->groupBy('device_id')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return;
        }

        $latestReadings = IotReading::whereIn('id', $latestIds)->get()->keyBy('device_id');

        // Update status per threshold
        $devices = Device::all();
        foreach ($devices as $device) {
            $lastReading = $latestReadings->get($device->id);
            if (! $lastReading) {
                continue;
            }

            $newStatus = 'online';
            if ($lastReading->co2_sensor > $co2Threshold ||
                $lastReading->air_temperature_sensor > $tempMax ||
                $lastReading->air_humidity_sensor < $humidityMin) {
                $newStatus = 'warning';
            }

            if ($device->device_status !== $newStatus) {
                // Property assignment — device_status tidak fillable
                $device->device_status = $newStatus;
                $device->save();
            }
        }
    }

    // Update profil pengguna
    public function updateProfile(Request $request)
    {
        // Log tanpa PII
        Log::info('Profile update', ['user_id' => $request->user()->id]);
        $user = $request->user();
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'profile_photo' => 'nullable|string',
        ]);

        $oldName = $user->name;
        $updates = ['name' => $validated['name']];

        if ($photoPath = $this->processProfilePhoto($validated['profile_photo'] ?? null, $user->id)) {
            $updates['profile_photo'] = $photoPath;
        }

        try {
            $user->update($updates);

            // Log perubahan profil
            activity()
                ->causedBy($user)
                ->useLog('Auth')
                ->log("Mengubah profil: \"{$oldName}\" → \"{$validated['name']}\"");

            return response()->json([
                'status' => 'success',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile_photo' => $user->profile_photo,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Profile Update Failed: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Internal Server Error during profile update',
            ], 500);
        }
    }

    /**
     * Validate, decode, and persist a base64-encoded profile photo.
     *
     * Returns the relative web path on success, or null when the input
     * fails any validation step (size, MIME prefix, real MIME, dimensions,
     * or unsupported extension). Callers should treat null as "no change"
     * — never raise an error for invalid photo input.
     */
    private function processProfilePhoto(?string $base64, int $userId): ?string
    {
        if (empty($base64)) {
            return null;
        }

        if (! preg_match('/^data:image\/(\w+);base64,/', $base64, $type)) {
            return null;
        }

        // Maks ~1MB (base64 overhead 1.37x)
        if (strlen($base64) > 1 * 1024 * 1024 * 1.37) {
            return null;
        }

        $extension = strtolower($type[1]);
        if (! in_array($extension, ['jpeg', 'jpg', 'png', 'webp'])) {
            return null;
        }

        $image = base64_decode(substr($base64, strpos($base64, ',') + 1));

        // Verifikasi MIME aktual
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->buffer($image);
        if (! in_array($realMime, ['image/jpeg', 'image/png', 'image/webp'])) {
            return null;
        }

        // Verifikasi dimensi
        $imgInfo = @getimagesizefromstring($image);
        if (! $imgInfo || $imgInfo[0] > 2048 || $imgInfo[1] > 2048) {
            return null;
        }

        $filename = 'profile_'.$userId.'_'.time().'.'.$extension;

        $path = public_path('uploads/profiles');
        if (! File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        File::put($path.'/'.$filename, $image);

        return '/uploads/profiles/'.$filename;
    }

    // Ubah password
    public function changePassword(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        // Cek password lama
        if (! Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Password lama tidak cocok.',
            ], 422);
        }

        $user->update([
            'password' => bcrypt($validated['new_password']),
        ]);

        // Log perubahan password
        activity()
            ->causedBy($user)
            ->useLog('Auth')
            ->log('Mengubah password akun');

        return response()->json(['status' => 'success']);
    }

    // Kirim test email
    public function sendTestEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $time = now()->timezone('Asia/Jakarta')->format('d M Y, H:i').' WIB';

        $message = implode("\n", [
            '══════════════════════════════════════',
            '  AGRISENSE — Uji Notifikasi Email',
            '══════════════════════════════════════',
            '',
            'Waktu Pengujian : '.$time,
            'Penerima        : '.$validated['email'],
            '',
            '──────────────────────────────────────',
            '  STATUS: BERHASIL',
            '──────────────────────────────────────',
            '',
            'Koneksi SMTP ke server Gmail telah berhasil.',
            'Email ini mengkonfirmasi bahwa sistem notifikasi',
            'AgriSense dapat mengirimkan pesan ke alamat Anda.',
            '',
            'Anda akan menerima notifikasi otomatis jika:',
            '• Node sensor terdeteksi offline/tidak aktif',
            '• Disertai lokasi lahan dan kemungkinan kendala',
            '',
            '──────────────────────────────────────',
            '',
            'Pesan ini dikirim secara otomatis oleh sistem.',
            'Jangan membalas email ini.',
            '',
            'Salam,',
            'Sistem Peringatan AgriSense',
        ]);

        try {
            Mail::raw($message, function ($mail) use ($validated) {
                $mail->to($validated['email'])
                    ->subject('[AgriSense] Uji Koneksi Notifikasi Email');
            });

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            Log::error('Failed to send test email: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengirim email. Periksa konfigurasi SMTP pada pengaturan sistem.',
            ], 500);
        }
    }
}
