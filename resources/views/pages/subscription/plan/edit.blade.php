<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('subscription.index') }}">Manajemen Langganan</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('subscription.plan.index') }}">Daftar Paket Langganan</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Ubah Data Paket Langganan') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Ubah Data Paket Langganan</h1>
                    <form action="{{ route('subscription.plan.update', $plan->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="name">{{ __('Nama Paket') }}</x-input-label>
                                <x-text-input id="name" class="block mt-1 w-full rounded-xl" type="text"
                                    name="name" :value="$plan->name" required autofocus autocomplete="name" />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="quota">{{ __('Quota') }}</x-input-label>
                                <x-text-input id="quota" class="block mt-1 w-full rounded-xl" type="number"
                                    name="quota" :value="$plan->quota ?? 0" required autofocus autocomplete="quota" min="0" />
                                <x-input-error :messages="$errors->get('quota')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="duration">{{ __('Durasi (Hari)') }}</x-input-label>
                                <x-text-input id="duration" class="block mt-1 w-full rounded-xl" type="number"
                                    name="duration" :value="$plan->duration ?? 0" required autofocus autocomplete="duration" min="0" />
                                <x-input-error :messages="$errors->get('duration')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="price">{{ __('Harga') }}</x-input-label>
                                <x-text-input id="price" class="block mt-1 w-full rounded-xl" type="number"
                                    name="price" :value="$plan->price ?? 0" required autofocus autocomplete="price" min="0" step="100" />
                                <x-input-error :messages="$errors->get('price')" class="mt-2" />
                            </div>
                            <div class="col-span-2 text-right">
                                <x-primary-button class="mt-3 rounded-xl">
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
