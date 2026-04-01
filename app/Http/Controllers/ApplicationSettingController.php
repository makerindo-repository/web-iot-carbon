<?php

namespace App\Http\Controllers;

use App\Models\ApplicationSetting;
use App\Services\ImageService;
use Illuminate\Http\Request;

class ApplicationSettingController extends Controller
{
    public function index()
    {
        $setting = ApplicationSetting::first();
        return view('pages.application-setting.index', compact('setting'));
    }

    public function save(Request $request)
    {
        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'name' => 'required|string',
            'version' => 'required|string',
            'copyright' => 'required|string',
            'copyright_year' => 'required|string',
            'login_text' => 'nullable|string',
        ]);

        $setting = ApplicationSetting::first();
        if (!$setting) {
            $setting = new ApplicationSetting();
        }

        if ($request->hasFile('image')) {
            ImageService::deleteImage($setting->image);
            $imgPath = ImageService::image_intervention($request->file('image'), 'uploads/application-settings/');
            $setting->image = $imgPath;
        }

        $setting->name = $request->name;
        $setting->version = $request->version;
        $setting->copyright = $request->copyright;
        $setting->copyright_year = $request->copyright_year;
        $setting->login_text = $request->login_text;
        $setting->save();

        return redirect()->route('application-setting.index')->with('success', 'Pengaturan aplikasi berhasil disimpan');
    }

    public function fetchApplicationSettings()
    {
        $setting = ApplicationSetting::first();
        return response()->json([
            'image' => $setting->image ?? null,
            'name' => $setting->name ?? 'UPI Edge',
            'version' => $setting->version ?? '1.0',
            'copyright' => $setting->copyright ?? 'Universitas Pendidikan Indonesia',
            'copyright_year' => $setting->copyright_year ?? '2025',
            'login_text' => $setting->login_text ?? '',
        ]);
    }
}
