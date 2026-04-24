<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Medicion de Servicios') }}</title>

        <link rel="icon" href="{{ asset('favicon-uniguajira.webp') }}" type="image/webp">
        <link rel="apple-touch-icon" href="{{ asset('favicon-uniguajira.webp') }}">

        @php
            $navbarCssPath = public_path('assets/css/components/navbar.css');
            $sidebarCssPath = public_path('assets/css/components/sidebar.css');
            $navbarCssVersion = file_exists($navbarCssPath) ? filemtime($navbarCssPath) : time();
            $sidebarCssVersion = file_exists($sidebarCssPath) ? filemtime($sidebarCssPath) : time();
        @endphp

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600;700&display=swap" rel="stylesheet" />
        <link rel="stylesheet" href="{{ asset('assets/css/components/navbar.css').'?v='.$navbarCssVersion }}">
        <link rel="stylesheet" href="{{ asset('assets/css/components/sidebar.css').'?v='.$sidebarCssVersion }}">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        @include('layouts.navbar')
        @include('layouts.sidebar')

        <main id="ms-main" class="ms-main">
            <div class="p-4 sm:p-6 lg:p-8">
                {{ $slot }}
            </div>
        </main>

        <script>
            window.MSLayout = {
                toggleSidebar() {
                    const sidebar = document.getElementById('ms-sidebar');
                    const main = document.getElementById('ms-main');
                    const menu = document.getElementById('ms-menu');

                    if (!sidebar || !main || !menu) {
                        return;
                    }

                    sidebar.classList.toggle('menu-toggle');
                    main.classList.toggle('menu-toggle');
                    menu.classList.toggle('menu-toggle');
                },
            };
        </script>
    </body>
</html>
