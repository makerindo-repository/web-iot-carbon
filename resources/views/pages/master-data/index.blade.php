<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Data Master') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                @if (in_array(Auth::user()->role, ['superuser', 'dosen']))
                    <a href="{{ route('student.index') }}" class="block">
                        <div
                            class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-row justify-between items-center">
                            <div>
                                <h5 class="text-md text-black font-bold">Data Mahasiswa</h5>
                            </div>
                            <div class="flex items-center">
                                <i class="fa-solid fa-user-graduate p-3 bg-primary text-white rounded-lg"></i>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('media.index') }}" class="block">
                        <div
                            class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-row justify-between items-center">
                            <div>
                                <h5 class="text-md text-black font-bold">Data Media</h5>
                            </div>
                            <div class="flex items-center">
                                <i class="fa-solid fa-seedling p-3 bg-primary text-white rounded-lg"></i>
                            </div>
                        </div>
                    </a>
                @endif
                @if (Auth::user()->role == 'superuser')
                    <a href="{{ route('lecturer.index') }}" class="block">
                        <div
                            class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-row justify-between items-center">
                            <div>
                                <h5 class="text-md text-black font-bold">Data Dosen</h5>
                            </div>
                            <div class="flex items-center">
                                <i class="fa-solid fa-chalkboard-user p-3 bg-primary text-white rounded-lg"></i>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('device.index') }}" class="block">
                        <div
                            class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-row justify-between items-center">
                            <div>
                                <h5 class="text-md text-black font-bold">Data Perangkat</h5>
                            </div>
                            <div class="flex items-center">
                                <i class="fa-solid fa-satellite-dish p-3 bg-primary text-white rounded-lg"></i>
                            </div>
                        </div>
                    </a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
