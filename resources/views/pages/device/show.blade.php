<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('master-data.index') }}">Data Master</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('device.index') }}">Data Perangkat</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Lihat Detail Perangkat') }}</li>
            </ol>
        </h2>
    </x-slot>

    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
        <style>
            #map-show { height: 350px; z-index: 1; }
        </style>
    @endpush

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Detail Perangkat</h1>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <table class="min-w-full bg-white">
                                <tbody>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Device Code</td>
                                        <td class="px-6 py-4 border-b">{{ $device->device_code }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Lahan</td>
                                        <td class="px-6 py-4 border-b">{{ $device->landPlot->name ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Latitude</td>
                                        <td class="px-6 py-4 border-b">{{ $device->latitude }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Longitude</td>
                                        <td class="px-6 py-4 border-b">{{ $device->longitude }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Status</td>
                                        <td class="px-6 py-4 border-b">{{ $device->device_status ?? 'offline' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b font-semibold">Terakhir Online</td>
                                        <td class="px-6 py-4 border-b">
                                            {{ $device->last_seen_at ? \Carbon\Carbon::parse($device->last_seen_at)->translatedFormat('d F Y H:i') : 'Belum pernah online' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div>
                            <p class="font-semibold mb-2">Lokasi Perangkat</p>
                            <div id="map-show" class="rounded-xl border border-gray-300"></div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <a href="{{ route('device.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-xl">Kembali</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                let lat = {{ $device->latitude ?? -6.887 }};
                let lng = {{ $device->longitude ?? 107.615 }};
                let hasCoords = {{ ($device->latitude && $device->longitude) ? 'true' : 'false' }};

                let map = L.map('map-show').setView([lat, lng], hasCoords ? 15 : 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                if (hasCoords) {
                    L.marker([lat, lng]).addTo(map)
                        .bindPopup('{{ $device->device_code }}')
                        .openPopup();
                }
            });
        </script>
    @endpush
</x-app-layout>
