<?php

namespace App\Http\Controllers;

use App\Models\IdentityToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdentityTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $token = IdentityToken::issueFor($request->user());

        return response()->json([
            'identity_token' => $token->token,
            'expires_at' => $token->expires_at->toIso8601String(),
        ], 201);
    }

    public function show(string $token): JsonResponse
    {
        $identityToken = IdentityToken::where('token', $token)->first();

        if (!$identityToken || !$identityToken->isValid()) {
            return response()->json(['message' => 'Invalid or expired identity token'], 401);
        }

        $user = $identityToken->user;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'expires_at' => $identityToken->expires_at->toIso8601String(),
        ]);
    }
}
