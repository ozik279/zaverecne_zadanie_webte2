<?php

namespace App\Services\OpenApi;

class OpenApiSpec
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        return [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'WEBTE2 CAS API',
                'version' => '1.0.0',
                'description' => 'REST API for Octave CAS commands, dynamic-system simulations, logs and usage statistics.',
            ],
            'servers' => [
                [
                    'url' => rtrim((string) config('app.url'), '/'),
                    'description' => 'Configured application URL',
                ],
            ],
            'security' => [
                ['ApiKeyAuth' => []],
            ],
            'tags' => [
                ['name' => 'CAS Console'],
                ['name' => 'CAS Logs'],
                ['name' => 'Simulations'],
                ['name' => 'Statistics'],
                ['name' => 'Documentation'],
            ],
            'paths' => [
                '/api/cas/execute' => $this->casExecutePath(),
                '/api/cas/reset' => $this->casResetPath(),
                '/api/logs' => $this->logsPath(),
                '/api/logs/export.csv' => $this->logsExportPath(),
                '/api/simulations/inverted-pendulum' => $this->invertedPendulumPath(),
                '/api/simulations/ball-beam' => $this->ballBeamPath(),
                '/api/statistics' => $this->statisticsIndexPath(),
                '/api/statistics/{simulation}' => $this->statisticsShowPath(),
                '/api/openapi.json' => $this->openApiJsonPath(),
                '/api/openapi.pdf' => $this->openApiPdfPath(),
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => config('cas.api_key_header', 'X-API-Key'),
                    ],
                ],
                'schemas' => $this->schemas(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function casExecutePath(): array
    {
        return [
            'post' => [
                'tags' => ['CAS Console'],
                'summary' => 'Execute an Octave command in a client session.',
                'requestBody' => $this->jsonRequest('CasExecuteRequest'),
                'responses' => [
                    '200' => $this->jsonResponse('Successful CAS command result.', 'CasExecuteResponse'),
                    '401' => $this->jsonResponse('Missing or invalid API key.', 'ErrorResponse'),
                    '422' => $this->jsonResponse('Validation or Octave execution error.', 'CasExecuteResponse'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function casResetPath(): array
    {
        return [
            'post' => [
                'tags' => ['CAS Console'],
                'summary' => 'Reset stored Octave command history for a client session.',
                'requestBody' => $this->jsonRequest('CasResetRequest'),
                'responses' => [
                    '200' => $this->jsonResponse('Session reset result.', 'CasResetResponse'),
                    '401' => $this->jsonResponse('Missing or invalid API key.', 'ErrorResponse'),
                    '422' => $this->jsonResponse('Validation error.', 'ErrorResponse'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function logsPath(): array
    {
        return [
            'get' => [
                'tags' => ['CAS Logs'],
                'summary' => 'List logged CAS requests.',
                'parameters' => [
                    $this->queryParameter('source', 'string', 'Filter by source, for example console.'),
                    $this->queryParameter('successful', 'string', 'Filter by true, false, 1 or 0.'),
                    $this->queryParameter('clientToken', 'string', 'Filter by anonymous client token.'),
                    $this->queryParameter('perPage', 'integer', 'Items per page, from 1 to 100.'),
                    $this->queryParameter('page', 'integer', 'Page number.'),
                ],
                'responses' => [
                    '200' => $this->jsonResponse('Paginated CAS request logs.', 'CasLogListResponse'),
                    '401' => $this->jsonResponse('Missing or invalid API key.', 'ErrorResponse'),
                    '422' => $this->jsonResponse('Validation error.', 'ErrorResponse'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function logsExportPath(): array
    {
        return [
            'get' => [
                'tags' => ['CAS Logs'],
                'summary' => 'Export logged CAS requests to CSV.',
                'parameters' => [
                    $this->queryParameter('source', 'string', 'Filter by source, for example console.'),
                    $this->queryParameter('successful', 'string', 'Filter by true, false, 1 or 0.'),
                    $this->queryParameter('clientToken', 'string', 'Filter by anonymous client token.'),
                ],
                'responses' => [
                    '200' => [
                        'description' => 'CSV export.',
                        'content' => [
                            'text/csv' => [
                                'schema' => ['type' => 'string', 'format' => 'binary'],
                            ],
                        ],
                    ],
                    '401' => $this->jsonResponse('Missing or invalid API key.', 'ErrorResponse'),
                    '422' => $this->jsonResponse('Validation error.', 'ErrorResponse'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function invertedPendulumPath(): array
    {
        return [
            'post' => [
                'tags' => ['Simulations'],
                'summary' => 'Run inverted pendulum simulation.',
                'requestBody' => $this->jsonRequest('InvertedPendulumRequest'),
                'responses' => [
                    '200' => $this->jsonResponse('Simulation result.', 'SimulationResponse'),
                    '401' => $this->jsonResponse('Missing or invalid API key.', 'ErrorResponse'),
                    '422' => $this->jsonResponse('Validation or simulation error.', 'SimulationResponse'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function ballBeamPath(): array
    {
        return [
            'post' => [
                'tags' => ['Simulations'],
                'summary' => 'Run ball and beam simulation.',
                'requestBody' => $this->jsonRequest('BallBeamRequest'),
                'responses' => [
                    '200' => $this->jsonResponse('Simulation result.', 'SimulationResponse'),
                    '401' => $this->jsonResponse('Missing or invalid API key.', 'ErrorResponse'),
                    '422' => $this->jsonResponse('Validation or simulation error.', 'SimulationResponse'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statisticsIndexPath(): array
    {
        return [
            'get' => [
                'tags' => ['Statistics'],
                'summary' => 'List usage statistics for all simulations.',
                'responses' => [
                    '200' => $this->jsonResponse('Simulation statistics list.', 'StatisticsListResponse'),
                    '401' => $this->jsonResponse('Missing or invalid API key.', 'ErrorResponse'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statisticsShowPath(): array
    {
        return [
            'get' => [
                'tags' => ['Statistics'],
                'summary' => 'Show usage statistics for one simulation.',
                'parameters' => [
                    [
                        'name' => 'simulation',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                            'enum' => ['inverted-pendulum', 'ball-beam'],
                        ],
                    ],
                ],
                'responses' => [
                    '200' => $this->jsonResponse('Simulation statistics.', 'StatisticsShowResponse'),
                    '401' => $this->jsonResponse('Missing or invalid API key.', 'ErrorResponse'),
                    '404' => $this->jsonResponse('Unknown simulation.', 'ErrorResponse'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function openApiJsonPath(): array
    {
        return [
            'get' => [
                'tags' => ['Documentation'],
                'summary' => 'Return the current OpenAPI specification.',
                'responses' => [
                    '200' => [
                        'description' => 'OpenAPI JSON document.',
                        'content' => [
                            'application/json' => [
                                'schema' => ['type' => 'object'],
                            ],
                        ],
                    ],
                    '401' => $this->jsonResponse('Missing or invalid API key.', 'ErrorResponse'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function openApiPdfPath(): array
    {
        return [
            'get' => [
                'tags' => ['Documentation'],
                'summary' => 'Return the current OpenAPI documentation as a PDF file.',
                'responses' => [
                    '200' => [
                        'description' => 'OpenAPI PDF document.',
                        'content' => [
                            'application/pdf' => [
                                'schema' => ['type' => 'string', 'format' => 'binary'],
                            ],
                        ],
                    ],
                    '401' => $this->jsonResponse('Missing or invalid API key.', 'ErrorResponse'),
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schemas(): array
    {
        return [
            'CasExecuteRequest' => [
                'type' => 'object',
                'required' => ['clientToken', 'command'],
                'properties' => [
                    'clientToken' => ['type' => 'string', 'maxLength' => 128],
                    'command' => ['type' => 'string', 'maxLength' => 10000, 'example' => 'a=1+1'],
                ],
            ],
            'CasExecuteResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'stdout' => ['type' => 'string'],
                    'stderr' => ['type' => 'string'],
                    'error' => ['type' => 'string', 'nullable' => true],
                    'executionMs' => ['type' => 'integer'],
                    'historyLength' => ['type' => 'integer'],
                ],
            ],
            'CasResetRequest' => [
                'type' => 'object',
                'required' => ['clientToken'],
                'properties' => [
                    'clientToken' => ['type' => 'string', 'maxLength' => 128],
                ],
            ],
            'CasResetResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'historyLength' => ['type' => 'integer'],
                ],
            ],
            'CasLogListResponse' => [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/CasLog'],
                    ],
                    'meta' => ['$ref' => '#/components/schemas/PaginationMeta'],
                ],
            ],
            'CasLog' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'createdAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'clientToken' => ['type' => 'string', 'nullable' => true],
                    'source' => ['type' => 'string'],
                    'command' => ['type' => 'string'],
                    'successful' => ['type' => 'boolean'],
                    'stdout' => ['type' => 'string', 'nullable' => true],
                    'stderr' => ['type' => 'string', 'nullable' => true],
                    'errorMessage' => ['type' => 'string', 'nullable' => true],
                    'executionMs' => ['type' => 'integer'],
                    'ipAddress' => ['type' => 'string', 'nullable' => true],
                    'userAgent' => ['type' => 'string', 'nullable' => true],
                ],
            ],
            'PaginationMeta' => [
                'type' => 'object',
                'properties' => [
                    'currentPage' => ['type' => 'integer'],
                    'perPage' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'lastPage' => ['type' => 'integer'],
                ],
            ],
            'InvertedPendulumRequest' => [
                'type' => 'object',
                'required' => ['reference'],
                'properties' => [
                    'clientToken' => ['type' => 'string', 'nullable' => true],
                    'reference' => ['type' => 'number', 'example' => 0.2],
                    'initialPosition' => ['type' => 'number', 'nullable' => true],
                    'initialAngle' => ['type' => 'number', 'nullable' => true],
                    'duration' => ['type' => 'number', 'nullable' => true],
                    'step' => ['type' => 'number', 'nullable' => true],
                    'slowdownMs' => ['type' => 'integer', 'nullable' => true],
                ],
            ],
            'BallBeamRequest' => [
                'type' => 'object',
                'required' => ['reference'],
                'properties' => [
                    'clientToken' => ['type' => 'string', 'nullable' => true],
                    'reference' => ['type' => 'number', 'example' => 0.25],
                    'initialSpeed' => ['type' => 'number', 'nullable' => true],
                    'initialAcceleration' => ['type' => 'number', 'nullable' => true],
                    'duration' => ['type' => 'number', 'nullable' => true],
                    'step' => ['type' => 'number', 'nullable' => true],
                    'slowdownMs' => ['type' => 'integer', 'nullable' => true],
                ],
            ],
            'SimulationResponse' => [
                'type' => 'object',
                'properties' => [
                    'success' => ['type' => 'boolean'],
                    'simulation' => ['type' => 'string', 'nullable' => true],
                    'time' => ['type' => 'array', 'items' => ['type' => 'number']],
                    'series' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/NamedSeries']],
                    'state' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/NamedSeries']],
                    'frames' => ['type' => 'array', 'items' => ['type' => 'object']],
                    'message' => ['type' => 'string', 'nullable' => true],
                    'error' => ['type' => 'string', 'nullable' => true],
                    'executionMs' => ['type' => 'integer', 'nullable' => true],
                ],
            ],
            'NamedSeries' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'values' => ['type' => 'array', 'items' => ['type' => 'number']],
                ],
            ],
            'StatisticsListResponse' => [
                'type' => 'object',
                'properties' => [
                    'data' => [
                        'type' => 'array',
                        'items' => ['$ref' => '#/components/schemas/SimulationStatistics'],
                    ],
                ],
            ],
            'StatisticsShowResponse' => [
                'type' => 'object',
                'properties' => [
                    'data' => ['$ref' => '#/components/schemas/SimulationStatistics'],
                ],
            ],
            'SimulationStatistics' => [
                'type' => 'object',
                'properties' => [
                    'simulation' => ['type' => 'string'],
                    'runs' => ['type' => 'integer'],
                    'successfulRuns' => ['type' => 'integer'],
                    'usages' => ['type' => 'integer'],
                    'lastRunAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                    'lastUsageAt' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
                ],
            ],
            'ErrorResponse' => [
                'type' => 'object',
                'properties' => [
                    'message' => ['type' => 'string'],
                    'errors' => ['type' => 'object', 'nullable' => true],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonRequest(string $schema): array
    {
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/'.$schema],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonResponse(string $description, string $schema): array
    {
        return [
            'description' => $description,
            'content' => [
                'application/json' => [
                    'schema' => ['$ref' => '#/components/schemas/'.$schema],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function queryParameter(string $name, string $type, string $description): array
    {
        return [
            'name' => $name,
            'in' => 'query',
            'required' => false,
            'description' => $description,
            'schema' => ['type' => $type],
        ];
    }
}
