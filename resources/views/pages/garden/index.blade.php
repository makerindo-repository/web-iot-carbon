<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('master-data.index') }}">Data Master</a></li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Data Kebun') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="flex flex-col sm:flex-row sm:justify-between my-5 items-center">
                    <h1 class="text-3xl font-extrabold text-start">Tabel Data Kebun</h1>
                    <a href="{{ route('garden.create') }}"
                        class="bg-primary px-4 py-2 text-white rounded-lg w-auto mt-2 sm:mt-0">Tambah Data</a>
                </div>

                <div class="overflow-x-scroll">
                    <table class="w-full align-middle border-slate-400 table mb-0 mt-3" id="garden-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Kode Kebun</th>
                                <th>Nama Kebun</th>
                                <th>Nama Lahan (Induk)</th>
                                <th>Luas (Ha)</th>
                                <th>Jenis Tanah</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($gardens as $item)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $item->garden_code }}</td>
                                    <td>{{ $item->garden_name }}</td>
                                    <td>{{ $item->landPlot->plot_name ?? '-' }}</td>
                                    <td>{{ $item->area_hectare }} Ha</td>
                                    <td>{{ $item->soil_type ?? '-' }}</td>
                                    <td class="flex space-x-2 items-center">
                                        <a href="{{ route('garden.show', $item->id) }}">
                                            <i class="fa fa-circle-info text-green-500"></i>
                                        </a>
                                        <a href="{{ route('garden.edit', $item->id) }}">
                                            <i class="fa fa-pen text-blue-500 hover:text-green-700"></i>
                                        </a>
                                        <form action="{{ route('garden.destroy', $item->id) }}" method="POST"
                                            class="delete-form" data-name="{{ $item->garden_name }}">
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
                $('#garden-table').DataTable({
                    responsive: true,
                    pageLength: 10,
                    dom: '<"flex flex-col md:flex-row md:justify-between items-center mb-2"Bf>rtip',
                    buttons: [{
                        extend: 'excel',
                        text: 'Export Excel',
                        className: 'bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600',
                        exportOptions: { columns: [0, 1, 2, 3, 4, 5] }
                    }],
                    language: {
                        search: "Cari:",
                        emptyTable: "Belum ada data kebun.",
                        paginate: { previous: "<", next: ">" }
                    },
                    columnDefs: [
                        { targets: [6], orderable: false, searchable: false }
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

            document.querySelectorAll('.delete-form').forEach(form => {
                form.addEventListener('submit', function(event) {
                    event.preventDefault();
                    const name = this.getAttribute('data-name');
                    Swal.fire({
                        title: 'Konfirmasi',
                        text: `Yakin ingin menghapus kebun "${name}"?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Ya, Hapus!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) this.submit();
                    });
                });
            });
        </script>
    @endpush
</x-app-layout>
