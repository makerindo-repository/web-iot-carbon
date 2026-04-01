<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Data Dosen') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold">Tabel Log Aktivitas</h1>
                </div>
                <div class="overflow-x-scroll">
                    @if ($logs->isEmpty())
                        <div class="text-center py-8 text-slate-500">
                            Tidak ada log aktivitas yang terdata.
                        </div>
                    @else
                        <table class="w-full align-middle border-slate-400 table mb-0 mt-3" id="activity-log-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Waktu</th>
                                    <th>User</th>
                                    <th>Aksi</th>
                                    <th>Deskripsi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($logs as $log)
                                    <tr>
                                        <td class="text-center">{{ $loop->iteration }}</td>
                                        <td class="text-center">{{ $log->created_at->format('d M Y H:i:s') }}</td>
                                        <td class="text-center">{{ $log->causer ? $log->causer->name : 'Sistem' }}</td>
                                        <td class="text-center">{{ $log->event }}</td>
                                        <td class="text-center">{{ $log->description }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
