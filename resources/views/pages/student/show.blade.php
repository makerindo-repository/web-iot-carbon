<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('master-data.index') }}">Data Master</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('student.index') }}">Data Mahasiswa</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Lihat Detail Mahasiswa') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Detail Mahasiswa</h1>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <img src="{{ asset($student->photo) }}" alt="Foto Dosen"
                                class="w-full h-auto rounded-lg shadow-md">
                        </div>
                        <div class="col-span-2">
                            <table class="min-w-full bg-white">
                                <tbody>
                                    <tr>
                                        <td class="px-6 py-4 border-b">Nama</td>
                                        <td class="px-6 py-4 border-b">{{ $student->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b">NIM</td>
                                        <td class="px-6 py-4 border-b">{{ $student->nim }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b">Email</td>
                                        <td class="px-6 py-4 border-b">{{ $student->email }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b">Jurusan</td>
                                        <td class="px-6 py-4 border-b">{{ $student->major }} - Semester {{ $student->semester }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b">Alamat</td>
                                        <td class="px-6 py-4 border-b">{{ $student->address }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b">Riwayat Kegiatan</td>
                                        <td class="px-6 py-4 border-b">
                                            <ul class="list-disc pl-5">
                                                @foreach ($student->activitySchedules as $activity)
                                                    <li>{{ $activity->agenda }}
                                                        ({{ \Carbon\Carbon::parse($activity->date)->translatedFormat('d F Y') }})
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
