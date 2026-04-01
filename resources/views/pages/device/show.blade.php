<x-app-layout>
    <x-slot name="header">
        <h2 class="leading-tight">
            <ol class="breadcrumb">
                <li class="breadcrumb-item">
                    <a href="{{ route('master-data.index') }}">Data Master</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="{{ route('device.index') }}">Data Perangkat</a>
                </li>
                <li class="breadcrumb-item breadcrumb-active">{{ __('Lihat Detail Perangkat') }}</li>
            </ol>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg px-4">
                <div class="p-6">
                    <h1 class="text-3xl font-extrabold mb-4">Detail perangkat</h1>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <img src="{{ asset($device->image) }}" alt="Foto Dosen"
                                class="w-full h-auto rounded-lg shadow-md">
                        </div>
                        <div class="col-span-2">
                            <table class="min-w-full bg-white">
                                <tbody>
                                    <tr>
                                        <td class="px-6 py-4 border-b">Series</td>
                                        <td class="px-6 py-4 border-b">{{ $device->series }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b">Nama</td>
                                        <td class="px-6 py-4 border-b">{{ $device->name }}</td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b">Tanggal Pemasangan</td>
                                        <td class="px-6 py-4 border-b">
                                            {{ \Carbon\Carbon::parse($device->installation_date)->translatedFormat('d F Y') }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="px-6 py-4 border-b">Tipe Koneksi</td>
                                        <td class="px-6 py-4 border-b">
                                            {{ $device->tipe_koneksi == 'lora' ? 'LoRa' : ($device->tipe_koneksi == 'gsm' ? 'GSM' : 'WiFi') }}
                                        </td>
                                    </tr>
                                    @switch($device->tipe_koneksi)
                                        @case('lora')
                                            <tr>
                                                <td class="px-6 py-4 border-b">LoRa ID</td>
                                                <td class="px-6 py-4 border-b">{{ $device->lora_id }}</td>
                                            </tr>
                                            <tr>
                                                <td class="px-6 py-4 border-b">LoRa Channel</td>
                                                <td class="px-6 py-4 border-b">{{ $device->lora_channel }}</td>
                                            </tr>
                                            <tr>
                                                <td class="px-6 py-4 border-b">LoRa Net ID</td>
                                                <td class="px-6 py-4 border-b">{{ $device->lora_net_id }}</td>
                                            </tr>
                                            <tr>
                                                <td class="px-6 py-4 border-b">LoRa Key</td>
                                                <td class="px-6 py-4 border-b">{{ $device->lora_key }}</td>
                                            </tr>
                                        @break

                                        @case('gsm')
                                            <tr>
                                                <td class="px-6 py-4 border-b">GSM Provider</td>
                                                <td class="px-6 py-4 border-b">{{ $device->gsm_provider }}</td>
                                            </tr>
                                            <tr>
                                                <td class="px-6 py-4 border-b">GSM Nomor Kartu</td>
                                                <td class="px-6 py-4 border-b">{{ $device->gsm_nomor_kartu }}</td>
                                            </tr>
                                        @break

                                        @case('wifi')
                                            <tr>
                                                <td class="px-6 py-4 border-b">WiFi SSID</td>
                                                <td class="px-6 py-4 border-b">{{ $device->wifi_ssid }}</td>
                                            </tr>
                                            <tr>
                                                <td class="px-6 py-4 border-b">WiFi Password</td>
                                                <td class="px-6 py-4 border-b">{{ $device->wifi_password }}</td>
                                            </tr>
                                        @break
                                    @endswitch
                                    <tr>
                                        <td class="px-6 py-4 border-b">Note</td>
                                        <td class="px-6 py-4 border-b">{{ $device->note }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
