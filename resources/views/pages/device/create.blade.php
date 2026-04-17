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
                <li class="breadcrumb-item breadcrumb-active">{{ __('Tambah Perangkat') }}</li>
            </ol>
        </h2>
    </x-slot>

    @push('styles')
        <style>
            #map { height: 450px; z-index: 1; }
            .grayscale-marker {
                filter: grayscale(100%) opacity(0.8);
            }
        </style>
    @endpush

    <div class="py-12">
        <div class="sm:max-w-7x xl:max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Tambah Data Perangkat</h1>
                    <form action="{{ route('device.store') }}" method="POST">
                        @csrf
                        <div class="flex flex-col lg:flex-row gap-6">
                            
                            <div class="lg:w-1/2 w-full flex flex-col">
                                <x-input-label>{{ __('Pilih Lokasi Perangkat (Klik pada peta)') }}</x-input-label>
                                <div id="map" class="mt-2 rounded-xl border border-gray-300 flex-1 min-h-[400px]"></div>
                                <p class="text-xs text-gray-500 mt-2">
                                    *Klik sekali pada peta untuk meletakkan titik perangkat.<br>
                                    <span class="text-blue-500">● Biru Putus-putus: Area Lahan</span> | 
                                    <span class="text-green-500">● Hijau: Area Kebun</span> |
                                    <span class="text-gray-400">● Abu-abu: Perangkat Terpasang</span>
                                </p>                            
                            </div>

                            <div class="lg:w-1/2 w-full flex flex-col gap-4">
                                <div class="w-full">
                                    <x-input-label for="device_code">{{ __('Device Code') }}</x-input-label>
                                    <x-text-input id="device_code" class="block mt-1 w-full rounded-xl" type="text"
                                        name="device_code" :value="old('device_code')" required autofocus />
                                    <x-input-error :messages="$errors->get('device_code')" class="mt-2" />
                                </div>

                                <div class="w-full">
                                    <x-input-label for="plot_id">{{ __('Pilih Lahan (Opsional)') }}</x-input-label>
                                    <select id="plot_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl" name="plot_id">
                                        <option value="">-- Tanpa Lahan --</option>
                                        @foreach($landPlots as $plot)
                                            <option value="{{ $plot->id }}" {{ old('plot_id') == $plot->id ? 'selected' : '' }}>
                                                {{ $plot->plot_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('plot_id')" class="mt-2" />
                                </div>

                                <div class="w-full">
                                    <x-input-label for="garden_id">{{ __('Pilih Kebun (Opsional)') }}</x-input-label>
                                    <select id="garden_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl" name="garden_id">
                                        <option value="">-- Tanpa Kebun --</option>
                                        @foreach($gardens as $garden)
                                            <option value="{{ $garden->id }}" {{ old('garden_id') == $garden->id ? 'selected' : '' }}>
                                                {{ $garden->garden_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('garden_id')" class="mt-2" />
                                </div>

                                <div class="w-full">
                                    <x-input-label for="firmware_version">{{ __('Versi Firmware') }}</x-input-label>
                                    <x-text-input id="firmware_version" class="block mt-1 w-full rounded-xl" type="text"
                                        name="firmware_version" :value="old('firmware_version')" placeholder="Contoh: v1.0.0" />
                                    <x-input-error :messages="$errors->get('firmware_version')" class="mt-2" />
                                </div>

                                <div class="flex flex-row gap-4 w-full">
                                    <div class="w-1/2">
                                        <x-input-label for="latitude">{{ __('Latitude') }}</x-input-label>
                                        <x-text-input id="latitude" class="block mt-1 w-full bg-gray-100 rounded-xl" type="text"
                                            name="latitude" :value="old('latitude')" readonly required />
                                        <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                                    </div>

                                    <div class="w-1/2">
                                        <x-input-label for="longitude">{{ __('Longitude') }}</x-input-label>
                                        <x-text-input id="longitude" class="block mt-1 w-full bg-gray-100 rounded-xl" type="text"
                                            name="longitude" :value="old('longitude')" readonly required />
                                        <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
                                    </div>
                                </div>
                                
                                <div class="w-full mt-4 flex justify-end gap-2">
                                    <a href="{{ route('device.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-xl">Batal</a>
                                    <x-primary-button>
                                        {{ __('Simpan') }}
                                    </x-primary-button>
                                </div>

                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                const latInput = document.getElementById('latitude');
                const lngInput = document.getElementById('longitude');
                const plotSelect = document.getElementById('plot_id');
                const gardenSelect = document.getElementById('garden_id');

                const allLandPlots = @json($landPlots);
                const allGardens = @json($gardens);
                const existingDevices = @json($existingDevices);

                let map = L.map('map').setView([-0.7893, 113.9213], 5);
                let marker;

                let polygonLayer = L.layerGroup().addTo(map);
                let existingDeviceLayer = L.layerGroup().addTo(map);

                const oldDeviceIcon = L.icon({
                    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                    iconSize: [20, 32], 
                    iconAnchor: [10, 32],
                    className: 'grayscale-marker'
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                if (navigator.geolocation && !latInput.value) {
                    navigator.geolocation.getCurrentPosition((position) => {
                        map.setView([position.coords.latitude, position.coords.longitude], 15);
                    });
                }

                if(latInput.value && lngInput.value) {
                    marker = L.marker([latInput.value, lngInput.value]).addTo(map);
                    map.setView([latInput.value, lngInput.value], 15);
                }

                function updatePolygons() {
                    polygonLayer.clearLayers();
                    existingDeviceLayer.clearLayers();
                    
                    let boundsToFit = L.latLngBounds();
                    let hasBounds = false;

                    const plotId = plotSelect.value;
                    const gardenId = gardenSelect.value;

                    if (plotId) {
                        const land = allLandPlots.find(l => l.id == plotId);
                        if (land && land.polygon) {
                            try {
                                const geom = typeof land.polygon === 'string' ? JSON.parse(land.polygon) : land.polygon;
                                const lLayer = L.geoJSON(geom, {
                                    style: { color: '#3b82f6', weight: 2, dashArray: '5, 10', fillOpacity: 0.05, interactive: false }
                                });
                                polygonLayer.addLayer(lLayer);
                                boundsToFit.extend(lLayer.getBounds());
                                hasBounds = true;
                            } catch(e) { console.error(e); }
                        }
                    }
                    if (gardenId) {
                        const garden = allGardens.find(g => g.id == gardenId);
                        if (garden && garden.polygon) {
                            try {
                                const geom = typeof garden.polygon === 'string' ? JSON.parse(garden.polygon) : garden.polygon;
                                const gLayer = L.geoJSON(geom, {
                                    style: { color: '#10b981', weight: 2, fillOpacity: 0.2, interactive: false }
                                });
                                polygonLayer.addLayer(gLayer);
                                boundsToFit.extend(gLayer.getBounds());
                                hasBounds = true;
                            } catch(e) { console.error(e); }
                        }
                    }
                    existingDevices.forEach(dev => {
                        if (dev.plot_id == plotId || dev.garden_id == gardenId) {
                            if (dev.latitude && dev.longitude) {
                                const oldMarker = L.marker([dev.latitude, dev.longitude], { icon: oldDeviceIcon })
                                    .bindPopup(`<b>Perangkat Terpasang:</b><br>${dev.device_code}`)
                                    .addTo(existingDeviceLayer);
                                
                                boundsToFit.extend(oldMarker.getLatLng());
                                hasBounds = true;
                            }
                        }
                    });

                    if (hasBounds) {
                        map.fitBounds(boundsToFit, { padding: [40, 40] });
                    }
                }

                plotSelect.addEventListener('change', function() {
                    const selectedPlotId = this.value;
                    gardenSelect.value = "";
                    gardenSelect.innerHTML = '<option value="">-- Tanpa Kebun --</option>';
                    if (selectedPlotId) {
                        const filteredGardens = allGardens.filter(g => g.land_plot_id == selectedPlotId);
                        
                        filteredGardens.forEach(garden => {
                            const option = document.createElement('option');
                            option.value = garden.id;
                            option.textContent = garden.garden_name;
                            gardenSelect.appendChild(option);
                        });
                    }
                    updatePolygons();
                });
                gardenSelect.addEventListener('change', updatePolygons);
                
                updatePolygons();

                map.on('click', function(e) {
                    let lat = e.latlng.lat.toFixed(8);
                    let lng = e.latlng.lng.toFixed(8);

                    if (marker) {
                        marker.setLatLng(e.latlng);
                    } else {
                        marker = L.marker(e.latlng).addTo(map);
                    }

                    latInput.value = lat;
                    lngInput.value = lng;
                });
            });
        </script>
    @endpush
</x-app-layout>
