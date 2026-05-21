<?php

namespace App\Http\Controllers;

use App\Models\CasRequestLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CasLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'source' => ['nullable', 'string', 'max:32'],
            'successful' => ['nullable', 'in:true,false,1,0'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $logs = $this->filteredQuery($validated)
            ->orderByDesc('id')
            ->paginate($validated['perPage'] ?? 25);

        return response()->json([
            'data' => collect($logs->items())->map(fn (CasRequestLog $log): array => $this->serializeLog($log))->values(),
            'meta' => [
                'currentPage' => $logs->currentPage(),
                'perPage' => $logs->perPage(),
                'total' => $logs->total(),
                'lastPage' => $logs->lastPage(),
            ],
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'source' => ['nullable', 'string', 'max:32'],
            'successful' => ['nullable', 'in:true,false,1,0'],
        ]);

        $fileName = 'cas-request-logs-'.now()->format('Ymd-His').'.csv';
        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ];

        return response()->streamDownload(function () use ($validated): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'created_at',
                'source',
                'command',
                'successful',
                'execution_ms',
                'error_message',
                'ip_address',
                'user_agent',
            ]);

            $this->filteredQuery($validated)
                ->orderBy('id')
                ->chunk(200, function ($logs) use ($handle): void {
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            optional($log->created_at)->toISOString(),
                            $log->source,
                            $this->csvText($log->command),
                            $log->successful ? 'true' : 'false',
                            $log->execution_ms,
                            $this->csvText($log->error_message),
                            $log->ip_address,
                            $this->csvText($log->user_agent),
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, $headers);
    }

    /**
     * @param array<string, mixed> $filters
     * @return Builder<CasRequestLog>
     */
    private function filteredQuery(array $filters): Builder
    {
        return CasRequestLog::query()
            ->when(isset($filters['source']), function (Builder $query) use ($filters): void {
                $query->where('source', $filters['source']);
            })
            ->when(array_key_exists('successful', $filters), function (Builder $query) use ($filters): void {
                $query->where('successful', filter_var($filters['successful'], FILTER_VALIDATE_BOOLEAN));
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLog(CasRequestLog $log): array
    {
        return [
            'createdAt' => optional($log->created_at)->toISOString(),
            'source' => $log->source,
            'command' => $log->command,
            'successful' => $log->successful,
            'stdout' => $log->stdout,
            'stderr' => $log->stderr,
            'errorMessage' => $log->error_message,
            'executionMs' => $log->execution_ms,
            'ipAddress' => $log->ip_address,
            'userAgent' => $log->user_agent,
        ];
    }
    private function csvText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return str_replace(["\r\n", "\r", "\n"], '\n', $value);
    }
}
