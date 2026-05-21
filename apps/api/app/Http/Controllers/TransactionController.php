<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayoutTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Amusement;
use App\Models\IdentityToken;
use App\Models\Stamp;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    private const RATE_LIMIT_MINUTES = 3;

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $amount = (float) $data['amount'];

        $amusement = Amusement::where('api_key', $data['api_key'])->first();
        if (!$amusement) {
            return response()->json(['message' => 'Invalid api_key'], 401);
        }

        if ($amusement->settled_at !== null) {
            return response()->json(['message' => 'Amusement has been settled'], 409);
        }

        $identityToken = IdentityToken::where('token', $data['identity_token'])->first();
        if (!$identityToken || !$identityToken->isValid()) {
            return response()->json(['message' => 'Invalid or expired identity token'], 401);
        }

        $user = $identityToken->user;

        if ($user->balance < $amount) {
            return response()->json(['message' => 'Insufficient balance'], 402);
        }

        return DB::transaction(function () use ($user, $amusement, $amount, $identityToken) {
            $user->decrement('balance', $amount);
            $amusement->increment('amusement_balance', $amount);

            // Income is also distributed to the amusement's group members in
            // real time. `amusement_balance` is a net tracker, not a pool —
            // the double-credit is intentional (spec D6).
            $this->distributeToOwners($amusement, $amount);

            // Stamp eligibility (option B — token gets one attempt):
            //  - this identity_token has not been used before (consumed_at is null), AND
            //  - no stamp has been issued for this (user, amusement) within the last 3 min.
            // Either way, mark the token as consumed on first use to spend its chance.
            $isFirstUse = $identityToken->consumed_at === null;

            $recentStamped = Transaction::where('user_id', $user->id)
                ->where('amusement_id', $amusement->id)
                ->whereNotNull('stamp_id')
                ->where('created_at', '>', now()->subMinutes(self::RATE_LIMIT_MINUTES))
                ->exists();

            $stamp = ($isFirstUse && !$recentStamped) ? Stamp::generate($user->id) : null;

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'amusement_id' => $amusement->id,
                'stamp_id' => $stamp?->id,
                'amount' => $amount,
                'type' => 'fee',
            ]);

            if ($stamp) {
                $stamp->update(['transaction_id' => $transaction->id]);
            }

            if ($isFirstUse) {
                $identityToken->update(['consumed_at' => now()]);
            }

            return response()->json([
                'transaction_id' => $transaction->id,
                'amount' => $amount,
                'stamp' => $stamp ? [
                    'animal' => $stamp->animal,
                    'metal' => $stamp->metal,
                    'image_url' => $stamp->image_url,
                ] : null,
            ], 201);
        });
    }

    public function payout(PayoutTransactionRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        $amusement = Amusement::where('api_key', $data['api_key'])->first();
        if (!$amusement) {
            return response()->json(['message' => 'Invalid api_key'], 401);
        }

        $original = Transaction::find($id);
        if (!$original) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        if ($original->amusement_id !== $amusement->id) {
            return response()->json(['message' => 'Transaction does not belong to this amusement'], 403);
        }

        if ($original->type !== 'fee') {
            return response()->json(['message' => 'Only fee transactions can be paid out'], 400);
        }

        if ($amusement->type === 'attraction') {
            return response()->json(['message' => 'Attractions cannot pay out'], 409);
        }

        if ($original->settled_at !== null) {
            return response()->json(
                ['message' => "Transaction #{$original->id} has already been paid out"],
                409,
            );
        }

        // Amusement balance is allowed to go negative; it's reconciled at
        // settle (group members absorb the debt).
        return DB::transaction(function () use ($original, $amusement, $data) {
            $amusement->decrement('amusement_balance', $data['amount']);
            $original->user->increment('balance', $data['amount']);
            $original->update(['settled_at' => now()]);

            $payout = Transaction::create([
                'user_id' => $original->user_id,
                'amusement_id' => $amusement->id,
                'amount' => $data['amount'],
                'type' => 'payout',
            ]);

            return response()->json([
                'transaction_id' => $payout->id,
                'amount' => (float) $data['amount'],
            ], 201);
        });
    }

    private function distributeToOwners(Amusement $amusement, float $amount): void
    {
        $members = $amusement->group?->users()->get() ?? collect();
        $n = $members->count();
        if ($n === 0) {
            return;
        }

        // Floor so the distributed total never exceeds $amount; any
        // sub-cent remainder stays un-attributed (the user already paid it,
        // and amusement_balance still tracks it gross).
        $share = floor($amount / $n * 100) / 100;
        if ($share <= 0) {
            return;
        }

        foreach ($members as $member) {
            $member->increment('balance', $share);
        }
    }
}
