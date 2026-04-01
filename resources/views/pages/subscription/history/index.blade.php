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
                    <a href="{{ route('subscription.index') }}">Manajemen Langganan</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Riwayat Transaksi') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex flex-col space-y-4">
                        <div class="flex justify-between">
                            <h1 class="text-3xl font-extrabold">Tabel Riwayat Transaksi</h1>
                        </div>
                    </div>
                </div>
                <div class="overflow-x-scroll">
                    <table class="w-full align-middle border-slate-400 table mb-0 px-2" id="histories-table">
                        <thead>
                            <tr>
                                <th class="dt-center">Timestamp</th>
                                <th class="dt-center">Order ID</th>
                                <th class="dt-center">User</th>
                                <th class="dt-center">Plan</th>
                                <th class="dt-center">Metode Pembayaran</th>
                                <th class="dt-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="table-border-bottom-0" id="histories-tbody">
                            @foreach ($histories as $history)
                                <tr>
                                    <td>{{ $history->updated_at ? $history->updated_at->translatedFormat('d-m-Y H:i:s') : $history->created_at }}</td>
                                    <td>{{ $history->order_id }}</td>
                                    <td>{{ $history->user->name }}</td>
                                    <td>{{ $history->plan->name }}</td>
                                    <td>{{ $history->payment_type }}</td>
                                    <td>
                                        @switch($history->transaction_status)
                                            @case('settlement')
                                                <span class="bg-green-600 text-white px-3 py-1.5 rounded-full">
                                                    {{ ucfirst($history->transaction_status) }}
                                                </span>
                                            @break

                                            @case('capture')
                                                <span class="bg-green-600 text-white px-3 py-1.5 rounded-full">
                                                    {{ ucfirst($history->transaction_status) }}
                                                </span>
                                            @break

                                            @case('pending')
                                                <span class="bg-yellow-500 text-white px-3 py-1.5 rounded-full text-center">
                                                    {{ ucfirst($history->transaction_status) }}
                                                </span>
                                            @break

                                            @case('challenge')
                                                <span class="bg-orange-500 text-white px-3 py-1.5 rounded-full">
                                                    {{ ucfirst($history->transaction_status) }}
                                                </span>
                                            @break

                                            @default
                                                <span class="bg-red-500 text-white px-3 py-1.5 rounded-full">
                                                    {{ ucfirst($history->transaction_status) }}
                                                </span>
                                        @endswitch
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
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

            // DataTable (using jQuery)
            let table;
            $(document).ready(function() {
                table = $('#histories-table').DataTable({
                    responsive: true,
                    ordering: false,
                    dom: '<"ms-5 mb-2"B>rtp',
                    buttons: [{
                        extend: 'excel',
                        text: 'Export Excel',
                        title: 'Data Riwayat Transaksi',
                        className: 'bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600',
                        filename: function() {
                            return `riwayat_transaksi_${timestamp()}`;
                        }
                    }],
                    columnDefs: [{
                        className: "text-center",
                        targets: "_all"
                    }],
                    language: {
                        emptyTable: "Tidak ada riwayat transaksi yang tersedia",
                        paginate: {
                            previous: "<",
                            next: ">"
                        }
                    }
                });
            });
        </script>
    @endpush
</x-app-layout>
