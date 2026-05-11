<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AmusementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $amusements = \App\Models\Amusement::select('id', 'name', 'type')->orderBy('type')->orderBy('name')->get();
        return response()->json(['data' => $amusements]);
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
