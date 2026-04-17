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
                border-radius: 0.5rem !important;
                font-size: 1rem !important;
                color: #374151;
                background-color: #ffffff !important;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
                background-color: #f3f4f6 !important;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button.current {
                background-color: #EF4444 !important;
                color: #ffffff !important;
                font-weight: 600 !important;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
                background-color: #DC2626 !important;
                color: #ffffff !important;
            }
        </style>
    @endpush
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('master-data.index') }}">Data Master</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Data Perangkat') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="flex flex-col sm:flex-row sm:justify-between my-5 items-center">
                    <h1 class="text-3xl font-extrabold text-start">Tabel Data Perangkat</h1>
                    <a href="{{ route('device.create') }}"
                        class="bg-primary px-4 py-2 text-white rounded-lg ml-auto w-auto mt-0">Tambah Data</a>
                </div>
                <div class="overflow-x-scroll">
                    <table class="w-full align-middle border-slate-400 table mb-0 mt-3" id="device-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Device Code</th>
                                <th>Penempatan</th>
                                <th>Firmware</th>
                                <th>Latitude</th>
                                <th>Longitude</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($devices as $device)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $device->device_code }}</td>
                                    <td>
                                        @if ($device->landPlot)
                                            <span class="text-gray-700">
                                                <strong>Lahan:</strong> {{ $device->landPlot->plot_name }}
                                            </span>
                                        @elseif($device->garden)
                                            <span class="text-gray-700">
                                                <strong>Kebun:</strong> {{ $device->garden->garden_name }}
                                            </span>
                                        @else
                                            <span class="text-gray-500 italic">
                                                Belum Terpasang
                                            </span>
                                        @endif
                                    </td>
                                    <td><code class="text-sm font-mono text-pink-600 bg-pink-50 px-1 rounded">{{ $device->firmware_version ?? 'v0.0.0' }}</code></td>
                                    <td>{{ $device->latitude }}</td>
                                    <td>{{ $device->longitude }}</td>
                                    <td>
                                        @if($device->device_status == 'online')
                                            <span class="flex items-center text-green-600 font-bold uppercase text-xs">
                                                <span class="w-2 h-2 bg-green-500 rounded-full mr-2 animate-pulse"></span>
                                                Online
                                            </span>
                                        @else
                                            <span class="flex items-center text-gray-500 font-bold uppercase text-xs">
                                                <span class="w-2 h-2 bg-gray-400 rounded-full mr-2"></span>
                                                Offline
                                            </span>
                                        @endif
                                    </td>
                                    <td class="flex space-x-2 items-center">
                                        <a href="{{ route('device.show', $device->id) }}">
                                            <i class="fa fa-circle-info text-green-500"></i>
                                        </a>
                                        <a href="{{ route('device.edit', $device->id) }}">
                                            <i class="fa fa-pen text-blue-500"></i>
                                        </a>
                                        <form action="{{ route('device.destroy', $device->id) }}" method="POST"
                                            class="delete-form" data-device-code="{{ $device->device_code }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit">
                                                <i class="fa fa-trash text-red-500"></i>
                                            </button>
                                        </form>
                                    </td>
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
            const timestamp = () => {
                const now = new Date();
                const date = now.getDate().toString().padStart(2, '0');
                const month = (now.getMonth() + 1).toString().padStart(2, '0');
                const year = now.getFullYear();
                const hours = now.getHours().toString().padStart(2, '0');
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');

                return `${year}${month}${date}_${hours}${minutes}${seconds}`;
            }

            $(document).ready(function() {
                $('#device-table').DataTable({
                    responsive: true,
                    pageLength: 10,
                    search: {
                        smart: false
                    },
                    dom: '<"flex flex-col md:flex-row md:justify-between items-center mb-2"Bf>rtip',
                    buttons: [{
                            extend: 'csv',
                            text: 'Export CSV',
                            title: 'Data Perangkat',
                            className: 'bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600',
                            filename: function() {
                                return `data_perangkat_${timestamp()}`;
                            },
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6]
                            }
                        },
                        {
                            extend: 'excel',
                            text: 'Export Excel',
                            title: 'Data Perangkat',
                            className: 'bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600',
                            filename: function() {
                                return `data_perangkat_${timestamp()}`;
                            },
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6]
                            }
                        },
                        {
                            extend: 'pdf',
                            text: 'Export PDF',
                            title: 'Data Perangkat',
                            className: 'bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600',
                            filename: function() {
                                return `data_perangkat_${timestamp()}`;
                            },
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6]
                            }
                        },
                    ],
                    language: {
                        search: "Cari:",
                        lengthMenu: "Tampilkan _MENU_ entri",
                        emptyTable: "Tidak ada data perangkat yang tersedia",
                        info: "Menampilkan _START_ hingga _END_ dari _TOTAL_ entri",
                        infoEmpty: "Tidak ada data perangkat yang dapat ditampilkan.",
                        zeroRecords: "Data perangkat tidak ditemukan.",
                        infoFiltered: "(difilter dari _MAX_ total entri)",
                        paginate: {
                            previous: "<",
                            next: ">"
                        }
                    },
                    columnDefs: [{
                        targets: [7],
                        orderable: false
                    }, {
                        targets: [0, 7],
                        searchable: false
                    }],
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

                    const deviceCode = this.getAttribute('data-device-code');
                    Swal.fire({
                        title: 'Konfirmasi',
                        text: `Apakah Anda yakin ingin menghapus perangkat ${deviceCode}?`,
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
        </script>
    @endpush
</x-app-layout>
