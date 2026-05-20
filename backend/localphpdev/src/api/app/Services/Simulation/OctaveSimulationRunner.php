<?php

namespace App\Services\Simulation;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class OctaveSimulationRunner
{
    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function simulateInvertedPendulum(array $parameters): array
    {
        return $this->runSimulation('inverted-pendulum', $parameters, $this->buildInvertedPendulumScript($parameters));
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function simulateBallBeam(array $parameters): array
    {
        return $this->runSimulation('ball-beam', $parameters, $this->buildBallBeamScript($parameters));
    }

    /**
     * @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    private function runSimulation(string $simulation, array $parameters, string $script): array
    {
        $startedAt = microtime(true);
        $tempPath = (string) config('simulations.temp_path');
        $this->ensureTempPathExists($tempPath);

        $scriptPath = $tempPath.DIRECTORY_SEPARATOR.'simulation_'.Str::uuid().'.m';
        File::put($scriptPath, $script);

        try {
            $process = new Process([
                'octave',
                '--quiet',
                '--no-gui',
                $scriptPath,
            ]);
            $process->setTimeout((int) config('simulations.timeout_seconds', 15));
            $process->run();

            if (! $process->isSuccessful()) {
                return [
                    'successful' => false,
                    'simulation' => $simulation,
                    'time' => [],
                    'series' => [],
                    'state' => [],
                    'frames' => [],
                    'message' => trim($process->getErrorOutput()) ?: 'Simulation command failed.',
                    'error' => trim($process->getErrorOutput()) ?: 'Simulation command failed.',
                    'execution_ms' => $this->elapsedMs($startedAt),
                ];
            }

            $parsed = $this->parseOutput((string) $process->getOutput());
            $time = $this->parseVector($parsed['TIME'] ?? '[]');
            $series = $this->parseSeriesLine($parsed['SERIES'] ?? '');
            $state = $this->parseSeriesLine($parsed['STATE'] ?? '');

            $this->applySlowdown((int) ($parameters['slowdownMs'] ?? config('simulations.slowdown_ms', 0)), count($time));

            return [
                'successful' => true,
                'simulation' => $simulation,
                'time' => $time,
                'series' => $series,
                'state' => $state,
                'frames' => $this->buildFrames($simulation, $time, $series, $state, $parameters),
                'message' => null,
                'error' => null,
                'execution_ms' => $this->elapsedMs($startedAt),
            ];
        } catch (ProcessTimedOutException) {
            return [
                'successful' => false,
                'simulation' => $simulation,
                'time' => [],
                'series' => [],
                'state' => [],
                'frames' => [],
                'message' => 'Simulation command timed out.',
                'error' => 'Simulation command timed out.',
                'execution_ms' => $this->elapsedMs($startedAt),
            ];
        } catch (Throwable $exception) {
            return [
                'successful' => false,
                'simulation' => $simulation,
                'time' => [],
                'series' => [],
                'state' => [],
                'frames' => [],
                'message' => $exception->getMessage(),
                'error' => $exception->getMessage(),
                'execution_ms' => $this->elapsedMs($startedAt),
            ];
        } finally {
            File::delete($scriptPath);
        }
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function buildInvertedPendulumScript(array $parameters): string
    {
        $reference = $this->floatValue($parameters['reference'] ?? 0.2);
        $initialPosition = $this->floatValue($parameters['initialPosition'] ?? 0.0);
        $initialAngle = $this->floatValue($parameters['initialAngle'] ?? 0.0);
        $duration = $this->floatValue($parameters['duration'] ?? 10.0);
        $step = $this->floatValue($parameters['step'] ?? 0.05);
        $baseScript = $this->loadModelScript('kyvadlo.txt');
        $modelScript = $this->stripDemoSimulation($baseScript);

        return <<<OCTAVE
pkg load control;

{$modelScript}

    t = 0:$step:$duration;
    r = $reference;
    initPozicia = $initialPosition;
    initUhol = $initialAngle;
    [y,t,x] = lsim(sys,r*ones(size(t)),t,[initPozicia;0;initUhol;0]);

printf('__SIM_BEGIN__\\n');
printf('TIME=%s\\n', mat2str(t, 12));
printf('SERIES=cart_position:%s|pendulum_angle:%s\\n', mat2str(y(:,1), 12), mat2str(y(:,2), 12));
printf('STATE=position:%s|velocity:%s|angle:%s|angular_velocity:%s\\n', mat2str(x(:,1), 12), mat2str(x(:,2), 12), mat2str(x(:,3), 12), mat2str(x(:,4), 12));
printf('__SIM_END__\\n');
OCTAVE;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function buildBallBeamScript(array $parameters): string
    {
        $reference = $this->floatValue($parameters['reference'] ?? 0.25);
        $initialPosition = $this->floatValue($parameters['initialPosition'] ?? ($parameters['initialSpeed'] ?? 0.0));
        $initialAngle = $this->floatValue($parameters['initialAngle'] ?? ($parameters['initialAcceleration'] ?? 0.0));
        $duration = $this->floatValue($parameters['duration'] ?? 5.0);
        $step = $this->floatValue($parameters['step'] ?? 0.01);
        $baseScript = $this->loadModelScript('gulicka.txt');
        $modelScript = $this->stripDemoSimulation($baseScript);

        return <<<OCTAVE
    pkg load control;

    {$modelScript}

t = 0:$step:$duration;
r = $reference;
initPoloha = $initialPosition;
initUhol = $initialAngle;
[y,t,x] = lsim(N*sys,r*ones(size(t)),t,[initPoloha;0;initUhol;0]);

printf('__SIM_BEGIN__\\n');
printf('TIME=%s\\n', mat2str(t, 12));
printf('SERIES=ball_position:%s|beam_angle:%s\\n', mat2str(y(:,1), 12), mat2str(x(:,3), 12));
printf('STATE=state_1:%s|state_2:%s|state_3:%s|state_4:%s\\n', mat2str(x(:,1), 12), mat2str(x(:,2), 12), mat2str(x(:,3), 12), mat2str(x(:,4), 12));
printf('__SIM_END__\\n');
OCTAVE;
    }

    private function loadModelScript(string $fileName): string
    {
        $candidates = [
            base_path('../other/'.$fileName),
            base_path('../../other/'.$fileName),
            base_path('../../../other/'.$fileName),
            base_path('../../../../other/'.$fileName),
        ];

        $scriptPath = null;
        foreach ($candidates as $candidate) {
            if (File::exists($candidate)) {
                $scriptPath = $candidate;
                break;
            }
        }

        if (! is_string($scriptPath)) {
            throw new \RuntimeException("Simulation model script not found: {$fileName}");
        }

        return trim((string) File::get($scriptPath));
    }

    private function stripDemoSimulation(string $script): string
    {
        $lines = preg_split('/\R/', $script) ?: [];
        $keptLines = [];

        foreach ($lines as $line) {
            if (preg_match('/^\s*t\s*=\s*0\s*:\s*/', $line)) {
                break;
            }

            if (preg_match('/^\s*\[y\s*,\s*t\s*,\s*x\s*\]\s*=/', $line)) {
                break;
            }

            if (preg_match('/^\s*plot\s*\(/', $line)) {
                break;
            }

            $keptLines[] = $line;
        }

        return trim(implode("\n", $keptLines));
    }

    private function applySlowdown(int $slowdownMs, int $frameCount): void
    {
        if ($slowdownMs <= 0 || $frameCount <= 0) {
            return;
        }

        $effectiveFrames = max(1, min($frameCount, 20));
        usleep($slowdownMs * 1000 * $effectiveFrames);
    }

    /**
     * @return array<string, string>
     */
    private function parseOutput(string $output): array
    {
        $lines = preg_split('/\R/', $output) ?: [];
        $capture = false;
        $data = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '__SIM_BEGIN__') {
                $capture = true;
                continue;
            }

            if ($line === '__SIM_END__') {
                break;
            }

            if (! $capture || $line === '' || ! str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $data[trim($key)] = trim($value);
        }

        return $data;
    }

    /**
     * @return array<int, float>
     */
    private function parseVector(string $value): array
    {
        $clean = trim($value);
        $clean = trim($clean, '[]');

        if ($clean === '') {
            return [];
        }

        $clean = str_replace([';', '|'], ',', $clean);
        $parts = preg_split('/[\s,]+/', $clean, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_map(fn (string $part): float => (float) str_replace(',', '.', $part), $parts);
    }

    /**
     * @return array<int, array{name: string, values: array<int, float>}>
     */
    private function parseSeriesLine(string $value): array
    {
        $value = trim($value);

        if ($value === '') {
            return [];
        }

        $series = [];
        foreach (explode('|', $value) as $segment) {
            if (! str_contains($segment, ':')) {
                continue;
            }

            [$name, $vector] = explode(':', $segment, 2);
            $series[] = [
                'name' => trim($name),
                'values' => $this->parseVector($vector),
            ];
        }

        return $series;
    }

    /**
     * @param array<int, float> $time
     * @param array<int, array{name: string, values: array<int, float>}> $series
     * @param array<int, array{name: string, values: array<int, float>}> $state
     * @param array<string, mixed> $parameters
     * @return array<int, array<string, mixed>>
     */
    private function buildFrames(string $simulation, array $time, array $series, array $state, array $parameters): array
    {
        $frames = [];
        $length = count($time);
        $seriesMap = [];
        $stateMap = [];

        foreach ($series as $item) {
            $seriesMap[$item['name']] = $item['values'];
        }

        foreach ($state as $item) {
            $stateMap[$item['name']] = $item['values'];
        }

        for ($index = 0; $index < $length; $index++) {
            $frame = [
                'time' => $time[$index],
                'reference' => $this->floatValue($parameters['reference'] ?? 0),
            ];

            foreach ($seriesMap as $name => $values) {
                $frame[$name] = $values[$index] ?? null;
            }

            foreach ($stateMap as $name => $values) {
                $frame[$name] = $values[$index] ?? null;
            }

            if ($simulation === 'inverted-pendulum') {
                $cartPosition = (float) ($frame['cart_position'] ?? 0.0);
                $angle = (float) ($frame['pendulum_angle'] ?? 0.0);
                $lengthValue = 0.3;

                $frame['cartX'] = $cartPosition;
                $frame['pendulumTipX'] = $cartPosition + $lengthValue * sin($angle);
                $frame['pendulumTipY'] = -$lengthValue * cos($angle);
            }

            if ($simulation === 'ball-beam') {
                $ballPosition = (float) ($frame['ball_position'] ?? ($frame['position'] ?? ($frame['state_1'] ?? 0.0)));
                $beamAngle = (float) ($frame['beam_angle'] ?? ($frame['angle'] ?? ($frame['state_3'] ?? 0.0)));

                $frame['ballPosition'] = $ballPosition;
                $frame['beamAngle'] = $beamAngle;
            }

            $frames[] = $frame;
        }

        return $frames;
    }

    private function ensureTempPathExists(string $tempPath): void
    {
        if (! File::isDirectory($tempPath)) {
            File::ensureDirectoryExists($tempPath);
        }
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }

    private function floatValue(mixed $value): float
    {
        return (float) str_replace(',', '.', (string) $value);
    }
}