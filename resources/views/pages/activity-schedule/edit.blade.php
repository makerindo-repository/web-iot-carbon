<x-app-layout>
    @push('styles')
        <!-- Select2 CSS -->
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    @endpush

    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('activity-schedule.index') }}">Jadwal Kegiatan Praktikum</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Ubah Jadwal Kegiatan Praktikum') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Ubah Jadwal Kegiatan Praktikum</h1>
                    <form action="{{ route('activity-schedule.update', $activitySchedule->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <x-input-label for="date">{{ __('Tanggal') }}</x-input-label>
                                <x-text-input id="date" class="block mt-1 w-full rounded-xl" type="date"
                                    name="date" :value="$activitySchedule->date" required autofocus autocomplete="date" />
                                <x-input-error :messages="$errors->get('date')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="day">{{ __('Hari') }}</x-input-label>
                                <x-text-input id="day" class="block mt-1 w-full rounded-xl" type="text"
                                    name="day" :value="$activitySchedule->day" required readonly autocomplete="day" />
                                <x-input-error :messages="$errors->get('day')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="start_time">{{ __('Waktu Mulai') }}</x-input-label>
                                <x-text-input id="start_time" class="block mt-1 w-full rounded-xl" type="time"
                                    name="start_time" :value="$activitySchedule->start_time" required autofocus autocomplete="start_time" />
                                <x-input-error :messages="$errors->get('start_time')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="end_time">{{ __('Waktu Selesai') }}</x-input-label>
                                <x-text-input id="end_time" class="block mt-1 w-full rounded-xl" type="time"
                                    name="end_time" :value="$activitySchedule->end_time" required autofocus autocomplete="end_time" />
                                <x-input-error :messages="$errors->get('end_time')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="lecturers" class="mb-1">{{ __('Daftar Dosen (pilih satu atau lebih)') }}</x-input-label>
                                <select name="lecturers[]" id="lecturers"
                                    class="block w-full rounded-xl py-1 px-2 select2" multiple required>
                                    @foreach ($lecturers as $lecturer)
                                        <option value="{{ $lecturer->id }}"
                                            {{ collect(old('lecturers', $activitySchedule->lecturers->pluck('id')->toArray()))->contains($lecturer->id) ? 'selected' : '' }}>
                                            {{ $lecturer->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('lecturers')" class="mt-2" />
                            </div>
                            <div>
                                <x-input-label for="students"
                                    class="mb-1">{{ __('Daftar Mahasiswa (pilih satu atau lebih)') }}</x-input-label>
                                <select name="students[]" id="students" class="block w-full rounded-xl select2"
                                    multiple required>
                                    @foreach ($students as $student)
                                        <option value="{{ $student->id }}"
                                            {{ collect(old('students', $activitySchedule->students->pluck('id')->toArray()))->contains($student->id) ? 'selected' : '' }}>
                                            {{ $student->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('students')" class="mt-2" />
                            </div>
                            <div class="col-span-2">
                                <x-input-label for="agenda">{{ __('Agenda') }}</x-input-label>
                                <x-text-input id="agenda" class="block mt-1 w-full rounded-xl" type="text"
                                    name="agenda" :value="$activitySchedule->agenda" required autofocus autocomplete="agenda" />
                                <x-input-error :messages="$errors->get('agenda')" class="mt-2" />
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
        <!-- Select2 JS -->
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <script>
            $(document).ready(function() {
                $('#lecturers').select2({
                    placeholder: 'Pilih Dosen',
                    allowClear: true,
                    width: '100%',
                    language: {
                        noResults: function() {
                            return "Tidak ada data dosen yang ditemukan.";
                        }
                    }
                });

                $('#students').select2({
                    placeholder: 'Pilih Mahasiswa',
                    allowClear: true,
                    width: '100%',
                    language: {
                        noResults: function() {
                            return "Tidak ada data mahasiswa yang ditemukan.";
                        }
                    }
                });

                $('#date').on('change', function() {
                    var date = $(this).val();
                    if (!date) {
                        $('#day').val('');
                        return;
                    }
                    var day = new Date(date).toLocaleDateString('id-ID', {
                        weekday: 'long'
                    });
                    $('#day').val(day);
                });
            });
        </script>
    @endpush
</x-app-layout>
