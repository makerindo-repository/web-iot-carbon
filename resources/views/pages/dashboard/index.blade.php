<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>
    @push('styles')
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    @endpush

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                @if (in_array(Auth::user()->role, ['dosen', 'superuser']))
                    <x-card-summary href="{{ route('student.index') }}" title="Total Mahasiswa" total="{{ $students }}"
                        icon="fa-solid fa-user-graduate" />
                @endif
                @if (Auth::user()->role == 'superuser')
                    <x-card-summary href="{{ route('lecturer.index') }}" title="Total Dosen" total="{{ $lecturers }}"
                        icon="fa-solid fa-chalkboard-user" />
                    <x-card-summary href="{{ route('device.index') }}" title="Total Perangkat" total="{{ $devices }}"
                        icon="fa-solid fa-satellite-dish" />
                @endif
                <x-card-summary href="{{ route('activity-schedule.index') }}" title="Total Jadwal Kegiatan Praktikum"
                    total="{{ $activitySchedules }}" icon="fa-solid fa-calendar-days" />
            </div>

            <div class="bg-white rounded-xl shadow p-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-calendar text-blue-500"></i>
                    Jadwal Kegiatan Mendatang
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @forelse ($upcomingActivities as $activity)
                        <div class="mb-4 border border-gray-200 p-4 rounded-lg">
                            <div class="text-xl font-semibold text-gray-900">{{ $activity->agenda }}</div>
                            <div class="text-base text-gray-500 mt-2">
                                {{ \Carbon\Carbon::parse($activity->date)->format('d M Y') }}<br>
                                {{ substr($activity->start_time, 0, 5) }} - {{ substr($activity->end_time, 0, 5) }}
                            </div>
                            <div class="mt-5 text-blue-600 font-bold text-xl countdown"
                                data-start="{{ \Carbon\Carbon::parse($activity->date . ' ' . $activity->start_time)->toIso8601String() }}">
                                Memuat countdown...
                            </div>
                        </div>
                    @empty
                        <div class="text-gray-500">Belum ada kegiatan terjadwal.</div>
                    @endforelse
                </div>
            </div>

            <!-- Spacer Fisik 25px -->
            <div style="height: 25px;"></div>

            <div class="bg-white rounded-xl shadow mb-12 overflow-hidden border border-gray-100">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 px-6 py-3 bg-white border-b border-gray-100">
                    <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                        <i class="fa-solid fa-map-location-dot text-blue-500"></i>
                        Peta
                    </h2>
                    
                    <!-- Search Bar Peta (Biru Solid Langsung) -->
                    <div class="flex-none flex shadow-sm rounded-lg overflow-hidden border border-gray-300">
                        <input type="text" id="map-search" placeholder="Cari..." 
                            class="px-3 py-1 text-xs border-none focus:ring-0 w-32 sm:w-40 bg-white">
                        <button id="btn-search" class="text-white px-3 py-1 transition-none" style="background-color: #2563eb !important;">
                            <i class="fa fa-search text-xs text-white"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Peta dengan Padding p-4 (Paling Aman) -->
                <div class="p-4">
                    <x-maps.leaflet 
                        :landPlots="$landPlots" 
                        :gardens="$gardens" 
                        :deviceLocations="$deviceLocations" 
                    />
                </div>
            </div>

            <!-- Spacer Fisik 25px -->
            <div style="height: 25px;"></div>

            <div class="bg-white rounded-xl shadow mb-6 overflow-hidden">
                <div class="p-8 pb-0">
                    <h2 class="text-xl font-bold text-gray-800 flex items-center gap-3 mt-2 ml-2">
                        <i class="fa-solid fa-chart-line text-blue-600"></i>
                        Grafik Telemetri 24 Jam Terakhir
                    </h2>
                </div>
                
                <!-- Grid 2 Kolom dengan Jarak Atas yang Sangat Luas (mt-32) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-16 gap-y-12 px-10 pb-12 mt-32">
                    <div id="co2Chart" class="w-full"></div>
                    <div id="socChart" class="w-full"></div>
                    <div id="cfChart" class="w-full"></div>
                </div>
            </div>

        </div>
    </div>
    @push('scripts')
        <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
        <script>
            // data for chart
            const co2Data = @json($co2);
            const socData = @json($soc);
            const cfData = @json($cf);

            const chartColors = [
                '#42a5f5', // biru
                '#66bb6a', // hijau
                '#ffa726', // oranye
                '#ab47bc', // ungu
                '#ef5350', // merah
                '#26c6da', // cyan
                '#ffca28', // kuning
                '#8d6e63', // coklat
            ];
            let usedColors = [];

            const getUniqueRandomColor = () => {
                if (usedColors.length === chartColors.length) {
                    usedColors = []; // reset colors
                }
                let color;
                do {
                    color = chartColors[Math.floor(Math.random() * chartColors.length)];
                } while (usedColors.includes(color));
                usedColors.push(color);
                return color;
            }

            const renderLineChart = (containerId, title, titleSeries, data, unit = '') => {
                const chartId = containerId.replace('#', '');
                const lineColor = getUniqueRandomColor();

                const options = {
                    chart: {
                        id: chartId,
                        type: 'area',
                        height: 250,
                        zoom: { enabled: false },
                        toolbar: { show: false },
                        fontFamily: 'Inter, ui-sans-serif, system-ui',
                    },
                    colors: [lineColor],
                    fill: {
                        type: 'gradient',
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.45,
                            opacityTo: 0.05,
                            stops: [0, 100]
                        }
                    },
                    title: {
                        text: title,
                        align: 'left',
                        offsetY: 10, // Memberikan jarak agar judul grafik tidak mepet ke atas
                        style: {
                            fontSize: '14px',
                            fontWeight: '600',
                            color: '#4b5563'
                        }
                    },
                    series: [{
                        name: titleSeries,
                        data: data
                    }],
                    xaxis: {
                        type: 'datetime',
                        labels: {
                            datetimeUTC: false,
                            format: 'HH:mm',
                            style: { colors: '#9ca3af' }
                        },
                        axisBorder: { show: false },
                        axisTicks: { show: false }
                    },
                    yaxis: {
                        labels: {
                            formatter: (val) => val.toFixed(1) + (unit ? ' ' + unit : ''),
                            style: { colors: '#9ca3af' }
                        }
                    },
                    stroke: {
                        curve: 'smooth',
                        width: 3,
                    },
                    markers: {
                        size: 0,
                        hover: { size: 5 }
                    },
                    grid: {
                        borderColor: '#f3f4f6',
                        strokeDashArray: 4,
                        padding: { left: 10, right: 10 }
                    },
                    tooltip: {
                        theme: 'light',
                        x: { format: 'dd MMM yyyy HH:mm:ss' },
                        y: {
                            formatter: function (value) {
                                return value !== null ? value.toFixed(2) + ' ' + unit : '-';
                            }
                        }
                    }
                };

                const chart = new ApexCharts(document.querySelector(containerId), options);
                chart.render();
            }

            // function for update area hart when new data received
            const updateLineChart = (chartId, value, timestamp) => {
                ApexCharts.exec(chartId, 'appendData', [{
                    data: [{
                        x: new Date(timestamp),
                        y: value
                    }]
                }]);
            }

            // function for switch data type (raw or filtered)
            const switchChartData = (chartId, titleSeries, data) => {
                ApexCharts.exec(chartId, 'updateSeries', [{
                    name: titleSeries,
                    data: data
                }]);
            }

            // Pusher
            var pusher = new Pusher("{{ config('broadcasting.connections.pusher.key') }}", {
                cluster: "{{ config('broadcasting.connections.pusher.options.cluster') }}"
            });

            var channel = pusher.subscribe('carbon-realtime');
            channel.bind('data.received', function (p) {
                // p will contain { reading: {...} } based on the event structure
                const data = p.reading || p; 

                updateLineChart('co2Chart', data.co2_sensor, data.reading_time);
                updateLineChart('socChart', data.soil_organic_carbon, data.reading_time);
                updateLineChart('cfChart', data.carbon_flux, data.reading_time);

                // Update Status Marker di Peta secara Real-time
                if (window.updateMarkerStatus) {
                    window.updateMarkerStatus(data.device_id, 'online');
                }
            });

            document.addEventListener('DOMContentLoaded', function () {
                // countdown for upcoming activities
                const countdownElements = document.querySelectorAll('.countdown');

                const updateCountdowns = () => {
                    const now = Date.now();

                    countdownElements.forEach(el => {
                        const startTime = new Date(el.dataset.start).getTime();
                        const diff = startTime - now;
                        if (diff <= 0) {
                            el.textContent = "Sedang berlangsung";
                            return;
                        }
                        const hours = Math.floor(diff / (1000 * 60 * 60));
                        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                        el.textContent = `Berlangsung dalam ${hours} jam ${minutes} menit`;
                    });
                };

                // run countdown
                updateCountdowns();

                // calculate time to next minute
                const now = new Date();
                const seconds = now.getSeconds();
                const msUntilNextMinute = (60 - seconds) * 1000;

                // sync to 00 seconds, then interval every minute
                setTimeout(() => {
                    updateCountdowns();

                    const interval = setInterval(() => {
                        updateCountdowns();

                        // stop if all countdown is finished
                        const unfinished = [...countdownElements].some(el => {
                            return new Date(el.dataset.start).getTime() > Date.now();
                        });

                        if (!unfinished) clearInterval(interval);
                    }, 1000 * 60); // every minute
                }, msUntilNextMinute);

                renderLineChart('#co2Chart', 'Grafik CO2', 'CO2', co2Data, 'ppm')
                renderLineChart('#socChart', 'Grafik Soil Organic Carbon', 'SOC', socData, '%')
                renderLineChart('#cfChart', 'Grafik Carbon Flux', 'Carbon Flux', cfData, 'g/m2/h')
            });
        </script>
    @endpush
</x-app-layout>