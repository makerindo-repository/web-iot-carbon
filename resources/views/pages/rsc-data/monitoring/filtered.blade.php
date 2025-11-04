<x-app-layout>
    @push('styles')
        <style>
            .dataTables_wrapper .dataTables_paginate .paginate_button {
                padding: 0.25rem 0.75rem !important;
                margin: 1rem 0.25rem !important;
                border: 1px solid #d1d5db !important;
                /* gray-300 */
                border-radius: 0.5rem !important;
                /* rounded-md */
                font-size: 1rem !important;
                /* text-sm */
                color: #374151;
                /* gray-700 */
                background-color: #ffffff !important;
                /* white */
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
                background-color: #f3f4f6 !important;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button.current {
                background-color: #EF4444 !important;
                /* blue-600 */
                color: #ffffff !important;
                /* white */
                font-weight: 600 !important;
                /* font-semibold */
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
                background-color: #DC2626 !important;
                /* blue-600 */
                color: #ffffff !important;
                /* white */
            }
        </style>
    @endpush
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('rsc-data.index') }}">Data Rapid Soil Checker (RSC)</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Filtered Monitoring') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col space-y-4">
                        <div class="flex justify-between">
                            <h1 class="text-3xl font-extrabold">Tabel Filtered Data Telemetri Fix Station</h1>
                        </div>
                        <div class="flex flex-col">
                            <h3>Terakhir diupdate: <span
                                    id="datetime-newest-data">{{ $lastUpdated ? \Carbon\Carbon::parse($lastUpdated->created_at)->translatedFormat('d F Y H:i:s') : '-' }}</span>
                            </h3>
                            <p id="quotaRemaining"
                                data-expires="{{ \Carbon\Carbon::parse($userExpires)->toIso8601String() }}">
                            </p>
                        </div>
                        <!-- Filter Section -->
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h4 class="text-lg font-semibold mb-3">Filter Data</h4>
                            <form id="filterForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div>
                                    <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Tanggal
                                        Mulai</label>
                                    <input type="date" id="start_date" name="start_date"
                                        value="{{ request('start_date') }}"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                                </div>
                                <div>
                                    <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Tanggal
                                        Akhir</label>
                                    <input type="date" id="end_date" name="end_date"
                                        value="{{ request('end_date') }}"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                                </div>
                                <div>
                                    <label for="device_id" class="block text-sm font-medium text-gray-700 mb-1">ID
                                        Perangkat</label>
                                    <select id="device_id" name="device_id"
                                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-red-500">
                                        <option value="">Semua Perangkat</option>
                                        @foreach ($uniqueDeviceIds as $deviceId)
                                            <option value="{{ $deviceId }}"
                                                {{ request('device_id') == $deviceId ? 'selected' : '' }}>
                                                {{ $deviceId }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="flex items-end">
                                    <button type="submit"
                                        class="bg-red-500 text-white px-4 py-2 rounded-md hover:bg-red-600 focus:outline-none focus:ring-2 focus:ring-red-500">
                                        Filter
                                    </button>
                                    <button type="button" id="resetFilter"
                                        class="ml-2 bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-gray-500">
                                        Reset
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="text-right">
                            <form action="{{ route('rsc-data.clear') }}?source=filtered" method="POST" id="clearDataForm">
                                @csrf
                                <button type="submit"
                                    class="bg-red-600 text-white px-3 py-1 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                                    Clear Data
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-scroll">
                    <table class="w-full align-middle border-slate-400 table mb-0 px-2" id="fix-station-table">
                        <thead>
                            <tr>
                                <th class="dt-center">Waktu</th>
                                <th class="dt-center">ID Perangkat</th>
                                <th class="dt-center">Nitrogen</th>
                                <th class="dt-center">Fosfor</th>
                                <th class="dt-center">Kalium</th>
                                <th class="dt-center">EC</th>
                                <th class="dt-center">pH Tanah</th>
                                <th class="dt-center">Suhu Tanah</th>
                                <th class="dt-center">Kelembapan Tanah</th>
                                <th class="dt-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0" id="fix-station-tbody">
                            @foreach ($data as $item)
                                <tr>
                                    <td>{{ $item->created_at }}</td>
                                    <td>{{ $item->device_id ?? '-' }}</td>
                                    <td>{{ $item->samples->Nitrogen ?? 0 }} mg/kg</td>
                                    <td>{{ $item->samples->Phosporus ?? 0 }} mg/kg</td>
                                    <td>{{ $item->samples->Kalium ?? 0 }} mg/kg</td>
                                    <td>{{ $item->samples->Ec ?? 0 }} uS/cm</td>
                                    <td>{{ $item->samples->Ph ?? 0 }}</td>
                                    <td>{{ $item->samples->Temperature ?? 0 }} &deg;C</td>
                                    <td>{{ $item->samples->Humidity ?? 0 }} %</td>
                                    <td class="align-middle">
                                        <div class="flex gap-2 items-center justify-center">
                                            <!-- Button untuk prompt rekomendasi tanaman ke gemini -->
                                            <button id="openPromptModalBtn" class="rounded-lg"
                                                data-id="{{ $item->id }}">
                                                <i class="fa-solid fa-lightbulb text-green-500"></i>
                                            </button>
                                            @if (in_array(Auth::user()->role, ['superuser', 'dosen']))
                                                <form
                                                    action="{{ route('rsc-data.destroy', ['id' => $item->id, 'page' => 'fm']) }}"
                                                    method="POST" class="delete-form"
                                                    data-series="{{ $item->created_at }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit">
                                                        <i class="fa fa-trash text-red-500"></i>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal prompt AI -->
    <div id="promptModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-lg p-6 relative max-h-[90vh] overflow-y-auto">
            <!-- Tombol close -->
            <button id="closePromptModalBtn" onclick="closeModal(document.getElementById('promptModal'))"
                class="absolute top-3 right-3 text-gray-500 hover:text-gray-800">
                <i class="fa-solid fa-xmark text-4xl"></i>
            </button>

            <h2 class="text-xl font-semibold mb-4">Rekomendasi Tanaman</h2>

            <!-- Loader -->
            <div id="loading" class="text-center py-4 hidden">
                <span class="text-gray-500">Sedang memproses...</span>
            </div>

            <!-- Klasifikasi tanah -->
            <div id="soilClassification" class="hidden mb-6 p-4 border-l-4 border-green-500 bg-green-50 rounded">
                <h3 class="font-semibold text-green-700">Klasifikasi Tanah</h3>
                <p id="soilCategory" class="text-gray-800 font-medium"></p>
                <p id="soilDescription" class="text-gray-600 text-sm"></p>
            </div>

            <!-- Container hasil -->
            <div id="recommendationList" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Card hasil akan ditambahkan via JS -->
            </div>
        </div>
    </div>

    <!-- Modal pilihan paket pro -->
    <div id="upgradeModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="bg-white w-full max-w-3xl rounded-2xl shadow-lg p-6 relative max-h-[90vh] overflow-y-auto">
            <!-- Tombol close -->
            <button id="closeUpgradeModalBtn" onclick="closeModal(document.getElementById('upgradeModal'))"
                class="absolute top-3 right-3 text-gray-500 hover:text-gray-800">
                <i class="fa-solid fa-xmark text-4xl"></i>
            </button>

            <h2 class="text-xl font-semibold mb-4">Pilihan paket Pro</h2>

            <!-- Container hasil -->
            <div id="planList" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                @foreach ($plans as $plan)
                    <div class="border rounded-xl p-4 shadow hover:shadow-md transition">
                        <h3 class="font-bold text-lg">{{ $plan->name }}</h3>
                        <p class="text-gray-600 my-2">Durasi: {{ $plan->duration }} Hari</p>
                        <p class="text-green-600">Rp{{ $plan->price }}</p>
                        <button id="openUpgradeModalBtn" onclick="pay({{ $plan->id ?? 0}})" class="bg-red-500 text-white px-3 py-2 mt-2 rounded-lg">
                            Bayar di sini
                        </button>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://app.sandbox.midtrans.com/snap/snap.js" data-client-key="{{ config('midtrans.client_key') }}">
        </script>
        <script src="https://js.pusher.com/8.4.0/pusher.min.js"></script>
        <script>
            // Get current timestamp for filename
            const timestamp = () => {
                const now = new Date();
                const date = now.getDate().toString().padStart(2, '0');
                const month = (now.getMonth() + 1).toString().padStart(2, '0');
                const year = now.getFullYear();
                const hours = now.getHours().toString().padStart(2, '0');
                const minutes = now.getMinutes().toString().padStart(2, '0');
                const seconds = now.getSeconds().toString().padStart(2, '0');

                return `${year}${month}${date}_${hours}${minutes}${seconds}`;
            }

            // open snap midtrans
            const pay = (planId) => {
                const baseUrl = window.location.origin;
                fetch(`${baseUrl}/payment/create/${planId}`)
                    .then(res => res.json())
                    .then(data => {
                        snap.pay(data.snap_token, {
                            onSuccess: function(res) {
                                console.log("Payment Success: ", res)
                            },
                            onPending: function(res) {
                                console.log("Payment Pending: ", res)
                            },
                            onError: function(res) {
                                console.log("Payment Error: ", res)
                            },
                        })
                    });
            }

            // Buka upgrade modal
            const openUpgradeModal = () => {
                document.getElementById('promptModal').classList.add('hidden');
                document.getElementById('promptModal').classList.remove('flex', 'items-center', 'justify-center');
                document.getElementById('upgradeModal').classList.remove('hidden');
                document.getElementById('upgradeModal').classList.add('flex', 'items-center', 'justify-center');
            }

            const closeModal = (modal) => {
                modal.classList.add('hidden');
                modal.classList.remove('flex', 'items-center', 'justify-center');
            }

            // DataTable (using jQuery)
            let table;
            $(document).ready(function() {
                table = $('#fix-station-table').DataTable({
                    responsive: true,
                    ordering: false,
                    dom: '<"ms-5 mb-2"B>rtp',
                    buttons: [{
                        extend: 'excel',
                        text: 'Export Excel',
                        title: 'Data Telemetri Rapid Soil Checker',
                        className: 'bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600',
                        filename: function() {
                            return `data_telemetri_rsc_${timestamp()}`;
                        }
                    }],
                    columnDefs: [{
                        className: "text-center",
                        targets: "_all"
                    }],
                    language: {
                        emptyTable: "Tidak ada data telemetri yang tersedia",
                        paginate: {
                            previous: "<",
                            next: ">"
                        }
                    }
                });

                // Handle filter form submission
                $('#filterForm').on('submit', function(e) {
                    e.preventDefault();
                    const formData = new FormData(this);
                    const params = new URLSearchParams(formData);
                    window.location.href = '{{ route('rsc-data.filtered-monitoring.index') }}?' + params
                        .toString();
                });

                // Handle reset filter
                $('#resetFilter').on('click', function() {
                    window.location.href = '{{ route('rsc-data.filtered-monitoring.index') }}';
                });

                // Update device ID options when date range changes
                $('#start_date, #end_date').on('change', function() {
                    updateDeviceIdOptions();
                });

                // Initialize device options if date range is already set
                if ($('#start_date').val() && $('#end_date').val()) {
                    updateDeviceIdOptions();
                }

                function updateDeviceIdOptions() {
                    const startDate = $('#start_date').val();
                    const endDate = $('#end_date').val();

                    if (startDate && endDate) {
                        $.get('{{ route('rsc-data.filtered-monitoring.device-ids') }}', {
                                start_date: startDate,
                                end_date: endDate
                            })
                            .done(function(deviceIds) {
                                const select = $('#device_id');
                                const currentValue = select.val();
                                select.find('option:not(:first)').remove();

                                deviceIds.forEach(function(deviceId) {
                                    const selected = currentValue == deviceId ? 'selected' : '';
                                    select.append(
                                        `<option value="${deviceId}" ${selected}>${deviceId}</option>`);
                                });
                            })
                            .fail(function() {
                                console.error('Failed to fetch device IDs');
                            });
                    } else {
                        // If no date range, clear device options except "Semua Perangkat"
                        $('#device_id').find('option:not(:first)').remove();
                    }
                }
            });


            // Enable pusher logging - don't include this in production
            const formatTimestamp = (timestamp) => {
                return new Date(timestamp).toLocaleString('sv-SE');
            }

            Pusher.logToConsole = true;

            var pusher = new Pusher("{{ config('broadcasting.connections.pusher.key') }}", {
                cluster: "{{ config('broadcasting.connections.pusher.options.cluster') }}"
            });

            var channel = pusher.subscribe('sensor-data');
            channel.bind('SensorData', function(p) {
                const data = p.filtered;
                const actionHtml = `
                <div class="flex gap-2 items-center justify-center">
                    <!-- Button untuk prompt rekomendasi tanaman ke gemini -->
                    <button id="openPromptModalBtn" class="rounded-lg" data-id="${data.id}"">
                        <i class="fa-solid fa-lightbulb text-green-500"></i>
                    </button>
                    @if (in_array(Auth::user()->role, ['superuser', 'dosen']))
                        <form
                            action="{{ route('rsc-data.destroy', ['id' => '__ID__', 'page' => 'fm']) }}"
                            method="POST" class="delete-form"
                            data-series="{{ $item->created_at ?? '' }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit">
                                    <i class="fa fa-trash text-red-500"></i>
                                </button>
                        </form>
                    @endif
                </div>
                `.replace('__ID__', data.id);
                const newRow = table.row.add([
                    formatTimestamp(data.created_at),
                    data.device_id,
                    `${data.samples.Nitrogen} mg/kg`,
                    `${data.samples.Phosporus} mg/kg`,
                    `${data.samples.Kalium} mg/kg`,
                    `${data.samples.Ec} uS/cm`,
                    `${data.samples.Ph}`,
                    `${data.samples.Temperature} &deg;C`,
                    `${data.samples.Humidity} %`,
                    actionHtml,
                ]).draw(false);

                let newIndex = table.rows().count() - 1;
                let newNode = table.row(newIndex).node();
                $(newNode).prependTo('#fix-station-tbody')
            });

            document.addEventListener("DOMContentLoaded", function() {
                const openPromptModalBtn = document.querySelectorAll('#openPromptModalBtn');
                const closePromptModalBtn = document.getElementById('closePromptModalBtn');
                const modal = document.getElementById('promptModal');
                const loading = document.getElementById('loading');
                const listContainer = document.getElementById('recommendationList');
                const soilBox = document.getElementById("soilClassification");
                const soilCategory = document.getElementById("soilCategory");
                const soilDescription = document.getElementById("soilDescription");
                const quotaRemaining = document.getElementById("quotaRemaining");
                const quota = "{{ $quotaRemaining }}"
                @if ($userExpires == null || $userExpires == 0)
                    quotaRemaining.textContent = `Sisa kuota Cek Rekomendasi Tanaman: ${quota}`;
                @else
                    const updateCountdowns = () => {
                        const now = Date.now();

                        const expiresTime = new Date(quotaRemaining.dataset.expires).getTime();
                        const diff = expiresTime - now;
                        const days = Math.floor(diff / (1000 * 60 * 60 * 24));
                        const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                        const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                        let text = "Sisa durasi langganan Cek Rekomendasi Tanaman: ";

                        if (days > 0) text += `${days} hari `;
                        if (hours > 0 || days > 0) text += `${hours} jam `;
                        text += `${minutes} menit`;

                        quotaRemaining.textContent = text.trim();
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
                                return new Date(quotaRemaining.dataset.expires).getTime() > Date
                                    .now();
                            });

                            if (!unfinished) clearInterval(interval);
                        }, 1000 * 60); // every minute
                    }, msUntilNextMinute);
                @endif

                // Buka modal hasil prompt AI
                openPromptModalBtn.forEach(btn => {
                    btn.addEventListener('click', async () => {
                        modal.classList.remove('hidden');
                        modal.classList.add('flex', 'items-center', 'justify-center');
                        listContainer.innerHTML = ''; // reset isi
                        soilBox.classList.add("hidden"); // reset klasifikasi tanah
                        loading.classList.remove('hidden');

                        const dataId = btn.getAttribute('data-id');

                        try {
                            // Panggil endpoint Laravel yang mengakses Gemini
                            const res = await fetch(
                                `/rekomendasi-tanaman/${dataId}?source=filtered`);
                            const data = await res.json();
                            loading.classList.add('hidden');

                            if (!res.ok) {
                                listContainer.innerHTML =
                                    `<p class="text-red-500 col-span-2">${data.message}</p>
                                    <button id="openUpgradeModalBtn" onclick="openUpgradeModal()" class="bg-green-600 text-white col-span-2 w-auto me-auto px-4 py-3 rounded-lg">
                                        Upgrade to Pro
                                    </button>`;
                                return;
                            }

                            if (data.success) {
                                if (data.response) {
                                    if (data.response.klasifikasi_tanah) {
                                        soilBox.classList.remove("hidden");
                                        soilCategory.innerText =
                                            `Kategori Tanah: ${data.response.klasifikasi_tanah.kategori}`;
                                        soilDescription.innerText = data.response.klasifikasi_tanah
                                            .deskripsi;
                                    }
                                    if (data.response.tanaman_rekomendasi) {
                                        data.response.tanaman_rekomendasi.forEach(item => {
                                            const card = `
                                        <div class="border rounded-xl p-4 shadow hover:shadow-md transition">
                                            <h3 class="font-bold text-lg">${item.nama}</h3>
                                            <p class="text-sm text-green-600">Kategori: ${item.kategori}</p>
                                            <p class="text-gray-600 mt-2">${item.alasan}</p>
                                            </div>
                                            `;
                                            listContainer.insertAdjacentHTML('beforeend',
                                                card);
                                        });
                                    }
                                } else {
                                    listContainer.innerHTML =
                                        `<p class="text-red-500">Tidak ada data rekomendasi.</p>`;
                                }
                            } else {
                                listContainer.innerHTML =
                                    `<p class="text-red-500">${data.message ?? 'Terjadi kesalahan.'}</p>`;
                            }
                        } catch (err) {
                            loading.classList.add('hidden');
                            listContainer.innerHTML =
                                `<p class="text-red-500">Gagal mengambil data.</p>`;
                            console.error(err);
                        }
                    });
                })

                @if (session('success'))
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: '{{ session('success') }}',
                        showConfirmButton: false,
                        timer: 2000,
                    });
                @endif

                document.querySelectorAll('.delete-form').forEach(form => {
                    form.addEventListener('submit', function(event) {
                        event.preventDefault();

                        const dataTimestamp = this.getAttribute('data-series');
                        Swal.fire({
                            title: 'Konfirmasi',
                            text: `Apakah Anda yakin ingin menghapus data RSC dengan timestamp ${dataTimestamp}?`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonText: 'Ya, Hapus!',
                            cancelButtonText: 'Batal'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                this.submit();
                            }
                        });
                    })
                });
            });

            document.getElementById('clearDataForm').addEventListener('submit', function(event) {
                event.preventDefault();

                Swal.fire({
                    title: 'Konfirmasi',
                    text: `Apakah Anda yakin ingin menghapus seluruh data RSC?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Ya, Hapus!',
                    cancelButtonText: 'Batal'
                }).then((result) => {
                    if (result.isConfirmed) {
                        this.submit();
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
