<?php

namespace App\Services\Reportes;

use App\Models\Dependencia;
use App\Models\Proceso;
use App\Models\Respuesta;
use App\Models\Servicio;
use App\Support\Legacy\DatosReferenciaLegado;
use Illuminate\Database\Eloquent\Builder;

class ServicioReportes
{
    private const QUESTION_NUMBERS = [1, 2, 3, 4, 5];

    private const SCALE_LABELS = [
        1 => 'Deficiente',
        2 => 'Malo',
        3 => 'Regular',
        4 => 'Bueno',
        5 => 'Excelente',
    ];

    private const SCALE_COLORS = [
        1 => '#FF0000',
        2 => '#FFC000',
        3 => '#FFFF00',
        4 => '#B7D7A8',
        5 => '#92D050',
    ];

    private const PIE_COLORS = [
        '#2563EB',
        '#059669',
        '#EA580C',
        '#7C3AED',
        '#DC2626',
        '#0891B2',
        '#CA8A04',
        '#4338CA',
        '#BE123C',
        '#0D9488',
    ];

    private const QUESTION_DIMENSIONS = [
        1 => 'Oportunidad y calidad',
        2 => 'Condiciones y comodidad',
        3 => 'Expectativa del servicio',
        4 => 'Informacion suministrada',
        5 => 'Trato, inclusion y lenguaje',
    ];

    private const CONSOLIDATED_CATEGORIES = [
        1 => 'Oportunidad y calidad',
        2 => 'Condiciones y comodidad',
        3 => 'Expectativa del servicio',
        4 => 'Informacion suministrada',
        5 => 'Trato, inclusion y lenguaje',
    ];

    /**
     * @return array{
     *     type: string,
     *     from: string,
     *     to: string,
     *     totals: array{survey_count: int, answer_count: int, questions_count: int},
     *     tables: array{
     *          surveyed_users: array<int, array{label: string, value: string}>,
     *          by_program: array<int, array{programa: string, encuestas: int, porcentaje: float}>,
     *          by_estamento: array<int, array{estamento: string, encuestas: int, porcentaje: float}>,
     *          services: array<int, array{servicio: string, encuestas: int, porcentaje: float}>,
     *          satisfaction_consolidated: array<int, array{
     *              number: int,
     *              dimension: string,
     *              question: string,
     *              satisfechos: int,
     *              neutros: int,
     *              insatisfechos: int,
     *              porcentaje_satisfechos: float,
     *              porcentaje_neutros: float,
     *              porcentaje_insatisfechos: float
     *          }>
     *     },
     *     questions: array<int, array{
     *          number: int,
     *          dimension: string,
     *          label: string,
     *          survey_base: int,
     *          valid_responses: int,
     *          average_score: float,
     *          frequencies: array<int, array{
     *              value: int,
     *              label: string,
     *              frequency: int,
     *              percentage: float,
     *              color: string
     *          }>,
     *          satisfaction: array{
     *              satisfied: int,
     *              neutral: int,
     *              dissatisfied: int,
     *              satisfied_percentage: float,
     *              neutral_percentage: float,
     *              dissatisfied_percentage: float
     *          }
     *     }>,
     *     indicators: array{
     *          global: array{
     *              satisfied_users: float,
     *              neutral_users: float,
     *              dissatisfied_users: float,
     *              satisfaction_percentage: float,
     *              neutral_percentage: float,
     *              dissatisfaction_percentage: float,
     *              satisfied_answers: int,
     *              neutral_answers: int,
     *              dissatisfied_answers: int,
     *              satisfaction_answer_percentage: float,
     *              neutral_answer_percentage: float,
     *              dissatisfaction_answer_percentage: float
     *          },
     *          by_question: array<int, array{
     *              number: int,
     *              dimension: string,
     *              question: string,
     *              satisfechos: int,
     *              neutros: int,
     *              insatisfechos: int,
     *              porcentaje_satisfechos: float,
     *              porcentaje_neutros: float,
     *              porcentaje_insatisfechos: float
     *          }>
     *     },
     *     charts: array{
     *          population_by_program: array{
     *              type: string,
     *              title: string,
     *              items: array<int, array{label: string, value: int, percentage: float, color: string}>
     *          },
     *          population_by_estamento: array{
     *              type: string,
     *              title: string,
     *              items: array<int, array{label: string, value: int, percentage: float, color: string}>
     *          },
     *          services: array{
     *              type: string,
     *              title: string,
     *              items: array<int, array{label: string, value: int, percentage: float, color: string}>
     *          },
     *          question_results: array<int, array{
     *              type: string,
     *              title: string,
     *              subtitle: string,
     *              items: array<int, array{label: string, value: int, percentage: float, color: string}>
     *          }>,
     *          satisfaction_by_dimension: array{
     *              type: string,
     *              title: string,
     *              items: array<int, array{label: string, value: float, color: string}>
     *          }
     *     },
     *     observations: array<int, string>
     * }
     */
    public function generate(
        string $type,
        string $from,
        string $to,
        ?int $processId = null,
        ?int $dependencyId = null,
        array $serviceIds = []
    ): array
    {
        $baseQuery = $this->filteredQuery($from, $to, $processId, $dependencyId, $serviceIds);

        $totalSurveys = (clone $baseQuery)->count('respuesta.id_respuesta');
        $totalAnswers = $totalSurveys * count(self::QUESTION_NUMBERS);

        $scopePopulation = $this->getScopePopulationTable($type, $from, $to, $processId, $dependencyId, $serviceIds);
        $populationByProgram = $this->getPopulationByProgram($baseQuery, $totalSurveys);
        $populationByEstamento = $this->getPopulationByEstamento($baseQuery, $totalSurveys);
        $servicesStats = $this->getServicesStats($baseQuery, $totalSurveys);

        $questionStats = $this->getQuestionStats($baseQuery, $totalSurveys);
        $satisfactionByQuestion = $this->getSatisfactionByQuestion($questionStats);
        $measurementConsolidated = $this->getMeasurementConsolidatedTable($questionStats);
        $globalIndicator = $this->getGlobalIndicator($satisfactionByQuestion, $totalSurveys, $totalAnswers);
        $observations = $this->getObservations($baseQuery);

        $serviceChartRows = $this->limitForChart($servicesStats, 'servicio');

        return [
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'filters' => [
                'process_id' => $processId,
                'dependency_id' => $dependencyId,
                'service_ids' => array_values($serviceIds),
            ],
            'totals' => [
                'survey_count' => $totalSurveys,
                'answer_count' => $totalAnswers,
                'questions_count' => count(self::QUESTION_NUMBERS),
            ],
            'tables' => [
                'surveyed_users' => [
                    [
                        'label' => 'Numero de usuarios encuestados',
                        'value' => (string) $totalSurveys,
                    ],
                    [
                        'label' => 'Numero total de respuestas',
                        'value' => (string) $totalAnswers,
                    ],
                ],
                'by_program' => $populationByProgram,
                'by_estamento' => $populationByEstamento,
                'services' => $servicesStats,
                'scope_population' => $scopePopulation,
                'measurement_consolidated' => $measurementConsolidated,
                'satisfaction_consolidated' => $satisfactionByQuestion,
            ],
            'questions' => $questionStats,
            'indicators' => [
                'global' => $globalIndicator,
                'by_question' => $satisfactionByQuestion,
            ],
            'charts' => [
                'population_by_program' => $this->buildPieChart(
                    'Poblacion atendida por programa',
                    $populationByProgram,
                    'programa'
                ),
                'population_by_estamento' => $this->buildPieChart(
                    'Poblacion atendida por estamento',
                    $populationByEstamento,
                    'estamento'
                ),
                'services' => $this->buildHorizontalBarChart(
                    'Servicios atendidos',
                    $serviceChartRows,
                    '#1D4ED8'
                ),
                'question_results' => $this->buildQuestionCharts($questionStats),
                'satisfaction_by_dimension' => $this->buildVerticalBarChart(
                    'Porcentaje de usuarios satisfechos por dimension',
                    array_map(static fn (array $row): array => [
                        'label' => $row['dimension'],
                        'value' => $row['porcentaje_satisfechos'],
                    ], $satisfactionByQuestion)
                ),
                'satisfied_users_percentage' => $this->buildVerticalBarChart(
                    'Indicador de satisfaccion por categoria',
                    array_map(static fn (array $row): array => [
                        'label' => self::QUESTION_DIMENSIONS[$row['question_number']],
                        'value' => $row['indicador_porcentaje'],
                    ], $measurementConsolidated['rows'])
                ),
            ],
            'observations' => $observations,
        ];
    }

    /**
     * @return array<int, array{programa: string, encuestas: int, porcentaje: float}>
     */
    public function getPopulationByProgram(Builder $query, int $totalSurveys): array
    {
        return $this->groupedDistribution(
            $query,
            'programa',
            'id_programa',
            'Sin programa',
            $totalSurveys
        );
    }

    /**
     * @return array<int, array{estamento: string, encuestas: int, porcentaje: float}>
     */
    public function getPopulationByEstamento(Builder $query, int $totalSurveys): array
    {
        return $this->groupedDistribution(
            $query,
            'estamento',
            'id_estamento',
            'Sin estamento',
            $totalSurveys
        );
    }

    /**
     * @return array<int, array{servicio: string, encuestas: int, porcentaje: float}>
     */
    public function getServicesStats(Builder $query, int $totalSurveys): array
    {
        return $this->groupedDistribution(
            $query,
            'servicio',
            'id_servicio',
            'Sin servicio',
            $totalSurveys
        );
    }

    /**
     * @return array<int, array{
     *      number: int,
     *      dimension: string,
     *      label: string,
     *      survey_base: int,
     *      valid_responses: int,
     *      average_score: float,
     *      frequencies: array<int, array{
     *          value: int,
     *          label: string,
     *          frequency: int,
     *          percentage: float,
     *          color: string
     *      }>,
     *      satisfaction: array{
     *          satisfied: int,
     *          neutral: int,
     *          dissatisfied: int,
     *          satisfied_percentage: float,
     *          neutral_percentage: float,
     *          dissatisfied_percentage: float
     *      }
     * }>
     */
    public function getQuestionStats(Builder $query, int $totalSurveys): array
    {
        $rows = (clone $query)->get(array_map(
            static fn (int $questionNumber): string => 'pregunta'.$questionNumber,
            self::QUESTION_NUMBERS
        ));

        $questionLabels = DatosReferenciaLegado::questionLabels();
        $stats = [];

        foreach (self::QUESTION_NUMBERS as $questionNumber) {
            $stats[$questionNumber] = [
                'number' => $questionNumber,
                'dimension' => self::QUESTION_DIMENSIONS[$questionNumber],
                'label' => $questionLabels[$questionNumber] ?? ('Pregunta '.$questionNumber),
                'survey_base' => $totalSurveys,
                'valid_responses' => 0,
                'average_score' => 0.0,
                'score_sum' => 0,
                'frequencies' => array_map(
                    static fn (int $value): array => [
                        'value' => $value,
                        'label' => self::SCALE_LABELS[$value],
                        'frequency' => 0,
                        'percentage' => 0.0,
                        'color' => self::SCALE_COLORS[$value],
                    ],
                    array_keys(self::SCALE_LABELS)
                ),
            ];
        }

        foreach ($rows as $row) {
            foreach (self::QUESTION_NUMBERS as $questionNumber) {
                $value = (int) data_get($row, 'pregunta'.$questionNumber);

                if (! array_key_exists($value, self::SCALE_LABELS)) {
                    continue;
                }

                $index = $value - 1;
                $stats[$questionNumber]['frequencies'][$index]['frequency']++;
                $stats[$questionNumber]['valid_responses']++;
                $stats[$questionNumber]['score_sum'] += $value;
            }
        }

        foreach (self::QUESTION_NUMBERS as $questionNumber) {
            $question = &$stats[$questionNumber];

            foreach ($question['frequencies'] as $index => $frequencyRow) {
                $question['frequencies'][$index]['percentage'] = $this->percentage(
                    $frequencyRow['frequency'],
                    $totalSurveys
                );
            }

            $satisfied = $question['frequencies'][3]['frequency'] + $question['frequencies'][4]['frequency'];
            $neutral = $question['frequencies'][2]['frequency'];
            $dissatisfied = $question['frequencies'][0]['frequency'] + $question['frequencies'][1]['frequency'];

            $question['satisfaction'] = [
                'satisfied' => $satisfied,
                'neutral' => $neutral,
                'dissatisfied' => $dissatisfied,
                'satisfied_percentage' => $this->percentage($satisfied, $totalSurveys),
                'neutral_percentage' => $this->percentage($neutral, $totalSurveys),
                'dissatisfied_percentage' => $this->percentage($dissatisfied, $totalSurveys),
            ];

            $question['average_score'] = $question['valid_responses'] > 0
                ? round($question['score_sum'] / $question['valid_responses'], 2)
                : 0.0;

            unset($question['score_sum']);
        }

        return array_values($stats);
    }

    /**
     * @param  array<int, array{
     *      number: int,
     *      dimension: string,
     *      label: string,
     *      satisfaction: array{
     *          satisfied: int,
     *          neutral: int,
     *          dissatisfied: int,
     *          satisfied_percentage: float,
     *          neutral_percentage: float,
     *          dissatisfied_percentage: float
     *      }
     * }>  $questionStats
     * @return array<int, array{
     *      number: int,
     *      dimension: string,
     *      question: string,
     *      satisfechos: int,
     *      neutros: int,
     *      insatisfechos: int,
     *      porcentaje_satisfechos: float,
     *      porcentaje_neutros: float,
     *      porcentaje_insatisfechos: float
     * }>
     */
    public function getSatisfactionByQuestion(array $questionStats): array
    {
        return array_values(array_map(
            static fn (array $question): array => [
                'number' => $question['number'],
                'dimension' => $question['dimension'],
                'question' => $question['label'],
                'satisfechos' => $question['satisfaction']['satisfied'],
                'neutros' => $question['satisfaction']['neutral'],
                'insatisfechos' => $question['satisfaction']['dissatisfied'],
                'porcentaje_satisfechos' => $question['satisfaction']['satisfied_percentage'],
                'porcentaje_neutros' => $question['satisfaction']['neutral_percentage'],
                'porcentaje_insatisfechos' => $question['satisfaction']['dissatisfied_percentage'],
            ],
            $questionStats
        ));
    }

    /**
     * @param  array<int, array{
     *      satisfechos: int,
     *      neutros: int,
     *      insatisfechos: int,
     *      porcentaje_satisfechos: float,
     *      porcentaje_neutros: float,
     *      porcentaje_insatisfechos: float
     * }>  $satisfactionRows
     * @return array{
     *      satisfied_users: float,
     *      neutral_users: float,
     *      dissatisfied_users: float,
     *      satisfaction_percentage: float,
     *      neutral_percentage: float,
     *      dissatisfaction_percentage: float,
     *      satisfied_answers: int,
     *      neutral_answers: int,
     *      dissatisfied_answers: int,
     *      satisfaction_answer_percentage: float,
     *      neutral_answer_percentage: float,
     *      dissatisfaction_answer_percentage: float
     * }
     */
    public function getGlobalIndicator(array $satisfactionRows, int $totalSurveys, int $totalAnswers): array
    {
        if ($satisfactionRows === []) {
            return [
                'satisfied_users' => 0.0,
                'neutral_users' => 0.0,
                'dissatisfied_users' => 0.0,
                'satisfaction_percentage' => 0.0,
                'neutral_percentage' => 0.0,
                'dissatisfaction_percentage' => 0.0,
                'satisfied_answers' => 0,
                'neutral_answers' => 0,
                'dissatisfied_answers' => 0,
                'satisfaction_answer_percentage' => 0.0,
                'neutral_answer_percentage' => 0.0,
                'dissatisfaction_answer_percentage' => 0.0,
            ];
        }

        $questionCount = count($satisfactionRows);
        $satisfiedAnswers = (int) array_sum(array_column($satisfactionRows, 'satisfechos'));
        $neutralAnswers = (int) array_sum(array_column($satisfactionRows, 'neutros'));
        $dissatisfiedAnswers = (int) array_sum(array_column($satisfactionRows, 'insatisfechos'));

        $satisfiedUsers = $questionCount > 0 ? round($satisfiedAnswers / $questionCount, 2) : 0.0;
        $neutralUsers = $questionCount > 0 ? round($neutralAnswers / $questionCount, 2) : 0.0;
        $dissatisfiedUsers = $questionCount > 0 ? round($dissatisfiedAnswers / $questionCount, 2) : 0.0;

        return [
            'satisfied_users' => $satisfiedUsers,
            'neutral_users' => $neutralUsers,
            'dissatisfied_users' => $dissatisfiedUsers,
            'satisfaction_percentage' => $this->percentage($satisfiedUsers, $totalSurveys),
            'neutral_percentage' => $this->percentage($neutralUsers, $totalSurveys),
            'dissatisfaction_percentage' => $this->percentage($dissatisfiedUsers, $totalSurveys),
            'satisfied_answers' => $satisfiedAnswers,
            'neutral_answers' => $neutralAnswers,
            'dissatisfied_answers' => $dissatisfiedAnswers,
            'satisfaction_answer_percentage' => $this->percentage($satisfiedAnswers, $totalAnswers),
            'neutral_answer_percentage' => $this->percentage($neutralAnswers, $totalAnswers),
            'dissatisfaction_answer_percentage' => $this->percentage($dissatisfiedAnswers, $totalAnswers),
        ];
    }

    /**
     * @return array{
     *      first_column_title: string,
     *      second_column_title: string,
     *      rows: array<int, array{label: string, total: int}>,
     *      total_general: int
     * }
     */
    public function getScopePopulationTable(
        string $type,
        string $from,
        string $to,
        ?int $processId,
        ?int $dependencyId,
        array $serviceIds = []
    ): array {
        $baseQuery = $this->filteredQuery($from, $to, $processId, $dependencyId, $serviceIds);

        $definition = match ($type) {
            'process' => [
                'field' => 'id_dependencia',
                'header' => 'Total encuestados del proceso',
                'items' => $processId !== null
                    ? Dependencia::query()
                        ->where('id_proceso', $processId)
                        ->orderBy('nombre')
                        ->get(['id_dependencia as id', 'nombre'])
                    : collect(),
                'fallback' => 'Dependencia sin catalogar',
            ],
            'individual' => $serviceIds !== []
                ? [
                    'field' => 'id_servicio',
                    'header' => 'Total encuestados del servicio',
                    'items' => Servicio::query()
                        ->whereIn('id_servicio', $serviceIds)
                        ->orderBy('nombre')
                        ->get(['id_servicio as id', 'nombre']),
                    'fallback' => 'Servicio sin catalogar',
                ]
                : [
                    'field' => 'id_dependencia',
                    'header' => 'Total encuestados de la dependencia',
                    'items' => $dependencyId !== null
                        ? Dependencia::query()
                            ->where('id_dependencia', $dependencyId)
                            ->get(['id_dependencia as id', 'nombre'])
                        : collect(),
                    'fallback' => 'Dependencia sin catalogar',
                ],
            default => [
                'field' => 'id_proceso',
                'header' => 'Total encuestados del proceso',
                'items' => Proceso::query()
                    ->orderBy('nombre')
                    ->get(['id_proceso as id', 'nombre']),
                'fallback' => 'Proceso sin catalogar',
            ],
        };

        $countRows = (clone $baseQuery)
            ->selectRaw('respuesta.'.$definition['field'].' as group_id, COUNT(*) as total')
            ->groupBy('group_id')
            ->get();

        $counts = [];

        foreach ($countRows as $row) {
            $key = $row->group_id === null ? 'null' : (string) $row->group_id;
            $counts[$key] = (int) $row->total;
        }

        $rows = [];

        foreach ($definition['items'] as $item) {
            $key = (string) $item->id;
            $rows[] = [
                'label' => (string) $item->nombre,
                'total' => $counts[$key] ?? 0,
            ];
            unset($counts[$key]);
        }

        if (array_key_exists('null', $counts)) {
            $rows[] = [
                'label' => $definition['fallback'],
                'total' => $counts['null'],
            ];
            unset($counts['null']);
        }

        foreach ($counts as $unmappedCount) {
            $rows[] = [
                'label' => $definition['fallback'],
                'total' => (int) $unmappedCount,
            ];
        }

        $totalGeneral = (int) array_sum(array_column($rows, 'total'));

        return [
            'first_column_title' => 'Encuestados',
            'second_column_title' => $definition['header'],
            'rows' => $rows,
            'total_general' => $totalGeneral,
        ];
    }

    /**
     * @param  array<int, array{
     *      number: int,
     *      satisfaction: array{ satisfied: int, neutral: int, dissatisfied: int }
     * }>  $questionStats
     * @return array{
     *      rows: array<int, array{
     *          question_number: int,
     *          categoria: string,
     *          usuarios_satisfechos: int,
     *          usuarios_insatisfechos: int,
     *          usuarios_neutros: int,
     *          total: int,
     *          mejora: float,
     *          indicador: float,
     *          mejora_porcentaje: float,
     *          indicador_porcentaje: float
     *      }>,
     *      summary: array{
     *          usuarios_satisfechos: float,
     *          usuarios_insatisfechos: float,
     *          usuarios_neutros: float,
     *          total: float,
     *          mejora: float,
     *          indicador: float,
     *          mejora_porcentaje: float,
     *          indicador_porcentaje: float
     *      }
     * }
     */
    public function getMeasurementConsolidatedTable(array $questionStats): array
    {
        $rows = [];

        foreach ($questionStats as $question) {
            $satisfied = (int) $question['satisfaction']['satisfied'];
            $dissatisfied = (int) $question['satisfaction']['dissatisfied'];
            $neutral = (int) $question['satisfaction']['neutral'];
            $total = $satisfied + $dissatisfied + $neutral;

            $mejora = $total > 0 ? round($neutral / $total, 5) : 0.0;
            $indicador = $total > 0 ? round($satisfied / $total, 5) : 0.0;

            $rows[] = [
                'question_number' => $question['number'],
                'categoria' => self::CONSOLIDATED_CATEGORIES[$question['number']] ?? ('Pregunta '.$question['number']),
                'usuarios_satisfechos' => $satisfied,
                'usuarios_insatisfechos' => $dissatisfied,
                'usuarios_neutros' => $neutral,
                'total' => $total,
                'mejora' => $mejora,
                'indicador' => $indicador,
                'mejora_porcentaje' => round($mejora * 100, 2),
                'indicador_porcentaje' => round($indicador * 100, 2),
            ];
        }

        $questionCount = count($rows);
        $summarySatisfied = $questionCount > 0
            ? round(array_sum(array_column($rows, 'usuarios_satisfechos')) / $questionCount, 2)
            : 0.0;
        $summaryDissatisfied = $questionCount > 0
            ? round(array_sum(array_column($rows, 'usuarios_insatisfechos')) / $questionCount, 2)
            : 0.0;
        $summaryNeutral = $questionCount > 0
            ? round(array_sum(array_column($rows, 'usuarios_neutros')) / $questionCount, 2)
            : 0.0;
        $summaryTotal = $questionCount > 0
            ? round(array_sum(array_column($rows, 'total')) / $questionCount, 2)
            : 0.0;
        $summaryMejora = $questionCount > 0
            ? round(array_sum(array_column($rows, 'mejora')) / $questionCount, 5)
            : 0.0;
        $summaryIndicador = $questionCount > 0
            ? round(array_sum(array_column($rows, 'indicador')) / $questionCount, 5)
            : 0.0;

        return [
            'rows' => $rows,
            'summary' => [
                'usuarios_satisfechos' => $summarySatisfied,
                'usuarios_insatisfechos' => $summaryDissatisfied,
                'usuarios_neutros' => $summaryNeutral,
                'total' => $summaryTotal,
                'mejora' => $summaryMejora,
                'indicador' => $summaryIndicador,
                'mejora_porcentaje' => round($summaryMejora * 100, 2),
                'indicador_porcentaje' => round($summaryIndicador * 100, 2),
            ],
        ];
    }

    private function filteredQuery(
        string $from,
        string $to,
        ?int $processId,
        ?int $dependencyId,
        array $serviceIds = []
    ): Builder
    {
        return Respuesta::query()
            ->whereDate('fecha_respuesta', '>=', $from)
            ->whereDate('fecha_respuesta', '<=', $to)
            ->when($processId !== null, fn (Builder $query) => $query->where('respuesta.id_proceso', $processId))
            ->when($dependencyId !== null, fn (Builder $query) => $query->where('respuesta.id_dependencia', $dependencyId))
            ->when($serviceIds !== [], fn (Builder $query) => $query->whereIn('respuesta.id_servicio', $serviceIds));
    }

    /**
     * @return array<int, array{label: string, value: int, percentage: float, color: string}>
     */
    private function pieItems(array $rows, string $labelKey): array
    {
        $items = [];

        foreach ($rows as $index => $row) {
            $items[] = [
                'label' => $row[$labelKey],
                'value' => $row['encuestas'],
                'percentage' => $row['porcentaje'],
                'color' => self::PIE_COLORS[$index % count(self::PIE_COLORS)],
            ];
        }

        return $items;
    }

    /**
     * @param  array<int, array{label: string, value: int, percentage: float}>  $rows
     * @return array{
     *      type: string,
     *      title: string,
     *      items: array<int, array{label: string, value: int, percentage: float, color: string}>
     * }
     */
    private function buildHorizontalBarChart(string $title, array $rows, string $defaultColor): array
    {
        return [
            'type' => 'bar-horizontal',
            'title' => $title,
            'items' => array_map(
                static fn (array $row): array => [
                    'label' => $row['label'],
                    'value' => $row['value'],
                    'percentage' => $row['percentage'],
                    'color' => $defaultColor,
                ],
                $rows
            ),
        ];
    }

    /**
     * @return array{
     *      type: string,
     *      title: string,
     *      items: array<int, array{label: string, value: float, color: string}>
     * }
     */
    private function buildVerticalBarChart(string $title, array $rows): array
    {
        return [
            'type' => 'bar-vertical',
            'title' => $title,
            'items' => array_values(array_map(
                static fn (array $row): array => [
                    'label' => $row['label'],
                    'value' => (float) $row['value'],
                    'color' => '#2563EB',
                ],
                $rows
            )),
        ];
    }

    /**
     * @return array{
     *      type: string,
     *      title: string,
     *      items: array<int, array{label: string, value: int, percentage: float, color: string}>
     * }
     */
    private function buildPieChart(string $title, array $rows, string $labelKey): array
    {
        return [
            'type' => 'pie',
            'title' => $title,
            'items' => $this->pieItems($rows, $labelKey),
        ];
    }

    /**
     * @param  array<int, array{
     *      number: int,
     *      dimension: string,
     *      frequencies: array<int, array{
     *          label: string,
     *          frequency: int,
     *          percentage: float,
     *          color: string
     *      }>
     * }>  $questionStats
     * @return array<int, array{
     *      type: string,
     *      title: string,
     *      subtitle: string,
     *      items: array<int, array{label: string, value: int, percentage: float, color: string}>
     * }>
     */
    private function buildQuestionCharts(array $questionStats): array
    {
        $charts = [];

        foreach ($questionStats as $question) {
            $satisfaction = $question['satisfaction'] ?? [];

            $charts[] = [
                'type' => 'pie',
                'title' => $question['dimension'],
                'subtitle' => 'Pregunta '.$question['number'],
                'items' => [
                    [
                        'label' => 'Satisfecho',
                        'value' => (int) ($satisfaction['satisfied'] ?? 0),
                        'percentage' => (float) ($satisfaction['satisfied_percentage'] ?? 0.0),
                        'color' => '#4472C4',
                    ],
                    [
                        'label' => 'Neutro',
                        'value' => (int) ($satisfaction['neutral'] ?? 0),
                        'percentage' => (float) ($satisfaction['neutral_percentage'] ?? 0.0),
                        'color' => '#ED7D31',
                    ],
                    [
                        'label' => 'Insatisfecho',
                        'value' => (int) ($satisfaction['dissatisfied'] ?? 0),
                        'percentage' => (float) ($satisfaction['dissatisfied_percentage'] ?? 0.0),
                        'color' => '#A5A5A5',
                    ],
                ],
            ];
        }

        return $charts;
    }

    /**
     * @return array<int, array{label: string, value: int, percentage: float}>
     */
    private function limitForChart(array $rows, string $labelKey, int $limit = 15): array
    {
        if (count($rows) <= $limit) {
            return array_values(array_map(
                static fn (array $row) => [
                    'label' => $row[$labelKey],
                    'value' => $row['encuestas'],
                    'percentage' => $row['porcentaje'],
                ],
                $rows
            ));
        }

        $head = array_slice($rows, 0, $limit - 1);
        $tail = array_slice($rows, $limit - 1);

        $otherCount = array_sum(array_column($tail, 'encuestas'));
        $otherPercentage = round(array_sum(array_column($tail, 'porcentaje')), 2);

        $head[] = [
            $labelKey => 'Otros servicios ('.count($tail).')',
            'encuestas' => $otherCount,
            'porcentaje' => $otherPercentage,
        ];

        return array_values(array_map(
            static fn (array $row) => [
                'label' => $row[$labelKey],
                'value' => $row['encuestas'],
                'percentage' => $row['porcentaje'],
            ],
            $head
        ));
    }

    /**
     * @return array<int, string>
     */
    private function getObservations(Builder $query): array
    {
        return (clone $query)
            ->whereNotNull('observaciones')
            ->where('observaciones', '!=', '')
            ->orderByDesc('fecha_respuesta')
            ->limit(10)
            ->pluck('observaciones')
            ->map(static fn (string $observation): string => trim($observation))
            ->filter(static fn (string $observation): bool => $observation !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{encuestas: int, porcentaje: float}>
     */
    private function groupedDistribution(
        Builder $baseQuery,
        string $table,
        string $foreignKey,
        string $fallbackLabel,
        int $totalSurveys
    ): array {
        $rows = (clone $baseQuery)
            ->leftJoin($table, 'respuesta.'.$foreignKey, '=', $table.'.'.$foreignKey)
            ->selectRaw(
                'COALESCE(NULLIF(TRIM('.$table.'.nombre), \'\'), ?) as label, COUNT(*) as total',
                [$fallbackLabel]
            )
            ->groupBy('label')
            ->orderByDesc('total')
            ->orderBy('label')
            ->get();

        $labelKey = match ($table) {
            'programa' => 'programa',
            'estamento' => 'estamento',
            default => 'servicio',
        };

        return $rows->map(fn ($row): array => [
            $labelKey => (string) $row->label,
            'encuestas' => (int) $row->total,
            'porcentaje' => $this->percentage((int) $row->total, $totalSurveys),
        ])->values()->all();
    }

    private function percentage(float|int $value, int $total): float
    {
        if ($total <= 0) {
            return 0.0;
        }

        return round(($value / $total) * 100, 2);
    }
}
