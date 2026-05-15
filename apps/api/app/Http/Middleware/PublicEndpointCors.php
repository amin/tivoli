<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Permissive CORS for the amusement-facing public endpoints
 * (api_key-authenticated, never cookie-authenticated).
 *
 * Runs before the default HandleCors so that:
 *   - preflight requests for these paths get answered here with `*`
 *   - actual requests get `Access-Control-Allow-Origin: *` set on the way out,
 *     overriding HandleCors (which rejects the origin since it's not in
 *     CORS_ALLOWED_ORIGINS).
 *
 * No `Access-Control-Allow-Credentials` is emitted — these endpoints do not
 * use the SPA session cookie, so credentialed CORS would be both unnecessary
 * and incompatible with `*`.
 */
class PublicEndpointCors
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->matches($request)) {
            return $next($request);
        }

        if ($request->getMethod() === 'OPTIONS') {
            return response('', 204, $this->corsHeaders($request));
        }

        $response = $next($request);
        foreach ($this->corsHeaders($request) as $k => $v) {
            $response->headers->set($k, $v);
        }
        return $response;
    }

    private function matches(Request $request): bool
    {
        $method = $request->isMethod('OPTIONS')
            ? strtoupper($request->headers->get('Access-Control-Request-Method', ''))
            : $request->getMethod();

        if ($method === 'GET' && $request->is('identity-tokens/*')) {
            return true;
        }
        if ($method === 'POST' && ($request->is('transactions') || $request->is('transactions/*/payout'))) {
            return true;
        }
        return false;
    }

    private function corsHeaders(Request $request): array
    {
        return [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => $request->headers->get(
                'Access-Control-Request-Headers',
                'Content-Type, Accept'
            ),
            'Access-Control-Max-Age' => '86400',
            'Vary' => 'Origin',
        ];
    }
}
