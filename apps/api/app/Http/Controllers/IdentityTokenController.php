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
}
