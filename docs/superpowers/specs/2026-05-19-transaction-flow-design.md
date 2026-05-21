# Transaction flow — entry/in-game split, immediate owner distribution, VP penalty

Date: 2026-05-19
Branch: `feature/updated-transaction-flow`

## Purpose

Reshape the amusement transaction flow on the Laravel API at `apps/api/`. Three behavioural changes drive the work:

1. **Two implicit transaction types** sharing a single endpoint: payments that mint a stamp (entry-equivalent) and payments that do not (in-game purchase). The classification is decided server-side from a rate-limit rule.
2. **Anti-stamp-farming rate limit**: one stamp per (user, amusement) per 3 minutes. Subsequent payments within the window still charge the user but skip stamp generation. This forces amusements to treat extra mid-game charges as in-game purchases instead of farming stamps via cheap restarts.
3. **Continuous owner distribution + VP penalty for net-negative amusements**: income flows directly to the owning group's personal balances on every transaction. The amusement keeps a separate net tracker (`amusement_balance`). If any amusement in a user's group has net < 0, that user's VP on the leaderboard is 0. A simplified `POST /settle` distributes the negative tracker as personal debt at event end.

## Scope

In scope:
- `apps/api/app/Http/Controllers/TransactionController.php`
- `apps/api/app/Http/Controllers/LeaderboardController.php`
- `apps/api/app/Http/Controllers/StampController.php`
- `apps/api/app/Http/Controllers/SettleController.php` (new — replaces the removed scaffold from the backend cleanup)
- `apps/api/app/Http/Requests/StoreTransactionRequest.php`, `PayoutTransactionRequest.php`
- `apps/api/app/Models/Transaction.php`, `Amusement.php`, `Stamp.php`, `User.php`, `Group.php`
- `apps/api/app/Services/` — new `OwnerDistributor.php`, `RateLimiter.php` (or inlined; see "Open implementation choices")
- New migrations under `apps/api/database/migrations/`
- New tests under `apps/api/tests/Feature/`
- `apps/api/routes/api.php`
- `docs/centralbank-api.yaml`

Out of scope:
- The SPA at `apps/web/`. Frontend has unrelated in-progress changes in `web/src/pages/user.tsx`; this design does not touch it.
- The exchange flow (`ExchangeController`), votes, groups, amusements CRUD, auth.
- Backwards compatibility for the existing `POST /transactions` request/response shape — pre-production, hard cut.

## Decisions

| # | Decision | Rationale |
|---|---|---|
| D1 | Single `POST /transactions` endpoint, not two | Smaller API surface; amusement does not need to know in advance whether it will mint a stamp |
| D2 | Body accepts either `identity_token` (first call) or `continuation_token` (subsequent), never both | Preserves single-use 5-min identity-token contract; enables mid-game payments |
| D3 | `continuation_token` lives in a new dedicated table with sliding 30-min TTL bound to (user, amusement) | Token has its own lifecycle (sliding TTL, invalidation on new identity-token use); a separate table is the cleanest fit |
| D4 | Stamps are only ever minted by an `identity_token`-initiated call. A `continuation_token` call never awards a stamp, regardless of elapsed time. | Stamp = "this user started a new visit". In-game purchases are part of the same visit, so no stamp. |
| D4b | Rate limit = no stamp if a stamp-bearing tx for the same (user, amusement) exists within the last 3 minutes; payment still goes through; rate limit only applies to identity-token calls. | Anti-farming: blocks the pattern "leave game → fresh identity_token → new stamp → repeat". Does not affect in-game purchases (which never award stamps anyway). |
| D5 | `transactions.type` is set by which token was used: `identity_token` → `fee`; `continuation_token` → `in_game_purchase`. Not by whether a stamp was awarded. | A rate-limited entry-call is still recorded as `fee` with `stamp_id = null` — the audit trail shows the intent ("started a new session, but farming-blocked") separately from the stamp outcome. |
| D6 | Income flows to owners' personal balances on every tx (double-credit; `amusement_balance` also rises) | Owners are rewarded continuously, not at /settle; `amusement_balance` becomes a pure net tracker |
| D7 | Owner distribution uses `round(amount / N, 2)` per member; small rounding deltas are tolerated (örar slängs) | Approved trade-off — exactness not required, simplest math |
| D8 | Payouts still draw from `amusement_balance`; the tracker can go negative without limit | Preserves current "payouts always succeed" contract |
| D9 | VP penalty trigger: any amusement in user's group has `amusement_balance < 0` at read time | Tracker reflects lifetime net income minus lifetime payouts; goes negative exactly when payouts > income cumulatively |
| D10 | `POST /settle` is reintroduced (simplified): for each amusement with `amusement_balance < 0`, split the deficit equally and deduct from members' personal balances; mark `settled_at` for idempotency | User-requested debt distribution. `amusement_balance` is NOT zeroed at settle — the negative value remains as the VP-penalty trigger after settle |
| D11 | Members' personal `balance` is allowed to go negative as a result of settle | Settle must always succeed; mirrors today's "amusement_balance allowed negative" policy |
| D12 | Existing scaffolded `/settle` (returns 501, deleted in backend-cleanup) is rebuilt with the new semantics | Spec reused; semantics rewritten in `centralbank-api.yaml` |

## Data model

### Migration 1 — Extend `transactions.type` enum
Final values: `fee`, `in_game_purchase`, `payout`, `owner_revenue`, `exchange`.

`owner_revenue` and `exchange` remain unused for now — out of scope to remove.

### Migration 2 — Stamp linkage and rate-limit index on `transactions`
- `stamp_id` (unsignedBigInteger, nullable, FK → `stamps.id` on delete set null) — set when a stamp is awarded.
- Composite index: `(user_id, amusement_id, created_at)` — supports the rate-limit lookup.

### Migration 3 — Reverse stamp linkage
- `stamps.transaction_id` (unsignedBigInteger, nullable, FK → `transactions.id` on delete set null) — kept for traceability and possible future stamp revocation.

### Migration 4 — `continuation_tokens` table
```
id            bigint pk
user_id       bigint fk users.id     // not null
amusement_id  bigint fk amusements.id // not null
token         string unique          // uuid
expires_at    timestamp              // sliding: now + 30 min
created_at, updated_at
unique (user_id, amusement_id)       // at most one active per pair
```
"At most one active per pair" is enforced by upsert on issuance and by deleting/replacing on each new identity-token consumption.

### Migration 5 — Settle idempotency on `amusements`
- `settled_at` (timestamp, nullable). Set by `POST /settle`; second call skips amusements where this is already set.

`buffer_required` and `buffer_locked` columns remain on `amusements` but are unused — out of scope.

## Endpoints

### `POST /transactions`

Replaces today's `POST /transactions`. Single endpoint for both first-of-game and mid-game payments.

**Request body** (exactly one of `identity_token` or `continuation_token` must be present):
```json
{
  "identity_token":     "<uuid>",   // OR
  "continuation_token": "<uuid>",
  "amount":             1.50,
  "api_key":            "<uuid>"
}
```

**Flow:**

1. Resolve and authenticate the amusement via `api_key`. 401 if invalid.
2. Resolve the user:
   - If `identity_token` is present: load token, verify unexpired and unconsumed, consume (`consumed_at = now`). 401 on failure.
   - Else if `continuation_token` is present: load token row, verify `amusement_id` matches the calling amusement (403 mismatch) and `expires_at > now` (401 expired).
   - Validation rejects both-present or both-absent with 422.
3. Verify `user.balance >= amount`. 402 otherwise.
4. Inside `DB::transaction(...)`:
   - `user.balance -= amount`
   - `amusement.amusement_balance += amount`
   - Distribute to owners: for each `member` in `amusement.group.users`, `member.balance += round(amount / N, 2)`. `N = group.users.count()`.
   - **If `identity_token` was used** (entry-call):
     - `type = 'fee'`.
     - Rate-limit check:
       ```php
       $recentStamped = Transaction::where('user_id', $userId)
           ->where('amusement_id', $amusementId)
           ->whereNotNull('stamp_id')
           ->where('created_at', '>', now()->subMinutes(3))
           ->exists();
       ```
       If true: `$stamp = null`, `stamp_id = null` on the tx.
       Otherwise: `$stamp = Stamp::generate($user->id)`, link `stamp_id` on the tx.
     - Delete any existing `continuation_tokens` row for (user, amusement); insert a fresh row with new UUID + `expires_at = now + 30 min`.
   - **If `continuation_token` was used** (in-game-call):
     - `type = 'in_game_purchase'`. `$stamp = null` unconditionally. `stamp_id = null`.
     - Update the continuation_token row's `expires_at = now + 30 min` (sliding).
   - Create the `transactions` row.
6. Return `201`:
   ```json
   {
     "id": 42,
     "stamp": { ... } | null,
     "continuation_token": "<uuid>",
     "continuation_expires_at": "2026-05-19T11:30:00+00:00"
   }
   ```

**Status codes:** 201, 401 (bad api_key, expired/consumed identity_token, expired continuation_token), 402, 403 (continuation_token from a different amusement), 422 (validation, including both/neither token fields).

### `POST /transactions/{id}/payout`

Behavioural change: only `type = fee` transactions are accepted as payout anchors. `in_game_purchase` rows are rejected with 400. Otherwise unchanged:
- `amusement.amusement_balance -= amount`
- `original.user.balance += amount`
- `original.settled_at = now` (existing column; reuses today's semantic of "this transaction has been paid out")
- Owner distribution does NOT run on payouts.

### `POST /settle`

Reintroduced from `centralbank-api.yaml` with new, simpler semantics. Implementation in a new `SettleController`.

**Flow:**

1. Wrap in `DB::transaction(...)`.
2. For each `Amusement` where `settled_at IS NULL`:
   - If `amusement_balance < 0`:
     - `$debt = abs(amusement_balance)`
     - `$N = group.users.count()`
     - For each member: `member.balance -= round($debt / $N, 2)`. Members may go negative.
   - Set `amusement.settled_at = now()` regardless of sign.
3. Return a summary describing per-amusement actions:
   ```json
   {
     "amusements_settled": 4,
     "details": [
       { "amusement_id": 1, "amusement_balance": -12.50, "deducted_per_member": 6.25, "member_count": 2 },
       { "amusement_id": 2, "amusement_balance":   3.00, "deducted_per_member": 0,    "member_count": 2 }
     ]
   }
   ```

Idempotency: a second call sees `settled_at IS NOT NULL` and skips. `amusement_balance` is never zeroed.

**Auth:** authenticated user **and** the user's group must be admin. Mirrors `ResetController`:

```php
if (!$user->group?->is_admin) {
    return response()->json(['message' => 'Forbidden'], 403);
}
```

403 for non-admin callers.

## VP penalty

Implemented in two places:

`App\Models\User::hasNegativeGroupAmusement(): bool`
```php
return $this->group
    ?->amusements()
    ->where('amusement_balance', '<', 0)
    ->exists() ?? false;
```

`LeaderboardController@show` — when building `vp_leaders`, for each user:
```php
$totalVp = $u->hasNegativeGroupAmusement()
    ? 0
    : VictoryPointsCalculator::compute($u->stamps);
```

`StampController@index` — same check, `total_vp = 0` when triggered.

The penalty is live before settle (the tracker reflects in-flight net) and persists after settle (the tracker keeps its negative value). No `vp_locked_at_zero` flag is needed.

## Error handling

All payment math runs inside `DB::transaction`. The owner-distribution loop is part of the same transaction — a failure rolls back the user debit, the tracker, and any partial member credits together.

The rate-limit check is read-then-write within the same transaction; concurrent stamp issuance for the same (user, amusement) is theoretically possible but is bounded to two extra stamps in a tight race and not worth a row lock for this domain.

If a group has zero members (only possible via direct DB tampering — every amusement is created via a member's group), owner distribution is a no-op. The tracker still increments; the user is still charged.

## Test plan

New / updated under `apps/api/tests/Feature/`:

- `TransactionsTest.php` (replaces the unwritten one for the old endpoint):
  - identity_token call consumes the token, awards a stamp, type=fee, stamp_id set, issues a continuation_token
  - continuation_token call never awards a stamp, type=in_game_purchase, stamp_id null, even hours later
  - continuation_token used twice extends sliding expiry
  - continuation_token from a different amusement → 403
  - continuation_token after expiry → 401
  - both tokens present → 422
  - neither token present → 422
  - identity_token within 3 min of a prior stamped tx for same (user, amusement) → type=fee, stamp_id null (rate-limit anti-farming)
  - identity_token > 3 min after the last stamped tx → stamp awarded again
  - new identity_token replaces the prior continuation_token for the same (user, amusement)
  - owner-distribution credits each group member (with rounding case 5.00 / 3)
  - amusement_balance increments by the full amount
  - insufficient balance → 402, no side effects
- `PayoutTest.php` (updated):
  - fee tx can be paid out
  - in_game_purchase tx → 400
  - second payout on same fee → 409 (existing settled_at check)
  - amusement_balance decrements; user.balance increments; owners untouched
- `SettleTest.php` (new):
  - non-admin caller → 403, no side effects
  - admin caller, amusement with balance < 0 → each member loses ceil-split debt
  - admin caller, amusement with balance >= 0 → no balance changes for members
  - settled_at gets set; second call is a no-op
  - amusement_balance is NOT zeroed
- `LeaderboardVpPenaltyTest.php` (new or merged into existing leaderboard test):
  - user whose group has one amusement with balance < 0 → vp = 0
  - same user after settle (balance still < 0) → vp = 0
  - user whose group has all non-negative amusements → vp = computed
- `StampsIndexVpPenaltyTest.php` (new):
  - mirror of the leaderboard case for `total_vp` field

Existing `ExchangeStampsTest.php` is unaffected — exchange flow does not change.

## Documentation updates (`docs/centralbank-api.yaml`)

- Rewrite `POST /transactions` request body to the new oneOf-token shape; update response schema to include `continuation_token` and `continuation_expires_at`; remove the implicit stamp guarantee and document the 3-minute rate-limit rule explicitly.
- Update `TransactionListItem.type` enum: include `in_game_purchase`.
- Rewrite the "User flow (amusements)" section to describe identity_token → first /transactions → continuation_token → subsequent /transactions → payout, replacing the current single-transaction narrative.
- Update `POST /transactions/{id}/payout` to reject `in_game_purchase` anchors (409 → 400).
- Reintroduce `POST /settle` with the simplified semantic; explicitly state idempotency via `settled_at`.
- Document the VP penalty under `GET /leaderboard` and `GET /stamps`.

## Open implementation choices

These do not affect correctness but the writer of the implementation plan should pick one:

- **Rate-limit check location**: inline in `TransactionController` versus a dedicated `StampRateLimiter` service. Lean toward inline given the query is two lines.
- **Owner-distribution location**: inline in `TransactionController` versus an `OwnerDistributor` service. Worth extracting because it appears twice (entry-type tx and in-game tx run the same logic — actually only once now under the single endpoint, so inline is fine).
- **Continuation-token model**: dedicated Eloquent model versus DB query helpers. Lean toward an Eloquent `ContinuationToken` model for symmetry with `IdentityToken`.

## Migration order

1. `add_in_game_purchase_to_transactions_type_enum`
2. `add_stamp_id_and_index_to_transactions`
3. `add_transaction_id_to_stamps`
4. `create_continuation_tokens_table`
5. `add_settled_at_to_amusements`

All up-only; rollbacks are the inverse drops.

## What this design intentionally does not change

- `IdentityToken` semantics (single-use, 5-min). It still mints continuation tokens — the new mechanism extends mid-game payments without altering Tivoli's redirect contract.
- The exchange / VP calculation algorithm. `VictoryPointsCalculator::compute` is reused untouched; the penalty wraps its result.
- `amusement_balance` storage type. It remains a `decimal(8,2)` with the same float cast.
- The frontend (`apps/web/`). The SPA only consumes `/leaderboard` and `/stamps`; the response shapes for those gain a behavioural change (0 VP under penalty) but no field changes.
