<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('master-data.index') }}">Data Master</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('media.index') }}">Data Media</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Ubah Data Media ') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Ubah Data Media</h1>
                    <form action="{{ route('media.update', $media->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="form-group">
                            <div>
                                <x-input-label for="name">{{ __('Nama') }}</x-input-label>
                                <x-text-input id="name" class="block mt-1 w-full rounded-xl" type="text"
                                    name="name" :value="$media->name ?? ''" required autofocus autocomplete="name" />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            @foreach ($media->plants as $i => $plant)
                                <div>
                                    <x-input-label for="plants-{{ $i }}">Tanaman
                                        {{ $i + 1 }}</x-input-label>
                                    <x-text-input id="plants-{{ $i }}" name="plants[]" :value="$plant"
                                        class="block mt-1 w-full rounded-xl" required autofocus autocomplete="plants" />
                                </div>
                            @endforeach

                            <template id="template-plants">
                                <div>
                                    <x-input-label for="plants">{{ __('Tanaman') }}</x-input-label>
                                    <x-text-input id="plants" class="block mt-1 w-full rounded-xl" type="text"
                                        name="plants[]" value="" required autofocus autocomplete="plants" />
                                    <x-input-error :messages="$errors->get('plants')" class="mt-2" />
                                </div>
                            </template>

                            <div class="col-span-2 text-right" id="add-plants">
                                <button type="button" onclick="tambahTanaman()"
                                    class="px-3 py-1 bg-green-500 text-white rounded-lg text-sm hover:bg-green-600">
                                    + Tambah Tanaman
                                </button>
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

    @push('scripts')
        <script>
            const tambahTanaman = () => {
                const group = document.getElementById('form-group');
                const addPlantsBtn = document.getElementById('add-plants');
                const plantsTemplate = document.getElementById('template-plants');
                const clone = plantsTemplate.content.cloneNode(true);
                // hitung jumlah input yang sudah ada
                const count = group.querySelectorAll('input[name="plants[]"]').length;

                // ambil elemen label dan input dari clone
                const label = clone.querySelector('label');
                const input = clone.querySelector('input');

                // update text label, for, dan id input
                label.innerText = `Tanaman ${count + 1}`;
                label.setAttribute('for', `plants-${count}`);
                input.id = `plants-${count}`;
                group.insertBefore(clone, addPlantsBtn);
            }
        </script>
    @endpush
</x-app-layout>
