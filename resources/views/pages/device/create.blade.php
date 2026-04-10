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
            #map { height: 400px; z-index: 1; }
        </style>
    @endpush

    <div class="py-12">
        <div class="sm:max-w-7x xl:max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Tambah Data Perangkat</h1>
                    <form action="{{ route('device.store') }}" method="POST">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            
                            <div class="w-full">
                                <x-input-label for="device_code">{{ __('Device Code') }}</x-input-label>
                                <x-text-input id="device_code" class="block mt-1 w-full rounded-xl" type="text"
                                    name="device_code" :value="old('device_code')" required autofocus />
                                <x-input-error :messages="$errors->get('device_code')" class="mt-2" />
                            </div>

                            <div class="w-full">
                                <x-input-label for="plot_id">{{ __('Pilih Lahan (Plot)') }}</x-input-label>
                                <select id="plot_id" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-xl" name="plot_id" required>
                                    <option value="">-- Pilih Lahan --</option>
                                    @foreach($landPlots as $plot)
                                        <option value="{{ $plot->id }}" {{ old('plot_id') == $plot->id ? 'selected' : '' }}>
                                            {{ $plot->plot_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('plot_id')" class="mt-2" />
                            </div>

                            <div class="w-full md:col-span-2 mt-4">
                                <x-input-label>{{ __('Pilih Lokasi Perangkat (Klik pada peta)') }}</x-input-label>
                                <div id="map" class="mt-2 rounded-xl border border-gray-300"></div>
                                <p class="text-xs text-gray-500 mt-1">*Klik tahan di peta untuk menggeser, klik sekali untuk meletakkan marker.</p>
                            </div>

                            <div class="w-full">
                                <x-input-label for="latitude">{{ __('Latitude (Terisi otomatis)') }}</x-input-label>
                                <x-text-input id="latitude" class="block mt-1 w-full bg-gray-100 rounded-xl" type="text"
                                    name="latitude" :value="old('latitude')" readonly required />
                                <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                            </div>

                            <div class="w-full">
                                <x-input-label for="longitude">{{ __('Longitude (Terisi otomatis)') }}</x-input-label>
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
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <!-- Load Leaflet JS -->
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
        <script>
            document.addEventListener("DOMContentLoaded", () => {
                // Inisialisasi Peta 
                let map = L.map('map').setView([-6.887, 107.615], 13);
                let marker;

                // Tambahkan Tile Layer (Peta dasar)
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '© OpenStreetMap'
                }).addTo(map);

                // Elemen input
                const latInput = document.getElementById('latitude');
                const lngInput = document.getElementById('longitude');

                if(latInput.value && lngInput.value) {
                    marker = L.marker([latInput.value, lngInput.value]).addTo(map);
                    map.setView([latInput.value, lngInput.value], 15);
                }

                // Event ketika peta diklik
                map.on('click', function(e) {
                    let lat = e.latlng.lat.toFixed(8);
                    let lng = e.latlng.lng.toFixed(8);

                    // Pindahkan atau buat marker
                    if (marker) {
                        marker.setLatLng(e.latlng);
                    } else {
                        marker = L.marker(e.latlng).addTo(map);
                    }

                    // Isi otomatis input form
                    latInput.value = lat;
                    lngInput.value = lng;
                });
            });
        </script>
    @endpush
</x-app-layout>
