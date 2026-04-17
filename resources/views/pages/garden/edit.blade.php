<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('master-data.index') }}">Data Master</a></li>
                <li class="breadcrumb-item"><a href="{{ route('garden.index') }}">Data Kebun</a></li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Edit Kebun') }}</li>
            </ol>
        </h2>
    </x-slot>

    @push('styles')
        <style>
            #map {
                height: 450px;
                z-index: 1;
            }
        </style>
    @endpush

    <div class="py-12">
        <div class="sm:max-w-7xl flex xl:max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm flex-1 sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Edit Data Kebun</h1>
                    <form action="{{ route('garden.update', $garden->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="polygon" id="polygon" value="{{ old('polygon', is_string($garden->polygon) ? $garden->polygon : json_encode($garden->polygon)) }}">

                        <div class="flex flex-col lg:flex-row gap-6">
                            
                            <div class="lg:w-1/2 w-full flex flex-col">
                                <x-input-label>{{ __('Ubah Area Kebun di Peta') }}</x-input-label>
                                <div id="map" class="mt-2 rounded-xl border border-gray-300 flex-1 min-h-[450px]"></div>
                                <p class="text-xs text-red-500 mt-2">*Biru putus-putus adalah batas Lahan. Hijau/Biru solid adalah area Kebun.</p>
                                <x-input-error :messages="$errors->get('polygon')" class="mt-2" />
                            </div>

                            <div class="lg:w-1/2 w-full flex flex-col gap-4">
                                <div>
                                    <x-input-label for="land_plot_id">{{ __('Pilih Lahan (Induk Kebun)') }}</x-input-label>
                                    <select id="land_plot_id" name="land_plot_id" class="block mt-1 w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="" disabled>-- Pilih Lahan --</option>
                                        @foreach($landPlots as $landPlot)
                                            <option value="{{ $landPlot->id }}" {{ (old('land_plot_id', $garden->land_plot_id) == $landPlot->id) ? 'selected' : '' }}>
                                                {{ $landPlot->plot_name }} ({{ $landPlot->plot_code }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('land_plot_id')" class="mt-2" />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="garden_code">{{ __('Kode Kebun') }}</x-input-label>
                                        <x-text-input id="garden_code" class="block mt-1 w-full rounded-xl" type="text"
                                            name="garden_code" :value="old('garden_code', $garden->garden_code)" required />
                                        <x-input-error :messages="$errors->get('garden_code')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="garden_name">{{ __('Nama Kebun') }}</x-input-label>
                                        <x-text-input id="garden_name" class="block mt-1 w-full rounded-xl" type="text"
                                            name="garden_name" :value="old('garden_name', $garden->garden_name)" required />
                                        <x-input-error :messages="$errors->get('garden_name')" class="mt-2" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="latitude">{{ __('Latitude') }}</x-input-label>
                                        <x-text-input id="latitude" class="block mt-1 w-full rounded-xl bg-gray-100" type="number" step="any"
                                            name="latitude" :value="old('latitude', $garden->latitude)" readonly required />
                                        <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="longitude">{{ __('Longitude') }}</x-input-label>
                                        <x-text-input id="longitude" class="block mt-1 w-full rounded-xl bg-gray-100" type="number" step="any"
                                            name="longitude" :value="old('longitude', $garden->longitude)" readonly required />
                                        <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="area_hectare">{{ __('Luas (Ha)') }}</x-input-label>
                                        <x-text-input id="area_hectare" class="block mt-1 w-full rounded-xl bg-gray-100" type="number" step="0.01"
                                            name="area_hectare" :value="old('area_hectare', $garden->area_hectare)" readonly required />
                                        <x-input-error :messages="$errors->get('area_hectare')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="soil_type">{{ __('Jenis Tanah') }}</x-input-label>
                                        <x-text-input id="soil_type" class="block mt-1 w-full rounded-xl" type="text"
                                            name="soil_type" :value="old('soil_type', $garden->soil_type)" />
                                        <x-input-error :messages="$errors->get('soil_type')" class="mt-2" />
                                    </div>
                                </div>

                                <div>
                                    <x-input-label for="plant_types">{{ __('Data Tanaman (Pisahkan dgn koma)') }}</x-input-label>
                                    <textarea id="plant_types" name="plant_types"
                                        class="block mt-1 w-full border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        rows="2" placeholder="Contoh: Singkong, Jagung">{{ old('plant_types', $garden->plant_types) }}</textarea>
                                    <x-input-error :messages="$errors->get('plant_types')" class="mt-2" />
                                </div>
                                <div class="mt-6 flex justify-end gap-2">
                                    <a href="{{ route('garden.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-xl">Batal</a>
                                    <x-primary-button>{{ __('Simpan Perubahan') }}</x-primary-button>
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
                const areaInput = document.getElementById('area_hectare');
                const polygonInput = document.getElementById('polygon');
                const allLandPlots = @json($landPlots);
                const dropdownLahan = document.getElementById('land_plot_id');
                
                let currentLandLayer = null; 

                const map = L.map('map').setView([-0.7893, 113.9213], 5);
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                const drawnItems = new L.FeatureGroup();
                map.addLayer(drawnItems);

                const drawControl = new L.Control.Draw({
                    edit: { featureGroup: drawnItems, remove: true },
                    draw: {
                        polygon: { 
                            allowIntersection: true, 
                            showArea: false, 
                            shapeOptions: { color: '#10b981' } 
                        },
                        polyline: false, rectangle: true, circle: false, marker: false, circlemarker: false
                    }
                });
                map.addControl(drawControl);

                let existingData = polygonInput.value;
                if (existingData && existingData !== 'null' && existingData.trim() !== '') {
                    try {
                        const parsedGeoJSON = JSON.parse(existingData);
                        const layer = L.geoJSON(parsedGeoJSON, {
                            style: { color: '#10b981' }
                        });
                        layer.eachLayer(function(l) { drawnItems.addLayer(l); });
                        map.fitBounds(layer.getBounds(), { padding: [20, 20] });
                    } catch (e) {
                        console.error("Gagal membaca data JSON Polygon Kebun:", e);
                    }
                }

                function showLandPolygon(id) {
                    const landData = allLandPlots.find(l => l.id == id);
                    if (currentLandLayer) map.removeLayer(currentLandLayer);
                    
                    if (landData && landData.polygon) {
                        try {
                            const geometry = typeof landData.polygon === 'string' ? JSON.parse(landData.polygon) : landData.polygon;
                            currentLandLayer = L.geoJSON(geometry, {
                                style: { 
                                    color: '#3b82f6', 
                                    weight: 2, 
                                    dashArray: '5, 10', 
                                    fillOpacity: 0.1, 
                                    interactive: false 
                                }
                            }).addTo(map);
                            
                            map.fitBounds(currentLandLayer.getBounds(), { padding: [50, 50] });
                        } catch (e) { console.error("Gagal load poligon lahan:", e); }
                    }
                }

                if (dropdownLahan.value) { showLandPolygon(dropdownLahan.value); }

                dropdownLahan.addEventListener('change', function() { showLandPolygon(this.value); });

                map.on(L.Draw.Event.CREATED, function (e) {
                    const layer = e.layer;
                    const latlngs = layer.getLatLngs()[0];
                    if (latlngs.length < 3) {
                        alert("Peringatan: Area Kebun harus mempunyai minimal 3 titik sudut!");
                        return;
                    }
                    drawnItems.clearLayers();
                    drawnItems.addLayer(layer);
                    const geojson = layer.toGeoJSON();
                    polygonInput.value = JSON.stringify(geojson.geometry);
                    updateInputs(layer);
                });

                map.on(L.Draw.Event.EDITED, function (e) {
                    e.layers.eachLayer(function (layer) { updateInputs(layer); });
                });

                map.on(L.Draw.Event.DELETED, function () {
                    latInput.value = lngInput.value = areaInput.value = polygonInput.value = '';
                });

                function updateInputs(layer) {
                    const geojson = layer.toGeoJSON();
                    polygonInput.value = JSON.stringify(geojson.geometry);
                    const center = layer.getBounds().getCenter();
                    latInput.value = center.lat.toFixed(7);
                    lngInput.value = center.lng.toFixed(7);
                    const area = L.GeometryUtil.geodesicArea(layer.getLatLngs()[0]);
                    areaInput.value = (area / 10000).toFixed(3);
                }
            });
        </script>
    @endpush
</x-app-layout>
