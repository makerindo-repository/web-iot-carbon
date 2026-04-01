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

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen flex flex-col font-sans text-gray-900 antialiased">
    <div class="flex-grow container mx-auto px-4 py-16 grid grid-cols-1 md:grid-cols-2 items-center justify-center">
        <div class="flex justify-center items-center">
            <div class="w-full sm:max-w-md mt-6 px-6 py-4">
                {{ $slot }}
            </div>
        </div>

        <div class="flex flex-col items-center justify-center max-md:order-first">
            <a href="/" id="app-logo" class="w-5/6"></a>
            <p class="font-bold text-2xl text-center mt-8" id="login-text"></p>
        </div>
    </div>
    <div class="w-full text-center mb-2" id="footer"></div>

    @stack('scripts')
    <script>
        const getApplicationSettings = async () => {
            const response = await fetch('/api/application-settings');
            const data = await response.json();

            const footer = document.getElementById('footer');
            const appLogo = document.getElementById('app-logo');
            const loginText = document.getElementById('login-text')
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
            loginText.textContent = data.login_text;
            footer.textContent = `Copyright © ${data.copyright_year} ${data.copyright}. All Right Reserved.`;
        }

        document.addEventListener('DOMContentLoaded', function() {
            getApplicationSettings();
        });
    </script>
</body>

</html>
