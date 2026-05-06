<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettleController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
