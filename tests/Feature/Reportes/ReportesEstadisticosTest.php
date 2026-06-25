<?php

namespace Tests\Feature\Reportes;

use App\Models\Dependencia;
use App\Models\Estamento;
use App\Models\Proceso;
use App\Models\Programa;
use App\Models\ReportingQuarter;
use App\Models\Respuesta;
use App\Models\Servicio;
use App\Models\User;
use App\Services\Reportes\ServicioImagenesGraficosPdf;
use App\Services\Reportes\ServicioReportes;
use Carbon\CarbonImmutable;
use Database\Seeders\EstamentoSeeder;
use Database\Seeders\EstructuraOrganizacionalSeeder;
use Database\Seeders\ProgramaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReportesEstadisticosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        ini_set('memory_limit', '512M');
        config(['logging.default' => 'null']);
        $compiledPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'medicion-views';

        if (! is_dir($compiledPath)) {
            mkdir($compiledPath, 0777, true);
        }

        config(['view.compiled' => $compiledPath]);

        $this->seed([
            EstamentoSeeder::class,
            ProgramaSeeder::class,
            EstructuraOrganizacionalSeeder::class,
        ]);
    }

    public function test_report_service_calculates_expected_statistics_and_indicators(): void
    {
        [$serviceA, $serviceB] = $this->sampleServicesInDifferentProcesses();

        $estudiante = Estamento::query()->where('nombre', 'Estudiante')->firstOrFail();
        $administrativo = Estamento::query()->where('nombre', 'Administrativo')->firstOrFail();
        $programa = Programa::query()->firstOrFail();

        $this->storeResponse(
            $estudiante->id_estamento,
            $programa->id_programa,
            $serviceA->id_proceso,
            $serviceA->id_dependencia,
            $serviceA->id_servicio,
            [5, 4, 3, 2, 1],
            '2026-01-05 10:00:00'
        );

        $this->storeResponse(
            $administrativo->id_estamento,
            null,
            $serviceB->id_proceso,
            $serviceB->id_dependencia,
            $serviceB->id_servicio,
            [4, 4, 4, 4, 4],
            '2026-01-08 15:00:00'
        );

        $this->storeResponse(
            $estudiante->id_estamento,
            $programa->id_programa,
            $serviceA->id_proceso,
            $serviceA->id_dependencia,
            $serviceA->id_servicio,
            [1, 2, 3, 4, 5],
            '2026-01-12 09:30:00'
        );

        $this->storeResponse(
            $estudiante->id_estamento,
            $programa->id_programa,
            $serviceA->id_proceso,
            $serviceA->id_dependencia,
            $serviceA->id_servicio,
            [5, 5, 5, 5, 5],
            '2025-12-20 09:30:00'
        );

        $service = app(ServicioReportes::class);
        $report = $service->generate('general', '2026-01-01', '2026-01-31');

        $this->assertSame(3, $report['totals']['survey_count']);
        $this->assertSame(15, $report['totals']['answer_count']);
        $this->assertCount(5, $report['questions']);
        $this->assertCount(5, $report['tables']['satisfaction_consolidated']);
        $this->assertCount(5, $report['charts']['question_results']);

        $questionOne = collect($report['questions'])->firstWhere('number', 1);
        $this->assertNotNull($questionOne);
        $this->assertSame(1, $questionOne['frequencies'][0]['frequency']);
        $this->assertSame(1, $questionOne['frequencies'][3]['frequency']);
        $this->assertSame(1, $questionOne['frequencies'][4]['frequency']);
        $this->assertEquals(66.67, $questionOne['satisfaction']['satisfied_percentage']);

        $programRows = collect($report['tables']['by_program'])->keyBy('programa');
        $this->assertSame(2, $programRows[$programa->nombre]['encuestas']);
        $this->assertSame(1, $programRows['Sin programa']['encuestas']);

        $measurementSummary = $report['tables']['measurement_consolidated']['summary'];
        $this->assertEquals(1.8, $measurementSummary['usuarios_satisfechos']);
        $this->assertEquals(0.4, $measurementSummary['usuarios_neutros']);
        $this->assertEquals(0.8, $measurementSummary['usuarios_insatisfechos']);
        $this->assertEquals(3.0, $measurementSummary['total']);
        $this->assertEquals(60.0, $measurementSummary['indicador_porcentaje']);
        $this->assertEquals(60.0, $report['indicators']['global']['satisfaction_percentage']);

        $renderedExport = view('reportes.exportar', [
            'chartImages' => [
                'population_by_program' => '',
                'population_by_estamento' => '',
                'services' => '',
                'question_results' => array_fill(0, 5, ''),
                'satisfied_users_percentage' => '',
            ],
            'contextRows' => [],
            'description' => 'Reporte general',
            'printFallback' => false,
            'report' => $report,
            'reportType' => 'general',
            'signature' => null,
            'title' => 'Reporte general',
        ])->render();

        $this->assertStringContainsString('<strong>Promedio</strong>', $renderedExport);

        $processFiltered = $service->generate('process', '2026-01-01', '2026-01-31', $serviceA->id_proceso);
        $this->assertSame(2, $processFiltered['totals']['survey_count']);

        $dependencyFiltered = $service->generate('individual', '2026-01-01', '2026-01-31', null, $serviceB->id_dependencia);
        $this->assertSame(1, $dependencyFiltered['totals']['survey_count']);
        $dependencyRow = $dependencyFiltered['tables']['scope_population']['rows'][0] ?? null;
        $dependencyName = Dependencia::query()->findOrFail($serviceB->id_dependencia)->nombre;
        $this->assertNotNull($dependencyRow);
        $this->assertSame($dependencyName, $dependencyRow['label']);
        $this->assertSame(1, $dependencyRow['total']);
    }

    public function test_general_report_route_uses_the_selected_quarter_configuration(): void
    {
        CarbonImmutable::setTestNow('2026-03-14 09:00:00');

        try {
            [$serviceA] = $this->sampleServicesInDifferentProcesses();

            $estudiante = Estamento::query()->where('nombre', 'Estudiante')->firstOrFail();
            $programa = Programa::query()->firstOrFail();
            $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);

            ReportingQuarter::query()->create([
                'year' => 2026,
                'quarter_number' => 1,
                'start_date' => '2026-01-10',
                'end_date' => '2026-03-31',
                'updated_by' => $admin->id,
            ]);

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $serviceA->id_servicio,
                [5, 5, 5, 5, 5],
                '2026-01-09 08:00:00'
            );

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $serviceA->id_servicio,
                [4, 4, 4, 4, 4],
                '2026-01-10 08:00:00'
            );

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $serviceA->id_servicio,
                [3, 3, 3, 3, 3],
                '2026-03-31 08:00:00'
            );

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $serviceA->id_servicio,
                [2, 2, 2, 2, 2],
                '2026-04-01 08:00:00'
            );

            $response = $this->actingAs($admin)
                ->get(route('reports.general', ['trimestre' => 1]));

            $response->assertOk();
            $response->assertViewHas('selectedQuarterNumber', 1);
            $response->assertViewHas('selectedQuarterPeriod', '10/01/2026 a 31/03/2026');
            $response->assertViewHas('report', fn (array $report): bool => $report['totals']['survey_count'] === 2
                && $report['from'] === '2026-01-10'
                && $report['to'] === '2026-03-31');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_export_view_renders_full_page_cover_for_each_report_type(): void
    {
        CarbonImmutable::setTestNow('2026-03-14 09:00:00');

        try {
            [$serviceA] = $this->sampleServicesInDifferentProcesses();

            $estudiante = Estamento::query()->where('nombre', 'Estudiante')->firstOrFail();
            $programa = Programa::query()->firstOrFail();
            $dependency = Dependencia::query()->findOrFail($serviceA->id_dependencia);
            $processName = (string) ($dependency->proceso?->nombre ?? 'Proceso seleccionado');
            $dependencyName = (string) $dependency->nombre;
            $processLeaderName = 'BRENDA BRITO ARREGOCES';
            $dependencyLeaderName = 'CARLOS PEREZ IGUARAN';

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $serviceA->id_servicio,
                [4, 4, 4, 4, 4],
                '2026-10-15 08:00:00'
            );

            $report = app(ServicioReportes::class)->generate('general', '2026-10-01', '2026-12-31');
            $chartImages = app(ServicioImagenesGraficosPdf::class)->build($report);

            $generalHtml = view('reportes.exportar', [
                'chartImages' => $chartImages,
                'contextRows' => [
                    ['label' => 'Trimestre', 'value' => 'IV Trimestre'],
                    ['label' => 'Periodo', 'value' => '01/10/2026 a 31/12/2026'],
                ],
                'description' => 'Reporte general',
                'printFallback' => false,
                'report' => $report,
                'reportType' => 'general',
                'signature' => null,
                'title' => 'Reporte general',
            ])->render();

            $processHtml = view('reportes.exportar', [
                'chartImages' => $chartImages,
                'contextRows' => [
                    ['label' => 'Trimestre', 'value' => 'IV Trimestre'],
                    ['label' => 'Periodo', 'value' => '01/10/2026 a 31/12/2026'],
                    ['label' => 'Proceso', 'value' => $processName],
                ],
                'description' => 'Reporte por proceso',
                'printFallback' => false,
                'report' => $report,
                'reportType' => 'process',
                'signature' => [
                    'name' => $processLeaderName,
                    'title' => 'Lider del proceso de',
                    'scope' => mb_strtoupper($processName, 'UTF-8'),
                ],
                'title' => 'Reporte por proceso',
            ])->render();

            $dependencyHtml = view('reportes.exportar', [
                'chartImages' => $chartImages,
                'contextRows' => [
                    ['label' => 'Trimestre', 'value' => 'IV Trimestre'],
                    ['label' => 'Periodo', 'value' => '01/10/2026 a 31/12/2026'],
                    ['label' => 'Proceso', 'value' => $processName],
                    ['label' => 'Dependencia', 'value' => $dependencyName],
                ],
                'description' => 'Reporte individual',
                'printFallback' => false,
                'report' => $report,
                'reportType' => 'individual',
                'signature' => [
                    'name' => $dependencyLeaderName,
                    'title' => 'Lider de dependencia de',
                    'scope' => mb_strtoupper($dependencyName, 'UTF-8'),
                ],
                'title' => 'Reporte individual',
            ])->render();

            $this->assertStringContainsString('class="cover-image"', $generalHtml);
            $this->assertStringContainsString('class="cover-image"', $processHtml);
            $this->assertStringContainsString('class="cover-image"', $dependencyHtml);
            $this->assertStringContainsString('2026', $generalHtml);
            $this->assertStringNotContainsString('class="cover-logo"', $generalHtml);
            $this->assertStringNotContainsString('class="cover-copy"', $generalHtml);
            $this->assertStringContainsString(
                '9. CONCLUSIONES DE LA MEDICION DE LA SATISFACCION DE LOS USUARIOS DE LOS PROCESOS EVALUADOS',
                $generalHtml
            );
            $this->assertStringContainsString(
                '9. CONCLUSIONES DE LA MEDICION DE LA SATISFACCION DE LOS USUARIOS DEL PROCESO '.mb_strtoupper($processName, 'UTF-8'),
                $processHtml
            );
            $this->assertStringContainsString(
                '9. CONCLUSIONES DE LA MEDICION DE LA SATISFACCION DE LOS USUARIOS DE LA DEPENDENCIA '.mb_strtoupper($dependencyName, 'UTF-8'),
                $dependencyHtml
            );
            $this->assertStringContainsString(
                'Evaluar el nivel de satisfaccion de los usuarios frente al servicio prestado por la dependencia '.$dependencyName,
                $dependencyHtml
            );
            $this->assertStringContainsString(
                'La encuesta de satisfaccion fue aplicada durante IV Trimestre de 2026 a 1 usuario que recibio atencion de los procesos evaluados, de la Universidad de La Guajira.',
                $generalHtml
            );
            $this->assertStringContainsString(
                'se concluye que el proceso '.$processName.' presenta un nivel de satisfaccion general del 100%',
                $processHtml
            );
            $this->assertStringContainsString(
                'se concluye que la dependencia '.$dependencyName.' presenta un nivel de satisfaccion general del 100%',
                $dependencyHtml
            );
            $this->assertStringContainsString(
                'Asi mismo, las respuestas clasificadas como neutras e insatisfechas constituyen un insumo relevante para el analisis interno del proceso',
                $generalHtml
            );
            $this->assertStringNotContainsString($processLeaderName, $generalHtml);
            $this->assertStringContainsString($processLeaderName, $processHtml);
            $this->assertStringContainsString('Lider del proceso de', $processHtml);
            $this->assertStringContainsString(mb_strtoupper($processName, 'UTF-8'), $processHtml);
            $this->assertStringContainsString($dependencyLeaderName, $dependencyHtml);
            $this->assertStringContainsString('Lider de dependencia de', $dependencyHtml);
            $this->assertStringContainsString(mb_strtoupper($dependencyName, 'UTF-8'), $dependencyHtml);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_general_report_view_requires_ai_confirmation_before_enabling_pdf_download(): void
    {
        CarbonImmutable::setTestNow('2026-03-14 09:00:00');

        try {
            [$serviceA] = $this->sampleServicesInDifferentProcesses();

            $estudiante = Estamento::query()->where('nombre', 'Estudiante')->firstOrFail();
            $programa = Programa::query()->firstOrFail();
            $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);

            ReportingQuarter::query()->create([
                'year' => 2026,
                'quarter_number' => 1,
                'start_date' => '2026-01-10',
                'end_date' => '2026-03-31',
                'updated_by' => $admin->id,
            ]);

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $serviceA->id_servicio,
                [4, 4, 4, 4, 4],
                '2026-01-10 08:00:00',
                'El servicio fue agil, pero la informacion inicial no fue tan clara.'
            );

            $response = $this->actingAs($admin)
                ->get(route('reports.general', ['trimestre' => 1]));

            $response->assertOk();
            $response->assertSee('data-report-conclusion-shell', false);
            $response->assertSee('data-report-pdf-button', false);
            $response->assertSee('disabled', false);
            $response->assertSee('Genera y confirma la conclusion para habilitar la descarga del PDF.', false);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_general_report_conclusion_endpoint_returns_ai_generated_text(): void
    {
        CarbonImmutable::setTestNow('2026-03-14 09:00:00');

        try {
            [$serviceA] = $this->sampleServicesInDifferentProcesses();

            $estudiante = Estamento::query()->where('nombre', 'Estudiante')->firstOrFail();
            $programa = Programa::query()->firstOrFail();
            $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);

            ReportingQuarter::query()->create([
                'year' => 2026,
                'quarter_number' => 1,
                'start_date' => '2026-01-10',
                'end_date' => '2026-03-31',
                'updated_by' => $admin->id,
            ]);

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $serviceA->id_servicio,
                [4, 4, 3, 4, 4],
                '2026-01-10 08:00:00',
                'La atencion fue amable, aunque el tiempo de espera pudo ser menor.'
            );

            config([
                'services.openai.key' => 'test-key',
                'services.openai.model' => 'gpt-5-mini',
            ]);

            Http::fake([
                'https://api.openai.com/v1/responses' => Http::response([
                    'output_text' => json_encode([
                        'conclusion' => 'La percepcion general del servicio fue favorable, aunque se identifican oportunidades de mejora en la agilidad de atencion y en la claridad de la informacion inicial.',
                    ], JSON_UNESCAPED_UNICODE),
                ]),
            ]);

            $response = $this->actingAs($admin)->postJson(route('reports.general.conclusion'), [
                'trimestre' => 1,
            ]);

            $response->assertOk();
            $response->assertJson([
                'conclusion' => 'La percepcion general del servicio fue favorable, aunque se identifican oportunidades de mejora en la agilidad de atencion y en la claridad de la informacion inicial.',
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_export_view_uses_the_ai_generated_conclusion_when_available(): void
    {
        [$serviceA] = $this->sampleServicesInDifferentProcesses();

        $estudiante = Estamento::query()->where('nombre', 'Estudiante')->firstOrFail();
        $programa = Programa::query()->firstOrFail();

        $this->storeResponse(
            $estudiante->id_estamento,
            $programa->id_programa,
            $serviceA->id_proceso,
            $serviceA->id_dependencia,
            $serviceA->id_servicio,
            [4, 4, 4, 4, 4],
            '2026-10-15 08:00:00',
            'El proceso fue claro y el personal atendio con respeto.'
        );

        $report = app(ServicioReportes::class)->generate('general', '2026-10-01', '2026-12-31');
        $chartImages = app(ServicioImagenesGraficosPdf::class)->build($report);

        $html = view('reportes.exportar', [
            'chartImages' => $chartImages,
            'contextRows' => [
                ['label' => 'Trimestre', 'value' => 'IV Trimestre'],
                ['label' => 'Periodo', 'value' => '01/10/2026 a 31/12/2026'],
            ],
            'description' => 'Reporte general',
            'generatedConclusion' => 'La conclusion generada por IA destaca una experiencia global favorable y plantea seguimiento a los tiempos de respuesta para fortalecer la satisfaccion del usuario.',
            'printFallback' => false,
            'report' => $report,
            'reportType' => 'general',
            'signature' => null,
            'title' => 'Reporte general',
        ])->render();

        $this->assertStringContainsString(
            'La conclusion generada por IA destaca una experiencia global favorable y plantea seguimiento a los tiempos de respuesta para fortalecer la satisfaccion del usuario.',
            $html
        );
        $this->assertStringNotContainsString(
            'En torno a los resultados obtenidos se presento un 0% donde los usuarios perciben un servicio ni satisfactorio ni insatisfactorio',
            $html
        );
    }

    public function test_general_report_pdf_can_be_downloaded_for_a_quarter(): void
    {
        CarbonImmutable::setTestNow('2026-03-14 09:00:00');

        try {
            [$serviceA] = $this->sampleServicesInDifferentProcesses();

            $estudiante = Estamento::query()->where('nombre', 'Estudiante')->firstOrFail();
            $programa = Programa::query()->firstOrFail();
            $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);
            User::factory()->create([
                'nombre' => 'BRENDA BRITO ARREGOCES',
                'rol' => User::ROLE_LIDER_PROCESO,
                'id_proceso' => $serviceA->id_proceso,
                'id_dependencia' => null,
            ]);

            ReportingQuarter::query()->create([
                'year' => 2026,
                'quarter_number' => 1,
                'start_date' => '2026-01-10',
                'end_date' => '2026-03-31',
                'updated_by' => $admin->id,
            ]);

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $serviceA->id_servicio,
                [4, 4, 4, 4, 4],
                '2026-01-10 08:00:00'
            );

            $response = $this->actingAs($admin)
                ->get(route('reports.general', [
                    'trimestre' => 1,
                    'export_pdf' => 1,
                    'generated_conclusion' => 'Conclusion final validada para el reporte general.',
                ]));

            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/pdf');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_process_report_pdf_can_be_downloaded_with_assigned_leader(): void
    {
        CarbonImmutable::setTestNow('2026-03-14 09:00:00');

        try {
            [$serviceA] = $this->sampleServicesInDifferentProcesses();

            $estudiante = Estamento::query()->where('nombre', 'Estudiante')->firstOrFail();
            $programa = Programa::query()->firstOrFail();
            $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);

            User::factory()->create([
                'nombre' => 'BRENDA BRITO ARREGOCES',
                'rol' => User::ROLE_LIDER_PROCESO,
                'id_proceso' => $serviceA->id_proceso,
                'id_dependencia' => null,
            ]);

            User::factory()->create([
                'nombre' => 'CARLOS PEREZ IGUARAN',
                'rol' => User::ROLE_LIDER_DEPENDENCIA,
                'id_proceso' => $serviceA->id_proceso,
                'id_dependencia' => $serviceA->id_dependencia,
            ]);

            ReportingQuarter::query()->create([
                'year' => 2026,
                'quarter_number' => 1,
                'start_date' => '2026-01-10',
                'end_date' => '2026-03-31',
                'updated_by' => $admin->id,
            ]);

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $serviceA->id_servicio,
                [4, 4, 4, 4, 4],
                '2026-01-10 08:00:00'
            );

            $processResponse = $this->actingAs($admin)
                ->get(route('reports.process', [
                    'trimestre' => 1,
                    'id_proceso' => $serviceA->id_proceso,
                    'export_pdf' => 1,
                    'generated_conclusion' => 'Conclusion final validada para el reporte por proceso.',
                ]));

            $processResponse->assertOk();
            $processResponse->assertHeader('Content-Type', 'application/pdf');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_admin_2_0_can_access_process_reports_module(): void
    {
        $admin20 = User::factory()->create(['rol' => User::ROLE_ADMIN_2_0]);

        $this->actingAs($admin20)
            ->get(route('reports.process'))
            ->assertOk();
    }

    public function test_process_leader_can_access_individual_reports_module(): void
    {
        $leaderProcess = User::factory()->create([
            'rol' => User::ROLE_LIDER_PROCESO,
            'id_proceso' => 10,
        ]);

        $this->actingAs($leaderProcess)
            ->get(route('reports.individual'))
            ->assertOk();
    }

    public function test_process_report_pdf_cannot_be_downloaded_when_selected_process_has_no_responses(): void
    {
        CarbonImmutable::setTestNow('2026-03-14 09:00:00');

        try {
            [$serviceA] = $this->sampleServicesInDifferentProcesses();
            $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);

            ReportingQuarter::query()->create([
                'year' => 2026,
                'quarter_number' => 1,
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'updated_by' => $admin->id,
            ]);

            $response = $this->actingAs($admin)
                ->get(route('reports.process', [
                    'trimestre' => 1,
                    'id_proceso' => $serviceA->id_proceso,
                    'export_pdf' => 1,
                ]));

            $response->assertOk();
            $response->assertHeaderMissing('Content-Disposition');
            $response->assertSee('No se puede descargar el PDF porque el proceso seleccionado no tiene respuestas en el periodo.');
            $response->assertDontSee('Descargar PDF');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_individual_report_pdf_cannot_be_downloaded_when_selected_dependency_has_no_responses(): void
    {
        CarbonImmutable::setTestNow('2026-03-14 09:00:00');

        try {
            [$serviceA] = $this->sampleServicesInDifferentProcesses();
            $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);

            ReportingQuarter::query()->create([
                'year' => 2026,
                'quarter_number' => 1,
                'start_date' => '2026-01-01',
                'end_date' => '2026-03-31',
                'updated_by' => $admin->id,
            ]);

            $response = $this->actingAs($admin)
                ->get(route('reports.individual', [
                    'trimestre' => 1,
                    'id_proceso' => $serviceA->id_proceso,
                    'id_dependencia' => $serviceA->id_dependencia,
                    'id_servicios' => [$serviceA->id_servicio],
                    'export_pdf' => 1,
                ]));

            $response->assertOk();
            $response->assertHeaderMissing('Content-Disposition');
            $response->assertSee('No se puede descargar el PDF porque los servicios seleccionados no tienen respuestas en el periodo.');
            $response->assertDontSee('Descargar PDF');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_individual_report_filters_results_by_selected_services(): void
    {
        CarbonImmutable::setTestNow('2026-03-14 09:00:00');

        try {
            [$serviceA] = $this->sampleServicesInDifferentProcesses();

            $estudiante = Estamento::query()->where('nombre', 'Estudiante')->firstOrFail();
            $programa = Programa::query()->firstOrFail();
            $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);

            ReportingQuarter::query()->create([
                'year' => 2026,
                'quarter_number' => 1,
                'start_date' => '2026-01-10',
                'end_date' => '2026-03-31',
                'updated_by' => $admin->id,
            ]);

            $secondaryService = Servicio::query()->create([
                'id_dependencia' => $serviceA->id_dependencia,
                'nombre' => 'Servicio complementario de prueba',
                'activo' => true,
            ]);

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $serviceA->id_servicio,
                [4, 4, 4, 4, 4],
                '2026-01-10 08:00:00'
            );

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $secondaryService->id_servicio,
                [5, 5, 5, 5, 5],
                '2026-01-11 08:00:00'
            );

            $response = $this->actingAs($admin)
                ->get(route('reports.individual', [
                    'trimestre' => 1,
                    'id_proceso' => $serviceA->id_proceso,
                    'id_dependencia' => $serviceA->id_dependencia,
                    'id_servicios' => [$secondaryService->id_servicio],
                ]));

            $response->assertOk();
            $response->assertSee('name="id_servicios[]"', false);
            $response->assertSee('Servicio complementario de prueba');
            $response->assertViewHas('selectedServiceIds', fn (array $serviceIds): bool => $serviceIds === [(int) $secondaryService->id_servicio]);
            $response->assertViewHas('report', fn (array $report): bool => $report['totals']['survey_count'] === 1
                && count($report['tables']['services']) === 1
                && ($report['tables']['services'][0]['servicio'] ?? null) === 'Servicio complementario de prueba'
                && ($report['tables']['scope_population']['rows'][0]['label'] ?? null) === 'Servicio complementario de prueba');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_individual_report_keeps_dependency_scope_for_processes_without_service_filter(): void
    {
        CarbonImmutable::setTestNow('2026-03-14 09:00:00');

        try {
            [, $serviceB] = $this->sampleServicesInDifferentProcesses();

            $estudiante = Estamento::query()->where('nombre', 'Estudiante')->firstOrFail();
            $programa = Programa::query()->firstOrFail();
            $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);

            ReportingQuarter::query()->create([
                'year' => 2026,
                'quarter_number' => 1,
                'start_date' => '2026-01-10',
                'end_date' => '2026-03-31',
                'updated_by' => $admin->id,
            ]);

            $secondaryService = Servicio::query()->create([
                'id_dependencia' => $serviceB->id_dependencia,
                'nombre' => 'Servicio alterno proceso sin filtro',
                'activo' => true,
            ]);

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceB->id_proceso,
                $serviceB->id_dependencia,
                $serviceB->id_servicio,
                [4, 4, 4, 4, 4],
                '2026-01-10 08:00:00'
            );

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceB->id_proceso,
                $serviceB->id_dependencia,
                $secondaryService->id_servicio,
                [5, 5, 5, 5, 5],
                '2026-01-11 08:00:00'
            );

            $dependencyName = Dependencia::query()->findOrFail($serviceB->id_dependencia)->nombre;

            $response = $this->actingAs($admin)
                ->get(route('reports.individual', [
                    'trimestre' => 1,
                    'id_proceso' => $serviceB->id_proceso,
                    'id_dependencia' => $serviceB->id_dependencia,
                ]));

            $response->assertOk();
            $response->assertDontSee('name="id_servicios[]"', false);
            $response->assertViewHas('serviceSelectionEnabled', false);
            $response->assertViewHas('selectedServiceIds', fn (array $serviceIds): bool => $serviceIds === []);
            $response->assertViewHas('report', fn (array $report): bool => $report['totals']['survey_count'] === 2
                && count($report['tables']['services']) === 2
                && ($report['tables']['scope_population']['rows'][0]['label'] ?? null) === $dependencyName);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_individual_report_services_endpoint_returns_services_for_selected_dependency(): void
    {
        [$serviceA] = $this->sampleServicesInDifferentProcesses();

        $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);
        $baseService = Servicio::query()->findOrFail($serviceA->id_servicio);

        $extraService = Servicio::query()->create([
            'id_dependencia' => $serviceA->id_dependencia,
            'nombre' => 'Servicio JSON de prueba',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('reports.individual.services', [
                'id_dependencia' => $serviceA->id_dependencia,
            ]));

        $response->assertOk();
        $response->assertJsonFragment([
            'id' => $baseService->id_servicio,
            'nombre' => $baseService->nombre,
        ]);
        $response->assertJsonFragment([
            'id' => $extraService->id_servicio,
            'nombre' => 'Servicio JSON de prueba',
        ]);
    }

    public function test_individual_report_services_endpoint_returns_empty_for_other_processes(): void
    {
        [, $serviceB] = $this->sampleServicesInDifferentProcesses();

        $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);

        Servicio::query()->create([
            'id_dependencia' => $serviceB->id_dependencia,
            'nombre' => 'Servicio JSON no visible',
            'activo' => true,
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('reports.individual.services', [
                'id_dependencia' => $serviceB->id_dependencia,
            ]));

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_individual_report_view_requires_confirmed_conclusion_even_without_observations(): void
    {
        CarbonImmutable::setTestNow('2026-03-14 09:00:00');

        try {
            [$serviceA] = $this->sampleServicesInDifferentProcesses();

            $estudiante = Estamento::query()->where('nombre', 'Estudiante')->firstOrFail();
            $programa = Programa::query()->firstOrFail();
            $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);

            ReportingQuarter::query()->create([
                'year' => 2026,
                'quarter_number' => 1,
                'start_date' => '2026-01-10',
                'end_date' => '2026-03-31',
                'updated_by' => $admin->id,
            ]);

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $serviceA->id_servicio,
                [4, 4, 4, 4, 4],
                '2026-01-10 08:00:00'
            );

            $response = $this->actingAs($admin)
                ->get(route('reports.individual', [
                    'trimestre' => 1,
                    'id_proceso' => $serviceA->id_proceso,
                    'id_dependencia' => $serviceA->id_dependencia,
                    'id_servicios' => [$serviceA->id_servicio],
                ]));

            $response->assertOk();
            $response->assertSee('data-report-conclusion-shell', false);
            $response->assertSee('data-report-pdf-button', false);
            $response->assertSee('data-can-generate-conclusion="0"', false);
            $response->assertSee('Genera y confirma la conclusion para habilitar la descarga del PDF.', false);
            $response->assertSee(
                'No se registraron observaciones recientes para este periodo. Redacta y confirma la conclusion manualmente para habilitar la descarga del PDF.',
                false
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_individual_report_pdf_requires_confirmed_conclusion_before_downloading(): void
    {
        CarbonImmutable::setTestNow('2026-03-14 09:00:00');

        try {
            [$serviceA] = $this->sampleServicesInDifferentProcesses();

            $estudiante = Estamento::query()->where('nombre', 'Estudiante')->firstOrFail();
            $programa = Programa::query()->firstOrFail();
            $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);

            ReportingQuarter::query()->create([
                'year' => 2026,
                'quarter_number' => 1,
                'start_date' => '2026-01-10',
                'end_date' => '2026-03-31',
                'updated_by' => $admin->id,
            ]);

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $serviceA->id_servicio,
                [4, 4, 4, 4, 4],
                '2026-01-10 08:00:00'
            );

            $response = $this->actingAs($admin)
                ->get(route('reports.individual', [
                    'trimestre' => 1,
                    'id_proceso' => $serviceA->id_proceso,
                    'id_dependencia' => $serviceA->id_dependencia,
                    'id_servicios' => [$serviceA->id_servicio],
                    'export_pdf' => 1,
                ]));

            $response->assertOk();
            $response->assertHeaderMissing('Content-Disposition');
            $response->assertSee('Debes generar o confirmar la conclusion antes de descargar el PDF.');
            $response->assertSee('data-report-conclusion-shell', false);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_individual_report_pdf_can_be_downloaded_after_confirming_conclusion(): void
    {
        CarbonImmutable::setTestNow('2026-03-14 09:00:00');

        try {
            [$serviceA] = $this->sampleServicesInDifferentProcesses();

            $estudiante = Estamento::query()->where('nombre', 'Estudiante')->firstOrFail();
            $programa = Programa::query()->firstOrFail();
            $admin = User::factory()->create(['rol' => User::ROLE_ADMIN]);

            User::factory()->create([
                'nombre' => 'CARLOS PEREZ IGUARAN',
                'rol' => User::ROLE_LIDER_DEPENDENCIA,
                'id_proceso' => $serviceA->id_proceso,
                'id_dependencia' => $serviceA->id_dependencia,
            ]);

            ReportingQuarter::query()->create([
                'year' => 2026,
                'quarter_number' => 1,
                'start_date' => '2026-01-10',
                'end_date' => '2026-03-31',
                'updated_by' => $admin->id,
            ]);

            $this->storeResponse(
                $estudiante->id_estamento,
                $programa->id_programa,
                $serviceA->id_proceso,
                $serviceA->id_dependencia,
                $serviceA->id_servicio,
                [4, 4, 4, 4, 4],
                '2026-01-10 08:00:00'
            );

            $response = $this->actingAs($admin)
                ->get(route('reports.individual', [
                    'trimestre' => 1,
                    'id_proceso' => $serviceA->id_proceso,
                    'id_dependencia' => $serviceA->id_dependencia,
                    'id_servicios' => [$serviceA->id_servicio],
                    'export_pdf' => 1,
                    'generated_conclusion' => 'Conclusion final validada para el reporte individual.',
                ]));

            $response->assertOk();
            $response->assertHeader('Content-Type', 'application/pdf');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * @return array{0: object{id_servicio: int, id_dependencia: int, id_proceso: int}, 1: object{id_servicio: int, id_dependencia: int, id_proceso: int}}
     */
    private function sampleServicesInDifferentProcesses(): array
    {
        $targetProcessId = Proceso::query()
            ->where('nombre', 'Gestion Bienestar Social Universitario')
            ->value('id_proceso');

        $dependencyA = Dependencia::query()
            ->where('id_proceso', $targetProcessId)
            ->orderBy('id_dependencia')
            ->firstOrFail();
        $dependencyB = Dependencia::query()
            ->where('id_proceso', '!=', $dependencyA->id_proceso)
            ->orderBy('id_dependencia')
            ->firstOrFail();

        $serviceA = Servicio::query()->where('id_dependencia', $dependencyA->id_dependencia)->firstOrFail();
        $serviceB = Servicio::query()->where('id_dependencia', $dependencyB->id_dependencia)->firstOrFail();

        return [
            (object) [
                'id_servicio' => (int) $serviceA->id_servicio,
                'id_dependencia' => (int) $dependencyA->id_dependencia,
                'id_proceso' => (int) $dependencyA->id_proceso,
            ],
            (object) [
                'id_servicio' => (int) $serviceB->id_servicio,
                'id_dependencia' => (int) $dependencyB->id_dependencia,
                'id_proceso' => (int) $dependencyB->id_proceso,
            ],
        ];
    }

    /**
     * @param  array{0: int, 1: int, 2: int, 3: int, 4: int}  $answers
     */
    private function storeResponse(
        int $estamentoId,
        ?int $programaId,
        int $procesoId,
        int $dependenciaId,
        int $servicioId,
        array $answers,
        string $fechaRespuesta,
        ?string $observaciones = null
    ): void {
        Respuesta::query()->create([
            'id_estamento' => $estamentoId,
            'id_programa' => $programaId,
            'id_proceso' => $procesoId,
            'id_dependencia' => $dependenciaId,
            'id_servicio' => $servicioId,
            'pregunta1' => $answers[0],
            'pregunta2' => $answers[1],
            'pregunta3' => $answers[2],
            'pregunta4' => $answers[3],
            'pregunta5' => $answers[4],
            'observaciones' => $observaciones,
            'fecha_respuesta' => $fechaRespuesta,
        ]);
    }
}
