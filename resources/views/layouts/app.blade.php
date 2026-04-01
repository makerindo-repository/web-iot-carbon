<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" href="{{ asset('images/logo-upi.png') }}">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Datatable & Datatable Buttons -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.tailwindcss.dataTables.min.css">

    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">

    <!-- Icons. Uncomment required icon fonts -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />

    @stack('styles')
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    <div x-data="{ sideopen: true }" class="min-h-screen w-full flex flex-auto bg-gray-100 items-stretch">
        @include('layouts.sidebar')
        <div :class="{ 'lg:pl-64': sideopen }" class="pt-0 flex-1 flex flex-col min-w-0">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            <!-- Page Content -->
            <main class="min-h-[75vh]">
                {{ $slot }}
            </main>

            <!-- Footer -->
            <footer class="px-6 py-1 font-normal text-base text-slate-400" id="footer"></footer>
        </div>
    </div>

    <x-modal name="sign-out" style="z-index: 999999999;" focusable>
        <form method="post" action="{{ route('logout') }}" class="p-6">
            @csrf
            <h2 class="text-lg font-medium text-gray-900">
                {{ __('Apakah anda yakin ingin keluar?') }}
            </h2>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Batalkan') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Keluar') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/js/all.min.js"
        integrity="sha512-u3fPA7V8qQmhBPNT5quvaXVa1mnnLSXUep5PS1qo5NRzHwG19aHmNJnj1Q8hpA/nBWZtZD4r4AX6YOt5ynLN2g=="
        crossorigin="anonymous" referrerpolicy="no-referrer"></script>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- DataTables core -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <!-- DataTables Buttons -->
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <!-- JSZip & pdfmake for export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script>
        const getApplicationSettings = async () => {
            const response = await fetch('/api/application-settings');
            const data = await response.json();

            const footer = document.getElementById('footer');
            const appLogo = document.getElementById('app-logo');
            const menuFooter = document.getElementById('menu-footer');
            const imgUrl = `/${data.image}`;
            const fallbackImg = '/images/logo-upi-horizontal.png';

            const img = new Image();
            img.src = imgUrl;
            img.onload = () => {
                appLogo.innerHTML = `<img src="${imgUrl}" alt="Logo" class="object-cover">`;
            };
            img.onerror = () => {
                appLogo.innerHTML = `<img src="${fallbackImg}" alt="Logo" class="object-cover">`;
            };

            menuFooter.textContent = `Versi ${data.version}`;
            footer.textContent = `Copyright © ${data.copyright_year} ${data.copyright}. All Right Reserved.`;
        }

        const updateCurrentTime = () => {
            const now = new Date();
            const day = now.toLocaleString('id-ID', {
                weekday: 'long'
            });
            const date = now.toLocaleDateString('id-ID', {
                day: 'numeric',
                month: 'long',
                year: 'numeric'
            });
            // Format time as HH:MM
            const hours = now.getHours().toString().padStart(2, '0');
            const minutes = now.getMinutes().toString().padStart(2, '0');
            document.getElementById('time-current').textContent = `${hours}:${minutes}`;
            document.getElementById('date-current').textContent = `${day}, ${date}`;
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Update time every minute at 00 seconds
            updateCurrentTime();
            const now = new Date();
            const msUntilNextMinute = 60000 - (now.getSeconds() * 1000 + now.getMilliseconds());
            // wait until 00 seconds, then update time
            setTimeout(() => {
                updateCurrentTime();
                setInterval(updateCurrentTime, 60000);
            }, msUntilNextMinute);

            // get application settings
            getApplicationSettings();
        });
    </script>
    @stack('scripts')
</body>

</html>
