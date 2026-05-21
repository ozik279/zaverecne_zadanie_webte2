<?php

namespace App\Services\OpenApi;

use Dompdf\Dompdf;
use Dompdf\Options;

class OpenApiPdf
{
    /**
     * @param array<string, mixed> $spec
     */
    public function build(array $spec): string
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->buildHtml($spec), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        $canvas = $pdf->getCanvas();
        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans');
        $canvas->page_text(500, 812, 'Page {PAGE_NUM}/{PAGE_COUNT}', $font, 8, [0.25, 0.25, 0.25]);

        return $pdf->output();
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function buildHtml(array $spec): string
    {
        $title = (string) ($spec['info']['title'] ?? 'WEBTE2 CAS API');
        $description = (string) ($spec['info']['description'] ?? '');
        $version = (string) ($spec['info']['version'] ?? '1.0.0');
        $apiKeyHeader = (string) ($spec['components']['securitySchemes']['ApiKeyAuth']['name'] ?? 'X-API-Key');

        return '<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    @page { margin: 92px 42px 62px; }
    body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 11px; line-height: 1.45; }
    header { position: fixed; top: -62px; left: 0; right: 0; border-bottom: 1px solid #cbd5e1; padding-bottom: 12px; }
    footer { position: fixed; bottom: -36px; left: 0; right: 0; border-top: 1px solid #cbd5e1; padding-top: 8px; color: #475569; font-size: 9px; }
    h1 { margin: 0; font-size: 20px; color: #0f172a; }
    h2 { margin: 22px 0 8px; font-size: 15px; color: #0f172a; page-break-after: avoid; }
    h3 { margin: 15px 0 6px; font-size: 12px; color: #1f2937; page-break-after: avoid; }
    p { margin: 4px 0; }
    code { font-family: DejaVu Sans Mono, monospace; font-size: 10px; background: #f1f5f9; padding: 1px 3px; border-radius: 3px; }
    table { width: 100%; border-collapse: collapse; margin: 8px 0 14px; page-break-inside: auto; }
    tr { page-break-inside: avoid; page-break-after: auto; }
    th, td { border: 1px solid #cbd5e1; padding: 6px; vertical-align: top; }
    th { background: #e2e8f0; text-align: left; }
    .meta { color: #475569; margin-top: 4px; }
    .method { font-weight: bold; color: #0f172a; }
    .muted { color: #64748b; }
  </style>
</head>
<body>
  <header>
    <h1>WEBTE2 CAS API Documentation</h1>
    <p class="meta">'.e($title).' | Version '.e($version).' | Generated '.e(now()->format('Y-m-d H:i:s')).'</p>
  </header>
  <footer>Generated from swagger-php OpenAPI specification. Security: API key in header '.e($apiKeyHeader).'</footer>
  <main>
    <h2>Overview</h2>
    <p>'.e($description).'</p>
    <p><strong>Security:</strong> API key in header <code>'.e($apiKeyHeader).'</code></p>
    '.$this->endpointsHtml($spec).'
    '.$this->schemasHtml($spec).'
  </main>
</body>
</html>';
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function endpointsHtml(array $spec): string
    {
        $html = '<h2>Endpoints</h2>';

        foreach (($spec['paths'] ?? []) as $path => $methods) {
            if (! is_array($methods)) {
                continue;
            }

            foreach ($methods as $method => $operation) {
                if (! is_array($operation)) {
                    continue;
                }

                $html .= '<h3><span class="method">'.e(strtoupper((string) $method)).'</span> <code>'.e((string) $path).'</code></h3>';
                $html .= '<p>'.e((string) ($operation['summary'] ?? '')).'</p>';
                $html .= $this->operationTable($operation);
            }
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $operation
     */
    private function operationTable(array $operation): string
    {
        $parameters = $operation['parameters'] ?? [];
        $responses = $operation['responses'] ?? [];

        $rows = [
            ['Tags', implode(', ', $operation['tags'] ?? [])],
            ['Parameters', $this->parametersText(is_array($parameters) ? $parameters : [])],
            ['Request', $this->requestText($operation['requestBody'] ?? null)],
            ['Responses', $this->responsesText(is_array($responses) ? $responses : [])],
        ];

        $html = '<table><tbody>';

        foreach ($rows as [$name, $value]) {
            $html .= '<tr><th style="width: 24%">'.e($name).'</th><td>'.e($value).'</td></tr>';
        }

        return $html.'</tbody></table>';
    }

    /**
     * @param array<int, mixed> $parameters
     */
    private function parametersText(array $parameters): string
    {
        if ($parameters === []) {
            return 'None';
        }

        return implode(', ', array_map(function (mixed $parameter): string {
            if (! is_array($parameter)) {
                return '';
            }

            return (string) ($parameter['name'] ?? '').' in '.(string) ($parameter['in'] ?? '');
        }, $parameters));
    }

    private function requestText(mixed $requestBody): string
    {
        if (! is_array($requestBody)) {
            return 'None';
        }

        return $this->schemaNameFromContent($requestBody['content'] ?? []) ?? 'Defined request body';
    }

    /**
     * @param array<string, mixed> $responses
     */
    private function responsesText(array $responses): string
    {
        if ($responses === []) {
            return 'None';
        }

        $lines = [];

        foreach ($responses as $code => $response) {
            $description = is_array($response) ? (string) ($response['description'] ?? '') : '';
            $schema = is_array($response) ? $this->schemaNameFromContent($response['content'] ?? []) : null;
            $lines[] = (string) $code.': '.$description.($schema !== null ? ' ['.$schema.']' : '');
        }

        return implode('; ', $lines);
    }

    /**
     * @param array<string, mixed> $spec
     */
    private function schemasHtml(array $spec): string
    {
        $schemas = $spec['components']['schemas'] ?? [];

        if (! is_array($schemas) || $schemas === []) {
            return '';
        }

        $html = '<h2>Schemas</h2><table><thead><tr><th>Name</th><th>Required</th></tr></thead><tbody>';

        foreach ($schemas as $name => $schema) {
            $required = is_array($schema) && isset($schema['required']) && is_array($schema['required'])
                ? implode(', ', $schema['required'])
                : '';
            $html .= '<tr><td><code>'.e((string) $name).'</code></td><td>'.e($required ?: '-').'</td></tr>';
        }

        return $html.'</tbody></table>';
    }

    /**
     * @param array<string, mixed> $content
     */
    private function schemaNameFromContent(array $content): ?string
    {
        foreach ($content as $mediaType) {
            if (! is_array($mediaType)) {
                continue;
            }

            $schema = $mediaType['schema'] ?? null;

            if (! is_array($schema)) {
                continue;
            }

            $ref = $schema['$ref'] ?? null;

            if (is_string($ref)) {
                return basename(str_replace('\\', '/', $ref));
            }

            if (isset($schema['type'])) {
                return (string) $schema['type'];
            }
        }

        return null;
    }
}
