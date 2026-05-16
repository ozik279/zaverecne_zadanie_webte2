<?php

return [
    'api_key' => env('CAS_API_KEY', 'dev-api-key'),
    'api_key_header' => env('CAS_API_KEY_HEADER', 'X-API-Key'),

    'octave_binary' => env('CAS_OCTAVE_BINARY', 'octave'),
    'timeout_seconds' => (int) env('CAS_TIMEOUT_SECONDS', 10),
    'output_limit_bytes' => (int) env('CAS_OUTPUT_LIMIT_BYTES', 20000),
    'temp_path' => env('CAS_TEMP_PATH', storage_path('app/cas')),

    'animation_slowdown_ms' => (int) env('CAS_ANIMATION_SLOWDOWN_MS', 0),
    'statistics_dedupe_minutes' => (int) env('CAS_STATISTICS_DEDUPE_MINUTES', 10),
];
