<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('master-data.index') }}">Data Master</a></li>
                <li class="breadcrumb-item"><a href="{{ route('garden.index') }}">Data Kebun</a></li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Detail Kebun') }}</li>
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
                    <h1 class="text-3xl font-extrabold mb-6">Detail Kebun</h1>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <table class="min-w-full bg-white">
                                <tbody>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold w-1/3">Kode Kebun</td>
                                        <td class="px-6 py-4 border-b">{{ $garden->garden_code }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Nama Kebun</td>
                                        <td class="px-6 py-4 border-b">{{ $garden->garden_name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Lahan Induk</td>
                                        <td class="px-6 py-4 border-b">
                                            @if($garden->landPlot)
                                                <a href="{{ route('land-plot.show', $garden->landPlot->id) }}" class="text-blue-600 hover:underline font-medium">
                                                    <i class="fa fa-map mr-1"></i> {{ $garden->landPlot->plot_name }}
                                                </a>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Luas</td>
                                        <td class="px-6 py-4 border-b">{{ $garden->area_hectare }} Ha</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Jenis Tanah</td>
                                        <td class="px-6 py-4 border-b">
                                            @if($garden->soil_type)
                                                <span class="text-amber-700 font-medium">{{ $garden->soil_type }}</span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Tanaman</td>
                                        <td class="px-6 py-4 border-b">
                                            @if($garden->plant_types)
                                                <span class="text-green-700 font-medium">{{ $garden->plant_types }}</span>
                                            @else
                                                <span class="text-gray-400">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Koordinat</td>
                                        <td class="px-6 py-4 border-b">
                                            <span class="text-sm text-gray-600">{{ $garden->latitude }}, {{ $garden->longitude }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div>
                            <p class="font-semibold mb-2">Lokasi Kebun</p>
                            <div id="map-show" class="rounded-xl border border-gray-300"></div>
                        </div>
                    </div>

                    <div class="mt-6 flex gap-2">
                        <a href="{{ route('garden.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-xl">Kembali</a>
                        <a href="{{ route('garden.edit', $garden->id) }}" class="inline-block text-white font-bold py-2 px-4 rounded-xl shadow-sm transition hover:opacity-90" style="background-color: #2563eb !important;">
                            <i class="fa fa-edit mr-1"></i> Edit Kebun
                        </a>
                        @if($garden->landPlot)
                            <a href="{{ route('land-plot.show', $garden->landPlot->id) }}" class="inline-block text-white font-bold py-2 px-4 rounded-xl shadow-sm transition hover:opacity-90" style="background-color: #16a34a !important;">
                                <i class="fa fa-map mr-1"></i> Ke Lahan Induk
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                let lat = {{ $garden->latitude ?? -6.887 }};
                let lng = {{ $garden->longitude ?? 107.615 }};

                let map = L.map('map-show').setView([lat, lng], 15);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                @if($garden->landPlot && $garden->landPlot->polygon)
                    const parentPoly = @json($garden->landPlot->polygon);
                    const parentGeom = typeof parentPoly === 'string' ? JSON.parse(parentPoly) : parentPoly;
                    L.geoJSON(parentGeom, {
                        style: { color: '#085086', weight: 1, fillOpacity: 0.15, dashArray: '5,5' }
                    }).addTo(map).bindPopup('<b>Lahan:</b> {{ $garden->landPlot->plot_name }}');
                @endif

                @if($garden->polygon)
                    const polygon = @json($garden->polygon);
                    const geometry = typeof polygon === 'string' ? JSON.parse(polygon) : polygon;
                    const layer = L.geoJSON(geometry, {
                        style: { color: '#0fed85', weight: 2, fillOpacity: 0.5 }
                    }).addTo(map);
                    map.fitBounds(layer.getBounds(), { padding: [30, 30] });
                @endif

                setTimeout(() => map.invalidateSize(), 500);
            });
        </script>
    @endpush
</x-app-layout>
