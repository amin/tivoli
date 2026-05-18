<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
