<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AccessKeyAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {

        $accessKey = $request->header('x-access-key');

        if (!$accessKey) {
            return response()->json(['error' => 'Missing access key'], 401);
        }

        // If the client also provided a name, prefer a case-insensitive lookup
        $nameHeader = $request->header('x-user-name') ?? $request->input('name');
        if ($nameHeader) {
            $user = User::whereRaw('LOWER(name) = ?', [\Illuminate\Support\Str::lower(trim($nameHeader))])->first();
            if ($user && $user->access_key && Hash::check($accessKey, $user->access_key)) {
                // found
            } else {
                $user = null;
            }
        } else {
            // Fallback: check users with non-null access_key — limit batch size to avoid loading everything
            $user = null;
            User::whereNotNull('access_key')
                ->cursor()
                ->each(function($u) use (&$user, $accessKey) {
                    if (!$user && Hash::check($accessKey, $u->access_key)) {
                        $user = $u;
                    }
                });
        }

        if (!$user) {
            return response()->json(['error' => 'Invalid access key'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'User is inactive'], 403);
        }

        // Attach user to request
        $request->setUserResolver(fn() => $user);

        return $next($request);
    }
}
