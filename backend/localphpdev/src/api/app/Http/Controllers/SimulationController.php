<?php

namespace App\Http\Controllers;

use App\Models\AnimationUsage;
use App\Models\SimulationRun;
use App\Services\Simulation\OctaveSimulationRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SimulationController extends Controller
{
    public function invertedPendulum(Request $request, OctaveSimulationRunner $runner): JsonResponse
    {
        $validated = $request->validate($this->baseRules([
            'reference' => ['required', 'numeric'],
            'initialPosition' => ['nullable', 'numeric'],
            'initialAngle' => ['nullable', 'numeric'],
        ]));

        return $this->execute($request, $runner->simulateInvertedPendulum($validated), $validated);
    }

    public function ballBeam(Request $request, OctaveSimulationRunner $runner): JsonResponse
    {
        $validated = $request->validate($this->baseRules([
            'reference' => ['required', 'numeric'],
            'initialSpeed' => ['nullable', 'numeric'],
            'initialAcceleration' => ['nullable', 'numeric'],
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

        if ($clientToken === null) {
            return response()->json([
                'success' => false,
                'simulation' => $result['simulation'] ?? null,
                'error' => 'Missing client token.',
                'message' => 'Missing client token.',
                'time' => [],
                'series' => [],
                'state' => [],
                'frames' => [],
            ], 422);
        }

        $payload = $this->buildRequestPayload($validated, $clientToken);

        SimulationRun::query()->create([
            'simulation' => (string) ($result['simulation'] ?? 'unknown'),
            'client_token' => $clientToken,
            'request_payload' => $payload,
            'result_payload' => $result,
            'successful' => (bool) ($result['successful'] ?? false),
            'duration_ms' => $result['execution_ms'] ?? null,
            'ip_address' => $request->ip(),
            'city' => 'Unknown',
            'country' => 'Unknown',
            'error_message' => $result['error'] ?? $result['message'] ?? null,
        ]);

        if ($result['successful'] ?? false) {
            $this->recordUsageIfNeeded($clientToken, (string) ($result['simulation'] ?? 'unknown'), $request);
        }

        return response()->json([
            'success' => (bool) ($result['successful'] ?? false),
            'simulation' => $result['simulation'] ?? null,
            'time' => $result['time'] ?? [],
            'series' => $result['series'] ?? [],
            'state' => $result['state'] ?? [],
            'frames' => $result['frames'] ?? [],
            'message' => $result['message'] ?? null,
            'error' => $result['error'] ?? null,
            'executionMs' => $result['execution_ms'] ?? null,
        ], ($result['successful'] ?? false) ? 200 : 422);
    }

    /**
     * @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    private function buildRequestPayload(array $validated, string $clientToken): array
    {
        $validated['clientToken'] = $clientToken;

        return $validated;
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function resolveClientToken(Request $request, array $validated): ?string
    {
        $clientToken = (string) $request->cookie('cas_client_token', $validated['clientToken'] ?? '');

        return $clientToken !== '' ? $clientToken : null;
    }

    private function recordUsageIfNeeded(string $clientToken, string $simulation, Request $request): void
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
            'ip_address' => $request->ip(),
            'city' => 'Unknown',
            'country' => 'Unknown',
        ]);
    }
}