<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('master-data.index') }}">Data Master</a></li>
                <li class="breadcrumb-item"><a href="{{ route('land-plot.index') }}">Data Lahan</a></li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Edit Lahan') }}</li>
            </ol>
        </h2>
    </x-slot>

    @push('styles')
        <style>
            #map {
                height: 100%;
                min-height: 420px;
                z-index: 1;
            }
        </style>
    @endpush

    <div class="py-12">
        <div class="sm:max-w-7xl flex xl:max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm flex-1 sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Edit Data Lahan</h1>
                    <form action="{{ route('land-plot.update', $landPlot->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="polygon" id="polygon" value="{{ old('polygon', is_string($landPlot->polygon) ? $landPlot->polygon : json_encode($landPlot->polygon)) }}">
                        <input type="hidden" name="latitude" id="latitude" value="{{ old('latitude', $landPlot->latitude) }}">
                        <input type="hidden" name="longitude" id="longitude" value="{{ old('longitude', $landPlot->longitude) }}">

                        <div class="flex flex-col lg:flex-row gap-6">
                            
                            <!-- Kolom Kiri: Peta -->
                            <div class="lg:w-1/2 w-full flex flex-col">
                                <x-input-label>{{ __('Ubah Area Lahan di Peta') }}</x-input-label>
                                <div id="map" class="mt-2 rounded-xl border border-gray-300 flex-1 min-h-[420px]"></div>
                                <p class="text-xs text-red-500 mt-2">*Jika Anda menghapus area polygon, Anda harus menggambar area baru untuk dapat menyimpannya.</p>
                                <x-input-error :messages="$errors->get('polygon')" class="mt-2" />
                            </div>

                            <div class="lg:w-1/2 w-full flex flex-col gap-4">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="plot_name">{{ __('Nama Lahan') }}</x-input-label>
                                        <x-text-input id="plot_name" class="block mt-1 w-full rounded-xl" type="text"
                                            name="plot_name" :value="old('plot_name', $landPlot->plot_name)" required />
                                        <x-input-error :messages="$errors->get('plot_name')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="plot_code">{{ __('Kode Lahan') }}</x-input-label>
                                        <x-text-input id="plot_code" class="block mt-1 w-full rounded-xl" type="text"
                                            name="plot_code" :value="old('plot_code', $landPlot->plot_code)" required />
                                        <x-input-error :messages="$errors->get('plot_code')" class="mt-2" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="area_hectare">{{ __('Luas (Ha)') }}</x-input-label>
                                        <x-text-input id="area_hectare" class="block mt-1 w-full rounded-xl bg-gray-100" type="number" step="0.01"
                                            name="area_hectare" :value="old('area_hectare', $landPlot->area_hectare)" readonly required />
                                        <x-input-error :messages="$errors->get('area_hectare')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="soil_type">{{ __('Jenis Tanah') }}</x-input-label>
                                        <x-text-input id="soil_type" class="block mt-1 w-full rounded-xl" type="text"
                                            name="soil_type" :value="old('soil_type', $landPlot->soil_type)" />
                                        <x-input-error :messages="$errors->get('soil_type')" class="mt-2" />
                                    </div>
                                </div>

                                <div>
                                    <x-input-label for="owner_name">{{ __('Nama Pemilik') }}</x-input-label>
                                    <x-text-input id="owner_name" class="block mt-1 w-full rounded-xl" type="text"
                                        name="owner_name" :value="old('owner_name', $landPlot->owner_name)" placeholder="Nama pemilik lahan" />
                                    <x-input-error :messages="$errors->get('owner_name')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="address">{{ __('Alamat Lengkap') }}</x-input-label>
                                    <textarea id="address" name="address"
                                        class="block mt-1 w-full border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        rows="3" placeholder="Ketik alamat lalu klik di luar, peta akan otomatis bergeser...">{{ old('address', $landPlot->address) }}</textarea>
                                    <x-input-error :messages="$errors->get('address')" class="mt-2" />
                                </div>

                                <div>
                                    <x-input-label for="plant_types">{{ __('Data Tanaman (Pisahkan dgn koma)') }}</x-input-label>
                                    <textarea id="plant_types" name="plant_types"
                                        class="block mt-1 w-full border-gray-300 rounded-xl focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                        rows="2" placeholder="Contoh: Singkong, Jagung">{{ old('plant_types', $landPlot->plant_types) }}</textarea>
                                    <x-input-error :messages="$errors->get('plant_types')" class="mt-2" />
                                </div>


                        <div class="mt-6 flex justify-end gap-2">
                            <a href="{{ route('land-plot.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-xl">Batal</a>
                            <x-primary-button>{{ __('Simpan Perubahan') }}</x-primary-button>
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

                const standardIcon = new L.Icon({
                    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
                    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
                    iconSize: [25, 41],
                    iconAnchor: [12, 41]
                });

                const map = L.map('map').setView([-6.9175, 107.6191], 13);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                const drawnItems = new L.FeatureGroup();
                map.addLayer(drawnItems);

                const drawControl = new L.Control.Draw({
                    edit: {
                        featureGroup: drawnItems,
                        remove: true
                    },
                    draw: {
                        polygon: {
                            allowIntersection: false,
                            showArea: true,
                            icon: standardIcon,
                            shapeOptions: {
                                color: '#10B981'
                            }
                        },
                        polyline: false,
                        rectangle: true,
                        circle: false,
                        marker: false,
                        circlemarker: false
                    }
                });
                map.addControl(drawControl);

                let existingData = polygonInput.value;
                if (existingData && existingData !== 'null' && existingData.trim() !== '') {
                    try {
                        const parsedGeoJSON = JSON.parse(existingData);
                        const layer = L.geoJSON(parsedGeoJSON, {
                            style: { color: '#10B981' }
                        });

                        layer.eachLayer(function(l) {
                            drawnItems.addLayer(l);
                        });

                        map.fitBounds(layer.getBounds());
                    } catch (e) {
                        console.error("Gagal membaca data JSON Polygon:", e);
                    }
                } else if (latInput.value && lngInput.value) {
                    map.setView([latInput.value, lngInput.value], 15);
                }

                map.on(L.Draw.Event.CREATED, function (e) {
                    const layer = e.layer;

                    drawnItems.clearLayers();
                    drawnItems.addLayer(layer);

                    const geojson = layer.toGeoJSON();
                    polygonInput.value = JSON.stringify(geojson.geometry);

                    const bounds = layer.getBounds();
                    const center = bounds.getCenter();
                    latInput.value = center.lat.toFixed(7);
                    lngInput.value = center.lng.toFixed(7);

                    const latlngs = layer.getLatLngs()[0];
                    const areaSquareMeters = L.GeometryUtil.geodesicArea(latlngs);
                    const areaHectares = areaSquareMeters / 10000;
                    areaInput.value = areaHectares.toFixed(2);
                });

                map.on(L.Draw.Event.EDITED, function (e) {
                    const layers = e.layers;
                    layers.eachLayer(function (layer) {
                        const geojson = layer.toGeoJSON();
                        polygonInput.value = JSON.stringify(geojson.geometry);

                        const bounds = layer.getBounds();
                        const center = bounds.getCenter();
                        latInput.value = center.lat.toFixed(7);
                        lngInput.value = center.lng.toFixed(7);

                        const latlngs = layer.getLatLngs()[0];
                        const areaHectares = L.GeometryUtil.geodesicArea(latlngs) / 10000;
                        areaInput.value = areaHectares.toFixed(2);
                    });
                });

                map.on(L.Draw.Event.DELETED, function () {
                    latInput.value = '';
                    lngInput.value = '';
                    areaInput.value = '';
                    polygonInput.value = '';
                });

                document.getElementById('address').addEventListener('blur', function() {
                    const query = this.value;
                    if (query.length > 5) {
                        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=id&limit=1`)
                            .then(res => res.json())
                            .then(data => {
                                if (data.length > 0) {
                                    map.flyTo([data[0].lat, data[0].lon], 15);
                                }
                            })
                            .catch(err => console.error('Geocoding error:', err));
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
