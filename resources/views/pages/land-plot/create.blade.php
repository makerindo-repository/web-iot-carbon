<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="{{ route('master-data.index') }}">Data Master</a></li>
                <li class="breadcrumb-item"><a href="{{ route('land-plot.index') }}">Data Lahan</a></li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Tambah Lahan') }}</li>
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
                    <h1 class="text-3xl font-extrabold mb-4">Tambah Data Lahan</h1>
                    <form action="{{ route('land-plot.store') }}" method="POST">
                        @csrf
                        <input type="hidden" name="polygon" id="polygon" value="{{ old('polygon') }}">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">

                            <div class="w-full">
                                <x-input-label for="plot_code">{{ __('Kode Lahan') }}</x-input-label>
                                <x-text-input id="plot_code" class="block mt-1 w-full rounded-xl" type="text"
                                    name="plot_code" :value="old('plot_code')" required autofocus placeholder="Contoh: LHN-001" />
                                <x-input-error :messages="$errors->get('plot_code')" class="mt-2" />
                            </div>

                            <div class="w-full">
                                <x-input-label for="plot_name">{{ __('Nama Lahan') }}</x-input-label>
                                <x-text-input id="plot_name" class="block mt-1 w-full rounded-xl" type="text"
                                    name="plot_name" :value="old('plot_name')" required placeholder="Contoh: Lahan Singkong" />
                                <x-input-error :messages="$errors->get('plot_name')" class="mt-2" />
                            </div>

                            <div class="w-full md:col-span-2 mt-4">
                                <x-input-label>{{ __('Gambar Area Lahan di Peta') }}</x-input-label>
                                <div id="map" class="mt-2 rounded-xl border border-gray-300"></div>
                                <p class="text-xs text-red-500 mt-1">*Gunakan alat pada sebelah kiri peta untuk menggambar area lahan.</p>
                                <x-input-error :messages="$errors->get('polygon')" class="mt-2" />
                            </div>

                            <div class="w-full">
                                <x-input-label for="latitude">{{ __('Latitude') }}</x-input-label>
                                <x-text-input id="latitude" class="block mt-1 w-full rounded-xl bg-gray-100" type="number" step="any"
                                    name="latitude" :value="old('latitude')" readonly required />
                                <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                            </div>

                            <div class="w-full">
                                <x-input-label for="longitude">{{ __('Longitude') }}</x-input-label>
                                <x-text-input id="longitude" class="block mt-1 w-full rounded-xl bg-gray-100" type="number" step="any"
                                    name="longitude" :value="old('longitude')" readonly required />
                                <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
                            </div>

                            <div class="w-full">
                                <x-input-label for="area_hectare">{{ __('Luas Area / Hektar') }}</x-input-label>
                                <x-text-input id="area_hectare" class="block mt-1 w-full rounded-xl bg-gray-100" type="number" step="0.01"
                                    name="area_hectare" :value="old('area_hectare')" readonly required />
                                <x-input-error :messages="$errors->get('area_hectare')" class="mt-2" />
                            </div>

                            <div class="w-full">
                                <x-input-label for="soil_type">{{ __('Jenis Tanah (Opsional)') }}</x-input-label>
                                <x-text-input id="soil_type" class="block mt-1 w-full rounded-xl" type="text"
                                    name="soil_type" :value="old('soil_type')" placeholder="Contoh: Lempung berpasir" />
                                <x-input-error :messages="$errors->get('soil_type')" class="mt-2" />
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-2">
                            <a href="{{ route('land-plot.index') }}" class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-xl">Batal</a>
                            <x-primary-button>{{ __('Simpan Lahan') }}</x-primary-button>
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


                const map = L.map('map').setView([-0.7893, 113.9213], 5);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition((position) => {
                        map.setView([position.coords.latitude, position.coords.longitude], 15);
                    });
                }

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
                            icon:standardIcon,
                            shapeOptions: {
                                color: '#0f8dedff',
                            }
                        },
                        polyline: false,
                        rectangle: false,
                        circle: false,
                        marker: false,
                        circlemarker: false
                    }
                });
                map.addControl(drawControl);

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
                    areaInput.value = areaHectares.toFixed(3);
                });

                map.on(L.Draw.Event.DELETED, function () {
                    latInput.value = '';
                    lngInput.value = '';
                    areaInput.value = '';
                    polygonInput.value = '';
                });
            });
        </script>
    @endpush
</x-app-layout>
