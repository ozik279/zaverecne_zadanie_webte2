<?php

namespace Tests\Feature;

use App\Models\CasRequestLog;
use App\Models\CasSession;
use App\Services\Cas\OctaveRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasConsoleTest extends TestCase
{
    use RefreshDatabase;

    private FakeOctaveRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cas.api_key' => 'test-key']);

        $this->runner = new FakeOctaveRunner();
        $this->app->instance(OctaveRunner::class, $this->runner);
    }

    public function test_execute_keeps_successful_commands_in_session_history(): void
    {
        $this->postJson('/api/cas/execute', [
            'clientToken' => 'client-1',
            'command' => 'a=1+1',
        ], ['X-API-Key' => 'test-key'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'historyLength' => 1,
            ])
            ->assertJsonPath('stdout', "a = 2\n");

        $this->postJson('/api/cas/execute', [
            'clientToken' => 'client-1',
            'command' => 'a+2',
        ], ['X-API-Key' => 'test-key'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'historyLength' => 2,
            ])
            ->assertJsonPath('stdout', "ans = 4\n");

        $this->assertSame(['a=1+1', 'a+2'], CasSession::query()->firstOrFail()->history);
        $this->assertSame([[], ['a=1+1']], $this->runner->historyCalls);
        $this->assertSame(2, CasRequestLog::query()->where('successful', true)->count());
    }

    public function test_reset_clears_session_history(): void
    {
        $this->postJson('/api/cas/execute', [
            'clientToken' => 'client-2',
            'command' => 'a=1+1',
        ], ['X-API-Key' => 'test-key'])->assertOk();

        $this->postJson('/api/cas/reset', [
            'clientToken' => 'client-2',
        ], ['X-API-Key' => 'test-key'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'historyLength' => 0,
            ]);

        $this->assertDatabaseMissing('cas_sessions', ['client_token' => 'client-2']);

        $this->postJson('/api/cas/execute', [
            'clientToken' => 'client-2',
            'command' => 'a+2',
        ], ['X-API-Key' => 'test-key'])
            ->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'historyLength' => 0,
            ]);
    }

    public function test_failed_command_is_logged_and_not_added_to_history(): void
    {
        $this->postJson('/api/cas/execute', [
            'clientToken' => 'client-3',
            'command' => 'bad command',
        ], ['X-API-Key' => 'test-key'])
            ->assertUnprocessable()
            ->assertJson([
                'success' => false,
                'error' => 'parse error',
                'historyLength' => 0,
            ]);

        $this->assertSame([], CasSession::query()->firstOrFail()->history);
        $this->assertDatabaseHas('cas_request_logs', [
            'client_token' => 'client-3',
            'command' => 'bad command',
            'successful' => false,
            'error_message' => 'parse error',
        ]);
    }
}

class FakeOctaveRunner extends OctaveRunner
{
    /** @var array<int, array<int, string>> */
    public array $historyCalls = [];

    /**
     * @param array<int, string> $history
     * @return array{successful: bool, stdout: string, stderr: string, error_message: ?string, execution_ms: int}
     */
    public function execute(array $history, string $command): array
    {
        $this->historyCalls[] = $history;

        if ($command === 'a=1+1') {
            return $this->success("a = 2\n");
        }

        if ($command === 'a+2' && $history === ['a=1+1']) {
            return $this->success("ans = 4\n");
        }

        if ($command === 'a+2') {
            return $this->failure('undefined variable a');
        }

        return $this->failure('parse error');
    }

    /**
     * @return array{successful: bool, stdout: string, stderr: string, error_message: ?string, execution_ms: int}
     */
    private function success(string $stdout): array
    {
        return [
            'successful' => true,
            'stdout' => $stdout,
            'stderr' => '',
            'error_message' => null,
            'execution_ms' => 1,
        ];
    }

    /**
     * @return array{successful: bool, stdout: string, stderr: string, error_message: ?string, execution_ms: int}
     */
    private function failure(string $message): array
    {
        return [
            'successful' => false,
            'stdout' => '',
            'stderr' => '',
            'error_message' => $message,
            'execution_ms' => 1,
        ];
    }
}
