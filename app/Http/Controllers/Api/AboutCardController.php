<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AboutCard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AboutCardController extends Controller
{
    // Semua role bisa baca
    public function index()
    {
        return response()->json(
            AboutCard::where('is_active', true)
                ->orderBy('type')
                ->orderBy('sort_order')
                ->get()
        );
    }

    // Admin: lihat semua (termasuk non-aktif)
    public function adminIndex()
    {
        return response()->json(
            AboutCard::orderBy('type')->orderBy('sort_order')->get()
        );
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:team,feature',
            'title' => 'required|string|max:100',
            'subtitle' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'image_url' => 'nullable|string|max:500',
            'image_upload' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'link' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image_upload')) {
            $path = $request->file('image_upload')->store('about-cards', 'public');
            $data['image_url'] = '/storage/'.$path;
        }

        // XSS Sanitization (konsisten dengan LandPlot/Garden/Planting/Komoditi)
        $data['title'] = strip_tags($data['title']);
        if (isset($data['subtitle'])) {
            $data['subtitle'] = strip_tags($data['subtitle']);
        }
        if (isset($data['description'])) {
            $data['description'] = strip_tags($data['description']);
        }
        if (isset($data['link'])) {
            $data['link'] = strip_tags($data['link']);
        }

        $data['sort_order'] = $data['sort_order'] ?? AboutCard::max('sort_order') + 1;
        $data['is_active'] = $data['is_active'] ?? true;

        $card = AboutCard::create($data);

        activity()->performedOn($card)->log("Menambah kartu About: {$card->title}");

        return response()->json($card, 201);
    }

    public function update(Request $request, $id)
    {
        $card = AboutCard::findOrFail($id);

        $data = $request->validate([
            'type' => 'sometimes|in:team,feature',
            'title' => 'sometimes|string|max:100',
            'subtitle' => 'nullable|string|max:100',
            'description' => 'nullable|string|max:1000',
            'image_url' => 'nullable|string|max:500',
            'image_upload' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'link' => 'nullable|string|max:500',
            'sort_order' => 'nullable|integer|min:0|max:999',
            'is_active' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image_upload')) {
            // Delete old file if exists
            if ($card->image_url && str_starts_with($card->image_url, '/storage/')) {
                $oldPath = str_replace('/storage/', '', $card->image_url);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image_upload')->store('about-cards', 'public');
            $data['image_url'] = '/storage/'.$path;
        }

        // XSS Sanitization
        if (isset($data['title'])) {
            $data['title'] = strip_tags($data['title']);
        }
        if (isset($data['subtitle'])) {
            $data['subtitle'] = strip_tags($data['subtitle']);
        }
        if (isset($data['description'])) {
            $data['description'] = strip_tags($data['description']);
        }
        if (isset($data['link'])) {
            $data['link'] = strip_tags($data['link']);
        }

        $card->update($data);

        activity()->performedOn($card)->log("Mengubah kartu About: {$card->title}");

        return response()->json($card);
    }

    public function destroy($id)
    {
        $card = AboutCard::findOrFail($id);

        activity()->log("Menghapus kartu About: {$card->title}");

        $card->delete();

        return response()->json(['message' => 'Kartu berhasil dihapus']);
    }
}
