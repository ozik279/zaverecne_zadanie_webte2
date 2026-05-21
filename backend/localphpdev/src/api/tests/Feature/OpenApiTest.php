<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['cas.api_key' => 'test-key']);
    }

    public function test_openapi_endpoint_requires_api_key(): void
    {
        $this->getJson('/api/openapi.json')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid or missing API key.']);
    }

    public function test_openapi_endpoint_returns_current_api_paths(): void
    {
        $response = $this->getJson('/api/openapi.json', ['X-API-Key' => 'test-key'])
            ->assertOk()
            ->assertJsonPath('openapi', '3.0.3')
            ->assertJsonPath('info.title', 'WEBTE2 CAS API')
            ->assertJsonPath('components.securitySchemes.ApiKeyAuth.name', 'X-API-Key');

        $paths = $response->json('paths');

        $this->assertSame('Execute an Octave command in a client session.', $paths['/api/cas/execute']['post']['summary']);
        $this->assertSame('Reset stored Octave command history for a client session.', $paths['/api/cas/reset']['post']['summary']);
        $this->assertSame('List logged CAS requests.', $paths['/api/logs']['get']['summary']);
        $this->assertSame('Export logged CAS requests to CSV.', $paths['/api/logs/export.csv']['get']['summary']);
        $this->assertSame('Run inverted pendulum simulation.', $paths['/api/simulations/inverted-pendulum']['post']['summary']);
        $this->assertSame('Run ball and beam simulation.', $paths['/api/simulations/ball-beam']['post']['summary']);
        $this->assertSame('List usage statistics for all simulations.', $paths['/api/statistics']['get']['summary']);
        $this->assertSame('Show usage statistics for one simulation.', $paths['/api/statistics/{simulation}']['get']['summary']);
        $this->assertSame('Return the current OpenAPI specification.', $paths['/api/openapi.json']['get']['summary']);
        $this->assertSame('Return the current OpenAPI documentation as a PDF file.', $paths['/api/openapi.pdf']['get']['summary']);
    }

    public function test_openapi_pdf_endpoint_requires_api_key(): void
    {
        $this->get('/api/openapi.pdf')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid or missing API key.']);
    }

    public function test_openapi_pdf_endpoint_returns_pdf_document(): void
    {
        $response = $this->get('/api/openapi.pdf', ['X-API-Key' => 'test-key']);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');

        $content = $response->getContent();

        $this->assertIsString($content);
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('%%EOF', $content);
        $this->assertGreaterThan(10000, strlen($content));
    }
}
