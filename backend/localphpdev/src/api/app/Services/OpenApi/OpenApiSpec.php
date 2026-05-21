<?php

namespace App\Services\OpenApi;

use JsonException;
use OpenApi\Annotations\OpenApi;
use OpenApi\Generator;
use RuntimeException;

class OpenApiSpec
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $document = (new Generator())->generate([
            app_path('OpenApi'),
        ]);

        if (! $document instanceof OpenApi) {
            throw new RuntimeException('Swagger OpenAPI document could not be generated.');
        }

        try {
            $decoded = json_decode($document->toJson(JSON_UNESCAPED_SLASHES), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Swagger OpenAPI JSON could not be decoded.', previous: $exception);
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Swagger OpenAPI document has invalid JSON structure.');
        }

        return $decoded;
    }
}
