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

    <div class="py-12">
        <div class="sm:max-w-7xl flex xl:max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm flex-1 sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Tambah Data Lahan</h1>
                    <form action="{{ route('land-plot.store') }}" method="POST">
                        @csrf
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

                            <div class="w-full">
                                <x-input-label for="latitude">{{ __('Latitude (Garis Lintang)') }}</x-input-label>
                                <x-text-input id="latitude" class="block mt-1 w-full rounded-xl" type="number" step="any"
                                    name="latitude" :value="old('latitude')" required min="-90" max="90" placeholder="Contoh: -6.1234567" />
                                <x-input-error :messages="$errors->get('latitude')" class="mt-2" />
                            </div>

                            <div class="w-full">
                                <x-input-label for="longitude">{{ __('Longitude (Garis Bujur)') }}</x-input-label>
                                <x-text-input id="longitude" class="block mt-1 w-full rounded-xl" type="number" step="any"
                                    name="longitude" :value="old('longitude')" required min="-180" max="180" placeholder="Contoh: 107.1234567" />
                                <x-input-error :messages="$errors->get('longitude')" class="mt-2" />
                            </div>

                            <div class="w-full">
                                <x-input-label for="area_hectare">{{ __('Luas Area (Hektar)') }}</x-input-label>
                                <x-text-input id="area_hectare" class="block mt-1 w-full rounded-xl" type="number" step="0.01"
                                    name="area_hectare" :value="old('area_hectare')" required min="0" max="999999.99" placeholder="Contoh: 2.50" />
                                <x-input-error :messages="$errors->get('area_hectare')" class="mt-2" />
                            </div>

                            <div class="w-full">
                                <x-input-label for="soil_type">{{ __('Jenis Tanah (Opsional)') }}</x-input-label>
                                <x-text-input id="soil_type" class="block mt-1 w-full rounded-xl" type="text"
                                    name="soil_type" :value="old('soil_type')" placeholder="Contoh: Lempung berpasir" />
                                <x-input-error :messages="$errors->get('soil_type')" class="mt-2" />
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end">
                            <x-primary-button>{{ __('Simpan') }}</x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
