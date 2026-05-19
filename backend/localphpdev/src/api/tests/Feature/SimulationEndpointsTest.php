<?php

namespace Tests\Feature;

use App\Models\AnimationUsage;
use App\Models\SimulationRun;
use App\Services\Simulation\OctaveSimulationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private FakeSimulationRunner $runner;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cas.api_key' => 'test-key',
            'simulations.dedupe_minutes' => 10,
        ]);

        $this->runner = new FakeSimulationRunner();
        $this->app->instance(OctaveSimulationRunner::class, $this->runner);
    }

    public function test_request_without_api_key_is_rejected(): void
    {
        $this->postJson('/api/simulations/inverted-pendulum', [
            'clientToken' => 'client-1',
            'reference' => 0.2,
        ])->assertUnauthorized();
    }

    public function test_inverted_pendulum_run_is_saved_and_counted_once_in_statistics(): void
    {
        $this->withHeader('X-API-Key', 'test-key')
            ->postJson('/api/simulations/inverted-pendulum', [
                'clientToken' => 'client-1',
                'reference' => 0.2,
                'initialPosition' => 0,
                'initialAngle' => 0,
                'duration' => 1,
                'step' => 0.5,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'simulation' => 'inverted-pendulum',
            ]);

        $this->withHeader('X-API-Key', 'test-key')
            ->postJson('/api/simulations/inverted-pendulum', [
                'clientToken' => 'client-1',
                'reference' => 0.2,
                'initialPosition' => 0,
                'initialAngle' => 0,
                'duration' => 1,
                'step' => 0.5,
            ])
            ->assertOk();

        $this->assertSame(2, SimulationRun::query()->where('simulation', 'inverted-pendulum')->count());
        $this->assertSame(1, AnimationUsage::query()->where('simulation', 'inverted-pendulum')->count());

        $this->withHeader('X-API-Key', 'test-key')
            ->getJson('/api/statistics/inverted-pendulum')
            ->assertOk()
            ->assertJsonPath('data.simulation', 'inverted-pendulum')
            ->assertJsonPath('data.runs', 2)
            ->assertJsonPath('data.usages', 1);
    }

    public function test_ball_beam_run_returns_series_data(): void
    {
        $this->withHeader('X-API-Key', 'test-key')
            ->postJson('/api/simulations/ball-beam', [
                'clientToken' => 'client-2',
                'reference' => 0.25,
                'initialSpeed' => 0,
                'initialAcceleration' => 0,
                'duration' => 1,
                'step' => 0.5,
            ])
            ->assertOk()
            ->assertJsonPath('simulation', 'ball-beam')
            ->assertJsonStructure([
                'success',
                'simulation',
                'time',
                'series',
                'state',
                'frames',
            ]);
    }
}

class FakeSimulationRunner extends OctaveSimulationRunner
{
    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function simulateInvertedPendulum(array $parameters): array
    {
        return [
            'successful' => true,
            'simulation' => 'inverted-pendulum',
            'time' => [0.0, 0.5, 1.0],
            'series' => [
                ['name' => 'cart_position', 'values' => [0.0, 0.1, 0.2]],
                ['name' => 'pendulum_angle', 'values' => [0.0, 0.05, 0.1]],
            ],
            'state' => [
                ['name' => 'position', 'values' => [0.0, 0.1, 0.2]],
                ['name' => 'velocity', 'values' => [0.0, 0.02, 0.03]],
                ['name' => 'angle', 'values' => [0.0, 0.05, 0.1]],
                ['name' => 'angular_velocity', 'values' => [0.0, 0.01, 0.02]],
            ],
            'frames' => [
                ['time' => 0.0, 'reference' => 0.2, 'cart_position' => 0.0, 'pendulum_angle' => 0.0],
                ['time' => 0.5, 'reference' => 0.2, 'cart_position' => 0.1, 'pendulum_angle' => 0.05],
                ['time' => 1.0, 'reference' => 0.2, 'cart_position' => 0.2, 'pendulum_angle' => 0.1],
            ],
            'message' => null,
            'error' => null,
            'execution_ms' => 1,
        ];
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function simulateBallBeam(array $parameters): array
    {
        return [
            'successful' => true,
            'simulation' => 'ball-beam',
            'time' => [0.0, 0.5, 1.0],
            'series' => [
                ['name' => 'ball_position', 'values' => [0.0, 0.12, 0.22]],
            ],
            'state' => [
                ['name' => 'state_1', 'values' => [0.0, 0.12, 0.22]],
                ['name' => 'state_2', 'values' => [0.0, 0.01, 0.02]],
                ['name' => 'state_3', 'values' => [0.0, 0.02, 0.03]],
                ['name' => 'state_4', 'values' => [0.0, 0.03, 0.04]],
            ],
            'frames' => [
                ['time' => 0.0, 'reference' => 0.25, 'ball_position' => 0.0],
                ['time' => 0.5, 'reference' => 0.25, 'ball_position' => 0.12],
                ['time' => 1.0, 'reference' => 0.25, 'ball_position' => 0.22],
            ],
            'message' => null,
            'error' => null,
            'execution_ms' => 1,
        ];
    }
}