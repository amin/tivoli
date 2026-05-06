<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AmusementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }

    public function regenerateKey(Request $request, int $id): JsonResponse
    {
        return response()->json(['message' => 'Not implemented'], 501);
    }
}
