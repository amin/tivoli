<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthSessionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'access_key' => ['required', 'string', 'uuid'],
        ]);

        $name = trim($validated['name']);
        $accessKey = $validated['access_key'];

        $user = User::whereRaw('LOWER(name) = ?', [Str::lower($name)])->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->access_key === null) {
            return response()->json(['message' => 'User not activated'], 400);
        }

        if (!Hash::check($accessKey, $user->access_key)) {
            return response()->json(['message' => 'Invalid access key'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'User is inactive'], 403);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json($user);
    }

    public function destroy(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out']);
    }
}
