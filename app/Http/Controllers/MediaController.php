<?php

namespace App\Http\Controllers;

use App\Models\FilteredFixStation;
use App\Models\Media;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MediaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $medias = Media::get()->map(function ($m) {
            $m->plants = json_decode($m->plants, true);
            $m->plants_condition = json_decode($m->plants_condition, true);
            return $m;
        });
        return view('pages.media.index', compact('medias'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.media.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'plants' => 'required|array',
        ], [
            'name.required' => 'Nama media harus diisi',
            'plants.required' => 'Tanaman harus diisi',
            'plants.array' => 'Tanaman harus berupa array',
        ]);

        $data = [
            'name' => $request->name,
            'plants' => json_encode($request->plants),
            'plants_condition' => json_encode($this->getPlantsCondition($request->plants)),
        ];

        $post = Media::create($data);

        activity()
            ->performedOn($post)
            ->event('create')
            ->causedBy(Auth::user())
            ->log('Media baru ditambahkan: ' . $request->name);

        return redirect()->route('media.index')->with('success', 'Data media berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $media = Media::findOrFail($id);
        $media->plants = json_decode($media->plants, true);
        $media->plants_condition = json_decode($media->plants_condition, true);
        return view('pages.media.edit', compact('media'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string',
            'plants' => 'required|array',
        ], [
            'name.required' => 'Nama harus diisi.',
            'plants.required' => 'Tanaman harus diisi.',
            'plants.array' => 'Tanaman harus berupa array.',
        ]);

        $media = Media::findOrFail($id);
        $beforeUpdate = $media->getOriginal();

        $data = [
            'name' => $request->name,
            'plants' => json_encode($request->plants),
        ];

        $oldPlants = json_decode($media->plants, true) ?? [];
        $newPlants = $request->plants;

        if ($oldPlants !== $newPlants) {
            $data['plants_condition'] = json_encode($this->getPlantsCondition($newPlants));
        } else {
            $data['plants_condition'] = $media->plants_condition;
        }

        $media->update($data);

        $changes = [];

        foreach ($data as $key => $value) {
            if (array_key_exists($key, $beforeUpdate) &&  $beforeUpdate[$key] !== $value) {
                $changes[$key] = [
                    'old' => $beforeUpdate[$key],
                    'new' => $value,
                ];
            }
        }

        activity()
            ->performedOn($media)
            ->event('update')
            ->withProperties(['changes' => $changes])
            ->causedBy(Auth::user())
            ->log('Media dengan ID ' . $media->id . ' berhasil diupdate');

        return redirect()->route('media.index')->with('success', 'Data media berhasil diupdate.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $media = Media::findOrFail($id);

        $media->delete();

        activity()
            ->performedOn($media)
            ->event('delete')
            ->causedBy(Auth::user())
            ->log('Media dihapus: ' . $media->name);

        return redirect()->route('media.index')->with('success', 'Data media berhasil dihapus.');
    }

    private function getPlantsCondition(array $plants)
    {
        $sensorData = FilteredFixStation::latest()->first();

        $n = $sensorData->samples->Nitrogen;
        $p = $sensorData->samples->Phosporus;
        $k = $sensorData->samples->Kalium;
        $ec = $sensorData->samples->Ec;
        $ph = $sensorData->samples->Ph;
        $temp = $sensorData->samples->Temperature;
        $humid = $sensorData->samples->Humidity;

        $plantsList = implode(", ", $plants);

        $prompt = "
            Kamu adalah sistem analisis pertanian.

            Berikut kondisi tanah saat ini:
            - pH: $ph
            - Kelembapan Tanah: $humid %
            - Nitrogen: $n mg/kg
            - Fosfor: $p mg/kg
            - Kalium: $k mg/kg
            - EC: $ec uS/cm
            - Suhu Tanah: $temp °C

            Tanaman yang ingin dianalisis: $plantsList

            Tugas kamu:
            - Analisis setiap tanaman berdasarkan data tanah di atas.
            - Untuk setiap tanaman, sebutkan kondisi singkat dalam 1-3 kalimat ringkas.
            - Harus menyinggung pH, kelembapan, N, P, K, EC, dan suhu (tanpa detail panjang).
            - Jawaban HARUS berupa JSON array valid dengan struktur berikut:
            [
                {
                    \"tanaman\": \"nama_tanaman\",
                    \"kondisi\": \"analisis_singkat_tanpa_bertele\"
                }
            ]
            - Jangan menambahkan teks lain di luar JSON.
            - Gunakan bahasa Indonesia.
        ";

        $result =  Gemini::generativeModel('gemini-2.0-flash')->generateContent($prompt);
        $response = $result->text();
        $cleanedResponse = trim($response);
        // Hapus blok code fence seperti ```json dan ```
        $cleanedResponse = preg_replace('/^```json\s*|\s*```$/i', '', $cleanedResponse);
        // Bersihkan lagi spasi berlebih
        $cleanedResponse = trim($cleanedResponse);
        $decodedResponse = json_decode($cleanedResponse, true);

        return $decodedResponse;
    }
}
