<?php

namespace App\Http\Middleware;

use App\Models\Amusement;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AmusementApiKeyAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('x-api-key');

        if (!$apiKey) {
            return response()->json(['error' => 'Missing API key'], 401);
        }

        $amusement = Amusement::where('api_key', $apiKey)->first();

        if (!$amusement) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        $request->attributes->set('amusement', $amusement);

        return $next($request);
    }
}
