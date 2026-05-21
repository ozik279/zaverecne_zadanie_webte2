<?php

namespace App\Http\Controllers;

use App\Models\CasRequestLog;
use App\Models\CasSession;
use App\Services\Cas\OctaveRunner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CasConsoleController extends Controller
{
    private const CLIENT_COOKIE = 'cas_client_token';
    private const CLIENT_COOKIE_MINUTES = 525600;

    public function execute(Request $request, OctaveRunner $runner): JsonResponse
    {
        $validated = $request->validate([
            'clientToken' => ['nullable', 'string', 'max:128'],
            'command' => ['required', 'string', 'max:10000'],
        ]);
        $clientToken = $this->resolveClientToken($request, $validated['clientToken'] ?? null);

        $session = CasSession::query()->firstOrCreate(
            ['client_token' => $clientToken],
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
            'client_token' => $clientToken,
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

        return $this->withClientCookie(response()->json([
            'success' => $result['successful'],
            'stdout' => $result['stdout'],
            'stderr' => $result['stderr'],
            'error' => $result['error_message'],
            'executionMs' => $result['execution_ms'],
            'historyLength' => count($history),
        ], $result['successful'] ? 200 : 422), $clientToken);
    }

    public function reset(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'clientToken' => ['nullable', 'string', 'max:128'],
        ]);
        $clientToken = $this->resolveClientToken($request, $validated['clientToken'] ?? null);

        CasSession::query()
            ->where('client_token', $clientToken)
            ->delete();

        return $this->withClientCookie(response()->json([
            'success' => true,
            'historyLength' => 0,
        ]), $clientToken);
    }

    private function resolveClientToken(Request $request, ?string $fallback = null): string
    {
        $clientToken = (string) $request->cookie(self::CLIENT_COOKIE, $fallback ?? '');

        if ($clientToken === '' || strlen($clientToken) > 128) {
            return (string) Str::uuid();
        }

        return $clientToken;
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
