<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Medición de servicios') : config('app.name', 'Medición de servicios') }}
</title>

<link rel="icon" href="{{ asset('favicon-uniguajira.webp') }}" type="image/webp">
<link rel="apple-touch-icon" href="{{ asset('favicon-uniguajira.webp') }}">

<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

@viteReactRefresh
@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
