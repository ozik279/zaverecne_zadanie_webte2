<?php

namespace App\Http\Controllers;

use App\Models\AnimationUsage;
use App\Models\SimulationRun;
use Illuminate\Http\JsonResponse;

class StatisticsController extends Controller
{
    private const SIMULATIONS = ['inverted-pendulum', 'ball-beam'];

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => array_map(fn (string $simulation): array => $this->summaryFor($simulation), self::SIMULATIONS),
        ]);
    }

    public function show(string $simulation): JsonResponse
    {
        abort_unless(in_array($simulation, self::SIMULATIONS, true), 404);

        return response()->json([
            'data' => array_merge($this->summaryFor($simulation), [
                'recentUsages' => AnimationUsage::query()
                    ->where('simulation', $simulation)
                    ->latest('created_at')
                    ->limit(50)
                    ->get()
                    ->map(fn (AnimationUsage $usage): array => [
                        'createdAt' => optional($usage->created_at)->toISOString(),
                        'city' => $usage->city ?: 'Unknown',
                        'country' => $usage->country ?: 'Unknown',
                    ])
                    ->values(),
                'recentRuns' => SimulationRun::query()
                    ->where('simulation', $simulation)
                    ->latest('created_at')
                    ->limit(50)
                    ->get()
                    ->map(fn (SimulationRun $run): array => [
                        'createdAt' => optional($run->created_at)->toISOString(),
                        'successful' => (bool) $run->successful,
                        'durationMs' => $run->duration_ms,
                        'city' => $run->city ?: 'Unknown',
                        'country' => $run->country ?: 'Unknown',
                    ])
                    ->values(),
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summaryFor(string $simulation): array
    {
        return [
            'simulation' => $simulation,
            'runs' => SimulationRun::query()->where('simulation', $simulation)->count(),
            'successfulRuns' => SimulationRun::query()->where('simulation', $simulation)->where('successful', true)->count(),
            'usages' => AnimationUsage::query()->where('simulation', $simulation)->count(),
            'lastRunAt' => optional(SimulationRun::query()->where('simulation', $simulation)->latest('created_at')->first()?->created_at)->toISOString(),
            'lastUsageAt' => optional(AnimationUsage::query()->where('simulation', $simulation)->latest('created_at')->first()?->created_at)->toISOString(),
        ];
    }
}
