<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('master-data.index') }}">Data Master</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Data Lahan') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="flex flex-col sm:flex-row sm:justify-between my-5 items-center">
                    <h1 class="text-3xl font-extrabold text-start">Tabel Data Lahan</h1>
                    <a href="{{ route('land-plot.create') }}"
                        class="bg-primary px-4 py-2 text-white rounded-lg w-auto mt-2 sm:mt-0">Tambah Data</a>
                </div>
                
                <div class="overflow-x-scroll">
                    <table class="w-full align-middle border-slate-400 table mb-0 mt-3" id="land-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Lahan</th>
                                <th>Nama Lahan</th>
                                <th>Luas (Ha)</th>
                                <th>Jenis Tanah</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($landPlots as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $item->plot_code }}</td>
                                    <td>{{ $item->plot_name }}</td>
                                    <td>{{ $item->area_hectare }} Ha</td>
                                    <td>{{ $item->soil_type ?? '-' }}</td>
                                    <td class="flex space-x-2 items-center">
                                        <a href="{{ route('land-plot.show', $item->id) }}">
                                            <i class="fa fa-circle-info text-green-500"></i>
                                        </a>
                                        <a href="{{ route('land-plot.edit', $item->id) }}">
                                            <i class="fa fa-pen text-blue-500 hover:text-blue-700"></i>
                                        </a>
                                        <form action="{{ route('land-plot.destroy', $item->id) }}" method="POST"
                                            class="delete-form" data-name="{{ $item->plot_name }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit">
                                                <i class="fa fa-trash text-red-500 hover:text-red-700"></i>
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
            $(document).ready(function() {
                $('#land-table').DataTable({
                    responsive: true,
                    pageLength: 10,
                    dom: '<"flex flex-col md:flex-row md:justify-between items-center mb-2"Bf>rtip',
                    buttons: [{
                        extend: 'excel',
                        text: 'Export Excel',
                        className: 'bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600',
                        exportOptions: { columns: [0, 1, 2, 3, 4] }
                    }],
                    language: {
                        search: "Cari:",
                        emptyTable: "Belum ada data lahan.",
                        paginate: { previous: "<", next: ">" }
                    },
                    columnDefs: [
                        { targets: [5], orderable: false, searchable: false }
                    ]
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

            // Peringatan Hapus Data
            document.querySelectorAll('.delete-form').forEach(form => {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    const name = this.getAttribute('data-name');
                    Swal.fire({
                        title: 'Konfirmasi',
                        text: `Yakin ingin menghapus lahan ${name}? (Kebun di dalamnya juga akan terhapus!)`,
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
