<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IpWhitelistMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedIps = config('app.allowed_client_ips');
        
        // Skip IP check if no IPs are configured
        if (empty($allowedIps)) {
            return $next($request);
        }

        $allowedIpsArray = array_map('trim', explode(',', $allowedIps));
        $clientIp = $request->ip();

        // Check if client IP is in the whitelist
        if (!in_array($clientIp, $allowedIpsArray)) {
            return response()->json([
                'success' => false,
                'error' => 'Forbidden',
                'message' => 'Your IP address is not authorized to access this service',
                'client_ip' => $clientIp,
                'timestamp' => now()->toISOString(),
            ], 403);
        }

        return $next($request);
    }
}