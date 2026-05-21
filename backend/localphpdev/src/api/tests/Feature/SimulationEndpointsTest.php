<?php

namespace Tests\Feature;

use App\Models\AnimationUsage;
use App\Models\CasRequestLog;
use App\Models\SimulationRun;
use App\Services\GeoIp\IpLocationResolver;
use App\Services\Simulation\OctaveSimulationRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SimulationEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private FakeSimulationRunner $runner;
    private FakeIpLocationResolver $locationResolver;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cas.api_key' => 'test-key',
            'simulations.dedupe_minutes' => 10,
        ]);

        $this->runner = new FakeSimulationRunner();
        $this->locationResolver = new FakeIpLocationResolver();

        $this->app->instance(OctaveSimulationRunner::class, $this->runner);
        $this->app->instance(IpLocationResolver::class, $this->locationResolver);
    }

    public function test_request_without_api_key_is_rejected(): void
    {
        $this->postJson('/api/simulations/inverted-pendulum', [
            'reference' => 0.2,
        ])->assertUnauthorized();
    }

    public function test_inverted_pendulum_run_is_saved_and_counted_once_in_statistics(): void
    {
        $this->withCredentials()
            ->withUnencryptedCookie('cas_client_token', 'client-1')
            ->withHeader('X-API-Key', 'test-key')
            ->withHeader('X-Forwarded-For', '8.8.8.8')
            ->postJson('/api/simulations/inverted-pendulum', [
                'reference' => 0.2,
                'initialPosition' => 0,
                'initialVelocity' => 0,
                'initialAngle' => 0,
                'initialAngularVelocity' => 0,
                'duration' => 1,
                'step' => 0.05,
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'simulation' => 'inverted-pendulum',
            ]);

        $this->withCredentials()
            ->withUnencryptedCookie('cas_client_token', 'client-1')
            ->withHeader('X-API-Key', 'test-key')
            ->withHeader('X-Forwarded-For', '8.8.8.8')
            ->postJson('/api/simulations/inverted-pendulum', [
                'reference' => 0.2,
                'initialPosition' => 0,
                'initialVelocity' => 0,
                'initialAngle' => 0,
                'initialAngularVelocity' => 0,
                'duration' => 1,
                'step' => 0.05,
            ])
            ->assertOk();

        $this->assertSame(2, SimulationRun::query()->where('simulation', 'inverted-pendulum')->count());
        $this->assertSame(2, CasRequestLog::query()->where('source', 'simulation')->where('successful', true)->count());
        $this->assertSame(1, AnimationUsage::query()->where('simulation', 'inverted-pendulum')->count());
        $this->assertSame('8.8.8.8', SimulationRun::query()->where('simulation', 'inverted-pendulum')->first()?->ip_address);
        $this->assertSame('8.8.8.8', $this->locationResolver->lastIpAddress);
        $this->assertSame('Bratislava', SimulationRun::query()->where('simulation', 'inverted-pendulum')->first()?->city);
        $this->assertSame('Slovakia', AnimationUsage::query()->where('simulation', 'inverted-pendulum')->first()?->country);

        $this->withHeader('X-API-Key', 'test-key')
            ->getJson('/api/statistics/inverted-pendulum')
            ->assertOk()
            ->assertJsonPath('data.simulation', 'inverted-pendulum')
            ->assertJsonPath('data.runs', 2)
            ->assertJsonPath('data.usages', 1)
            ->assertJsonPath('data.recentRuns.0.city', 'Bratislava')
            ->assertJsonPath('data.recentUsages.0.country', 'Slovakia');
    }

    public function test_ball_beam_run_returns_series_data(): void
    {
        $this->withCredentials()
            ->withUnencryptedCookie('cas_client_token', 'client-2')
            ->withHeader('X-API-Key', 'test-key')
            ->postJson('/api/simulations/ball-beam', [
                'reference' => 0.25,
                'initialBallPosition' => 0.01,
                'initialBallVelocity' => 0.02,
                'initialBeamAngle' => 0.03,
                'initialBeamAngularVelocity' => 0.04,
                'duration' => 1,
                'step' => 0.05,
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

        $this->assertSame(0.01, $this->runner->lastBallBeamParameters['initialBallPosition']);
        $this->assertSame(0.02, $this->runner->lastBallBeamParameters['initialBallVelocity']);
        $this->assertSame(0.03, $this->runner->lastBallBeamParameters['initialBeamAngle']);
        $this->assertSame(0.04, $this->runner->lastBallBeamParameters['initialBeamAngularVelocity']);
        $this->assertDatabaseHas('cas_request_logs', [
            'source' => 'simulation',
            'successful' => true,
        ]);
        $this->assertStringContainsString(
            'simulation: ball-beam',
            CasRequestLog::query()->where('source', 'simulation')->latest('id')->firstOrFail()->command,
        );
    }

    public function test_failed_simulation_request_is_logged(): void
    {
        $this->runner->ballBeamResultOverride = [
            'successful' => false,
            'simulation' => 'ball-beam',
            'time' => [],
            'series' => [],
            'state' => [],
            'frames' => [],
            'message' => 'Simulation command failed.',
            'error' => 'Octave error',
            'execution_ms' => 7,
        ];

        $this->withCredentials()
            ->withUnencryptedCookie('cas_client_token', 'client-failed')
            ->withHeader('X-API-Key', 'test-key')
            ->postJson('/api/simulations/ball-beam', [
                'reference' => 0.25,
                'duration' => 1,
                'step' => 0.05,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('cas_request_logs', [
            'client_token' => 'client-failed',
            'source' => 'simulation',
            'successful' => false,
            'error_message' => 'Octave error',
            'execution_ms' => 7,
        ]);
    }

    public function test_simulation_parameters_are_limited_to_empirical_ranges(): void
    {
        $this->withHeader('X-API-Key', 'test-key')
            ->postJson('/api/simulations/ball-beam', [
                'reference' => 0.25,
                'initialBallPosition' => 0,
                'initialBallVelocity' => 0,
                'initialBeamAngle' => -1,
                'initialBeamAngularVelocity' => 0,
                'duration' => 1,
                'step' => 0.01,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['initialBeamAngle']);

        $this->withHeader('X-API-Key', 'test-key')
            ->postJson('/api/simulations/inverted-pendulum', [
                'reference' => 0.2,
                'initialPosition' => 0,
                'initialVelocity' => 0,
                'initialAngle' => 0.5,
                'initialAngularVelocity' => 0,
                'duration' => 1,
                'step' => 0.05,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['initialAngle']);
    }
}

class FakeIpLocationResolver extends IpLocationResolver
{
    public ?string $lastIpAddress = null;
    public ?float $lastLatitude = null;
    public ?float $lastLongitude = null;

    /**
     * @return array{city: string, country: string}
     */
    public function resolve(?string $ipAddress, ?float $latitude = null, ?float $longitude = null): array
    {
        $this->lastIpAddress = $ipAddress;
        $this->lastLatitude = $latitude;
        $this->lastLongitude = $longitude;

        return [
            'city' => 'Bratislava',
            'country' => 'Slovakia',
        ];
    }
}

class FakeSimulationRunner extends OctaveSimulationRunner
{
    /**
     * @var array<string, mixed>
     */
    public array $lastBallBeamParameters = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $ballBeamResultOverride = null;

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
        $this->lastBallBeamParameters = $parameters;

        if ($this->ballBeamResultOverride !== null) {
            return $this->ballBeamResultOverride;
        }

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
