<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\OpenApi(
    openapi: '3.0.3',
    info: new OA\Info(
        version: '1.0.0',
        title: 'WEBTE2 CAS API',
        description: 'REST API for Octave CAS commands, dynamic-system simulations, logs and usage statistics.',
    ),
    servers: [
        new OA\Server(url: '/', description: 'Application root'),
    ],
    security: [
        ['ApiKeyAuth' => []],
    ],
    tags: [
        new OA\Tag(name: 'CAS Console'),
        new OA\Tag(name: 'CAS Logs'),
        new OA\Tag(name: 'Simulations'),
        new OA\Tag(name: 'Statistics'),
        new OA\Tag(name: 'Documentation'),
    ],
    components: new OA\Components(
        securitySchemes: [
            new OA\SecurityScheme(
                securityScheme: 'ApiKeyAuth',
                type: 'apiKey',
                name: 'X-API-Key',
                in: 'header',
            ),
        ],
        schemas: [
            new OA\Schema(
                schema: 'CasExecuteRequest',
                type: 'object',
                required: ['command'],
                properties: [
                    new OA\Property(property: 'command', type: 'string', maxLength: 10000, example: 'a=1+1'),
                ],
            ),
            new OA\Schema(
                schema: 'CasExecuteResponse',
                type: 'object',
                properties: [
                    new OA\Property(property: 'success', type: 'boolean'),
                    new OA\Property(property: 'stdout', type: 'string'),
                    new OA\Property(property: 'stderr', type: 'string'),
                    new OA\Property(property: 'error', type: 'string', nullable: true),
                    new OA\Property(property: 'executionMs', type: 'integer'),
                    new OA\Property(property: 'historyLength', type: 'integer'),
                ],
            ),
            new OA\Schema(
                schema: 'CasResetRequest',
                type: 'object',
                properties: [],
            ),
            new OA\Schema(
                schema: 'CasResetResponse',
                type: 'object',
                properties: [
                    new OA\Property(property: 'success', type: 'boolean'),
                    new OA\Property(property: 'historyLength', type: 'integer'),
                ],
            ),
            new OA\Schema(
                schema: 'CasLogListResponse',
                type: 'object',
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/CasLog')),
                    new OA\Property(property: 'meta', ref: '#/components/schemas/PaginationMeta'),
                ],
            ),
            new OA\Schema(
                schema: 'CasLog',
                type: 'object',
                properties: [
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'source', type: 'string'),
                    new OA\Property(property: 'command', type: 'string'),
                    new OA\Property(property: 'successful', type: 'boolean'),
                    new OA\Property(property: 'stdout', type: 'string', nullable: true),
                    new OA\Property(property: 'stderr', type: 'string', nullable: true),
                    new OA\Property(property: 'errorMessage', type: 'string', nullable: true),
                    new OA\Property(property: 'executionMs', type: 'integer'),
                    new OA\Property(property: 'ipAddress', type: 'string', nullable: true),
                    new OA\Property(property: 'userAgent', type: 'string', nullable: true),
                ],
            ),
            new OA\Schema(
                schema: 'PaginationMeta',
                type: 'object',
                properties: [
                    new OA\Property(property: 'currentPage', type: 'integer'),
                    new OA\Property(property: 'perPage', type: 'integer'),
                    new OA\Property(property: 'total', type: 'integer'),
                    new OA\Property(property: 'lastPage', type: 'integer'),
                ],
            ),
            new OA\Schema(
                schema: 'InvertedPendulumRequest',
                type: 'object',
                required: ['reference'],
                properties: [
                    new OA\Property(property: 'reference', description: 'Target cart position r.', type: 'number', minimum: -0.5, maximum: 0.5, example: 0.2),
                    new OA\Property(property: 'initialPosition', description: 'Initial cart position initPozicia.', type: 'number', minimum: -0.5, maximum: 0.5, nullable: true),
                    new OA\Property(property: 'initialVelocity', description: 'Initial cart velocity, second state of the lsim initial vector.', type: 'number', minimum: -0.5, maximum: 0.5, nullable: true),
                    new OA\Property(property: 'initialAngle', description: 'Initial pendulum angle initUhol.', type: 'number', minimum: -0.2, maximum: 0.2, nullable: true),
                    new OA\Property(property: 'initialAngularVelocity', description: 'Initial pendulum angular velocity, fourth state of the lsim initial vector.', type: 'number', minimum: -1, maximum: 1, nullable: true),
                    new OA\Property(property: 'duration', description: 'Simulation duration in seconds.', type: 'number', minimum: 0.5, maximum: 10, nullable: true),
                    new OA\Property(property: 'step', description: 'Calculation time step in seconds.', type: 'number', minimum: 0.01, maximum: 0.1, nullable: true),
                    new OA\Property(property: 'slowdownMs', description: 'Animation slowdown in milliseconds per frame. Zero uses smooth default playback.', type: 'integer', minimum: 0, maximum: 5000, nullable: true),
                ],
            ),
            new OA\Schema(
                schema: 'BallBeamRequest',
                type: 'object',
                required: ['reference'],
                properties: [
                    new OA\Property(property: 'reference', description: 'Target ball position r.', type: 'number', minimum: 0, maximum: 0.5, example: 0.25),
                    new OA\Property(property: 'initialBallPosition', description: 'Initial ball position, first state of the lsim initial vector. This replaces the initRychlost demo value from gulicka.txt.', type: 'number', minimum: 0, maximum: 0.5, nullable: true),
                    new OA\Property(property: 'initialBallVelocity', description: 'Initial ball velocity, second state of the lsim initial vector.', type: 'number', minimum: -0.1, maximum: 0.5, nullable: true),
                    new OA\Property(property: 'initialBeamAngle', description: 'Initial beam angle, third state of the lsim initial vector. This replaces the initZrychlenie demo value from gulicka.txt.', type: 'number', minimum: -0.2, maximum: 0.2, nullable: true),
                    new OA\Property(property: 'initialBeamAngularVelocity', description: 'Initial beam angular velocity, fourth state of the lsim initial vector.', type: 'number', minimum: -2, maximum: 2, nullable: true),
                    new OA\Property(property: 'duration', description: 'Simulation duration in seconds.', type: 'number', minimum: 0.1, maximum: 5, nullable: true),
                    new OA\Property(property: 'step', description: 'Calculation time step in seconds.', type: 'number', minimum: 0.005, maximum: 0.05, nullable: true),
                    new OA\Property(property: 'slowdownMs', description: 'Animation slowdown in milliseconds per frame. Zero uses smooth default playback.', type: 'integer', minimum: 0, maximum: 5000, nullable: true),
                ],
            ),
            new OA\Schema(
                schema: 'SimulationResponse',
                type: 'object',
                properties: [
                    new OA\Property(property: 'success', type: 'boolean'),
                    new OA\Property(property: 'simulation', type: 'string', nullable: true),
                    new OA\Property(property: 'time', type: 'array', items: new OA\Items(type: 'number')),
                    new OA\Property(property: 'series', type: 'array', items: new OA\Items(ref: '#/components/schemas/NamedSeries')),
                    new OA\Property(property: 'state', type: 'array', items: new OA\Items(ref: '#/components/schemas/NamedSeries')),
                    new OA\Property(property: 'frames', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'message', type: 'string', nullable: true),
                    new OA\Property(property: 'error', type: 'string', nullable: true),
                    new OA\Property(property: 'executionMs', type: 'integer', nullable: true),
                ],
            ),
            new OA\Schema(
                schema: 'NamedSeries',
                type: 'object',
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'values', type: 'array', items: new OA\Items(type: 'number')),
                ],
            ),
            new OA\Schema(
                schema: 'StatisticsListResponse',
                type: 'object',
                properties: [
                    new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/SimulationStatistics')),
                ],
            ),
            new OA\Schema(
                schema: 'StatisticsShowResponse',
                type: 'object',
                properties: [
                    new OA\Property(property: 'data', ref: '#/components/schemas/SimulationStatisticsDetail'),
                ],
            ),
            new OA\Schema(
                schema: 'SimulationStatistics',
                type: 'object',
                properties: [
                    new OA\Property(property: 'simulation', type: 'string'),
                    new OA\Property(property: 'runs', type: 'integer'),
                    new OA\Property(property: 'successfulRuns', type: 'integer'),
                    new OA\Property(property: 'usages', type: 'integer'),
                    new OA\Property(property: 'lastRunAt', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'lastUsageAt', type: 'string', format: 'date-time', nullable: true),
                ],
            ),
            new OA\Schema(
                schema: 'SimulationStatisticsDetail',
                type: 'object',
                properties: [
                    new OA\Property(property: 'simulation', type: 'string'),
                    new OA\Property(property: 'runs', type: 'integer'),
                    new OA\Property(property: 'successfulRuns', type: 'integer'),
                    new OA\Property(property: 'usages', type: 'integer'),
                    new OA\Property(property: 'lastRunAt', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'lastUsageAt', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(
                        property: 'recentUsages',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/SimulationUsageDetail'),
                    ),
                    new OA\Property(
                        property: 'recentRuns',
                        type: 'array',
                        items: new OA\Items(ref: '#/components/schemas/SimulationRunDetail'),
                    ),
                ],
            ),
            new OA\Schema(
                schema: 'SimulationUsageDetail',
                type: 'object',
                properties: [
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'city', type: 'string'),
                    new OA\Property(property: 'country', type: 'string'),
                ],
            ),
            new OA\Schema(
                schema: 'SimulationRunDetail',
                type: 'object',
                properties: [
                    new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', nullable: true),
                    new OA\Property(property: 'successful', type: 'boolean'),
                    new OA\Property(property: 'durationMs', type: 'integer', nullable: true),
                    new OA\Property(property: 'city', type: 'string'),
                    new OA\Property(property: 'country', type: 'string'),
                ],
            ),
            new OA\Schema(
                schema: 'ErrorResponse',
                type: 'object',
                properties: [
                    new OA\Property(property: 'message', type: 'string'),
                    new OA\Property(property: 'errors', type: 'object', nullable: true),
                ],
            ),
        ],
    ),
)]
#[OA\Post(
    path: '/api/cas/execute',
    tags: ['CAS Console'],
    summary: 'Execute an Octave command in the current browser session.',
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/CasExecuteRequest')),
    responses: [
        new OA\Response(response: 200, description: 'Successful CAS command result.', content: new OA\JsonContent(ref: '#/components/schemas/CasExecuteResponse')),
        new OA\Response(response: 401, description: 'Missing or invalid API key.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        new OA\Response(response: 422, description: 'Validation or Octave execution error.', content: new OA\JsonContent(ref: '#/components/schemas/CasExecuteResponse')),
    ],
)]
#[OA\Post(
    path: '/api/cas/reset',
    tags: ['CAS Console'],
    summary: 'Reset stored Octave command history for the current browser session.',
    requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(ref: '#/components/schemas/CasResetRequest')),
    responses: [
        new OA\Response(response: 200, description: 'Session reset result.', content: new OA\JsonContent(ref: '#/components/schemas/CasResetResponse')),
        new OA\Response(response: 401, description: 'Missing or invalid API key.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
    ],
)]
#[OA\Get(
    path: '/api/logs',
    tags: ['CAS Logs'],
    summary: 'List logged CAS requests.',
    parameters: [
        new OA\Parameter(name: 'source', in: 'query', description: 'Filter by source.', schema: new OA\Schema(type: 'string', enum: ['console', 'simulation'])),
        new OA\Parameter(name: 'successful', in: 'query', description: 'Filter by true, false, 1 or 0.', schema: new OA\Schema(type: 'string')),
        new OA\Parameter(name: 'perPage', in: 'query', description: 'Items per page, from 1 to 100.', schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'page', in: 'query', description: 'Page number.', schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Paginated CAS request logs.', content: new OA\JsonContent(ref: '#/components/schemas/CasLogListResponse')),
        new OA\Response(response: 401, description: 'Missing or invalid API key.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
    ],
)]
#[OA\Get(
    path: '/api/logs/export.csv',
    tags: ['CAS Logs'],
    summary: 'Export logged CAS requests to CSV.',
    parameters: [
        new OA\Parameter(name: 'source', in: 'query', description: 'Filter by source.', schema: new OA\Schema(type: 'string', enum: ['console', 'simulation'])),
        new OA\Parameter(name: 'successful', in: 'query', description: 'Filter by true, false, 1 or 0.', schema: new OA\Schema(type: 'string')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'CSV export.', content: new OA\MediaType(mediaType: 'text/csv', schema: new OA\Schema(type: 'string', format: 'binary'))),
        new OA\Response(response: 401, description: 'Missing or invalid API key.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        new OA\Response(response: 422, description: 'Validation error.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
    ],
)]
#[OA\Post(
    path: '/api/simulations/inverted-pendulum',
    tags: ['Simulations'],
    summary: 'Run inverted pendulum simulation.',
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/InvertedPendulumRequest')),
    responses: [
        new OA\Response(response: 200, description: 'Simulation result.', content: new OA\JsonContent(ref: '#/components/schemas/SimulationResponse')),
        new OA\Response(response: 401, description: 'Missing or invalid API key.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        new OA\Response(response: 422, description: 'Validation or simulation error.', content: new OA\JsonContent(ref: '#/components/schemas/SimulationResponse')),
    ],
)]
#[OA\Post(
    path: '/api/simulations/ball-beam',
    tags: ['Simulations'],
    summary: 'Run ball and beam simulation.',
    requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/BallBeamRequest')),
    responses: [
        new OA\Response(response: 200, description: 'Simulation result.', content: new OA\JsonContent(ref: '#/components/schemas/SimulationResponse')),
        new OA\Response(response: 401, description: 'Missing or invalid API key.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        new OA\Response(response: 422, description: 'Validation or simulation error.', content: new OA\JsonContent(ref: '#/components/schemas/SimulationResponse')),
    ],
)]
#[OA\Get(
    path: '/api/statistics',
    tags: ['Statistics'],
    summary: 'List usage statistics for all simulations.',
    responses: [
        new OA\Response(response: 200, description: 'Simulation statistics list.', content: new OA\JsonContent(ref: '#/components/schemas/StatisticsListResponse')),
        new OA\Response(response: 401, description: 'Missing or invalid API key.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
    ],
)]
#[OA\Get(
    path: '/api/statistics/{simulation}',
    tags: ['Statistics'],
    summary: 'Show usage statistics for one simulation.',
    parameters: [
        new OA\Parameter(
            name: 'simulation',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', enum: ['inverted-pendulum', 'ball-beam']),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Simulation statistics.', content: new OA\JsonContent(ref: '#/components/schemas/StatisticsShowResponse')),
        new OA\Response(response: 401, description: 'Missing or invalid API key.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
        new OA\Response(response: 404, description: 'Unknown simulation.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
    ],
)]
#[OA\Get(
    path: '/api/openapi.json',
    tags: ['Documentation'],
    summary: 'Return the current OpenAPI specification.',
    responses: [
        new OA\Response(response: 200, description: 'OpenAPI JSON document.', content: new OA\JsonContent(type: 'object')),
        new OA\Response(response: 401, description: 'Missing or invalid API key.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
    ],
)]
#[OA\Get(
    path: '/api/openapi.pdf',
    tags: ['Documentation'],
    summary: 'Return the current OpenAPI documentation as a PDF file.',
    responses: [
        new OA\Response(response: 200, description: 'OpenAPI PDF document.', content: new OA\MediaType(mediaType: 'application/pdf', schema: new OA\Schema(type: 'string', format: 'binary'))),
        new OA\Response(response: 401, description: 'Missing or invalid API key.', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse')),
    ],
)]
final class WebteOpenApi
{
}
