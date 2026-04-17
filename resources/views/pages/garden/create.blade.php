<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('master-data.index') }}">Data Master</a></li>
                <li class="breadcrumb-item"><a href="{{ route('garden.index') }}">Data Kebun</a></li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Tambah Kebun') }}</li>
            </ol>
        </h2>
    </x-slot>

    @push('styles')
        <style>
            #map {
                height: 480px;
                z-index: 1;
            }
            .leaflet-draw-toolbar a { background-color: white !important; }
        </style>
    @endpush

    <div class="py-12">
        <div class="sm:max-w-7xl flex xl:max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm flex-1 sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Tambah Data Kebun</h1>
                    <form action="{{ route('garden.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="polygon" id="polygon" value="{{ old('polygon') }}">

                        <div class="flex flex-col lg:flex-row gap-6">
                            
                            <div class="lg:w-1/2 w-full flex flex-col">
                                <x-input-label>{{ __('Gambar Area Kebun di Peta') }}</x-input-label>
                                <div id="map" class="mt-2 rounded-xl border border-gray-300 flex-1 min-h-[480px]"></div>
                                <p class="text-xs text-gray-500 mt-2">
                                    <span class="text-blue-500">● Biru Putus-putus: Batas Lahan</span> | 
                                    <span class="text-orange-500">● Oranye: Kebun yang sudah ada</span>
                                </p>
                                <x-input-error :messages="$errors->get('polygon')" class="mt-2" />
                            </div>

                            <div class="lg:w-1/2 w-full flex flex-col gap-4">
                                <div>
                                    <x-input-label for="land_plot_id">{{ __('Pilih Lahan (Induk Kebun)') }}</x-input-label>
                                    <select id="land_plot_id" name="land_plot_id" class="block mt-1 w-full rounded-xl border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        <option value="" disabled selected>-- Pilih Lahan --</option>
                                        @foreach($landPlots as $landPlot)
                                            <option value="{{ $landPlot->id }}" {{ old('land_plot_id') == $landPlot->id ? 'selected' : '' }}>
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
                                            name="garden_code" :value="old('garden_code')" required placeholder="KBN-001" />
                                        <x-input-error :messages="$errors->get('garden_code')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="garden_name">{{ __('Nama Kebun') }}</x-input-label>
                                        <x-text-input id="garden_name" class="block mt-1 w-full rounded-xl" type="text"
                                            name="garden_name" :value="old('garden_name')" required placeholder="Kebun Tomat" />
                                        <x-input-error :messages="$errors->get('garden_name')" class="mt-2" />
                                    </div>
                                </div>

                                <div class="hidden">
                                    <x-input-label for="latitude">{{ __('Latitude') }}</x-input-label>
                                    <x-text-input id="latitude" class="block mt-1 w-full rounded-xl bg-gray-100" type="number" step="any"
                                        name="latitude" :value="old('latitude')" readonly required />
                                </div>

                                <div class="hidden">
                                    <x-input-label for="longitude">{{ __('Longitude') }}</x-input-label>
                                    <x-text-input id="longitude" class="block mt-1 w-full rounded-xl bg-gray-100" type="number" step="any"
                                        name="longitude" :value="old('longitude')" readonly required />
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="area_hectare">{{ __('Luas (Ha)') }}</x-input-label>
                                        <x-text-input id="area_hectare" class="block mt-1 w-full rounded-xl bg-gray-100" type="number" step="0.01"
                                            name="area_hectare" :value="old('area_hectare')" readonly required />
                                    </div>
                                    <div>
                                        <x-input-label for="soil_type">{{ __('Jenis Tanah') }}</x-input-label>
                                        <x-text-input id="soil_type" class="block mt-1 w-full rounded-xl" type="text"
                                            name="soil_type" :value="old('soil_type')" placeholder="Lempung" />
                                    </div>
                                </div>

                                <div>
                                    <x-input-label for="plant_types">{{ __('Data Tanaman (Pisahkan dgn koma)') }}</x-input-label>
                                    <textarea id="plant_types" name="plant_types"
                                        class="block mt-1 w-full border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        rows="2" placeholder="Contoh: Singkong, Jagung">{{ old('plant_types') }}</textarea>
                                    <x-input-error :messages="$errors->get('plant_types')" class="mt-2" />
                                </div>

                                <div class="mt-4 flex justify-end gap-2">
                                    <a href="{{ route('garden.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-xl">Batal</a>
                                    <x-primary-button>{{ __('Simpan Kebun') }}</x-primary-button>
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
            const allLandPlots = @json($landPlots);
            const dropdownLahan = document.getElementById('land_plot_id');
            const latInput = document.getElementById('latitude');
            const lngInput = document.getElementById('longitude');
            const areaInput = document.getElementById('area_hectare');
            const polygonInput = document.getElementById('polygon');
            
            let currentLandLayer = null; 
            let otherGardensLayer = L.layerGroup(); 

            const map = L.map('map').setView([-0.7893, 113.9213], 5);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(map);
            otherGardensLayer.addTo(map);

            dropdownLahan.addEventListener('change', function() {
                const selectedId = this.value;
                const landData = allLandPlots.find(l => l.id == selectedId);

                if (currentLandLayer) map.removeLayer(currentLandLayer);
                otherGardensLayer.clearLayers();

                if (landData) {
                    if (landData.polygon) {
                        try {
                            const geometry = typeof landData.polygon === 'string' ? JSON.parse(landData.polygon) : landData.polygon;
                            currentLandLayer = L.geoJSON(geometry, {
                                style: { color: '#3b82f6', weight: 2, dashArray: '5, 10', fillOpacity: 0.05, interactive: false }
                            }).addTo(map);
                            map.fitBounds(currentLandLayer.getBounds(), { padding: [40, 40] });
                        } catch (e) { console.error("Error parse poligon lahan:", e); }
                    }

                    if (landData.gardens && landData.gardens.length > 0) {
                        landData.gardens.forEach(garden => {
                            if (garden.polygon) {
                                try {
                                    const gGeom = typeof garden.polygon === 'string' ? JSON.parse(garden.polygon) : garden.polygon;
                                    const gLayer = L.geoJSON(gGeom, {
                                        style: { color: '#f59e0b', weight: 2, fillOpacity: 0.2, interactive: false }
                                    });
                                    gLayer.bindTooltip(`Kebun: ${garden.garden_name}`);
                                    otherGardensLayer.addLayer(gLayer);
                                } catch (e) { console.error("Error render kebun lama:", e); }
                            }
                        });
                    }
                }
            });

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

                const bounds = layer.getBounds();
                const center = bounds.getCenter();
                latInput.value = center.lat.toFixed(7);
                lngInput.value = center.lng.toFixed(7);

                const areaSquareMeters = L.GeometryUtil.geodesicArea(latlngs);
                areaInput.value = (areaSquareMeters / 10000).toFixed(3);
            });

            map.on(L.Draw.Event.DELETED, function () {
                latInput.value = lngInput.value = areaInput.value = polygonInput.value = '';
            });
        });
    </script>
    @endpush
</x-app-layout>
