<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('rsc-data.index') }}">Data Rapid Soil Checker (RSC)</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('rsc-data.sensor-threshold.index') }}">Pengaturan Threshold</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Ubah Threshold') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="sm:max-w-7x xl:max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Ubah Data Threshold</h1>
                    <form action="{{ route('rsc-data.sensor-threshold.update', $threshold->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <div class="w-full">
                                <x-input-label for="parameter">{{ __('Parameter') }}</x-input-label>
                                <x-text-input id="parameter" class="block mt-1 w-full rounded-xl bg-gray-300" type="text"
                                    name="parameter" :value="$threshold->parameter ?? ''" disabled autocomplete="parameter" />
                            </div>
                            <div class="w-full">
                                <x-input-label for="min">{{ __('Batas Bawah') }}</x-input-label>
                                <x-text-input id="min" class="block mt-1 w-full rounded-xl" type="number"
                                    name="min" :value="old('min', $threshold->min)" required autofocus autocomplete="min"
                                    step="0.1" min="0" />
                                <x-input-error :messages="$errors->get('min')" class="mt-2" />
                            </div>
                            <div class="w-full">
                                <x-input-label for="max">{{ __('Batas Atas') }}</x-input-label>
                                <x-text-input id="max" class="block mt-1 w-full rounded-xl" type="number"
                                    name="max" :value="old('max', $threshold->max)" required autofocus autocomplete="max"
                                    step="0.1" min="0" />
                                <x-input-error :messages="$errors->get('max')" class="mt-2" />
                            </div>
                            <div class="w-full col-span-1 md:col-span-2 lg:col-span-3 flex justify-end">
                                <x-primary-button>
                                    {{ __('Simpan') }}
                                </x-primary-button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
