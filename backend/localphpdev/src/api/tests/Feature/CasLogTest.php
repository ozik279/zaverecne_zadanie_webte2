<?php

namespace Tests\Feature;

use App\Models\CasRequestLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasLogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cas.api_key' => 'test-key']);
    }

    public function test_logs_endpoint_requires_api_key(): void
    {
        $this->getJson('/api/logs')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Invalid or missing API key.']);
    }

    public function test_logs_endpoint_returns_paginated_logs(): void
    {
        $this->createLog(command: 'a=1+1', successful: true, clientToken: 'client-1');
        $this->createLog(command: 'bad command', successful: false, clientToken: 'client-2', errorMessage: 'parse error');

        $this->getJson('/api/logs?perPage=10', ['X-API-Key' => 'test-key'])
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.perPage', 10)
            ->assertJsonPath('data.0.command', 'bad command')
            ->assertJsonPath('data.0.successful', false)
            ->assertJsonPath('data.1.command', 'a=1+1')
            ->assertJsonPath('data.1.successful', true)
            ->assertJsonMissingPath('data.0.id')
            ->assertJsonMissingPath('data.0.clientToken');
    }

    public function test_logs_endpoint_filters_failed_logs(): void
    {
        $this->createLog(command: 'a=1+1', successful: true, clientToken: 'client-1');
        $this->createLog(command: 'bad command', successful: false, clientToken: 'client-2', errorMessage: 'parse error');

        $this->getJson('/api/logs?successful=false', ['X-API-Key' => 'test-key'])
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.command', 'bad command')
            ->assertJsonPath('data.0.successful', false);
    }

    public function test_logs_endpoint_filters_simulation_logs(): void
    {
        $this->createLog(command: 'a=1+1', successful: true, clientToken: 'client-1');
        $this->createLog(
            command: 'simulation: ball-beam',
            successful: true,
            clientToken: 'client-2',
            source: 'simulation',
        );

        $this->getJson('/api/logs?source=simulation', ['X-API-Key' => 'test-key'])
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.source', 'simulation')
            ->assertJsonPath('data.0.command', 'simulation: ball-beam');
    }

    public function test_logs_csv_export_contains_header_and_filtered_rows(): void
    {
        $this->createLog(command: 'a=1+1', successful: true, clientToken: 'client-1');
        $this->createLog(command: 'bad command', successful: false, clientToken: 'client-2', errorMessage: 'parse error');

        $response = $this->get('/api/logs/export.csv?successful=false', ['X-API-Key' => 'test-key']);

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();

        $this->assertStringContainsString('created_at,source,command,successful,execution_ms,error_message,ip_address,user_agent', $csv);
        $this->assertStringNotContainsString('client_token', $csv);
        $this->assertStringNotContainsString('client-2', $csv);
        $this->assertStringContainsString('bad command', $csv);
        $this->assertStringContainsString('parse error', $csv);
        $this->assertStringNotContainsString('a=1+1', $csv);
    }

    private function createLog(
        string $command,
        bool $successful,
        string $clientToken,
        ?string $errorMessage = null,
        string $source = 'console',
    ): CasRequestLog {
        return CasRequestLog::query()->create([
            'client_token' => $clientToken,
            'source' => $source,
            'command' => $command,
            'successful' => $successful,
            'stdout' => $successful ? "ok\n" : '',
            'stderr' => '',
            'error_message' => $errorMessage,
            'execution_ms' => 15,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);
    }
}
