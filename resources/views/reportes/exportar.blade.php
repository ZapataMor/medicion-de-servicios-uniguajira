<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title></title>
        @include('reportes.pdf.parciales.estilos')
    </head>
    <body>
        @php
            $toDataUri = static function (string $relativePath): ?string {
                $absolutePath = public_path($relativePath);

                if (! is_file($absolutePath)) {
                    return null;
                }

                $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
                $mime = match ($extension) {
                    'png' => 'image/png',
                    'jpg', 'jpeg' => 'image/jpeg',
                    'webp' => 'image/webp',
                    default => 'application/octet-stream',
                };

                $contents = file_get_contents($absolutePath);

                if ($contents === false) {
                    return null;
                }

                return 'data:'.$mime.';base64,'.base64_encode($contents);
            };

            $sidebarImage = $toDataUri('assets/images/sidebar.png');
            $encabezadoImage = $toDataUri('assets/images/Ecabezado2.png');
            $portadaImage = $toDataUri('assets/images/Portada2.png');

            $signature = $signature ?? null;
            $quarterLabel = null;
            $quarterRoman = null;
            $periodLabel = null;
            $processName = null;
            $dependencyName = null;
            $selectedServicesLabel = null;

            foreach (($contextRows ?? []) as $contextRow) {
                if (($contextRow['label'] ?? '') === 'Trimestre') {
                    $quarterLabel = $contextRow['value'] ?? null;
                }

                if (($contextRow['label'] ?? '') === 'Periodo') {
                    $periodLabel = $contextRow['value'] ?? null;
                }

                if (($contextRow['label'] ?? '') === 'Proceso') {
                    $processName = $contextRow['value'] ?? null;
                }

                if (($contextRow['label'] ?? '') === 'Dependencia') {
                    $dependencyName = $contextRow['value'] ?? null;
                }

                if (($contextRow['label'] ?? '') === 'Servicios') {
                    $selectedServicesLabel = $contextRow['value'] ?? null;
                }
            }

            if (is_string($quarterLabel)) {
                $normalizedQuarterLabel = mb_strtoupper($quarterLabel, 'UTF-8');

                if (preg_match('/\b(IV|III|II|I)\b/u', $normalizedQuarterLabel, $romanMatch) === 1) {
                    $quarterRoman = $romanMatch[1];
                } elseif (preg_match('/\b([1-4])\b/u', $normalizedQuarterLabel, $numericMatch) === 1) {
                    $quarterRoman = match ((int) $numericMatch[1]) {
                        1 => 'I',
                        2 => 'II',
                        3 => 'III',
                        4 => 'IV',
                        default => null,
                    };
                }
            }

            $reportType = $reportType ?? 'general';
            $surveyCount = (int) ($report['totals']['survey_count'] ?? 0);
            $answerCount = (int) ($report['totals']['answer_count'] ?? 0);
            $scopeTable = $report['tables']['scope_population'] ?? ['rows' => [], 'total_general' => 0, 'first_column_title' => 'Encuestados', 'second_column_title' => 'Total'];
            $estamentoRows = $report['tables']['by_estamento'] ?? [];
            $consolidated = $report['tables']['measurement_consolidated'] ?? ['rows' => [], 'summary' => []];
            $globalIndicator = $report['indicators']['global'] ?? [];
            $coverYear = \Carbon\CarbonImmutable::parse($report['from'] ?? now()->toDateString(), config('app.timezone'))->format('Y');
            $scopeName = match ($reportType) {
                'process' => $processName ?: 'Proceso seleccionado',
                'individual' => $dependencyName ?: 'Dependencia seleccionada',
                default => 'procesos evaluados',
            };
            $scopeNameUpper = mb_strtoupper((string) $scopeName, 'UTF-8');
            $scopeSentence = match ($reportType) {
                'process' => 'el proceso '.$scopeName,
                'individual' => filled($selectedServicesLabel)
                    ? 'la dependencia '.$scopeName.' en los servicios seleccionados'
                    : 'la dependencia '.$scopeName,
                default => 'los procesos evaluados',
            };
            $scopeInstitutional = match ($reportType) {
                'process' => 'del proceso '.$scopeName,
                'individual' => filled($selectedServicesLabel)
                    ? 'en los servicios seleccionados de la dependencia '.$scopeName
                    : 'de la dependencia '.$scopeName,
                default => 'de los procesos evaluados',
            };
            $scopeIndicatorTitle = match ($reportType) {
                'process' => 'DEL PROCESO '.$scopeNameUpper,
                'individual' => 'DE LA DEPENDENCIA '.$scopeNameUpper,
                default => 'DE LOS PROCESOS EVALUADOS',
            };
            $scopeTocOfficeTitle = match ($reportType) {
                'process', 'individual' => 'DE LA OFICINA DE '.$scopeNameUpper,
                default => 'DE LOS PROCESOS EVALUADOS',
            };
            $estamentoTotal = (int) array_sum(array_column($estamentoRows, 'encuestas'));
            $formatValue = static function (float|int $value): string {
                $formatted = number_format((float) $value, 2, '.', '');

                return rtrim(rtrim($formatted, '0'), '.');
            };
            $questionActionParagraphs = [
                1 => 'En atencion a estos resultados, se continuara fortaleciendo las estrategias orientadas a mantener la calidad del servicio y la atencion oportuna al usuario.',
                2 => 'En atencion a estos resultados, se continuaran fortaleciendo las acciones orientadas a garantizar condiciones adecuadas de seguridad y comodidad para los usuarios.',
                3 => 'En atencion a estos resultados, se continuaran fortaleciendo las estrategias orientadas a mejorar la experiencia del usuario y el valor percibido del servicio.',
                4 => 'En atencion a estos resultados, se continuaran fortaleciendo las acciones orientadas a garantizar informacion clara, pertinente y oportuna para los usuarios.',
                5 => 'En atencion a estos resultados, se continuaran fortaleciendo las practicas institucionales orientadas a garantizar una comunicacion respetuosa, incluyente y libre de cualquier forma de discriminacion.',
            ];
            $resolvedGeneratedConclusion = filled($generatedConclusion ?? null)
                ? trim((string) $generatedConclusion)
                : null;
            $questionChartOffset = 4;
            $tocEntries = [
                ['label' => '1. INTRODUCCION', 'page' => 3],
                ['label' => '2. OBJETIVO', 'page' => 3],
                ['label' => '3. CONTEXTUALIZACION DE LA ENCUESTA', 'page' => 3],
                ['label' => '4. NUMERO DE USUARIOS ENCUESTADOS', 'page' => 4],
                ['label' => '5. CARACTERIZACION DE LOS USUARIOS ENCUESTADOS', 'page' => 5],
                ['label' => '6. RESULTADOS DE LA ENCUESTA APLICADA', 'page' => 7],
                ['label' => '7. ANALISIS DE LA ENCUESTA', 'page' => 12],
                ['label' => '8. INDICADOR DE MEDICION DE LA SATISFACCION GLOBAL DE LOS USUARIOS '.$scopeTocOfficeTitle, 'page' => 13],
                ['label' => '9. CONCLUSIONES DE LA MEDICION DE LA SATISFACCION DE LOS USUARIOS '.$scopeTocOfficeTitle, 'page' => 14],
            ];
        @endphp

        <section class="page cover-page">
            @if ($portadaImage)
                <img src="{{ $portadaImage }}" alt="Portada" class="cover-image">
            @endif
            @if ($quarterRoman)
                <div class="cover-quarter-roman">{{ $quarterRoman }}</div>
            @endif
        </section>

        <section class="page page-with-decor toc-page">
            @include('reportes.pdf.parciales.page-decor')

            <div class="content-block">
                <h2 class="section-title">CONTENIDO</h2>

                <div class="toc-list">
                    @foreach ($tocEntries as $tocEntry)
                        <div class="toc-entry">
                            <span class="toc-entry-page">{{ $tocEntry['page'] }}</span>
                            <span class="toc-entry-label">{{ $tocEntry['label'] }}</span>
                            <span class="toc-entry-dots"> .......................................................................................................................</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="page page-with-decor balanced-right-spacing">
            @include('reportes.pdf.parciales.page-decor')

            <div class="content-block" style="margin-right: 116px !important;">
                <h2 class="section-title">1. INTRODUCCION</h2>
                <p class="section-text">
                    El presente informe tiene como finalidad presentar los resultados de la medicion de la prestacion del servicio, realizada a traves de una encuesta de percepcion aplicada a los usuarios que hicieron uso del servicio durante el periodo evaluado.
                </p>
                <p class="section-text">
                    La informacion obtenida constituye un insumo para la toma de decisiones, el seguimiento al desempeno del proceso y el fortalecimiento de la mejora continua, en concordancia con los lineamientos del Sistema Integrado de Gestion.
                </p>

                <h2 class="section-title">2. OBJETIVO</h2>
                <p class="section-text">
                    Evaluar el nivel de satisfaccion de los usuarios frente al servicio prestado por {{ $scopeSentence }}, considerando la percepcion de los diferentes grupos de interes, a partir de los resultados obtenidos en la encuesta aplicada durante el periodo evaluado.
                </p>

                <h2 class="section-title">3. CONTEXTUALIZACION DE LA ENCUESTA</h2>
                <p class="section-text">
                    La medicion de la satisfaccion del usuario constituye una herramienta estrategica para evaluar la eficacia en la prestacion del servicio y el cumplimiento de las expectativas de los grupos de interes. Este ejercicio se desarrolla en el marco del Sistema Integrado de Gestion, como mecanismo de seguimiento a la percepcion del usuario y al desempeno del proceso.
                </p>
                <p class="section-text">
                    Respecto a las preguntas contenidas en la encuesta, a continuacion, se presenta el analisis integral de los resultados obtenidos, considerando tanto las respuestas cerradas como las observaciones registradas por los participantes. El compromiso institucional se orienta al mejoramiento continuo de la calidad en la atencion prestada a la comunidad universitaria.
                </p>
                <p class="section-text">
                    La encuesta de satisfaccion fue aplicada durante {{ $quarterLabel ?? 'el trimestre seleccionado' }} de {{ $coverYear }} a {{ $surveyCount }} {{ $surveyCount === 1 ? 'usuario' : 'usuarios' }} que {{ $surveyCount === 1 ? 'recibio' : 'recibieron' }} atencion {{ $scopeInstitutional }}, de la Universidad de La Guajira.
                </p>
            </div>
        </section>

        <section class="page page-with-decor balanced-right-spacing">
            @include('reportes.pdf.parciales.page-decor')

            <div class="content-block" style="margin-right: 116px !important;">
                <h2 class="section-title">4. NUMERO DE USUARIOS ENCUESTADOS</h2>
                <p class="section-text">
                    Durante el periodo evaluado, la encuesta de medicion del servicio fue aplicada a un total de {{ $scopeTable['total_general'] ?? $surveyCount }} {{ ($scopeTable['total_general'] ?? $surveyCount) === 1 ? 'usuario' : 'usuarios' }}, quienes hicieron uso de los servicios ofrecidos dentro del alcance analizado, como se describe en la tabla 1.
                </p>

                <p class="table-title">Tabla 1. Numero de usuarios encuestados {{ $quarterLabel ?? '' }}</p>
                <table class="compact-table">
                    <thead>
                        <tr>
                            <th>{{ $scopeTable['first_column_title'] }}</th>
                            <th>{{ $scopeTable['second_column_title'] }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse (($scopeTable['rows'] ?? []) as $row)
                            <tr>
                                <td>{{ $row['label'] }}</td>
                                <td>{{ $row['total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="2">Sin datos para el periodo seleccionado.</td>
                            </tr>
                        @endforelse
                        <tr>
                            <td><strong>Total general</strong></td>
                            <td><strong>{{ $scopeTable['total_general'] ?? 0 }}</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="page page-with-decor balanced-right-spacing">
            @include('reportes.pdf.parciales.page-decor')

            <div class="content-block" style="margin-right: 116px !important;">
                <h2 class="section-title">5. CARACTERIZACION DE LOS USUARIOS ENCUESTADOS</h2>
                <p class="section-text">
                    La encuesta permitio identificar el grupo de interes al que pertenecen los usuarios que participaron en el ejercicio de medicion, lo cual facilita el analisis integral de la percepcion del servicio desde las diferentes partes interesadas de la institucion.
                </p>
                <p class="section-text">
                    Durante el periodo evaluado participaron {{ $estamentoTotal }} {{ $estamentoTotal === 1 ? 'usuario' : 'usuarios' }}, cuya distribucion por grupo de interes se presenta a continuacion:
                </p>

                <p class="table-title">Tabla 2. Distribucion de usuarios por grupo de interes</p>
                <table class="centered-table">
                    <thead>
                        <tr>
                            <th>Grupo de interes</th>
                            <th>Numero de usuarios</th>
                            <th>Porcentaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($estamentoRows as $row)
                            <tr>
                                <td>{{ $row['estamento'] }}</td>
                                <td>{{ $row['encuestas'] }}</td>
                                <td>{{ $formatValue($row['porcentaje']) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3">Sin datos para el periodo seleccionado.</td>
                            </tr>
                        @endforelse
                        <tr>
                            <td><strong>Total</strong></td>
                            <td><strong>{{ $estamentoTotal }}</strong></td>
                            <td><strong>{{ $estamentoTotal > 0 ? '100' : '0' }}%</strong></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="page page-with-decor">
            @include('reportes.pdf.parciales.page-decor')

            <div class="content-block-wide">
                <p class="section-title">GRAFICAS COMPLEMENTARIAS DE CARACTERIZACION</p>

                <div class="chart-grid">
                    <div>
                        <div class="chart-shell">
                            <img src="{{ $chartImages['population_by_program'] ?? '' }}" alt="Programas atendidos" class="chart-image">
                        </div>
                        <p class="chart-caption">Grafica 1. Poblacion atendida por programa</p>
                    </div>

                    <div>
                        <div class="chart-shell">
                            <img src="{{ $chartImages['population_by_estamento'] ?? '' }}" alt="Estamentos atendidos" class="chart-image">
                        </div>
                        <p class="chart-caption">Grafica 2. Poblacion atendida por estamento</p>
                    </div>
                </div>
            </div>
        </section>

        <section class="page page-with-decor services-page">
            @include('reportes.pdf.parciales.page-decor')

            <div class="content-block-wide">
                <p class="section-title">SERVICIOS ATENDIDOS</p>

                <div class="services-chart">
                    <div class="chart-shell">
                        <img src="{{ $chartImages['services'] ?? '' }}" alt="Servicios atendidos" class="chart-image">
                    </div>
                    <p class="chart-caption">Grafica 3. Servicios atendidos</p>
                </div>
            </div>
        </section>

        @foreach (($report['questions'] ?? []) as $index => $question)
            @php
                $satisfiedPercentage = (float) ($question['satisfaction']['satisfied_percentage'] ?? 0);
                $neutralPercentage = (float) ($question['satisfaction']['neutral_percentage'] ?? 0);
                $dissatisfiedPercentage = (float) ($question['satisfaction']['dissatisfied_percentage'] ?? 0);
                $graphNumber = $questionChartOffset + $index;
                $dimensionLower = mb_strtolower((string) ($question['dimension'] ?? 'la pregunta '.$question['number']), 'UTF-8');
                $questionSummaryParagraph = 'Los resultados evidencian que el '.$formatValue($satisfiedPercentage).'% de los usuarios manifiesta estar satisfecho, mientras que un '.$formatValue($neutralPercentage).'% presenta una percepcion neutra y un '.$formatValue($dissatisfiedPercentage).'% expresa insatisfaccion frente a '.$dimensionLower.'.';
            @endphp

            <section class="page page-with-decor">
                @include('reportes.pdf.parciales.page-decor')

                <div class="content-block-wide">
                    @if ($index === 0)
                        <h2 class="section-title">6. RESULTADOS DE LA ENCUESTA APLICADA</h2>
                    @endif

                    <table class="question-result-table">
                        <colgroup>
                            <col style="width: 41%">
                            <col style="width: 29%">
                            <col style="width: 30%">
                        </colgroup>
                        <tbody>
                            <tr>
                                <td colspan="3" class="question-row-title">
                                    PREGUNTA {{ $question['number'] }}. {{ mb_strtoupper((string) ($question['label'] ?? 'Pregunta'), 'UTF-8') }}
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" class="question-row-intro">
                                    Al preguntarles a los <span class="question-inline-number">{{ $surveyCount }}</span> {{ $surveyCount === 1 ? 'usuario encuestado' : 'usuarios encuestados' }} la pregunta No. <strong>{{ $question['number'] }}</strong>, ellos respondieron:
                                </td>
                            </tr>
                            <tr>
                                <td class="question-row-summary">
                                    El <strong>{{ $formatValue($satisfiedPercentage) }}%</strong> considera en una calificacion <strong>SATISFECHO</strong>
                                </td>
                                <td class="question-row-summary">
                                    Y el <strong>{{ $formatValue($neutralPercentage) }}%</strong> la califica como <strong>NEUTRO</strong>
                                </td>
                                <td rowspan="2" class="question-row-indicator">
                                    SATISFACCION <strong>{{ $formatValue($satisfiedPercentage) }}%</strong>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="2" class="question-row-summary">
                                    Mientras que el <strong>{{ $formatValue($dissatisfiedPercentage) }}%</strong> manifiesta estar <strong>INSATISFECHO</strong>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" class="question-row-analysis">
                                    <p>{{ $questionSummaryParagraph }}</p>
                                    <p>{{ $questionActionParagraphs[$question['number']] ?? 'En atencion a estos resultados, se continuaran fortaleciendo las acciones de mejora orientadas a elevar la satisfaccion de los usuarios.' }}</p>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" class="question-row-chart">
                                    <div class="question-chart-figure">
                                        <img src="{{ $chartImages['question_results'][$index] ?? '' }}" alt="Resultado pregunta {{ $question['number'] }}" class="chart-image">
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td colspan="3" class="question-row-caption">
                                    <strong>Grafica {{ $graphNumber }}.</strong> Resultado de la medicion de {{ $dimensionLower }} {{ $scopeInstitutional }}.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach

        <section class="page page-with-decor balanced-right-spacing">
            @include('reportes.pdf.parciales.page-decor')

            <div class="content-block" style="margin-right: 116px !important;">
                <h2 class="section-title">7. ANALISIS DE LA ENCUESTA</h2>
                <p class="section-text">
                    Con base en los resultados obtenidos a traves de la encuesta de satisfaccion aplicada durante el periodo evaluado, se realizo el analisis integral de la percepcion de los usuarios frente a la prestacion del servicio {{ $scopeInstitutional }}.
                </p>
                <p class="section-text">
                    El analisis contempla la revision de las respuestas relacionadas con la oportunidad y calidad de la atencion, las condiciones del entorno para la prestacion del servicio, el cumplimiento de necesidades y expectativas, la claridad y pertinencia de la informacion suministrada, asi como la comunicacion incluyente y respetuosa durante la atencion.
                </p>
                <p class="section-text">
                    Los resultados evidencian, en terminos generales, una percepcion mayoritariamente favorable por parte de los usuarios. No obstante, las percepciones neutras e insatisfactorias identificadas constituyen oportunidades de mejora que seran consideradas en la formulacion de acciones orientadas al fortalecimiento continuo del servicio.
                </p>
                <p class="section-text">
                    Este ejercicio de medicion permite realizar seguimiento a la satisfaccion de los grupos de interes y apoyar la toma de decisiones en el marco del Sistema Integrado de Gestion.
                </p>
            </div>
        </section>

        <section class="page page-with-decor balanced-right-spacing">
            @include('reportes.pdf.parciales.page-decor')

            <div class="content-block-full" style="margin-right: 116px !important;">
                <h2 class="section-title">8. INDICADOR DE MEDICION DE LA SATISFACCION GLOBAL DE LOS USUARIOS {{ $scopeIndicatorTitle }}</h2>
                <p class="section-text">
                    Con base en los resultados consolidados de la encuesta aplicada durante el periodo evaluado, se calculo el indicador de satisfaccion global de los usuarios, el cual permite medir el nivel general de percepcion frente a la calidad del servicio prestado.
                </p>
                <p class="section-text">
                    Durante el periodo evaluado, el indicador global de satisfaccion alcanzo un valor de {{ $formatValue($globalIndicator['satisfaction_percentage'] ?? 0) }}%, con {{ $formatValue($globalIndicator['neutral_answer_percentage'] ?? 0) }}% de respuestas neutras y {{ $formatValue($globalIndicator['dissatisfaction_answer_percentage'] ?? 0) }}% de respuestas insatisfactorias, sobre un total de {{ $answerCount }} respuestas consolidadas.
                </p>

                <p class="table-title">Tabla 3. Consolidado {{ $quarterLabel ?? '' }}</p>
                <table class="consolidated-table">
                    <thead>
                        <tr>
                            <th>Pregunta</th>
                            <th>Categoria</th>
                            <th>Nro. usuarios satisfechos</th>
                            <th>Nro. usuarios neutros</th>
                            <th>Nro. usuarios insatisfechos</th>
                            <th>Total encuestados</th>
                            <th>Indicador</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (($consolidated['rows'] ?? []) as $row)
                            <tr>
                                <td>{{ $row['question_number'] }}</td>
                                <td>{{ $row['categoria'] }}</td>
                                <td>{{ $row['usuarios_satisfechos'] }}</td>
                                <td>{{ $row['usuarios_neutros'] }}</td>
                                <td>{{ $row['usuarios_insatisfechos'] }}</td>
                                <td>{{ $row['total'] }}</td>
                                <td>{{ $formatValue($row['indicador_porcentaje']) }}%</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="2"><strong>Promedio</strong></td>
                            <td><strong>{{ $formatValue($consolidated['summary']['usuarios_satisfechos'] ?? 0) }}</strong></td>
                            <td><strong>{{ $formatValue($consolidated['summary']['usuarios_neutros'] ?? 0) }}</strong></td>
                            <td><strong>{{ $formatValue($consolidated['summary']['usuarios_insatisfechos'] ?? 0) }}</strong></td>
                            <td><strong>{{ $formatValue($consolidated['summary']['total'] ?? 0) }}</strong></td>
                            <td><strong>{{ $formatValue($consolidated['summary']['indicador_porcentaje'] ?? 0) }}%</strong></td>
                        </tr>
                    </tbody>
                </table>

                <div class="indicator-chart">
                    <div class="chart-shell">
                        <img src="{{ $chartImages['satisfied_users_percentage'] ?? '' }}" alt="Indicador por categoria" class="chart-image">
                    </div>
                    <p class="chart-caption">Grafica {{ $questionChartOffset + count($report['questions'] ?? []) }}. Indicador de satisfaccion por categoria</p>
                </div>
            </div>
        </section>

        <section class="page page-with-decor balanced-right-spacing">
            @include('reportes.pdf.parciales.page-decor')

            <div class="content-block" style="margin-right: 116px !important;">
                <h2 class="section-title">9. CONCLUSIONES DE LA MEDICION DE LA SATISFACCION DE LOS USUARIOS {{ $scopeIndicatorTitle }}</h2>
                <p class="section-text">
                    Con base en los resultados obtenidos durante {{ $quarterLabel ?? 'el periodo evaluado' }} de {{ $coverYear }}, se concluye que {{ $scopeSentence }} presenta un nivel de satisfaccion general del {{ $formatValue($globalIndicator['satisfaction_percentage'] ?? 0) }}%, a partir de la percepcion de los usuarios que participaron en la medicion del servicio.
                </p>
                <p class="section-text">
                    Los aspectos relacionados con la oportunidad y calidad de la atencion, la informacion suministrada, el cumplimiento de necesidades y expectativas, las condiciones del entorno de prestacion del servicio y la comunicacion institucional reflejan una percepcion mayoritariamente positiva, lo que evidencia el compromiso del equipo de trabajo con la prestacion de un servicio eficiente, pertinente y orientado al usuario.
                </p>
                <p class="section-text">
                    {{ $resolvedGeneratedConclusion ?? 'Asi mismo, las respuestas clasificadas como neutras e insatisfechas constituyen un insumo relevante para el analisis interno del proceso, permitiendo identificar oportunidades de mejora y fortalecer las estrategias orientadas a optimizar la experiencia del usuario y consolidar la mejora continua.' }}
                </p>

                @if ($signature)
                    <div class="signature-block">
                        <p class="signature-name">{{ $signature['name'] }}</p>
                        <p class="signature-title">{{ $signature['title'] }}</p>
                        <p class="signature-scope">{{ $signature['scope'] }}</p>
                    </div>
                @endif
            </div>
        </section>
    </body>
</html>
