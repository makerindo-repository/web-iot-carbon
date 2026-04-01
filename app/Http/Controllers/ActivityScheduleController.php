<?php

namespace App\Http\Controllers;

use App\Models\ActivitySchedule;
use App\Models\Lecturer;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActivityScheduleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $activitySchedules = ActivitySchedule::forCurrentUser()->with('students', 'lecturers')->withCount('students', 'lecturers')->get();
        return view('pages.activity-schedule.index', compact('activitySchedules'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $lecturers = Lecturer::get();
        $students = Student::get();
        return view('pages.activity-schedule.create', compact('lecturers', 'students'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'day' => 'required|string|max:255',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'lecturers' => 'required|array',
            'students' => 'required|array',
            'agenda' => 'required|string|max:255',
        ], [
            'day.required' => 'Hari harus diisi.',
            'date.required' => 'Tanggal harus diisi.',
            'start_time.required' => 'Waktu mulai harus diisi.',
            'start_time.date_format' => 'Format waktu mulai tidak valid.',
            'end_time.required' => 'Waktu selesai harus diisi.',
            'end_time.after' => 'Waktu selesai harus lebih dari waktu mulai.',
            'end_time.date_format' => 'Format waktu selesai tidak valid.',
            'lecturers.required' => 'Dosen harus dipilih.',
            'lecturers.array' => 'Dosen harus berupa array.',
            'students.array' => 'Mahasiswa harus berupa array.',
            'students.required' => 'Mahasiswa harus dipilih.',
            'agenda.required' => 'Agenda harus diisi.',
        ]);

        $data = $request->only([
            'day',
            'date',
            'start_time',
            'end_time',
            'agenda',
        ]);

        $activitySchedule = ActivitySchedule::create($data);

        $activitySchedule->lecturers()->attach($request->lecturers);
        $activitySchedule->students()->attach($request->students);

        activity()
            ->causedBy(Auth::user())
            ->event('create')
            ->performedOn($activitySchedule)
            ->log('Jadwal kegiatan dibuat: ' . $activitySchedule->agenda);

        return redirect()->route('activity-schedule.index')->with('success', 'Jadwal kegiatan berhasil dibuat.');
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
        $activitySchedule = ActivitySchedule::with('lecturers', 'students')->findOrFail($id);
        $lecturers = Lecturer::get();
        $students = Student::get();
        $activitySchedule->start_time = Carbon::parse($activitySchedule->start_time)->format('H:i');
        $activitySchedule->end_time = Carbon::parse($activitySchedule->end_time)->format('H:i');
        return view('pages.activity-schedule.edit', compact('activitySchedule', 'lecturers', 'students'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'day' => 'required|string|max:255',
            'date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'lecturers' => 'required|array',
            'students' => 'required|array',
            'agenda' => 'required|string|max:255',
        ], [
            'day.required' => 'Hari harus diisi.',
            'date.required' => 'Tanggal harus diisi.',
            'start_time.required' => 'Waktu mulai harus diisi.',
            'start_time.date_format' => 'Format waktu mulai tidak valid.',
            'end_time.required' => 'Waktu selesai harus diisi.',
            'end_time.after' => 'Waktu selesai harus lebih dari waktu mulai.',
            'end_time.date_format' => 'Format waktu selesai tidak valid.',
            'lecturers.required' => 'Dosen harus dipilih.',
            'lecturers.array' => 'Dosen harus berupa array.',
            'students.array' => 'Mahasiswa harus berupa array.',
            'students.required' => 'Mahasiswa harus dipilih.',
            'agenda.required' => 'Agenda harus diisi.',
        ]);

        $data = $request->only([
            'day',
            'date',
            'start_time',
            'end_time',
            'agenda',
        ]);

        $activitySchedule = ActivitySchedule::findOrFail($id);
        $beforeUpdate = $activitySchedule->getOriginal();
        $activitySchedule->update($data);

        $activitySchedule->lecturers()->sync($request->lecturers);
        $activitySchedule->students()->sync($request->students);
        $changes = [];
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $beforeUpdate) && $beforeUpdate[$key] !== $value) {
                $changes[$key] = [
                    'old' => $beforeUpdate[$key],
                    'new' => $value,
                ];
            }
        }

        activity()
            ->causedBy(Auth::user())
            ->event('update')
            ->withProperties(['changes' => $changes])
            ->performedOn($activitySchedule)
            ->log('Jadwal kegiatan diperbarui: ' . $activitySchedule->agenda);

        return redirect()->route('activity-schedule.index')->with('success', 'Jadwal kegiatan berhasil diupdate.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $activitySchedule = ActivitySchedule::with('students', 'lecturers')->findOrFail($id);
        $activitySchedule->delete();

        activity()
            ->causedBy(Auth::user())
            ->event('delete')
            ->performedOn($activitySchedule)
            ->log('Jadwal kegiatan dihapus: ' . $activitySchedule->agenda);

        return redirect()->route('activity-schedule.index')->with('success', 'Jadwal kegiatan berhasil dihapus.');
    }
}
