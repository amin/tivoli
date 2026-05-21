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
        $user = $request->user();

        $stamps = Stamp::where('user_id', $user->id)
            ->whereNull('exchanged_at')
            ->get();

        $totalVp = $user->hasNegativeGroupAmusement()
            ? 0
            : VpCalculator::compute($stamps)['total'];

        return response()->json([
            'data' => $stamps,
            'total_vp' => $totalVp,
        ]);
    }
}
