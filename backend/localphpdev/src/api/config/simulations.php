<?php

return [
    'api_key' => env('CAS_API_KEY', 'dev-api-key'),
    'api_key_header' => env('CAS_API_KEY_HEADER', 'X-API-Key'),

    'timeout_seconds' => (int) env('SIMULATION_TIMEOUT_SECONDS', 15),
    'slowdown_ms' => (int) env('SIMULATION_SLOWDOWN_MS', 0),
    'dedupe_minutes' => (int) env('SIMULATION_DEDUPE_MINUTES', 10),
    'output_limit_bytes' => (int) env('SIMULATION_OUTPUT_LIMIT_BYTES', 20000),
    'temp_path' => env('SIMULATION_TEMP_PATH', storage_path('app/simulations')),
    'octave_binary' => env('SIMULATION_OCTAVE_BINARY', 'octave'),
];