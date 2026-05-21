<?php

namespace App\Http\Controllers;

use App\Models\AnimationUsage;
use App\Models\CasRequestLog;
use App\Models\SimulationRun;
use App\Services\GeoIp\IpLocationResolver;
use App\Services\Simulation\OctaveSimulationRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SimulationController extends Controller
{
    private const CLIENT_COOKIE = 'cas_client_token';
    private const CLIENT_COOKIE_MINUTES = 525600;

    public function __construct(private readonly IpLocationResolver $ipLocationResolver)
    {
    }

    public function invertedPendulum(Request $request, OctaveSimulationRunner $runner): JsonResponse
    {
        $validated = $request->validate($this->baseRules([
            'reference' => ['required', 'numeric', 'min:-0.5', 'max:0.5'],
            'initialPosition' => ['nullable', 'numeric', 'min:-0.5', 'max:0.5'],
            'initialVelocity' => ['nullable', 'numeric', 'min:-0.5', 'max:0.5'],
            'initialAngle' => ['nullable', 'numeric', 'min:-0.2', 'max:0.2'],
            'initialAngularVelocity' => ['nullable', 'numeric', 'min:-1', 'max:1'],
            'duration' => ['nullable', 'numeric', 'min:0.5', 'max:10'],
            'step' => ['nullable', 'numeric', 'min:0.01', 'max:0.1'],
        ]));

        return $this->execute($request, $runner->simulateInvertedPendulum($validated), $validated);
    }

    public function ballBeam(Request $request, OctaveSimulationRunner $runner): JsonResponse
    {
        $validated = $request->validate($this->baseRules([
            'reference' => ['required', 'numeric', 'min:0', 'max:0.5'],
            'initialBallPosition' => ['nullable', 'numeric', 'min:0', 'max:0.5'],
            'initialBallVelocity' => ['nullable', 'numeric', 'min:-0.1', 'max:0.5'],
            'initialBeamAngle' => ['nullable', 'numeric', 'min:-0.2', 'max:0.2'],
            'initialBeamAngularVelocity' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'initialPosition' => ['nullable', 'numeric', 'min:0', 'max:0.5'],
            'initialVelocity' => ['nullable', 'numeric', 'min:-0.1', 'max:0.5'],
            'initialAngle' => ['nullable', 'numeric', 'min:-0.2', 'max:0.2'],
            'initialAngularVelocity' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'initialSpeed' => ['nullable', 'numeric', 'min:0', 'max:0.5'],
            'initialAcceleration' => ['nullable', 'numeric', 'min:-0.2', 'max:0.2'],
            'duration' => ['nullable', 'numeric', 'min:0.1', 'max:5'],
            'step' => ['nullable', 'numeric', 'min:0.005', 'max:0.05'],
        ]));

        return $this->execute($request, $runner->simulateBallBeam($validated), $validated);
    }

    /**
     * @param array<string, array<int, string>> $extraRules
     * @return array<string, array<int, string>>
     */
    private function baseRules(array $extraRules): array
    {
        return array_merge([
            'clientToken' => ['nullable', 'string', 'max:128'],
            'reference' => ['nullable', 'numeric'],
            'duration' => ['nullable', 'numeric', 'gt:0', 'max:120'],
            'step' => ['nullable', 'numeric', 'gt:0', 'max:1'],
            'slowdownMs' => ['nullable', 'integer', 'min:0', 'max:5000'],
        ], $extraRules);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function execute(Request $request, array $result, array $validated): JsonResponse
    {
        $clientToken = $this->resolveClientToken($request, $validated);
        $ipAddress = $this->resolveClientIp($request);
        $location = $this->ipLocationResolver->resolve($ipAddress);

        SimulationRun::query()->create([
            'simulation' => (string) ($result['simulation'] ?? 'unknown'),
            'client_token' => $clientToken,
            'request_payload' => $validated,
            'result_payload' => $result,
            'successful' => (bool) ($result['successful'] ?? false),
            'duration_ms' => $result['execution_ms'] ?? null,
            'ip_address' => $ipAddress,
            'city' => $location['city'],
            'country' => $location['country'],
            'error_message' => $result['error'] ?? $result['message'] ?? null,
        ]);

        if ($result['successful'] ?? false) {
            $this->recordUsageIfNeeded($clientToken, (string) ($result['simulation'] ?? 'unknown'), $ipAddress, $location);
        }

        $this->recordCasRequestLog($clientToken, $request, $result, $validated, $ipAddress);

        return $this->withClientCookie(response()->json([
            'success' => (bool) ($result['successful'] ?? false),
            'simulation' => $result['simulation'] ?? null,
            'time' => $result['time'] ?? [],
            'series' => $result['series'] ?? [],
            'state' => $result['state'] ?? [],
            'frames' => $result['frames'] ?? [],
            'message' => $result['message'] ?? null,
            'error' => $result['error'] ?? null,
            'executionMs' => $result['execution_ms'] ?? null,
        ], ($result['successful'] ?? false) ? 200 : 422), $clientToken);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function resolveClientToken(Request $request, array $validated): string
    {
        $clientToken = (string) $request->cookie(self::CLIENT_COOKIE, $validated['clientToken'] ?? '');

        if ($clientToken === '' || strlen($clientToken) > 128) {
            return (string) Str::uuid();
        }

        return $clientToken;
    }

    private function resolveClientIp(Request $request): ?string
    {
        $forwardedFor = (string) $request->headers->get('X-Forwarded-For', '');
        $candidates = array_filter(array_map('trim', explode(',', $forwardedFor)));
        $realIp = trim((string) $request->headers->get('X-Real-IP', ''));

        if ($realIp !== '') {
            $candidates[] = $realIp;
        }

        foreach ($candidates as $candidate) {
            if ($this->isPublicIp($candidate)) {
                return $candidate;
            }
        }

        return $request->ip();
    }

    private function isPublicIp(string $ipAddress): bool
    {
        return filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * @param array{city: string, country: string} $location
     */
    private function recordUsageIfNeeded(string $clientToken, string $simulation, ?string $ipAddress, array $location): void
    {
        $dedupeMinutes = (int) config('simulations.dedupe_minutes', 10);
        $threshold = now()->subMinutes($dedupeMinutes);

        $recentUsageExists = AnimationUsage::query()
            ->where('simulation', $simulation)
            ->where('client_token', $clientToken)
            ->where('created_at', '>=', $threshold)
            ->exists();

        if ($recentUsageExists) {
            return;
        }

        AnimationUsage::query()->create([
            'simulation' => $simulation,
            'client_token' => $clientToken,
            'ip_address' => $ipAddress,
            'city' => $location['city'],
            'country' => $location['country'],
        ]);
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $validated
     */
    private function recordCasRequestLog(
        string $clientToken,
        Request $request,
        array $result,
        array $validated,
        ?string $ipAddress,
    ): void {
        CasRequestLog::query()->create([
            'client_token' => $clientToken,
            'source' => 'simulation',
            'command' => $this->simulationCommandText($result, $validated),
            'successful' => (bool) ($result['successful'] ?? false),
            'stdout' => $this->simulationOutputText($result),
            'stderr' => '',
            'error_message' => $result['error'] ?? $result['message'] ?? null,
            'execution_ms' => $result['execution_ms'] ?? 0,
            'ip_address' => $ipAddress,
            'user_agent' => $request->userAgent(),
        ]);
    }

    /**
     * @param array<string, mixed> $result
     * @param array<string, mixed> $validated
     */
    private function simulationCommandText(array $result, array $validated): string
    {
        $simulation = (string) ($result['simulation'] ?? 'unknown');
        $payload = json_encode($validated, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return "simulation: {$simulation}\npayload: ".($payload !== false ? $payload : '{}');
    }

    /**
     * @param array<string, mixed> $result
     */
    private function simulationOutputText(array $result): string
    {
        $summary = [
            'timePoints' => count($result['time'] ?? []),
            'series' => $this->seriesNames($result['series'] ?? []),
            'state' => $this->seriesNames($result['state'] ?? []),
            'frames' => count($result['frames'] ?? []),
        ];

        $encoded = json_encode($summary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded !== false ? $encoded : '';
    }

    /**
     * @return array<int, mixed>
     */
    private function seriesNames(mixed $series): array
    {
        if (! is_array($series)) {
            return [];
        }

        return array_values(array_map(
            fn (mixed $item): mixed => is_array($item) ? ($item['name'] ?? null) : null,
            $series,
        ));
    }

    private function withClientCookie(JsonResponse $response, string $clientToken): JsonResponse
    {
        return $response->cookie(
            self::CLIENT_COOKIE,
            $clientToken,
            self::CLIENT_COOKIE_MINUTES,
            '/',
            null,
            false,
            true,
            false,
            'Lax',
        );
    }
}
