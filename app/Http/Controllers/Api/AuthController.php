<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle Login and Issue Sanctum Token
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|max:64',
            'recaptcha_token' => 'nullable|string',
        ]);

        // Verifikasi reCAPTCHA v3 jika secret key tersedia di .env
        $recaptchaSecret = env('RECAPTCHA_SECRET_KEY');
        if (! empty($recaptchaSecret) && $request->filled('recaptcha_token')) {
            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $recaptchaSecret,
                'response' => $request->recaptcha_token,
                'remoteip' => $request->ip(),
            ]);

            $result = $response->json();

            // Bypass blockir untuk localhost (127.0.0.1 atau ::1) agar tidak menyusahkan saat testing lokal
            $isLocalhost = in_array($request->ip(), ['127.0.0.1', '::1']);

            // Guard: bila Google tak terjangkau / balasan non-JSON, $result bisa null.
            // Normalisasi supaya tidak "array offset on null" dan tetap fail-secure.
            $recaptchaSuccess = is_array($result) && ($result['success'] ?? false);
            $recaptchaScore = is_array($result) ? (float) ($result['score'] ?? 1.0) : 0.0;

            // Skor < 0.5 dianggap sebagai bot
            if ((! $recaptchaSuccess || $recaptchaScore < 0.5) && ! $isLocalhost) {
                Log::warning('Login ditolak: terdeteksi bot reCAPTCHA. Skor: '.$recaptchaScore.' IP: '.$request->ip());
                throw ValidationException::withMessages([
                    'email' => ['Deteksi aktivitas mencurigakan. Anda terindikasi sebagai bot.'],
                ]);
            }
        }

        $user = User::where('email', $request->email)->first();

        // Cek apakah user ada dan password cocok
        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Kredensial yang Anda berikan salah.'],
            ]);
        }

        // Buat Token Sanct_um
        $token = $user->createToken('agrisense-token')->plainTextToken;

        // Log aktivitas login
        activity()
            ->causedBy($user)
            ->useLog('Auth')
            ->log("Login berhasil: {$user->name} ({$user->email})");

        return response()->json([
            'status' => 'success',
            'message' => 'Login berhasil',
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'profile_photo' => $user->profile_photo,
            ],
        ]);
    }

    /**
     * Handle Google Login
     */
    public function googleLogin(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            // Verifikasi JWT ID Token via Google's tokeninfo endpoint
            // (Menggantikan Socialite popup flow yang terkena blokir COOP)
            $response = Http::get('https://oauth2.googleapis.com/tokeninfo', [
                'id_token' => $request->token,
            ]);

            if ($response->failed()) {
                throw new \Exception('Google token verification failed: '.($response->json('error_description') ?? 'Unknown error'));
            }

            $googlePayload = $response->json();

            // Validasi audience (client_id) untuk keamanan
            $expectedClientId = config('services.google.client_id');
            if ($expectedClientId && $googlePayload['aud'] !== $expectedClientId) {
                throw new \Exception('Token audience mismatch. Possible token hijacking attempt.');
            }

            $email = $googlePayload['email'] ?? null;
            $name = $googlePayload['name'] ?? ($googlePayload['email'] ?? 'Google User');
            $googleId = $googlePayload['sub'] ?? null;

            if (! $email) {
                throw new \Exception('Email tidak ditemukan dalam token Google.');
            }

            // Cek apakah user sudah ada berdasarkan email
            $user = User::where('email', $email)->first();

            if (! $user) {
                // Jika user belum ada, buat user baru dengan role viewer
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'google_id' => $googleId,
                    'password' => Hash::make(Str::random(16)), // Random password
                    'role' => 'viewer', // Role default
                ]);
            } else {
                // Jika user sudah ada, update google_id nya (jika login pertama via google tapi sdh pny akun lokal)
                $user->update([
                    'google_id' => $googleId,
                ]);
            }

            // Buat token sanctum
            $token = $user->createToken('agrisense-token')->plainTextToken;

            // Log aktivitas login via Google
            activity()
                ->causedBy($user)
                ->useLog('Auth')
                ->log("Login via Google: {$user->name} ({$user->email})");

            return response()->json([
                'status' => 'success',
                'message' => 'Google Login berhasil',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'profile_photo' => $user->profile_photo,
                ],
            ]);
        } catch (\Exception $e) {
            $errorId = (string) Str::uuid();

            // Log error untuk debug internal
            Log::error('Google Login Error', [
                'error_id' => $errorId,
                'msg' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            // Mapping pesan domain-specific (UX value: user perlu tahu apakah
            // token expired vs DB down vs client ID mismatch).
            $message = 'Gagal memverifikasi token Google.';
            if (str_contains($e->getMessage(), 'Connection refused') || str_contains($e->getMessage(), 'SQLSTATE')) {
                $message = 'Gagal terhubung ke Database. Pastikan MySQL sudah menyala.';
            } elseif (str_contains($e->getMessage(), 'audience mismatch')) {
                $message = 'Token Google tidak valid (client ID tidak cocok).';
            } elseif (str_contains($e->getMessage(), 'verification failed')) {
                $message = 'Token Google tidak valid atau sudah kadaluarsa.';
            }

            return response()->json([
                'status' => 'error',
                'message' => $message,
                'error_id' => $errorId,
            ], 401);
        }
    }

    /**
     * Handle Logout and Revoke Token
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        // Log aktivitas logout
        activity()
            ->causedBy($user)
            ->useLog('Auth')
            ->log("Logout: {$user->name} ({$user->email})");

        $user->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully',
        ]);
    }
}
