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
            'data' => $this->summaryFor($simulation),
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