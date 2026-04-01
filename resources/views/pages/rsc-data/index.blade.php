<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Data Rapid Soil Checker (RSC)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <a href="{{ route('rsc-data.monitoring.index') }}" class="block">
                    <div
                        class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-row justify-between items-center">
                        <div>
                            <h5 class="text-md text-black font-bold">Raw Data Monitoring</h5>
                        </div>
                        <div class="flex items-center">
                            <i class="fa-solid fa-gauge p-3 bg-primary text-white rounded-lg"></i>
                        </div>
                    </div>
                </a>
                <a href="{{ route('rsc-data.schedule.index', ['type' => 'raw']) }}" class="block">
                    <div
                        class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-row justify-between items-center">
                        <div>
                            <h5 class="text-md text-black font-bold">Raw Data Penjadwalan</h5>
                        </div>
                        <div class="flex items-center">
                            <i class="fa-solid fa-calendar p-3 bg-primary text-white rounded-lg"></i>
                        </div>
                    </div>
                </a>
                <a href="{{ route('rsc-data.filtered-monitoring.index') }}" class="block">
                    <div
                        class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-row justify-between items-center">
                        <div>
                            <h5 class="text-md text-black font-bold">Filtered Data Monitoring</h5>
                        </div>
                        <div class="flex items-center">
                            <i class="fa-solid fa-filter p-3 bg-primary text-white rounded-lg"></i>
                        </div>
                    </div>
                </a>
                <a href="{{ route('rsc-data.schedule.index', ['type' => 'filtered']) }}" class="block">
                    <div
                        class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-row justify-between items-center">
                        <div>
                            <h5 class="text-md text-black font-bold">Filtered Data Penjadwalan</h5>
                        </div>
                        <div class="flex items-center">
                            <i class="fa-solid fa-hourglass-half p-3 bg-primary text-white rounded-lg"></i>
                        </div>
                    </div>
                </a>
                <a href="{{ route('rsc-data.sensor-threshold.index') }}" class="block">
                    <div
                        class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-row justify-between items-center">
                        <div>
                            <h5 class="text-md text-black font-bold">Pengaturan Threshold</h5>
                        </div>
                        <div class="flex items-center">
                            <i class="fa-solid fa-sliders p-3 bg-primary text-white rounded-lg"></i>
                        </div>
                    </div>
                </a>
                <div class="md:col-span-2 px-3 sm:px-0">
                    <p class="text-sm text-gray-500 font-bold">
                        CATATAN:
                    </p>
                    <p class="text-sm text-gray-500">
                        Raw = Data dari sensor langsung ditampilkan di tabel.
                    <p class="text-sm text-gray-500">
                        Filtered = Data dari sensor difilter terlebih dahulu sesuai threshold, lalu hasilnya ditampilkan
                        di tabel.
                    </p>
                    <p class="text-sm text-gray-500">
                        Monitoring = Data akan ditampilkan terus-menerus sesuai interval yang ditentukan.
                    </p>
                    <p class="text-sm text-gray-500">
                        Penjadwalan = Hanya data yang timestamp nya berada di rentang waktu yang ditentukan yang akan
                        ditampilkan.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
