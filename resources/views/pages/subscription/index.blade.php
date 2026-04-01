<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Manajemen Langganan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                @if (Auth::user()->role == 'superuser')
                    <a href="{{ route('subscription.plan.index') }}" class="block">
                        <div
                            class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-row justify-between items-center">
                            <div>
                                <h5 class="text-md text-black font-bold">Daftar Paket Langganan</h5>
                            </div>
                            <div class="flex items-center">
                                <i class="fa-solid fa-crown p-3 bg-primary text-white rounded-lg"></i>
                            </div>
                        </div>
                    </a>
                    <a href="{{ route('subscription.history.index') }}" class="block">
                        <div
                            class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 flex flex-row justify-between items-center">
                            <div>
                                <h5 class="text-md text-black font-bold">Riwayat Transaksi</h5>
                            </div>
                            <div class="flex items-center">
                                <i class="fa-solid fa-receipt p-3 bg-primary text-white rounded-lg"></i>
                            </div>
                        </div>
                    </a>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
