<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip API key check if not required
        if (!config('app.require_api_key', true)) {
            return $next($request);
        }

        $apiKey = $request->header('X-API-Key') ?? $request->get('api_key');
        $expectedApiKey = config('app.api_secret_key');

        if (!$apiKey || !$expectedApiKey || !hash_equals($expectedApiKey, $apiKey)) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'Invalid or missing API key',
                'timestamp' => now()->toISOString(),
            ], 401);
        }

        return $next($request);
    }
}