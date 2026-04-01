<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('rsc-data.index') }}">Data Rapid Soil Checker</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ $type == 'raw' ? __('Raw Penjadwalan') : __('Filtered Penjadwalan') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-2 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @forelse ($schedules as $schedule)
                    @php
                        $start = \Carbon\Carbon::parse($schedule->date . ' ' . $schedule->start_time);
                        $end = \Carbon\Carbon::parse($schedule->date . ' ' . $schedule->end_time);
                        $now = now();
                        $status = $now < $start ? 'Belum Mulai' : ($now > $end ? 'Selesai' : 'Berlangsung');
                        $color = match ($status) {
                            'Belum Mulai' => 'text-red-600',
                            'Berlangsung' => 'text-yellow-600',
                            'Selesai' => 'text-green-600',
                        };
                    @endphp

                    <div
                        class="flex flex-col bg-white rounded-xl shadow-md px-3 py-4 border justify-between border-gray-200 h-100">
                        <div class="flex flex-col">
                            <div class="font-bold text-2xl">{{ $schedule->agenda }}</div>
                            <div class="text-base text-gray-500 mt-2">
                                {{ \Carbon\Carbon::parse($schedule->date)->translatedFormat('l, d F Y') }}
                            </div>
                            <div class="text-base text-gray-600">{{ $start->translatedFormat('H:i') }} -
                                {{ $end->translatedFormat('H:i') }} WIB</div>
                        </div>

                        <div class="flex justify-between items-center">
                            <div class="mt-2 font-semibold {{ $color }}">
                                {{ $status }}
                            </div>
                            @if ($status != 'Belum Mulai')
                                <a href="{{ $type == 'raw' ? route('rsc-data.schedule.show', $schedule->id) : route('rsc-data.filtered-schedule.show', $schedule->id) }}"
                                    class="inline-block mt-3 ms-auto px-4 py-2 bg-blue-500 text-white text-sm rounded hover:bg-blue-700">
                                    Lihat Data
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-1 md:col-span-2 lg:col-span-3 text-center">
                        <p class="text-xl font-semibold">Belum ada jadwal yang dibuat.</p>
                    </div>
                    <div class="col-span-1 md:col-span-2 lg:col-span-3 text-center">
                        <a href="{{ route('activity-schedule.create') }}" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-700">Buat Jadwal</a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
