@php
    $selectedEstamento = old('id_estamento')
        ? $estamentos->firstWhere('id_estamento', (int) old('id_estamento'))
        : null;
    $showProgram = $selectedEstamento
        ? in_array(mb_strtolower($selectedEstamento->nombre), ['estudiante', 'docente', 'egresado'], true)
        : false;
    $preguntas = \App\Support\Legacy\DatosReferenciaLegado::questionLabels();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Encuesta de satisfaccion - {{ config('app.name', 'Medicion de Servicios') }}</title>

        <link rel="icon" href="{{ asset('favicon-uniguajira.webp') }}" type="image/webp">
        <link rel="apple-touch-icon" href="{{ asset('favicon-uniguajira.webp') }}">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/encuesta/formulario.js'])
    </head>
    <body class="min-h-screen bg-slate-950 text-slate-900">
        <div class="relative min-h-screen overflow-hidden">
            <div class="absolute inset-0 bg-cover bg-center bg-no-repeat" style="background-image: url('{{ asset('images/fondo-uniguajira.jpeg') }}')"></div>
            <div class="absolute inset-0 bg-gradient-to-br from-slate-950/85 via-slate-900/75 to-[#003c40]/72"></div>
            <div class="absolute -left-20 top-20 h-72 w-72 rounded-full bg-[#7de7ea]/18 blur-3xl"></div>
            <div class="absolute bottom-0 right-0 h-80 w-80 rounded-full bg-[#00a9ad]/20 blur-3xl"></div>

            <main class="relative mx-auto flex min-h-screen max-w-6xl items-center px-4 py-10 sm:px-6 lg:px-8">
                <div class="grid w-full gap-8 lg:grid-cols-[1.1fr_1.4fr]">
                    <section class="rounded-3xl border border-white/10 bg-white/10 p-8 text-white shadow-2xl backdrop-blur">
                        <p class="text-sm font-semibold uppercase tracking-[0.3em] text-[#c8f6f7]">Universidad de La Guajira</p>
                        <h1 class="mt-4 text-4xl font-black tracking-tight sm:text-5xl">Encuesta de satisfaccion del servicio</h1>
                        <p class="mt-4 text-base leading-7 text-slate-200">
                            Comparte tu experiencia con el servicio recibido. La informacion se registra de forma anonima
                            y sera utilizada para fortalecer los procesos de atencion.
                        </p>

                        <div class="mt-8 grid gap-4 sm:grid-cols-2">
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#c8f6f7]">Escala</p>
                                <p class="mt-2 text-sm text-slate-100">1=Deficiente | 2=Malo | 3=Regular | 4=Bueno | 5=Excelente</p>
                            </div>
                            <div class="rounded-2xl border border-white/10 bg-white/10 p-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-[#c8f6f7]">Cobertura</p>
                                <p class="mt-2 text-sm text-slate-100">Selecciona el proceso, la dependencia y el servicio para calificar la atencion recibida.</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-3xl border border-white/20 bg-white p-6 shadow-2xl sm:p-8 lg:p-10">
                        @if ($directAccessNotice)
                            <div class="mb-6 rounded-2xl border border-[#8ddcdf] bg-[#e6f8f8] px-4 py-4 text-[#0b5d60]">
                                <p class="text-sm font-semibold">Acceso por enlace unico</p>
                                <p class="mt-1 text-sm">{{ $directAccessNotice }}</p>
                            </div>
                        @endif

                        @if ($surveySubmitted)
                            <div class="mb-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-4 text-emerald-800">
                                <p class="text-sm font-semibold">Gracias por compartir tu opinion.</p>
                                <p class="mt-1 text-sm">La respuesta fue registrada correctamente.</p>
                            </div>
                        @endif

                        @if ($errors->any())
                            <div class="mb-6 rounded-2xl border border-[#8ddcdf] bg-[#e6f8f8] px-4 py-4 text-[#0b5d60]">
                                <p class="text-sm font-semibold">Revisa la informacion del formulario.</p>
                                <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('survey.store') }}" class="space-y-8" data-survey-form>
                            @csrf

                            <div class="grid gap-6 md:grid-cols-2">
                                <div>
                                    <label for="id_estamento" class="block text-sm font-semibold text-slate-800">Estamento</label>
                                    <select
                                        id="id_estamento"
                                        name="id_estamento"
                                        class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm outline-none transition focus:border-[#00a9ad] focus:ring-2 focus:ring-[#9fecee]"
                                        required
                                    >
                                        <option value="">Seleccione un estamento</option>
                                        @foreach ($estamentos as $estamento)
                                            <option
                                                value="{{ $estamento->id_estamento }}"
                                                data-requires-program="{{ in_array(mb_strtolower($estamento->nombre), ['estudiante', 'docente', 'egresado'], true) ? '1' : '0' }}"
                                                @selected((string) old('id_estamento') === (string) $estamento->id_estamento)
                                            >
                                                {{ $estamento->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('id_estamento')
                                        <p class="mt-2 text-sm text-[#0b5d60]">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div id="programa-container" class="{{ $showProgram ? '' : 'hidden' }}">
                                    <label for="id_programa" class="block text-sm font-semibold text-slate-800">Programa</label>
                                    <select
                                        id="id_programa"
                                        name="id_programa"
                                        class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm outline-none transition focus:border-[#00a9ad] focus:ring-2 focus:ring-[#9fecee]"
                                        {{ $showProgram ? '' : 'disabled' }}
                                        {{ $showProgram ? 'required' : '' }}
                                    >
                                        <option value="">Seleccione un programa</option>
                                        @foreach ($programas as $programa)
                                            <option value="{{ $programa->id_programa }}" @selected((string) old('id_programa') === (string) $programa->id_programa)>
                                                {{ $programa->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('id_programa')
                                        <p class="mt-2 text-sm text-[#0b5d60]">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="id_proceso" class="block text-sm font-semibold text-slate-800">Proceso</label>
                                    <select
                                        id="id_proceso"
                                        name="id_proceso"
                                        class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm outline-none transition focus:border-[#00a9ad] focus:ring-2 focus:ring-[#9fecee]"
                                        required
                                    >
                                        <option value="">Seleccione un proceso</option>
                                        @foreach ($procesos as $proceso)
                                            <option value="{{ $proceso->id_proceso }}" @selected((string) old('id_proceso') === (string) $proceso->id_proceso)>
                                                {{ $proceso->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('id_proceso')
                                        <p class="mt-2 text-sm text-[#0b5d60]">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="id_dependencia" class="block text-sm font-semibold text-slate-800">Dependencia</label>
                                    <select
                                        id="id_dependencia"
                                        name="id_dependencia"
                                        class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm outline-none transition focus:border-[#00a9ad] focus:ring-2 focus:ring-[#9fecee] disabled:cursor-not-allowed disabled:bg-slate-100"
                                        {{ $dependencias->isEmpty() ? 'disabled' : '' }}
                                        required
                                    >
                                        <option value="">Seleccione una dependencia</option>
                                        @foreach ($dependencias as $dependencia)
                                            <option value="{{ $dependencia->id_dependencia }}" @selected((string) old('id_dependencia') === (string) $dependencia->id_dependencia)>
                                                {{ $dependencia->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('id_dependencia')
                                        <p class="mt-2 text-sm text-[#0b5d60]">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="md:col-span-2">
                                    <label for="id_servicio" class="block text-sm font-semibold text-slate-800">Servicio recibido</label>
                                    <select
                                        id="id_servicio"
                                        name="id_servicio"
                                        class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm outline-none transition focus:border-[#00a9ad] focus:ring-2 focus:ring-[#9fecee] disabled:cursor-not-allowed disabled:bg-slate-100"
                                        {{ $servicios->isEmpty() ? 'disabled' : '' }}
                                        required
                                    >
                                        <option value="">Seleccione un servicio</option>
                                        @foreach ($servicios as $servicio)
                                            <option value="{{ $servicio->id_servicio }}" @selected((string) old('id_servicio') === (string) $servicio->id_servicio)>
                                                {{ $servicio->nombre }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('id_servicio')
                                        <p class="mt-2 text-sm text-[#0b5d60]">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="space-y-6 rounded-3xl bg-slate-50 p-5 sm:p-6">
                                <div>
                                    <p class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">Evaluacion del servicio</p>
                                    <p class="mt-2 text-sm text-slate-600">
                                        Califica segun tu percepcion de 1 a 5, donde 5 es el nivel mas optimo de satisfaccion.
                                    </p>
                                </div>

                                @foreach ($preguntas as $numero => $pregunta)
                                    <fieldset class="rounded-2xl border border-slate-200 bg-white p-4">
                                        <legend class="px-1 text-sm font-semibold text-slate-800">{{ $numero }}. {{ $pregunta }}</legend>

                                        <div class="mt-3 flex flex-wrap gap-2">
                                            @foreach (range(1, 5) as $valor)
                                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 transition hover:border-[#00a9ad] hover:bg-[#e6f8f8]">
                                                    <input
                                                        type="radio"
                                                        name="pregunta{{ $numero }}"
                                                        value="{{ $valor }}"
                                                        class="h-4 w-4 border-slate-300 text-[#00a9ad] focus:ring-[#00a9ad]"
                                                        @checked((string) old('pregunta'.$numero) === (string) $valor)
                                                        required
                                                    >
                                                    <span>{{ $valor }}</span>
                                                </label>
                                            @endforeach
                                        </div>

                                        @error('pregunta'.$numero)
                                            <p class="mt-2 text-sm text-[#0b5d60]">{{ $message }}</p>
                                        @enderror
                                    </fieldset>
                                @endforeach

                                <div>
                                    <label for="observaciones" class="block text-sm font-semibold text-slate-800">Observaciones</label>
                                    <textarea
                                        id="observaciones"
                                        name="observaciones"
                                        rows="4"
                                        maxlength="1000"
                                        class="mt-2 block w-full rounded-2xl border border-slate-300 bg-white px-4 py-3 text-sm shadow-sm outline-none transition focus:border-[#00a9ad] focus:ring-2 focus:ring-[#9fecee]"
                                        placeholder="Escribe aqui cualquier comentario adicional sobre el servicio recibido."
                                    >{{ old('observaciones') }}</textarea>
                                    @error('observaciones')
                                        <p class="mt-2 text-sm text-[#0b5d60]">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <p class="text-sm text-slate-500">Tu respuesta ayudara a mejorar la calidad del servicio.</p>
                                <button
                                    type="submit"
                                    class="inline-flex items-center justify-center rounded-full bg-[#00a9ad] px-6 py-3 text-sm font-bold uppercase tracking-[0.2em] text-white transition hover:bg-[#008d90] focus:outline-none focus:ring-2 focus:ring-[#8de4e6] focus:ring-offset-2"
                                >
                                    Enviar respuesta
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </main>
        </div>
    </body>
</html>
