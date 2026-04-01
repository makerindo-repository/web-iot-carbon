<?php

namespace App\Http\Controllers;

use App\Models\Lecturer;
use App\Models\User;
use App\Models\UserSubscription;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LecturerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $lecturers = Lecturer::get();
        return view('pages.lecturer.index', compact('lecturers'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('pages.lecturer.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'nip' => 'required|string|unique:lecturers,nip',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:lecturers,email|unique:users,email',
            'address' => 'required|string',
            'gender' => 'required|in:l,p',
            'department' => 'required|string',
            'photo' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'password' => 'required|confirmed',
        ], [
            'nip.required' => 'NIP harus diisi.',
            'nip.unique' => 'NIP sudah terdaftar.',
            'name.required' => 'Nama harus diisi.',
            'email.required' => 'Email harus diisi.',
            'email.unique' => 'Email sudah terdaftar.',
            'address.required' => 'Alamat harus diisi.',
            'gender.required' => 'Jenis kelamin harus dipilih.',
            'department.required' => 'Departemen harus diisi.',
            'photo.required' => 'Foto harus diunggah.',
            'photo.image' => 'File yang diunggah harus berupa gambar.',
            'photo.mimes' => 'Format gambar harus jpeg, png, atau jpg.',
            'photo.max' => 'Ukuran gambar tidak boleh lebih dari 2MB.',
            'password.required' => 'Password harus diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok',
        ]);

        // Simpan user baru
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'dosen',
        ]);

        // Simpan default subscription
        UserSubscription::create([
            'user_id' => $user->id,
            'plan_id' => 1,
            'used_quota' => 0,
            'expires_at' => null
        ]);

        $data = [
            'user_id' => $user->id,
            'nip' => $request->nip,
            'name' => $request->name,
            'email' => $request->email,
            'address' => $request->address,
            'gender' => $request->gender,
            'department' => $request->department,
        ];

        $photo = ImageService::image_intervention($request->file('photo'), 'uploads/lecturers/', 1 / 1);

        $post = Lecturer::create($data + [
            'photo' => $photo,
        ]);

        activity()
            ->performedOn($post)
            ->event('create')
            ->causedBy(Auth::user())
            ->log('Dosen baru ditambahkan: ' . $request->name);

        return redirect()->route('lecturer.index')->with('success', 'Data dosen berhasil ditambahkan.');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $lecturer = Lecturer::with('activitySchedules')->findOrFail($id);
        return view('pages.lecturer.show', compact('lecturer'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $lecturer = Lecturer::findOrFail($id);
        return view('pages.lecturer.edit', compact('lecturer'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'nip' => 'required|string|unique:lecturers,nip,' . $id,
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:lecturers,email,' . $id . '|unique:users,email,' . (Lecturer::findOrFail($id)->user_id ?? 'NULL'),
            'address' => 'required|string',
            'gender' => 'required|in:l,p',
            'department' => 'required|string',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
            'password' => 'nullable|confirmed',
        ], [
            'nip.required' => 'NIP harus diisi.',
            'nip.unique' => 'NIP sudah terdaftar.',
            'name.required' => 'Nama harus diisi.',
            'email.required' => 'Email harus diisi.',
            'email.unique' => 'Email sudah terdaftar.',
            'address.required' => 'Alamat harus diisi.',
            'gender.required' => 'Jenis kelamin harus dipilih.',
            'department.required' => 'Departemen harus diisi.',
            'photo.image' => 'File yang diunggah harus berupa gambar.',
            'photo.mimes' => 'Format gambar harus jpeg, png, atau jpg.',
            'photo.max' => 'Ukuran gambar tidak boleh lebih dari 2MB.',
            'password.confirmed' => 'Konfirmasi password tidak cocok',
        ]);

        $data = [
            'nip' => $request->nip,
            'name' => $request->name,
            'email' => $request->email,
            'address' => $request->address,
            'gender' => $request->gender,
            'department' => $request->department,
        ];

        $lecturer = Lecturer::findOrFail($id);
        $beforeUpdate = $lecturer->getOriginal();
        $lecturer->update($data);

        // Update password user jika diisi
        if ($request->filled('password')) {
            $lecturer->user->update([
                'password' => Hash::make($request->password),
            ]);
        }

        $changes = [];

        if ($request->hasFile('photo')) {
            ImageService::deleteImage($lecturer->photo);
            $photo = ImageService::image_intervention($request->file('photo'), 'uploads/lecturers/', 1 / 1);
            $lecturer->update(['photo' => $photo]);
            $changes['photo'] = [
                'old' => $beforeUpdate['photo'],
                'new' => $photo,
            ];
        }

        foreach ($data as $key => $value) {
            if (array_key_exists($key, $beforeUpdate) &&  $beforeUpdate[$key] !== $value) {
                $changes[$key] = [
                    'old' => $beforeUpdate[$key],
                    'new' => $value,
                ];
            }
        }

        activity()
            ->performedOn($lecturer)
            ->event('update')
            ->withProperties(['changes' => $changes])
            ->causedBy(Auth::user())
            ->log('Dosen dengan ID ' . $lecturer->id . ' berhasil diupdate');

        return redirect()->route('lecturer.index')->with('success', 'Data dosen berhasil diupdate.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $lecturer = Lecturer::findOrFail($id);

        if ($lecturer->photo) {
            ImageService::deleteImage($lecturer->photo);
        }

        $lecturer->delete();

        activity()
            ->performedOn($lecturer)
            ->event('delete')
            ->causedBy(Auth::user())
            ->log('Dosen dihapus: ' . $lecturer->name);

        return redirect()->route('lecturer.index')->with('success', 'Data dosen berhasil dihapus.');
    }
}
