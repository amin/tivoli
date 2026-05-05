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

        $user = User::whereNotNull('access_key')->get()
            ->first(fn($u) => Hash::check($accessKey, $u->access_key));

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
