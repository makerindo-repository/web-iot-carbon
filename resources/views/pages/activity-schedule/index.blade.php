<x-app-layout>
    @push('styles')
        <style>
            .dataTables_wrapper .dataTables_info {
                padding: 0.25rem 0.75rem !important;
                margin: 1rem 0.25rem !important;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button {
                padding: 0.25rem 0.75rem !important;
                margin: 1rem 0.25rem !important;
                border: 1px solid #d1d5db !important;
                /* gray-300 */
                border-radius: 0.5rem !important;
                /* rounded-md */
                font-size: 1rem !important;
                /* text-sm */
                color: #374151;
                /* gray-700 */
                background-color: #ffffff !important;
                /* white */
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
                background-color: #f3f4f6 !important;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button.current {
                background-color: #EF4444 !important;
                /* blue-600 */
                color: #ffffff !important;
                /* white */
                font-weight: 600 !important;
                /* font-semibold */
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
                background-color: #DC2626 !important;
                /* blue-600 */
                color: #ffffff !important;
                /* white */
            }
        </style>
    @endpush
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Jadwal Kegiatan Praktikum') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="flex flex-col sm:flex-row sm:justify-between my-5 items-center">
                    <h1 class="text-3xl font-extrabold text-start">Tabel Jadwal Kegiatan Praktikum</h1>
                    @if (in_array(Auth::user()->role, ['superuser', 'dosen']))
                        <a href="{{ route('activity-schedule.create') }}"
                            class="bg-primary px-4 py-2 text-white rounded-lg ml-auto w-auto mt-0">Tambah Data</a>
                    @endif
                </div>
                <div class="overflow-x-scroll">
                    <table class="w-full align-middle border-slate-400 table mb-0 mt-3" id="activity-schedule-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Hari</th>
                                <th>Tanggal</th>
                                <th>Waktu Mulai</th>
                                <th>Waktu Selesai</th>
                                <th>Jumlah Dosen</th>
                                <th>Jumlah Mahasiswa</th>
                                <th>Agenda</th>
                                @if (in_array(Auth::user()->role, ['superuser', 'dosen']))
                                    <th>Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($activitySchedules as $activitySchedule)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $activitySchedule->day }}</td>
                                    <td>{{ \Carbon\Carbon::parse($activitySchedule->date)->translatedFormat('d F Y') }}
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($activitySchedule->start_time)->format('H:i') }}</td>
                                    <td>{{ \Carbon\Carbon::parse($activitySchedule->end_time)->format('H:i') }}</td>
                                    <td>{{ $activitySchedule->lecturers_count }} <button class="ml-2"
                                            onclick="toggleDetails('lecturers', {{ $activitySchedule->id }})"><i
                                                class="fa-solid fa-caret-down text-green-500"
                                                id="icon-lecturers-{{ $activitySchedule->id }}"></i></button>
                                        <div id="lecturers-{{ $activitySchedule->id }}" class="hidden text-sm mt-2">
                                            <strong>Daftar Dosen:</strong>
                                            <ul class="list-disc list-inside">
                                                @foreach ($activitySchedule->lecturers as $lecturer)
                                                    <li>{{ $lecturer->name }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </td>
                                    <td>{{ $activitySchedule->students_count }} <button class="ml-2"
                                            onclick="toggleDetails('students', {{ $activitySchedule->id }})"><i
                                                class="fa-solid fa-caret-down text-green-500"
                                                id="icon-students-{{ $activitySchedule->id }}"></i></button>
                                        <div id="students-{{ $activitySchedule->id }}" class="hidden text-sm mt-2">
                                            <strong>Daftar Mahasiswa:</strong>
                                            <ul class="list-disc list-inside">
                                                @foreach ($activitySchedule->students as $student)
                                                    <li>{{ $student->name }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </td>
                                    <td>{{ $activitySchedule->agenda }}</td>
                                    @if (in_array(Auth::user()->role, ['superuser', 'dosen']))
                                        <td class="h-full">
                                            <div class="flex items-center gap-2 h-full">
                                                <a href="{{ route('activity-schedule.edit', $activitySchedule->id) }}"
                                                    class="h-100">
                                                    <i class="fa fa-pen text-blue-500"></i>
                                                </a>
                                                <form
                                                    action="{{ route('activity-schedule.destroy', $activitySchedule->id) }}"
                                                    method="POST" class="delete-form"
                                                    data-nama="{{ $activitySchedule->agenda }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit">
                                                        <i class="fa fa-trash text-red-500"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(document).ready(function() {
                const now = new Date();
                const date = now.getDate().toString().padStart(2, '0');
                const month = (now.getMonth() + 1).toString().padStart(2, '0');
                const year = now.getFullYear();
                const hours = now.getHours().toString().padStart(2, '0');
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');
                const formattedTimestamp = `${year}${month}${date}_${hours}${minutes}${seconds}`;

                $('#activity-schedule-table').DataTable({
                    responsive: true,
                    pageLength: 10,
                    search: {
                        smart: false
                    },
                    dom: '<"flex flex-col md:flex-row md:justify-between items-center mb-2"Bf>rtip',
                    buttons: [{
                            extend: 'csv',
                            text: 'Export CSV',
                            title: 'Data Mahasiswa',
                            className: 'bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600',
                            filename: `jadwal_kegiatan_praktikum_${formattedTimestamp}`,
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7]
                            }
                        },
                        {
                            extend: 'excel',
                            text: 'Export Excel',
                            title: 'Data Mahasiswa',
                            className: 'bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600',
                            filename: `jadwal_kegiatan_praktikum_${formattedTimestamp}`,
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7]
                            }
                        },
                        {
                            extend: 'pdf',
                            text: 'Export PDF',
                            title: 'Data Mahasiswa',
                            className: 'bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600',
                            filename: `jadwal_kegiatan_praktikum_${formattedTimestamp}`,
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7]
                            }
                        },
                    ],
                    language: {
                        search: "Cari:",
                        lengthMenu: "Tampilkan _MENU_ entri",
                        emptyTable: "Tidak ada data agenda yang tersedia",
                        info: "Menampilkan _START_ hingga _END_ dari _TOTAL_ entri",
                        infoEmpty: "Tidak ada data agenda yang dapat ditampilkan.",
                        zeroRecords: "Data agenda tidak ditemukan.",
                        infoFiltered: "(difilter dari _MAX_ total entri)",
                        paginate: {
                            previous: "<",
                            next: ">"
                        }
                    },
                    @if (in_array(Auth::user()->role, ['superuser', 'dosen']))
                        columnDefs: [{
                            targets: [5, 6, 8],
                            orderable: false
                        }, {
                            targets: [0, 5, 6, 8],
                            searchable: false
                        }],
                    @else
                        columnDefs: [{
                            targets: [5, 6, 7],
                            orderable: false
                        }, {
                            targets: [0, 5, 6, 7],
                            searchable: false
                        }],
                    @endif
                });
            });

            @if (session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: '{{ session('success') }}',
                    showConfirmButton: false,
                    timer: 2000,
                });
            @endif

            document.querySelectorAll('.delete-form').forEach(form => {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();

                    const activityScheduleName = this.getAttribute('data-nama');
                    Swal.fire({
                        title: 'Konfirmasi',
                        text: `Apakah Anda yakin ingin menghapus data agenda ${activityScheduleName}?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Hapus!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit();
                        }
                    });
                })


            });

            function toggleDetails(type, id) {
                const content = document.getElementById(`${type}-${id}`);
                const icon = document.getElementById(`icon-${type}-${id}`);

                content.classList.toggle('hidden');

                if (content.classList.contains('hidden')) {
                    icon.classList.remove('fa-caret-up');
                    icon.classList.add('fa-caret-down');
                } else {
                    icon.classList.remove('fa-caret-down');
                    icon.classList.add('fa-caret-up');
                }
            }
        </script>
    @endpush
</x-app-layout>
