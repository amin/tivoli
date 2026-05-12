<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAmusementRequest;
use App\Models\Amusement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AmusementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $amusements = Amusement::orderBy('type')
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $amusements]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $amusement = Amusement::find($id);

        if (!$amusement) {
            return response()->json(['error' => 'Amusement not found'], 404);
        }

        if ($request->user()->group_id === $amusement->group_id) {
            $amusement->makeVisible('api_key');
        }

        return response()->json($amusement);
    }

    public function update(UpdateAmusementRequest $request, int $id): JsonResponse
    {
        $amusement = Amusement::find($id);

        if (!$amusement) {
            return response()->json(['error' => 'Amusement not found'], 404);
        }

        if ($request->user()->group_id !== $amusement->group_id) {
            return response()->json(['error' => 'You do not own this amusement'], 403);
        }

        $amusement->update($request->validated());

        return response()->json($amusement->makeVisible('api_key'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $amusement = Amusement::find($id);

        if (!$amusement) {
            return response()->json(['error' => 'Amusement not found'], 404);
        }

        if ($request->user()->group_id !== $amusement->group_id) {
            return response()->json(['error' => 'You do not own this amusement'], 403);
        }

        if ((float) $amusement->amusement_balance != 0.0) {
            return response()->json([
                'error' => "Cannot delete amusement with balance €" . number_format($amusement->amusement_balance, 2) . ". Settle first.",
            ], 409);
        }

        $amusement->delete();

        return response()->json(null, 204);
    }

    public function regenerateKey(Request $request, int $id): JsonResponse
    {
        $amusement = Amusement::find($id);

        if (!$amusement) {
            return response()->json(['error' => 'Amusement not found'], 404);
        }

        if ($request->user()->group_id !== $amusement->group_id) {
            return response()->json(['error' => 'You do not own this amusement'], 403);
        }

        $amusement->api_key = (string) Str::uuid();
        $amusement->save();

        return response()->json([
            'api_key' => $amusement->api_key,
        ]);
    }

    public function transactions(Request $request, int $id): JsonResponse
    {
        $amusement = Amusement::find($id);
        if (!$amusement) {
            return response()->json(['error' => 'Amusement not found'], 404);
        }
        if ($request->user()->group_id !== $amusement->group_id) {
            return response()->json(['error' => "Not in this amusement's group"], 403);
        }

        $tx = $amusement->transactions()
            ->select('id', 'user_id', 'amount', 'type', 'created_at')
            ->orderByDesc('created_at')
            ->get();

        return response()->json(['data' => $tx]);
    }

    public function stats(Request $request, int $id): JsonResponse
    {
        $amusement = Amusement::find($id);
        if (!$amusement) {
            return response()->json(['error' => 'Amusement not found'], 404);
        }
        if ($request->user()->group_id !== $amusement->group_id) {
            return response()->json(['error' => "Not in this amusement's group"], 403);
        }

        $rows = $amusement->transactions()
            ->selectRaw('type, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('type')
            ->get()
            ->keyBy('type');

        $fees = (float) ($rows['fee']->total ?? 0);
        $payouts = (float) ($rows['payout']->total ?? 0);

        return response()->json([
            'amusement_balance' => (float) $amusement->amusement_balance,
            'fees_total' => $fees,
            'fees_count' => (int) ($rows['fee']->count ?? 0),
            'payouts_total' => $payouts,
            'payouts_count' => (int) ($rows['payout']->count ?? 0),
            'net' => $fees - $payouts,
        ]);
    }
}
