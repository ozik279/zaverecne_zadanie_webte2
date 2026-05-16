<?php

namespace App\Services\Cas;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class OctaveRunner
{
    /**
     * @param array<int, string> $history
     * @return array{successful: bool, stdout: string, stderr: string, error_message: ?string, execution_ms: int}
     */
    public function execute(array $history, string $command): array
    {
        $startedAt = microtime(true);

        if ($error = $this->validateCommand($command)) {
            return [
                'successful' => false,
                'stdout' => '',
                'stderr' => '',
                'error_message' => $error,
                'execution_ms' => $this->elapsedMs($startedAt),
            ];
        }

        $tempPath = (string) config('cas.temp_path');
        $this->ensureTempPathExists($tempPath);

        $scriptPath = $tempPath.DIRECTORY_SEPARATOR.'cas_'.Str::uuid().'.m';
        File::put($scriptPath, $this->buildScript($history, $command));

        try {
            $process = new Process([
                (string) config('cas.octave_binary', 'octave'),
                '--quiet',
                '--no-gui',
                $scriptPath,
            ]);
            $process->setTimeout((int) config('cas.timeout_seconds', 10));
            $process->run();

            $stdout = $this->limitOutput($process->getOutput());
            $stderr = $this->limitOutput($this->cleanStderr($process->getErrorOutput()));
            $octaveError = $this->extractMarker($stdout, 'CAS_ERROR');
            $successful = $process->isSuccessful() && $octaveError === null;

            return [
                'successful' => $successful,
                'stdout' => $this->extractMarker($stdout, 'CAS_STDOUT') ?? ($successful ? $stdout : ''),
                'stderr' => $stderr,
                'error_message' => $successful ? null : ($octaveError ?? trim($stderr) ?: 'Octave command failed.'),
                'execution_ms' => $this->elapsedMs($startedAt),
            ];
        } catch (ProcessTimedOutException) {
            return [
                'successful' => false,
                'stdout' => '',
                'stderr' => '',
                'error_message' => 'Octave command timed out.',
                'execution_ms' => $this->elapsedMs($startedAt),
            ];
        } catch (Throwable $exception) {
            return [
                'successful' => false,
                'stdout' => '',
                'stderr' => '',
                'error_message' => $exception->getMessage(),
                'execution_ms' => $this->elapsedMs($startedAt),
            ];
        } finally {
            File::delete($scriptPath);
        }
    }

    private function validateCommand(string $command): ?string
    {
        $blocked = [
            'cd', 'delete', 'diary', 'dos', 'edit', 'fopen', 'load', 'mkdir',
            'movefile', 'popen', 'rmdir', 'save', 'system', 'type', 'unix',
        ];

        foreach ($blocked as $function) {
            if (preg_match('/(^|[^A-Za-z0-9_])'.preg_quote($function, '/').'\s*(\(|$)/i', $command)) {
                return "Command uses blocked Octave function: {$function}.";
            }
        }

        return null;
    }

    private function ensureTempPathExists(string $tempPath): void
    {
        if (File::isDirectory($tempPath)) {
            return;
        }

        try {
            File::ensureDirectoryExists($tempPath);
        } catch (Throwable $exception) {
            if (! File::isDirectory($tempPath)) {
                throw $exception;
            }
        }
    }

    /**
     * @param array<int, string> $history
     */
    private function buildScript(array $history, string $command): string
    {
        $historyLines = collect($history)
            ->map(fn (string $line): string => '  evalc('.$this->octaveString($line).');')
            ->implode("\n");

        return <<<OCTAVE
more off;
try
{$historyLines}
  __cas_stdout__ = evalc({$this->octaveString($command)});
  disp('__CAS_STDOUT_BEGIN__');
  fprintf('%s', __cas_stdout__);
  disp('');
  disp('__CAS_STDOUT_END__');
catch __cas_error__
  disp('__CAS_ERROR_BEGIN__');
  fprintf('%s\\n', __cas_error__.message);
  if isfield(__cas_error__, 'stack')
    for __cas_frame__ = __cas_error__.stack'
      fprintf('at %s:%d\\n', __cas_frame__.name, __cas_frame__.line);
    endfor
  endif
  disp('__CAS_ERROR_END__');
  exit(1);
end_try_catch
OCTAVE;
    }

    private function octaveString(string $value): string
    {
        if ($value === '') {
            return "''";
        }

        $bytes = unpack('C*', $value);

        return 'char(['.implode(',', $bytes).'])';
    }

    private function extractMarker(string $output, string $marker): ?string
    {
        $begin = "__{$marker}_BEGIN__";
        $end = "__{$marker}_END__";
        $start = strpos($output, $begin);
        $finish = strpos($output, $end);

        if ($start === false || $finish === false || $finish < $start) {
            return null;
        }

        $contentStart = $start + strlen($begin);

        return trim(substr($output, $contentStart, $finish - $contentStart));
    }

    private function limitOutput(string $output): string
    {
        $limit = (int) config('cas.output_limit_bytes', 20000);

        if ($limit <= 0 || strlen($output) <= $limit) {
            return $output;
        }

        return substr($output, 0, $limit)."\n[output truncated]";
    }

    private function cleanStderr(string $stderr): string
    {
        $lines = preg_split('/\R/', $stderr) ?: [];
        $lines = array_filter($lines, function (string $line): bool {
            return ! str_contains($line, 'ignoring const execution_exception& while preparing to exit');
        });

        return trim(implode("\n", $lines));
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
