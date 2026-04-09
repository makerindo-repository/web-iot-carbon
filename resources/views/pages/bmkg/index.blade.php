<x-app-layout>
    @push('styles')
        <style>
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
                background-color: #f3f4f6 !important;
                color: #374151 !important;
                font-weight: 600 !important;
                border: 1px solid #d1d5db !important;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
                background-color: #e5e7eb !important;
                color: #374151 !important;
            }
        </style>
    @endpush
    
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('rsc-data.index') }}">Data Rapid Soil Checker (RSC)</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Data Klimatologi (BMKG)') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col space-y-4 mb-4">
                        <div class="flex flex-col sm:flex-row justify-between sm:items-center space-y-4 sm:space-y-0">
                            <h1 class="text-3xl font-extrabold">Data Klimatologi BMKG</h1>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500">
                                Data di bawah diambil dari API Open Data BMKG.
                            </p>
                        </div>
                        <div class="flex flex-col sm:flex-row justify-between items-center mt-6 z-10 relative">
                            <div id="export-btn-container"></div>
                            
                            @if (in_array(Auth::user()->role, ['superuser', 'dosen']))
                                <div class="flex flex-row space-x-2 mt-4 sm:mt-0">
                                    <form action="{{ route('bmkg.clear') }}" method="POST" id="clearDataForm">
                                        @csrf
                                        <button type="submit"
                                            class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 hover:scale-105 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-red-500 w-auto shadow-sm">
                                            Clear Data
                                        </button>
                                    </form>
                                    <form action="{{ route('bmkg.fetch') }}" method="POST" id="fetchDataForm">
                                        @csrf
                                        <button type="submit"
                                            class="bg-blue-500 text-white px-8 py-2 rounded-lg hover:bg-blue-600 hover:scale-105 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 font-semibold w-auto whitespace-nowrap shadow-sm">
                                            <i class="fa fa-sync-alt mr-2"></i> Ambil Data Cuaca Terkini
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-scroll pb-6">
                    <table class="w-full align-middle border-slate-400 table mb-0 px-2" id="bmkg-table">
                        <thead>
                            <tr>
                                <th class="dt-center">Waktu (Reference)</th>
                                <th class="dt-center">ID Lahan (Plot ID)</th>
                                <th class="dt-center">Suhu Udara</th>
                                <th class="dt-center">Kelembaban</th>
                                <th class="dt-center">Curah Hujan</th>
                                <th class="dt-center">Kecep. Angin</th>
                                <th class="dt-center">Arah Angin</th>
                                <th class="dt-center">Tekanan Udara</th>
                                @if (in_array(Auth::user()->role, ['superuser', 'dosen']))
                                    <th class="dt-center">Aksi</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0">
                            @foreach ($data as $item)
                                <tr>
                                    <td>{{ $item->reference_time }}</td>
                                    <td>Plot-{{ $item->plot_id }}</td>
                                    <td>{{ $item->air_temperature_bmkg }} &deg;C</td>
                                    <td>{{ $item->air_humidity_bmkg }} %</td>
                                    <td>{{ $item->rainfall_mm }} mm</td>
                                    <td>{{ $item->wind_speed_bmkg }} km/h</td>
                                    <td>{{ $item->wind_direction }}&deg;</td>
                                    <td>{{ $item->air_pressure }} hPa</td>
                                    <!-- Delete button -->
                                    @if (in_array(Auth::user()->role, ['superuser', 'dosen']))
                                        <td>
                                            <form action="{{ route('bmkg.destroy', ['id' => $item->id]) }}"
                                                method="POST" class="delete-form"
                                                data-series="{{ $item->reference_time }}">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit">
                                                    <i class="fa fa-trash text-red-500"></i>
                                                </button>
                                            </form>
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
            $('#bmkg-table').DataTable({
                responsive: true,
                ordering: false,
                dom: '<"hidden"B>rtp',
                buttons: [{
                    extend: 'excel',
                    text: 'Export Excel',
                    title: 'Data Cuaca BMKG',
                    className: 'bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 hover:scale-105 transition-all duration-200'
                }],
                columnDefs: [{
                    className: "text-center",
                    targets: "_all"
                }],
                language: {
                    emptyTable: "Tidak ada data laporan cuaca (BMKG) yang tersedia saat ini.",
                    paginate: {
                        previous: "<",
                        next: ">"
                    }
                }
            });

            $('.dt-buttons').detach().appendTo('#export-btn-container').removeClass('hidden');

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

                    const dataTimestamp = this.getAttribute('data-series');
                    Swal.fire({
                        title: 'Konfirmasi',
                        text: `Apakah Anda yakin ingin menghapus data cuaca pada waktu ${dataTimestamp}?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Hapus!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            this.submit();
                        }
                    });
                });
            });

            document.getElementById('clearDataForm').addEventListener('submit', function(event) {
                event.preventDefault();
                Swal.fire({
                    title: 'Konfirmasi',
                    text: `Apakah Anda yakin ingin menghapus SELURUH data cuaca BMKG?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Hapus Semua!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.submit();
                    }
                });
            });
            document.getElementById('fetchDataForm').addEventListener('submit', function(event) {
            Swal.fire({
                title: 'Memproses...',
                    text: 'Sedang mengambil data dari API OpenWeather',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        });
        });
     
        </script>
    @endpush
</x-app-layout>
