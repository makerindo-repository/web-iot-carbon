<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('master-data.index') }}">Data Master</a></li>
                <li class="breadcrumb-item"><a href="{{ route('land-plot.index') }}">Data Lahan</a></li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Detail Lahan') }}</li>
            </ol>
        </h2>
    </x-slot>

    @push('styles')
        <style>
            #map-show { height: 100%; min-height: 350px; z-index: 1; }
        </style>
    @endpush

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-6">Detail Lahan</h1>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <table class="min-w-full bg-white">
                                <tbody>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold w-1/3">Kode Lahan</td>
                                        <td class="px-6 py-4 border-b">{{ $landPlot->plot_code }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Nama Lahan</td>
                                        <td class="px-6 py-4 border-b">{{ $landPlot->plot_name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Luas</td>
                                        <td class="px-6 py-4 border-b">{{ $landPlot->area_hectare }} Ha</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Jenis Tanah</td>
                                        <td class="px-6 py-4 border-b">{{ $landPlot->soil_type ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Tanaman</td>
                                        <td class="px-6 py-4 border-b">
                                            @if($landPlot->plant_types)
                                                <span class="text-green-700 font-medium">{{ $landPlot->plant_types }}</span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Pemilik</td>
                                        <td class="px-6 py-4 border-b">{{ $landPlot->owner_name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Alamat</td>
                                        <td class="px-6 py-4 border-b">{{ $landPlot->address ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Koordinat</td>
                                        <td class="px-6 py-4 border-b">
                                            <span class="text-sm text-gray-600">{{ $landPlot->latitude }}, {{ $landPlot->longitude }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">Lokasi Lahan</p>
                            <div id="map-show" class="rounded-xl border border-gray-300"></div>
                        </div>
                    </div>

                    @if($landPlot->gardens->count() > 0)
                        <div class="mt-8">
                            <h2 class="text-xl font-bold mb-4">
                                Kebun Terdaftar ({{ $landPlot->gardens->count() }})
                            </h2>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white border rounded-xl overflow-hidden">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">No</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Kode</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Nama Kebun</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Luas (Ha)</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Jenis Tanah</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Tanaman</th>
                                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-600">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($landPlot->gardens as $garden)
                                            <tr class="border-t hover:bg-gray-50">
                                                <td class="px-4 py-3 text-sm">{{ $loop->iteration }}</td>
                                                <td class="px-4 py-3 text-sm">{{ $garden->garden_code }}</td>
                                                <td class="px-4 py-3 text-sm font-medium">{{ $garden->garden_name }}</td>
                                                <td class="px-4 py-3 text-sm">{{ $garden->area_hectare }} Ha</td>
                                                <td class="px-4 py-3 text-sm">{{ $garden->soil_type ?? '-' }}</td>
                                                <td class="px-4 py-3 text-sm">{{ $garden->plant_types ?? '-' }}</td>
                                                <td class="px-4 py-3 text-sm">
                                                    <a href="{{ route('garden.show', $garden->id) }}" class="text-blue-600 hover:underline font-medium">
                                                        <i class="fa fa-eye mr-1"></i> Detail
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <div class="mt-6 flex gap-2">
                        <a href="{{ route('land-plot.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-xl">Kembali</a>
                        <a href="{{ route('land-plot.edit', $landPlot->id) }}" class="inline-block text-white font-bold py-2 px-4 rounded-xl shadow-sm transition hover:opacity-90" style="background-color: #2563eb !important;">
                            <i class="fa fa-edit mr-1"></i> Edit Lahan
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                let lat = {{ $landPlot->latitude ?? -6.887 }};
                let lng = {{ $landPlot->longitude ?? 107.615 }};

                let map = L.map('map-show').setView([lat, lng], 15);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                @if($landPlot->polygon)
                    const polygon = @json($landPlot->polygon);
                    const geometry = typeof polygon === 'string' ? JSON.parse(polygon) : polygon;
                    const layer = L.geoJSON(geometry, {
                        style: { color: '#085086', weight: 2, fillOpacity: 0.4 }
                    }).addTo(map);
                    map.fitBounds(layer.getBounds(), { padding: [30, 30] });
                @endif

                // Tampilkan kebun-kebun di atas peta lahan
                @foreach($landPlot->gardens as $garden)
                    @if($garden->polygon)
                        const gardenPoly{{ $garden->id }} = @json($garden->polygon);
                        const gardenGeom{{ $garden->id }} = typeof gardenPoly{{ $garden->id }} === 'string' ? JSON.parse(gardenPoly{{ $garden->id }}) : gardenPoly{{ $garden->id }};
                        L.geoJSON(gardenGeom{{ $garden->id }}, {
                            style: { color: '#000000ff', weight: 2, fillOpacity: 0.5 }
                        }).addTo(map).bindPopup('<b>Kebun:</b> {{ $garden->garden_name }}');
                    @endif
                @endforeach

                setTimeout(() => map.invalidateSize(), 500);
            });
        </script>
    @endpush
</x-app-layout>
