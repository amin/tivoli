<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayoutTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Amusement;
use App\Models\Stamp;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (!$key) {
            return response()->json(['error' => 'Missing amusement key'], 401);
        }

        $amusement = Amusement::where('api_key', $key)->first();

        if (!$amusement) {
            return response()->json(['error' => 'Invalid amusement key'], 401);
        }

        return $amusement;
    }

    public function store(Request $request): JsonResponse
    {
        $amusement = $this->authenticate($request);
        if ($amusement instanceof JsonResponse) {
            return $amusement;
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        if ($user->balance < $amusement->price) {
            return response()->json(['error' => 'Insufficient balance'], 422);
        }

        $transaction = null;
        $stamp       = null;

        DB::transaction(function () use ($amusement, $user, &$transaction, &$stamp) {
            $user->decrement('balance', $amusement->price);
            $amusement->increment('amusement_balance', $amusement->price);

            $transaction = Transaction::create([
                'user_id'      => $user->id,
                'amusement_id' => $amusement->id,
                'amount'       => $amusement->price,
                'type'         => 'fee',
            ]);

            $stamp = Stamp::generate($user->id, $amusement->id);
        });

        return response()->json([
            'transaction' => $transaction,
            'stamp'       => $stamp,
        ], 201);
    }

    public function payout(PayoutTransactionRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        $amusement = Amusement::where('api_key', $data['api_key'])->first();

        if (!$amusement) {
            return response()->json(['message' => 'Invalid api_key'], 401);
        }

        $fee = Transaction::findOrFail($id);

        if ($fee->amusement_id !== $amusement->id) {
            return response()->json(['error' => 'Transaction does not belong to this amusement'], 403);
        }

        if ($fee->type !== 'fee') {
            return response()->json(['error' => 'Transaction is not a fee'], 422);
        }

        if ($fee->settled_at !== null) {
            return response()->json(['error' => "Transaction #{$fee->id} has already been paid out"], 409);
        }

        if (!$amusement->player_payout) {
            return response()->json(['error' => 'This amusement has no player payout configured'], 422);
        }

        $user              = User::findOrFail($fee->user_id);
        $payoutTransaction = null;

        DB::transaction(function () use ($amusement, $user, $fee, &$payoutTransaction) {
            $user->increment('balance', $amusement->player_payout);
            $amusement->decrement('amusement_balance', $amusement->player_payout);
            $fee->update(['settled_at' => now()]);

            $payoutTransaction = Transaction::create([
                'user_id'      => $user->id,
                'amusement_id' => $amusement->id,
                'amount'       => $amusement->player_payout,
                'type'         => 'payout',
            ]);
        });

        return response()->json(['transaction' => $payoutTransaction], 201);
    }
}
