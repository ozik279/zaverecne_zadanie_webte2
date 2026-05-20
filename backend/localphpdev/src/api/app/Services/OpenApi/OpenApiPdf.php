<?php

namespace App\Services\OpenApi;

class OpenApiPdf
{
    private const PAGE_WIDTH = 612;
    private const PAGE_HEIGHT = 792;
    private const CONTENT_LEFT = 50;
    private const CONTENT_TOP = 720;
    private const LINE_HEIGHT = 14;
    private const LINES_PER_PAGE = 46;

    /**
     * @param array<string, mixed> $spec
     */
    public function build(array $spec): string
    {
        $lines = $this->buildDocumentLines($spec);
        $pages = array_chunk($lines, self::LINES_PER_PAGE);

        if ($pages === []) {
            $pages = [[]];
        }

        return $this->renderPdf($pages, 'WEBTE2 CAS API Documentation');
    }

    /**
     * @param array<string, mixed> $spec
     * @return array<int, string>
     */
    private function buildDocumentLines(array $spec): array
    {
        $lines = [
            (string) ($spec['info']['title'] ?? 'WEBTE2 CAS API'),
            'Version: '.(string) ($spec['info']['version'] ?? '1.0.0'),
            'Generated: '.now()->format('Y-m-d H:i:s'),
            'Security: API key in header '.(string) ($spec['components']['securitySchemes']['ApiKeyAuth']['name'] ?? 'X-API-Key'),
            '',
            'Description:',
        ];

        $lines = array_merge($lines, $this->wrap((string) ($spec['info']['description'] ?? '')));
        $lines[] = '';
        $lines[] = 'Endpoints:';

        foreach (($spec['paths'] ?? []) as $path => $methods) {
            if (! is_array($methods)) {
                continue;
            }

            foreach ($methods as $method => $operation) {
                if (! is_array($operation)) {
                    continue;
                }

                $method = strtoupper((string) $method);
                $summary = (string) ($operation['summary'] ?? '');
                $tags = implode(', ', $operation['tags'] ?? []);

                $lines[] = '';
                $lines[] = $method.' '.(string) $path;
                $lines = array_merge($lines, $this->wrap('Summary: '.$summary, 4));

                if ($tags !== '') {
                    $lines[] = '    Tags: '.$tags;
                }

                if (isset($operation['parameters']) && is_array($operation['parameters'])) {
                    $lines[] = '    Parameters:';
                    foreach ($operation['parameters'] as $parameter) {
                        if (! is_array($parameter)) {
                            continue;
                        }

                        $name = (string) ($parameter['name'] ?? '');
                        $in = (string) ($parameter['in'] ?? '');
                        $description = (string) ($parameter['description'] ?? '');
                        $lines = array_merge($lines, $this->wrap('- '.$name.' in '.$in.': '.$description, 8));
                    }
                }

                if (isset($operation['requestBody'])) {
                    $schema = $this->schemaNameFromContent($operation['requestBody']['content'] ?? []);

                    if ($schema !== null) {
                        $lines[] = '    Request schema: '.$schema;
                    }
                }

                if (isset($operation['responses']) && is_array($operation['responses'])) {
                    $lines[] = '    Responses:';
                    foreach ($operation['responses'] as $code => $response) {
                        $description = is_array($response) ? (string) ($response['description'] ?? '') : '';
                        $schema = is_array($response) ? $this->schemaNameFromContent($response['content'] ?? []) : null;
                        $line = '- '.(string) $code.': '.$description;

                        if ($schema !== null) {
                            $line .= ' ['.$schema.']';
                        }

                        $lines = array_merge($lines, $this->wrap($line, 8));
                    }
                }
            }
        }

        $lines[] = '';
        $lines[] = 'Schemas:';

        foreach (($spec['components']['schemas'] ?? []) as $name => $schema) {
            if (! is_array($schema)) {
                continue;
            }

            $required = isset($schema['required']) && is_array($schema['required'])
                ? implode(', ', $schema['required'])
                : '';

            $schemaLine = (string) $name;

            if ($required !== '') {
                $schemaLine .= ' (required: '.$required.')';
            }

            $lines = array_merge($lines, $this->wrap('- '.$schemaLine, 4));
        }

        return array_map(fn (string $line): string => $this->sanitizeText($line), $lines);
    }

    /**
     * @param array<int, array<int, string>> $pages
     */
    private function renderPdf(array $pages, string $title): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $kids = [];
        $nextObject = 4;
        $totalPages = count($pages);

        foreach ($pages as $index => $lines) {
            $pageNumber = $index + 1;
            $pageObject = $nextObject;
            $contentObject = $nextObject + 1;
            $stream = $this->renderPageStream($lines, $title, $pageNumber, $totalPages);

            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.self::PAGE_WIDTH.' '.self::PAGE_HEIGHT.'] /Resources << /Font << /F1 3 0 R >> >> /Contents '.$contentObject.' 0 R >>';
            $objects[$contentObject] = "<< /Length ".strlen($stream)." >>\nstream\n".$stream."\nendstream";
            $kids[] = $pageObject.' 0 R';
            $nextObject += 2;
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$totalPages.' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $number => $content) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number." 0 obj\n".$content."\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $objectCount = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 ".$objectCount."\n";
        $pdf .= "0000000000 65535 f \n";

        for ($index = 1; $index < $objectCount; $index++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$index] ?? 0);
        }

        $pdf .= "trailer\n<< /Size ".$objectCount." /Root 1 0 R >>\n";
        $pdf .= "startxref\n".$xrefOffset."\n%%EOF\n";

        return $pdf;
    }

    /**
     * @param array<int, string> $lines
     */
    private function renderPageStream(array $lines, string $title, int $pageNumber, int $totalPages): string
    {
        $commands = [];
        $commands[] = 'BT /F1 14 Tf 50 760 Td ('.$this->pdfText($title).') Tj ET';
        $commands[] = '0.5 w 50 748 m 562 748 l S';

        $y = self::CONTENT_TOP;
        foreach ($lines as $line) {
            $commands[] = 'BT /F1 10 Tf '.self::CONTENT_LEFT.' '.$y.' Td ('.$this->pdfText($line).') Tj ET';
            $y -= self::LINE_HEIGHT;
        }

        $commands[] = '0.5 w 50 45 m 562 45 l S';
        $commands[] = 'BT /F1 9 Tf 50 30 Td (Page '.$pageNumber.'/'.$totalPages.') Tj ET';

        return implode("\n", $commands);
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

    /**
     * @return array<int, string>
     */
    private function wrap(string $line, int $indent = 0, int $width = 96): array
    {
        $prefix = str_repeat(' ', $indent);
        $availableWidth = max(20, $width - $indent);
        $wrapped = explode("\n", wordwrap($line, $availableWidth, "\n", true));

        return array_map(fn (string $part): string => $prefix.$part, $wrapped);
    }

    private function sanitizeText(string $text): string
    {
        $text = preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $text) ?? '';

        return str_replace(["\r", "\n", "\t"], [' ', ' ', '    '], $text);
    }

    private function pdfText(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $this->sanitizeText($text));
    }
}
