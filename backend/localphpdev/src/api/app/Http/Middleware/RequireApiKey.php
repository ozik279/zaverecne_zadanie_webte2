<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedKey = (string) config('cas.api_key');
        $headerName = (string) config('cas.api_key_header', 'X-API-Key');
        $providedKey = (string) $request->header($headerName, '');

        if ($expectedKey === '' || ! hash_equals($expectedKey, $providedKey)) {
            return response()->json([
                'message' => 'Invalid or missing API key.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
