<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AgrisenseSetting;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class SystemController extends Controller
{
    // ═══════════════════════════════════════════════════════════
    //  GET /api/agrisense/logs — Activity logs
    // ═══════════════════════════════════════════════════════════
    public function getLogs()
    {
        $logs = Activity::with('causer')->latest()->limit(50)->get();

        return response()->json($logs->map(function ($l) {
            return [
                'id' => 'LOG-'.str_pad($l->id, 3, '0', STR_PAD_LEFT),
                'user' => $l->causer->name ?? 'System',
                'action' => $l->description,
                'module' => $l->log_name ?? 'System',
                'timestamp' => $l->created_at->toIso8601String(),
                'status' => 'success',
                'ip' => '127.0.0.1',
            ];
        }));
    }

    public function recordLog(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|string',
            'module' => 'sometimes|string',
        ]);

        activity()->log($validated['action']);

        return response()->json(['status' => 'success']);
    }

    // ═══════════════════════════════════════════════════════════
    //  GET /api/agrisense/users — Daftar pengguna
    // ═══════════════════════════════════════════════════════════
    public function getUsers()
    {
        return response()->json(User::all()->map(function ($u) {
            return [
                'id' => 'USR-'.str_pad($u->id, 3, '0', STR_PAD_LEFT),
                'real_id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role ?? 'operator',
                'status' => 'active',
                'lastLogin' => $u->updated_at->toIso8601String(),
            ];
        }));
    }

    // ═══════════════════════════════════════════════════════════
    //  POST /api/agrisense/users — Create pengguna
    // ═══════════════════════════════════════════════════════════
    public function createUser(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'role' => 'required|in:admin,operator,viewer',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt('password123'), // default password
            'role' => $validated['role'],
        ]);

        return response()->json(['status' => 'success', 'user' => $user]);
    }

    // ═══════════════════════════════════════════════════════════
    //  PUT /api/agrisense/users/{id} — Update pengguna
    // ═══════════════════════════════════════════════════════════
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|string',
            'email' => 'sometimes|email|unique:users,email,'.$id,
            'role' => 'sometimes|in:admin,operator,viewer',
        ]);

        $user->update($validated);

        return response()->json(['status' => 'success', 'user' => $user]);
    }

    // ═══════════════════════════════════════════════════════════
    //  DELETE /api/agrisense/users/{id} — Delete pengguna
    // ═══════════════════════════════════════════════════════════
    public function deleteUser($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        return response()->json(['status' => 'success']);
    }

    // ═══════════════════════════════════════════════════════════
    //  GET /api/agrisense/settings — Get sistem settings
    // ═══════════════════════════════════════════════════════════
    public function getSettings()
    {
        $settings = AgrisenseSetting::pluck('value', 'key')->toArray();

        // Default values if empty
        $defaults = [
            'appName' => 'AgriSense V1.0',
            'co2Threshold' => '1000',
            'tempMax' => '35',
            'humidityMin' => '40',
            'samplingInterval' => '60',
            'mqttUrl' => 'mqtt://broker.agrisense.id:1883',
            'aiEngineKey' => 'sk-agrisense-ai-engine-key-2026',
            'emailAlert' => '1',
            'telegramBot' => '0',
        ];

        return response()->json(array_merge($defaults, $settings));
    }

    // ═══════════════════════════════════════════════════════════
    //  POST /api/agrisense/settings — Update sistem settings
    // ═══════════════════════════════════════════════════════════
    public function updateSettings(Request $request)
    {
        $data = $request->all();
        foreach ($data as $key => $value) {
            // handle boolean as 1/0
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            AgrisenseSetting::updateOrCreate(
                ['key' => $key],
                ['value' => (string) $value]
            );
        }

        return response()->json(['status' => 'success']);
    }
}
