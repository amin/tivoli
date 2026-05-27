<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAmusementRequest;
use App\Http\Requests\UpdateAmusementRequest;
use App\Models\Amusement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AmusementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userGroupId = $request->user()?->group_id;

        $query = Amusement::orderBy('type')->orderBy('name');

        if ($request->boolean('owned')) {
            if (!$userGroupId) {
                return response()->json(['data' => []]);
            }
            $query->where('group_id', $userGroupId);
        }

        $amusements = $query->get()->each(function ($amusement) use ($userGroupId) {
            if ($userGroupId && $amusement->group_id === $userGroupId) {
                $amusement->makeVisible('api_key');
            }
        });

        return response()->json(['data' => $amusements]);
    }

    public function store(StoreAmusementRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!$user->group_id) {
            return response()->json([
                'message' => 'Your user is not assigned to a group',
            ], 400);
        }

        $data = $request->validated();
        $data['group_id'] = $user->group_id;
        $data['api_key'] = (string) Str::uuid();

        $amusement = Amusement::forceCreate($data);

        return response()->json([
            'message' => 'Amusement registered. Save the api_key — it is only shown here.',
            'amusement' => $amusement->makeVisible('api_key'),
        ], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $amusement = Amusement::find($id);

        if (!$amusement) {
            return response()->json(['message' => 'Amusement not found'], 404);
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
            return response()->json(['message' => 'Amusement not found'], 404);
        }

        if ($request->user()->group_id !== $amusement->group_id) {
            return response()->json(['message' => 'You do not own this amusement'], 403);
        }

        $amusement->update($request->validated());

        return response()->json($amusement->makeVisible('api_key'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $amusement = Amusement::find($id);

        if (!$amusement) {
            return response()->json(['message' => 'Amusement not found'], 404);
        }

        if ($request->user()->group_id !== $amusement->group_id) {
            return response()->json(['message' => 'You do not own this amusement'], 403);
        }

        if ($amusement->settled_at === null && $amusement->transactions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete amusement with transactions before it has been settled.',
            ], 409);
        }

        $amusement->delete();

        return response()->json(null, 204);
    }

    public function regenerateKey(Request $request, int $id): JsonResponse
    {
        $amusement = Amusement::find($id);

        if (!$amusement) {
            return response()->json(['message' => 'Amusement not found'], 404);
        }

        if ($request->user()->group_id !== $amusement->group_id) {
            return response()->json(['message' => 'You do not own this amusement'], 403);
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
            return response()->json(['message' => 'Amusement not found'], 404);
        }
        if ($request->user()->group_id !== $amusement->group_id) {
            return response()->json(['message' => "Not in this amusement's group"], 403);
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
            return response()->json(['message' => 'Amusement not found'], 404);
        }
        if ($request->user()->group_id !== $amusement->group_id) {
            return response()->json(['message' => "Not in this amusement's group"], 403);
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
