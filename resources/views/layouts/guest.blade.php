<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="{{ asset('favicon-uniguajira.webp') }}" type="image/webp">
        <link rel="apple-touch-icon" href="{{ asset('favicon-uniguajira.webp') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600;700&display=swap" rel="stylesheet" />

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen antialiased text-white">
        <div class="relative min-h-screen overflow-hidden flex items-center justify-center">
            <div class="absolute inset-0 -z-20 bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('images/fondo-uniguajira.png') }}')"></div>

            <div class="absolute -z-10 -top-24 -left-16 h-80 w-80 rounded-full bg-[#00a9ad]/30 blur-3xl"></div>
            <div class="absolute -z-10 bottom-0 right-0 h-72 w-72 rounded-full bg-[#008d90]/28 blur-3xl"></div>
            <div class="absolute -z-10 top-1/3 right-1/3 h-56 w-56 rounded-[35%_65%_55%_45%/45%_35%_65%_55%] bg-[#00777a]/34 blur-2xl"></div>

            <main class="relative flex items-center justify-center p-6 sm:p-10 lg:ml-auto lg:mr-[8vw] xl:mr-[10vw]">
                <div class="absolute inset-0 -z-10 bg-black/35"></div>

                <div class="w-full max-w-md rounded-xl border border-white/15 bg-black/65 p-6 shadow-2xl backdrop-blur-sm sm:p-8">
                    <div class="mb-6 flex items-center justify-center gap-2 lg:hidden">
                        <x-app-logo-icon />
                        <span class="text-lg font-semibold">{{ config('app.name', 'Medicion de Servicios') }}</span>
                    </div>

                    {{ $slot }}
                </div>
            </main>
        </div>
    </body>
</html>
