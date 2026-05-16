<?php

namespace App\Http\Controllers;

use App\Models\CasRequestLog;
use App\Models\CasSession;
use App\Services\Cas\OctaveRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CasConsoleController extends Controller
{
    public function execute(Request $request, OctaveRunner $runner): JsonResponse
    {
        $validated = $request->validate([
            'clientToken' => ['required', 'string', 'max:128'],
            'command' => ['required', 'string', 'max:10000'],
        ]);

        $session = CasSession::query()->firstOrCreate(
            ['client_token' => $validated['clientToken']],
            ['history' => []],
        );

        $history = $session->history ?? [];
        $result = $runner->execute($history, $validated['command']);

        if ($result['successful']) {
            $history[] = $validated['command'];
            $session->forceFill([
                'history' => $history,
                'last_used_at' => now(),
            ])->save();
        }

        CasRequestLog::query()->create([
            'client_token' => $validated['clientToken'],
            'source' => 'console',
            'command' => $validated['command'],
            'successful' => $result['successful'],
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
            'error_message' => $result['error_message'],
            'execution_ms' => $result['execution_ms'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json([
            'success' => $result['successful'],
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
            'error' => $result['error_message'],
            'executionMs' => $result['execution_ms'],
            'historyLength' => count($history),
        ], $result['successful'] ? 200 : 422);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clientToken' => ['required', 'string', 'max:128'],
        ]);

        CasSession::query()
            ->where('client_token', $validated['clientToken'])
            ->delete();

        return response()->json([
            'success' => true,
            'historyLength' => 0,
        ]);
    }
}
