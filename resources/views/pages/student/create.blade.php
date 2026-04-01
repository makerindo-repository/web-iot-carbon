<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('master-data.index') }}">Data Master</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('student.index') }}">Data Mahasiswa</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Tambah Data Mahasiswa ') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Tambah Data Mahasiswa</h1>
                    <form action="{{ route('student.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="col-span-2">
                                <x-input-label for="photo">{{ __('Foto') }}</x-input-label>
                                <div class="mt-1">
                                    <div class="relative">
                                        <!-- Preview Container -->
                                        <div id="imagePreviewContainer"
                                            class="w-56 h-56 border-2 border-dashed border-gray-300 rounded-xl flex items-center justify-center cursor-pointer hover:border-gray-400 transition-colors">
                                            <div id="imagePreviewContent" class="text-center">
                                                <i
                                                    class="fas fa-cloud-upload-alt mx-auto text-5xl text-gray-400 mb-2"></i>
                                                <p class="mt-2 text-sm text-gray-600">Klik untuk memilih foto</p>
                                                <p class="text-xs text-gray-500">PNG, JPG, GIF hingga 2MB</p>
                                            </div>
                                            <img id="imagePreview" class="hidden w-full h-full object-cover rounded-xl"
                                                alt="Preview">
                                        </div>

                                        <!-- Transparent File Input -->
                                        <input id="photo"
                                            class="opacity-0 inset-0 absolute w-full h-full cursor-pointer"
                                            type="file" name="photo" accept="image/*" required>
                                    </div>
                                </div>
                                <x-input-error :messages="$errors->get('photo')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="name">{{ __('Nama') }}</x-input-label>
                                <x-text-input id="name" class="block mt-1 w-full rounded-xl" type="text"
                                    name="name" :value="old('name')" required autofocus autocomplete="name" />
                                <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="nim">{{ __('NIM') }}</x-input-label>
                                <x-text-input id="nim" class="block mt-1 w-full rounded-xl" type="text"
                                    name="nim" :value="old('nim')" required autofocus autocomplete="name" />
                                <x-input-error :messages="$errors->get('nim')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="gender">{{ __('Jenis Kelamin') }}</x-input-label>
                                <label class="flex items-center gap-2 mt-1">
                                    <x-text-input id="gender" class="block mt-1 rounded-xl" type="radio"
                                        name="gender" value="l" :checked="old('gender') == 'l'" required />
                                    <span>Pria</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <x-text-input id="gender" class="block mt-1 rounded-xl" type="radio"
                                        name="gender" value="p" :checked="old('gender') == 'p'" required />
                                    <span>Wanita</span>
                                </label>

                                <x-input-error :messages="$errors->get('gender')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="email">{{ __('Email') }}</x-input-label>
                                <x-text-input id="email" class="block mt-1 w-full rounded-xl" type="email"
                                    name="email" :value="old('email')" required autofocus autocomplete="email" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="password">{{ __('Password') }}</x-input-label>
                                <x-text-input id="password" class="block mt-1 w-full rounded-xl" type="password"
                                    name="password" required autofocus />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label
                                    for="password_confirmation">{{ __('Konfirmasi Password') }}</x-input-label>
                                <x-text-input id="password_confirmation" class="block mt-1 w-full rounded-xl"
                                    type="password" name="password_confirmation" required autofocus />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="major">{{ __('Jurusan') }}</x-input-label>
                                <x-text-input id="major" class="block mt-1 w-full rounded-xl" type="text"
                                    name="major" :value="old('major')" required autofocus autocomplete="major" />
                                <x-input-error :messages="$errors->get('major')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="semester">{{ __('Semester') }}</x-input-label>
                                <x-text-input id="semester" class="block mt-1 w-full rounded-xl" type="number"
                                    min="1" name="semester" :value="old('semester')" required autofocus
                                    autocomplete="semester" />
                                <x-input-error :messages="$errors->get('semester')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="address">{{ __('Alamat') }}</x-input-label>
                                <textarea id="address" class="block mt-1 w-full rounded-xl" name="address" rows="3" required>{{ old('address') }}</textarea>
                                <x-input-error :messages="$errors->get('address')" class="mt-2" />
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
            document.addEventListener('DOMContentLoaded', function() {
                const fileInput = document.getElementById('photo');
                const imagePreview = document.getElementById('imagePreview');
                const imagePreviewContent = document.getElementById('imagePreviewContent');

                fileInput.addEventListener('change', function(e) {
                    const file = e.target.files[0];

                    if (file) {
                        const reader = new FileReader();

                        reader.onload = function(e) {
                            imagePreview.src = e.target.result;
                            imagePreview.classList.remove('hidden');
                            imagePreviewContent.classList.add('hidden');
                        };

                        reader.readAsDataURL(file);
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
