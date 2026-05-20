<?php

namespace App\Http\Controllers;

use App\Models\Stamp;
use App\Services\VpCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StampController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $stamps = Stamp::where('user_id', $request->user()->id)
            ->whereNull('exchanged_at')
            ->get();

        return response()->json([
            'data' => $stamps,
            'vp' => VpCalculator::compute($stamps)['total'],
        ]);
    }
}
