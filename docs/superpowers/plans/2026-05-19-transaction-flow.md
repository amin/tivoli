# Updated Transaction Flow Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reshape the Tivoli centralbank `/transactions` flow so a single endpoint handles both entry (identity-token-driven) and in-game (continuation-token-driven) payments, distributes income to owners in real time, gates new stamps behind a 3-minute anti-farming window on identity-token use, applies a VP penalty when any of a user's group's amusements is net-negative, and adds an admin-only `/settle` endpoint that deducts deficits from group members.

**Architecture:**

- **One endpoint, two token modes.** `POST /transactions` accepts exactly one of `identity_token` or `continuation_token`. Identity-token calls record `type = fee` (stamp eligible, rate-limited), continuation-token calls record `type = in_game_purchase` (never stamped). Identity-token consumption rotates the continuation token; continuation-token use extends sliding TTL.
- **Continuous owner distribution.** Every income transaction credits each owner's personal balance with `round(amount/N, 2)` while also incrementing `amusement_balance` as a net tracker. Payouts decrement the tracker only.
- **VP penalty as a live read.** `User.hasNegativeGroupAmusement()` flips `total_vp` to 0 in `/leaderboard` and `/stamps`. The tracker never gets zeroed — even after `/settle`, the negative value remains and continues to trigger the penalty.
- **`/settle` is admin-only and idempotent.** It distributes each negative tracker's debt across the group's members and marks `amusements.settled_at`. Cash flow only — VP semantics are unchanged by settling.

**Tech Stack:** Laravel 11, PHPUnit, SQLite (test DB), Postgres (staging). Spec lives at `docs/superpowers/specs/2026-05-19-transaction-flow-design.md`.

---

## File Structure

**Migrations (5 new under `apps/api/database/migrations/`):**

| File | Purpose |
| --- | --- |
| `2026_05_19_000001_extend_transactions_type_enum.php` | Allow `in_game_purchase` in `transactions.type`. SQLite stores enum as string so the migration only matters for Postgres; use raw `DB::statement` guarded by driver. |
| `2026_05_19_000002_add_stamp_id_and_index_to_transactions.php` | Add nullable `stamp_id` FK + composite index on `(user_id, amusement_id, created_at)`. |
| `2026_05_19_000003_add_transaction_id_to_stamps.php` | Symmetric nullable FK on `stamps`. |
| `2026_05_19_000004_create_continuation_tokens_table.php` | New table for session tokens. |
| `2026_05_19_000005_add_settled_at_to_amusements.php` | Idempotency marker for `/settle`. |

**Models:**

| File | Change |
| --- | --- |
| `apps/api/app/Models/ContinuationToken.php` | New. Parallel to `IdentityToken`. |
| `apps/api/app/Models/Transaction.php` | Add `stamp_id` to fillable, `stamp()` relation. |
| `apps/api/app/Models/Stamp.php` | Add `transaction_id` to fillable, `transaction()` relation. |
| `apps/api/app/Models/Amusement.php` | Add `settled_at` to casts (`datetime`). |
| `apps/api/app/Models/User.php` | Add `hasNegativeGroupAmusement(): bool`. |

**Requests:**

| File | Change |
| --- | --- |
| `apps/api/app/Http/Requests/StoreTransactionRequest.php` | Rewrite rules: exactly one of `identity_token`/`continuation_token`, plus `amount`, `api_key`. |

**Controllers:**

| File | Change |
| --- | --- |
| `apps/api/app/Http/Controllers/TransactionController.php` | Rewrite `store()`; small change to `payout()` (reject in-game anchors). |
| `apps/api/app/Http/Controllers/SettleController.php` | New. Admin-only, idempotent debt distribution. |
| `apps/api/app/Http/Controllers/LeaderboardController.php` | Apply VP penalty when building `vp_leaders`. |
| `apps/api/app/Http/Controllers/StampController.php` | Apply VP penalty to `total_vp`. |

**Routes:**

| File | Change |
| --- | --- |
| `apps/api/routes/api.php` | Add `POST /settle` under `auth:sanctum` middleware. |

**Tests:**

| File | Change |
| --- | --- |
| `apps/api/tests/Feature/TransactionTest.php` | Update existing tests for new response shape and rewrite stats test to avoid the 3-min rate-limit hitting the same player twice. |
| `apps/api/tests/Feature/InGameTransactionTest.php` | New. Continuation-token-driven paths. |
| `apps/api/tests/Feature/StampRateLimitTest.php` | New. 3-min rate-limit on identity-token-driven stamps. |
| `apps/api/tests/Feature/OwnerDistributionTest.php` | New. Income credits owners; rounding behaviour. |
| `apps/api/tests/Feature/SettleTest.php` | New. Admin gate, debt split, idempotency, tracker preservation. |
| `apps/api/tests/Feature/VpPenaltyTest.php` | New. `/leaderboard` and `/stamps total_vp` go to 0 when group has a negative amusement. |

**Documentation:**

| File | Change |
| --- | --- |
| `docs/centralbank-api.yaml` | Update `POST /transactions` body and response, `TransactionListItem.type` enum, user-flow narrative, payout 400 case, reintroduce `/settle`, document VP penalty. |

---

## Conventions used in every task

- All tests use `RefreshDatabase`, the existing `Tests\TestCase` base class, and the same `makeGroup` / `makeUser` / `makeAmusement` / `issueToken` helpers as `tests/Feature/TransactionTest.php`. Copy them into new test files to keep tests self-contained.
- Run all tests for the API with: `cd apps/api && php artisan test`.
- Run a single test file with: `cd apps/api && php artisan test tests/Feature/<File>.php`.
- Run a single method with: `cd apps/api && php artisan test --filter <method>`.
- Commits use the existing co-authored trailer style. Example commit body:

  ```
  feat(api): add continuation_tokens table

  Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
  ```

  Use `git -c commit.gpgsign=false commit -m "$(cat <<'EOF' ... EOF )"`.

---

## Task 1: Migration — extend transactions.type enum

**Files:**
- Create: `apps/api/database/migrations/2026_05_19_000001_extend_transactions_type_enum.php`

- [ ] **Step 1: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT transactions_type_check');
            DB::statement(
                "ALTER TABLE transactions ADD CONSTRAINT transactions_type_check "
                . "CHECK (type IN ('fee', 'in_game_purchase', 'payout', 'owner_revenue', 'exchange'))"
            );
        }
        // SQLite stores enum as a plain VARCHAR with no constraint, no migration needed.
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT transactions_type_check');
            DB::statement(
                "ALTER TABLE transactions ADD CONSTRAINT transactions_type_check "
                . "CHECK (type IN ('fee', 'payout', 'owner_revenue', 'exchange'))"
            );
        }
    }
};
```

- [ ] **Step 2: Run migrations**

Run: `cd apps/api && php artisan migrate`
Expected: migration runs without error.

- [ ] **Step 3: Commit**

```bash
git add apps/api/database/migrations/2026_05_19_000001_extend_transactions_type_enum.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
feat(api): allow in_game_purchase in transactions.type

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 2: Migration — stamp_id + index on transactions

**Files:**
- Create: `apps/api/database/migrations/2026_05_19_000002_add_stamp_id_and_index_to_transactions.php`

- [ ] **Step 1: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('stamp_id')->nullable()->after('amusement_id');
            $table->foreign('stamp_id')->references('id')->on('stamps')->nullOnDelete();
            $table->index(['user_id', 'amusement_id', 'created_at'], 'transactions_user_amusement_created_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_amusement_created_idx');
            $table->dropForeign(['stamp_id']);
            $table->dropColumn('stamp_id');
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `cd apps/api && php artisan migrate`
Expected: migration succeeds.

- [ ] **Step 3: Commit**

```bash
git add apps/api/database/migrations/2026_05_19_000002_add_stamp_id_and_index_to_transactions.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
feat(api): link transactions to stamps and index rate-limit lookup

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 3: Migration — transaction_id on stamps

**Files:**
- Create: `apps/api/database/migrations/2026_05_19_000003_add_transaction_id_to_stamps.php`

- [ ] **Step 1: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stamps', function (Blueprint $table) {
            $table->unsignedBigInteger('transaction_id')->nullable()->after('stamptype_id');
            $table->foreign('transaction_id')->references('id')->on('transactions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('stamps', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropColumn('transaction_id');
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `cd apps/api && php artisan migrate`
Expected: migration succeeds.

- [ ] **Step 3: Commit**

```bash
git add apps/api/database/migrations/2026_05_19_000003_add_transaction_id_to_stamps.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
feat(api): symmetric transaction_id FK on stamps

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 4: Migration — continuation_tokens table

**Files:**
- Create: `apps/api/database/migrations/2026_05_19_000004_create_continuation_tokens_table.php`

- [ ] **Step 1: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('continuation_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users');
            $table->unsignedBigInteger('amusement_id');
            $table->foreign('amusement_id')->references('id')->on('amusements');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->unique(['user_id', 'amusement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuation_tokens');
    }
};
```

- [ ] **Step 2: Run migration**

Run: `cd apps/api && php artisan migrate`
Expected: migration succeeds.

- [ ] **Step 3: Commit**

```bash
git add apps/api/database/migrations/2026_05_19_000004_create_continuation_tokens_table.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
feat(api): create continuation_tokens table

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 5: Migration — settled_at on amusements

**Files:**
- Create: `apps/api/database/migrations/2026_05_19_000005_add_settled_at_to_amusements.php`

- [ ] **Step 1: Write migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('amusements', function (Blueprint $table) {
            $table->timestamp('settled_at')->nullable()->after('amusement_balance');
        });
    }

    public function down(): void
    {
        Schema::table('amusements', function (Blueprint $table) {
            $table->dropColumn('settled_at');
        });
    }
};
```

- [ ] **Step 2: Run migration**

Run: `cd apps/api && php artisan migrate`
Expected: migration succeeds.

- [ ] **Step 3: Commit**

```bash
git add apps/api/database/migrations/2026_05_19_000005_add_settled_at_to_amusements.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
feat(api): add settled_at idempotency marker on amusements

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 6: ContinuationToken model

**Files:**
- Create: `apps/api/tests/Unit/ContinuationTokenTest.php`
- Create: `apps/api/app/Models/ContinuationToken.php`

- [ ] **Step 1: Write failing unit test**

```php
<?php

namespace Tests\Unit;

use App\Models\Amusement;
use App\Models\ContinuationToken;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContinuationTokenTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(int $groupId): User
    {
        return User::forceCreate([
            'name' => 'Player',
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => 0,
            'is_active' => true,
        ]);
    }

    private function makeAmusement(int $groupId): Amusement
    {
        return Amusement::forceCreate([
            'group_id' => $groupId,
            'name' => 'Wheel-' . Str::random(6),
            'url' => 'https://example.com',
            'api_key' => (string) Str::uuid(),
            'type' => 'game',
        ]);
    }

    public function test_issue_for_creates_token_bound_to_pair(): void
    {
        $group = Group::forceCreate(['name' => 'G-' . Str::random(8)]);
        $user = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);

        $token = ContinuationToken::issueFor($user, $amusement);

        $this->assertNotEmpty($token->token);
        $this->assertSame($user->id, $token->user_id);
        $this->assertSame($amusement->id, $token->amusement_id);
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertTrue($token->isValid());
    }

    public function test_is_valid_returns_false_when_expired(): void
    {
        $group = Group::forceCreate(['name' => 'G-' . Str::random(8)]);
        $user = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);

        $token = ContinuationToken::issueFor($user, $amusement);
        $token->update(['expires_at' => now()->subMinute()]);

        $this->assertFalse($token->isValid());
    }

    public function test_issue_for_replaces_existing_token_for_pair(): void
    {
        $group = Group::forceCreate(['name' => 'G-' . Str::random(8)]);
        $user = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);

        $first = ContinuationToken::issueFor($user, $amusement);
        $second = ContinuationToken::issueFor($user, $amusement);

        $this->assertNotEquals($first->token, $second->token);
        $this->assertSame(1, ContinuationToken::where('user_id', $user->id)
            ->where('amusement_id', $amusement->id)->count());
    }

    public function test_extend_pushes_expiry_to_now_plus_default(): void
    {
        $group = Group::forceCreate(['name' => 'G-' . Str::random(8)]);
        $user = $this->makeUser($group->id);
        $amusement = $this->makeAmusement($group->id);
        $token = ContinuationToken::issueFor($user, $amusement);

        $token->update(['expires_at' => now()->addMinutes(5)]);
        $original = $token->expires_at->copy();

        $token->extend();

        $token->refresh();
        $this->assertTrue($token->expires_at->greaterThan($original));
    }
}
```

- [ ] **Step 2: Run tests to verify failure**

Run: `cd apps/api && php artisan test tests/Unit/ContinuationTokenTest.php`
Expected: FAIL — class `App\Models\ContinuationToken` not found.

- [ ] **Step 3: Implement the model**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContinuationToken extends Model
{
    public const DEFAULT_TTL_MINUTES = 30;

    protected $fillable = ['token', 'user_id', 'amusement_id', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amusement(): BelongsTo
    {
        return $this->belongsTo(Amusement::class);
    }

    public function isValid(): bool
    {
        return $this->expires_at->isFuture();
    }

    public function extend(int $ttlMinutes = self::DEFAULT_TTL_MINUTES): void
    {
        $this->update(['expires_at' => now()->addMinutes($ttlMinutes)]);
    }

    public static function issueFor(User $user, Amusement $amusement, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): self
    {
        return DB::transaction(function () use ($user, $amusement, $ttlMinutes) {
            self::where('user_id', $user->id)
                ->where('amusement_id', $amusement->id)
                ->delete();

            return self::create([
                'token' => (string) Str::uuid(),
                'user_id' => $user->id,
                'amusement_id' => $amusement->id,
                'expires_at' => now()->addMinutes($ttlMinutes),
            ]);
        });
    }
}
```

- [ ] **Step 4: Run tests to verify pass**

Run: `cd apps/api && php artisan test tests/Unit/ContinuationTokenTest.php`
Expected: 4 passing.

- [ ] **Step 5: Commit**

```bash
git add apps/api/app/Models/ContinuationToken.php apps/api/tests/Unit/ContinuationTokenTest.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
feat(api): add ContinuationToken model with issueFor and extend

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 7: Transaction + Stamp model updates

**Files:**
- Modify: `apps/api/app/Models/Transaction.php`
- Modify: `apps/api/app/Models/Stamp.php`

No new tests in this task — the relations are exercised by feature tests in later tasks. Existing tests must still pass.

- [ ] **Step 1: Update `Transaction.php`**

Replace the `$fillable` array to include `stamp_id`, and add a `stamp()` relation. Final content:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'amusement_id',
        'stamp_id',
        'amount',
        'type',
        'settled_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'settled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function amusement(): BelongsTo
    {
        return $this->belongsTo(Amusement::class);
    }

    public function stamp(): BelongsTo
    {
        return $this->belongsTo(Stamp::class);
    }
}
```

- [ ] **Step 2: Update `Stamp.php`**

Add `transaction_id` to fillable and add a `transaction()` relation. The rest of the file stays unchanged. Final `$fillable` line and new method to add:

```php
protected $fillable = ['user_id', 'stamptype_id', 'transaction_id', 'exchanged_at'];
```

```php
public function transaction(): BelongsTo
{
    return $this->belongsTo(Transaction::class);
}
```

- [ ] **Step 3: Run full test suite**

Run: `cd apps/api && php artisan test`
Expected: all existing tests pass (no regressions).

- [ ] **Step 4: Commit**

```bash
git add apps/api/app/Models/Transaction.php apps/api/app/Models/Stamp.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
feat(api): link transactions and stamps via stamp_id / transaction_id

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 8: Amusement model — settled_at cast

**Files:**
- Modify: `apps/api/app/Models/Amusement.php`

- [ ] **Step 1: Add `settled_at` to the `$casts` array**

In `apps/api/app/Models/Amusement.php`, change:

```php
protected $casts = [
    'price' => 'float',
    'player_payout' => 'float',
    'amusement_balance' => 'float',
    'buffer_required' => 'float',
    'buffer_locked' => 'float',
];
```

To:

```php
protected $casts = [
    'price' => 'float',
    'player_payout' => 'float',
    'amusement_balance' => 'float',
    'buffer_required' => 'float',
    'buffer_locked' => 'float',
    'settled_at' => 'datetime',
];
```

- [ ] **Step 2: Run the full suite**

Run: `cd apps/api && php artisan test`
Expected: pass.

- [ ] **Step 3: Commit**

```bash
git add apps/api/app/Models/Amusement.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
feat(api): cast Amusement.settled_at to datetime

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 9: User.hasNegativeGroupAmusement()

**Files:**
- Create: `apps/api/tests/Unit/UserVpPenaltyTest.php`
- Modify: `apps/api/app/Models/User.php`

- [ ] **Step 1: Write failing test**

```php
<?php

namespace Tests\Unit;

use App\Models\Amusement;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserVpPenaltyTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(?int $groupId, float $balance = 0): User
    {
        return User::forceCreate([
            'name' => 'Player-' . Str::random(6),
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function makeAmusement(int $groupId, float $balance = 0): Amusement
    {
        return Amusement::forceCreate([
            'group_id' => $groupId,
            'name' => 'A-' . Str::random(6),
            'url' => 'https://example.com',
            'api_key' => (string) Str::uuid(),
            'type' => 'game',
            'amusement_balance' => $balance,
        ]);
    }

    public function test_returns_false_when_user_has_no_group(): void
    {
        $user = $this->makeUser(null);
        $this->assertFalse($user->hasNegativeGroupAmusement());
    }

    public function test_returns_false_when_group_has_no_amusements(): void
    {
        $group = Group::forceCreate(['name' => 'G-' . Str::random(8)]);
        $user = $this->makeUser($group->id);
        $this->assertFalse($user->hasNegativeGroupAmusement());
    }

    public function test_returns_false_when_all_amusements_non_negative(): void
    {
        $group = Group::forceCreate(['name' => 'G-' . Str::random(8)]);
        $user = $this->makeUser($group->id);
        $this->makeAmusement($group->id, 10.0);
        $this->makeAmusement($group->id, 0.0);
        $this->assertFalse($user->hasNegativeGroupAmusement());
    }

    public function test_returns_true_when_any_amusement_negative(): void
    {
        $group = Group::forceCreate(['name' => 'G-' . Str::random(8)]);
        $user = $this->makeUser($group->id);
        $this->makeAmusement($group->id, 10.0);
        $this->makeAmusement($group->id, -0.01);
        $this->assertTrue($user->hasNegativeGroupAmusement());
    }
}
```

- [ ] **Step 2: Run tests to verify failure**

Run: `cd apps/api && php artisan test tests/Unit/UserVpPenaltyTest.php`
Expected: FAIL — `hasNegativeGroupAmusement` method missing.

- [ ] **Step 3: Implement the method**

Add to `apps/api/app/Models/User.php`, after the `vote()` method:

```php
public function hasNegativeGroupAmusement(): bool
{
    return $this->group
        ?->amusements()
        ->where('amusement_balance', '<', 0)
        ->exists() ?? false;
}
```

- [ ] **Step 4: Run tests to verify pass**

Run: `cd apps/api && php artisan test tests/Unit/UserVpPenaltyTest.php`
Expected: 4 passing.

- [ ] **Step 5: Commit**

```bash
git add apps/api/app/Models/User.php apps/api/tests/Unit/UserVpPenaltyTest.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
feat(api): add User.hasNegativeGroupAmusement for VP penalty

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 10: Rewrite StoreTransactionRequest validation

**Files:**
- Modify: `apps/api/app/Http/Requests/StoreTransactionRequest.php`
- Test: validation behaviour will be exercised via the controller feature tests in Task 11 — no separate request-only test.

- [ ] **Step 1: Replace `rules()` and add a custom `withValidator` callback**

Final content of `apps/api/app/Http/Requests/StoreTransactionRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'identity_token' => ['nullable', 'string', 'required_without:continuation_token'],
            'continuation_token' => ['nullable', 'string', 'required_without:identity_token'],
            'amount' => ['required', 'numeric', 'min:0'],
            'api_key' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $hasIdentity = filled($this->input('identity_token'));
            $hasContinuation = filled($this->input('continuation_token'));
            if ($hasIdentity && $hasContinuation) {
                $v->errors()->add(
                    'identity_token',
                    'Provide either identity_token or continuation_token, not both.'
                );
            }
        });
    }
}
```

- [ ] **Step 2: Run the existing suite**

Run: `cd apps/api && php artisan test`
Expected: existing tests still pass — `StoreTransactionRequest` is only consumed by `TransactionController@store` (rewritten in Task 11). The current happy-path test still supplies `identity_token` + `amount` + `api_key`, which satisfies the new rules.

- [ ] **Step 3: Commit**

```bash
git add apps/api/app/Http/Requests/StoreTransactionRequest.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
feat(api): require exactly one token field on StoreTransactionRequest

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 11: Rewrite TransactionController@store

This task is the heart of the change. We TDD it in several sub-steps: extend existing test helpers to expect the new response shape, add identity-token tests for stamp + rate limit, add continuation-token tests, add owner-distribution tests, then write the controller body.

**Files:**
- Modify: `apps/api/tests/Feature/TransactionTest.php` (update existing tests for new response shape + rate limit interactions)
- Create: `apps/api/tests/Feature/InGameTransactionTest.php`
- Create: `apps/api/tests/Feature/StampRateLimitTest.php`
- Create: `apps/api/tests/Feature/OwnerDistributionTest.php`
- Modify: `apps/api/app/Http/Controllers/TransactionController.php` (rewrite `store()`)

### 11a — Update existing TransactionTest expectations

- [ ] **Step 1: Update `test_happy_path_creates_transaction_and_consumes_token`**

In `apps/api/tests/Feature/TransactionTest.php`, change the `assertJsonStructure` to include the new continuation fields:

```php
$response->assertJsonStructure(['id', 'stamp', 'continuation_token', 'continuation_expires_at']);
```

- [ ] **Step 2: Update `test_stats_returns_correct_totals` to avoid hitting the rate-limit**

The current test posts two entries from the same player to the same amusement; under the new model the second would become `in_game_purchase`, breaking the `fees_count = 2` assertion. Use two different players instead:

```php
public function test_stats_returns_correct_totals(): void
{
    $group = $this->makeGroup();
    $member = $this->makeUser($group->id, 100.0, 'Member');
    $playerA = $this->makeUser($group->id, 100.0, 'Player-A');
    $playerB = $this->makeUser($group->id, 100.0, 'Player-B');
    $amusement = $this->makeAmusement($group->id);

    foreach ([$playerA, $playerB] as $player) {
        $token = $this->issueToken($player);
        $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);
    }

    $firstFeeId = $amusement->transactions()->where('type', 'fee')->orderBy('id')->first()->id;
    $this->postJson("/transactions/{$firstFeeId}/payout", [
        'amount' => 3.00,
        'api_key' => $amusement->api_key,
    ])->assertStatus(201);

    $response = $this->actingAs($member)
        ->getJson("/amusements/{$amusement->id}/stats");

    $response->assertStatus(200);
    $response->assertJson([
        'fees_total' => 10.0,
        'fees_count' => 2,
        'payouts_total' => 3.0,
        'payouts_count' => 1,
        'net' => 7.0,
        'amusement_balance' => 7.0,
    ]);
}
```

(Note: `fees_total = 10.0` here is per-amusement gross income. Owner distribution does not affect amusement_balance — the tracker keeps the full amount.)

- [ ] **Step 3: Update `test_happy_path` to expect owner distribution does not break member balance assertions**

The existing happy path makes the player a member of the same group as the amusement, then asserts `player->balance == 95.00`. After owner-distribution the player (who is the only group member) will also receive `5.00 / 1 = 5.00` back into their balance, making the post-balance `100.00`, not `95.00`.

To keep the assertion meaningful, put the player in a different group than the amusement's group. Replace the test body with:

```php
public function test_happy_path_creates_transaction_and_consumes_token(): void
{
    $ownerGroup = $this->makeGroup('Owners');
    $playerGroup = $this->makeGroup('Players');
    $player = $this->makeUser($playerGroup->id);
    $amusement = $this->makeAmusement($ownerGroup->id);
    $token = $this->issueToken($player);

    $response = $this->postJson('/transactions', [
        'identity_token' => $token->token,
        'amount' => 5.00,
        'api_key' => $amusement->api_key,
    ]);

    $response->assertStatus(201);
    $response->assertJsonStructure(['id', 'stamp', 'continuation_token', 'continuation_expires_at']);

    $token->refresh();
    $this->assertNotNull($token->consumed_at);

    $player->refresh();
    $this->assertEquals(95.00, $player->balance);

    $amusement->refresh();
    $this->assertEquals(5.00, $amusement->amusement_balance);
}
```

Audit the rest of `TransactionTest.php` and any other test files for the same trap — anywhere the player is a member of the amusement's owning group AND a balance equality is asserted, fix the group split (or assert the owner-credited balance). Specifically:

  - `test_invalid_api_key_returns_401`: balance assertion not present; only checks token state. No change.
  - `test_missing_api_key_returns_422`: same. No change.
  - `test_already_consumed_token_returns_401`: same. No change.
  - `test_expired_token_returns_401`: same. No change.
  - `test_payout_succeeds_into_debt`: asserts player balance = 115 after a 5-paid + 20-payout flow. With owner credit, in same group the player would gain 5.00/1 = 5.00 extra, ending at 120, not 115. **Fix:** move the player into a different group, mirroring the happy-path fix.
  - `test_payout_with_wrong_api_key_returns_403`: no balance assertion. No change.
  - `test_attraction_cannot_payout`: no balance assertion. No change.
  - `test_double_payout_is_rejected`: no balance assertion. No change.
  - `test_amusement_balance_serializes_as_number`: no balance assertion. No change.
  - `test_group_member_can_list_amusement_transactions`: no balance assertion. No change.
  - `test_non_group_member_cannot_list_amusement_transactions`: no balance assertion. No change.
  - `test_stats_returns_correct_totals`: rewrite handled in Step 2 above.

- [ ] **Step 4: Run TransactionTest, expect failures**

Run: `cd apps/api && php artisan test tests/Feature/TransactionTest.php`
Expected: most tests now FAIL because the response lacks `continuation_token`/`continuation_expires_at` and the controller doesn't yet do owner-distribution. This is fine — we implement them in 11e.

### 11b — Add InGameTransactionTest

- [ ] **Step 5: Create `apps/api/tests/Feature/InGameTransactionTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Amusement;
use App\Models\ContinuationToken;
use App\Models\Group;
use App\Models\IdentityToken;
use App\Models\Stamptype;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InGameTransactionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $animals = ['lion', 'dolphin', 'toucan', 'beetlebug', 'snake'];
        foreach ($animals as $a) {
            Stamptype::forceCreate(['animal' => $a, 'metal' => null]);
            foreach (['silver', 'gold', 'platinum'] as $m) {
                Stamptype::forceCreate(['animal' => $a, 'metal' => $m]);
            }
        }
    }

    private function makeGroup(string $name = 'G'): Group
    {
        return Group::forceCreate(['name' => $name . '-' . Str::random(6)]);
    }

    private function makeUser(?int $groupId, float $balance = 100.0): User
    {
        return User::forceCreate([
            'name' => 'P-' . Str::random(6),
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function makeAmusement(int $groupId): Amusement
    {
        return Amusement::forceCreate([
            'group_id' => $groupId,
            'name' => 'W-' . Str::random(6),
            'url' => 'https://example.com',
            'api_key' => (string) Str::uuid(),
            'type' => 'game',
        ]);
    }

    private function entry(User $player, Amusement $amusement, float $amount = 1.00): array
    {
        $token = IdentityToken::issueFor($player);
        $res = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => $amount,
            'api_key' => $amusement->api_key,
        ]);
        $res->assertStatus(201);
        return $res->json();
    }

    public function test_continuation_token_records_in_game_purchase_with_no_stamp(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);

        $entry = $this->entry($player, $amusement, 1.00);

        $res = $this->postJson('/transactions', [
            'continuation_token' => $entry['continuation_token'],
            'amount' => 0.50,
            'api_key' => $amusement->api_key,
        ]);

        $res->assertStatus(201);
        $this->assertNull($res->json('stamp'));
        $this->assertSame($entry['continuation_token'], $res->json('continuation_token'));
        $this->assertDatabaseHas('transactions', [
            'id' => $res->json('id'),
            'type' => 'in_game_purchase',
            'stamp_id' => null,
        ]);
    }

    public function test_continuation_token_extends_sliding_expiry(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);

        $entry = $this->entry($player, $amusement);
        $original = ContinuationToken::where('token', $entry['continuation_token'])->firstOrFail();
        $original->update(['expires_at' => now()->addMinutes(5)]);
        $originalExpiry = $original->expires_at->copy();

        $res = $this->postJson('/transactions', [
            'continuation_token' => $entry['continuation_token'],
            'amount' => 0.10,
            'api_key' => $amusement->api_key,
        ]);
        $res->assertStatus(201);

        $original->refresh();
        $this->assertTrue($original->expires_at->greaterThan($originalExpiry));
    }

    public function test_continuation_token_from_wrong_amusement_returns_403(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);
        $other = $this->makeAmusement($owners->id);

        $entry = $this->entry($player, $amusement);

        $res = $this->postJson('/transactions', [
            'continuation_token' => $entry['continuation_token'],
            'amount' => 0.10,
            'api_key' => $other->api_key,
        ]);

        $res->assertStatus(403);
    }

    public function test_expired_continuation_token_returns_401(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);

        $entry = $this->entry($player, $amusement);
        ContinuationToken::where('token', $entry['continuation_token'])
            ->update(['expires_at' => now()->subMinute()]);

        $res = $this->postJson('/transactions', [
            'continuation_token' => $entry['continuation_token'],
            'amount' => 0.10,
            'api_key' => $amusement->api_key,
        ]);

        $res->assertStatus(401);
    }

    public function test_unknown_continuation_token_returns_401(): void
    {
        $owners = $this->makeGroup('owners');
        $amusement = $this->makeAmusement($owners->id);

        $res = $this->postJson('/transactions', [
            'continuation_token' => (string) Str::uuid(),
            'amount' => 0.10,
            'api_key' => $amusement->api_key,
        ]);

        $res->assertStatus(401);
    }

    public function test_both_tokens_present_returns_422(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);

        $entry = $this->entry($player, $amusement);
        $newToken = IdentityToken::issueFor($player);

        $res = $this->postJson('/transactions', [
            'identity_token' => $newToken->token,
            'continuation_token' => $entry['continuation_token'],
            'amount' => 0.10,
            'api_key' => $amusement->api_key,
        ]);

        $res->assertStatus(422);
    }

    public function test_neither_token_present_returns_422(): void
    {
        $owners = $this->makeGroup('owners');
        $amusement = $this->makeAmusement($owners->id);

        $res = $this->postJson('/transactions', [
            'amount' => 0.10,
            'api_key' => $amusement->api_key,
        ]);

        $res->assertStatus(422);
    }

    public function test_new_identity_token_replaces_prior_continuation_token(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);

        $first = $this->entry($player, $amusement);
        $second = $this->entry($player, $amusement);

        $this->assertNotSame($first['continuation_token'], $second['continuation_token']);

        // Old token no longer works.
        $res = $this->postJson('/transactions', [
            'continuation_token' => $first['continuation_token'],
            'amount' => 0.10,
            'api_key' => $amusement->api_key,
        ]);
        $res->assertStatus(401);
    }
}
```

- [ ] **Step 6: Run InGameTransactionTest, expect failures**

Run: `cd apps/api && php artisan test tests/Feature/InGameTransactionTest.php`
Expected: FAIL — controller does not implement continuation tokens yet.

### 11c — Add StampRateLimitTest

- [ ] **Step 7: Create `apps/api/tests/Feature/StampRateLimitTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Amusement;
use App\Models\Group;
use App\Models\IdentityToken;
use App\Models\Stamptype;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class StampRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['lion', 'dolphin', 'toucan', 'beetlebug', 'snake'] as $a) {
            Stamptype::forceCreate(['animal' => $a, 'metal' => null]);
            foreach (['silver', 'gold', 'platinum'] as $m) {
                Stamptype::forceCreate(['animal' => $a, 'metal' => $m]);
            }
        }
    }

    private function makeGroup(): Group
    {
        return Group::forceCreate(['name' => 'G-' . Str::random(6)]);
    }

    private function makeUser(?int $groupId): User
    {
        return User::forceCreate([
            'name' => 'P-' . Str::random(6),
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => 100,
            'is_active' => true,
        ]);
    }

    private function makeAmusement(int $groupId): Amusement
    {
        return Amusement::forceCreate([
            'group_id' => $groupId,
            'name' => 'A-' . Str::random(6),
            'url' => 'https://example.com',
            'api_key' => (string) Str::uuid(),
            'type' => 'game',
        ]);
    }

    private function entry(User $u, Amusement $a, float $amount = 1.00): array
    {
        $token = IdentityToken::issueFor($u);
        $res = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => $amount,
            'api_key' => $a->api_key,
        ]);
        $res->assertStatus(201);
        return $res->json();
    }

    public function test_second_entry_within_3_min_is_fee_with_no_stamp(): void
    {
        $owners = $this->makeGroup();
        $players = $this->makeGroup();
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);

        $first = $this->entry($player, $amusement);
        $this->assertNotNull($first['stamp']);
        $firstStampedTxId = $first['id'];

        $second = $this->entry($player, $amusement);
        $this->assertNull($second['stamp']);

        // Type stays `fee` because identity_token was used (not in_game_purchase).
        $this->assertDatabaseHas('transactions', [
            'id' => $second['id'],
            'type' => 'fee',
            'stamp_id' => null,
        ]);

        // First transaction still has its stamp.
        $this->assertDatabaseMissing('transactions', [
            'id' => $firstStampedTxId,
            'stamp_id' => null,
        ]);
    }

    public function test_entry_more_than_3_min_after_last_stamp_awards_stamp_again(): void
    {
        $owners = $this->makeGroup();
        $players = $this->makeGroup();
        $player = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);

        $first = $this->entry($player, $amusement);
        $this->assertNotNull($first['stamp']);

        // Backdate the prior stamped tx to outside the 3-minute window.
        Transaction::where('id', $first['id'])->update([
            'created_at' => now()->subMinutes(4),
        ]);

        $second = $this->entry($player, $amusement);
        $this->assertNotNull($second['stamp']);
        $this->assertNotSame($first['stamp']['id'], $second['stamp']['id']);
    }

    public function test_rate_limit_is_per_user_amusement_pair(): void
    {
        $owners = $this->makeGroup();
        $players = $this->makeGroup();
        $player = $this->makeUser($players->id);
        $other = $this->makeUser($players->id);
        $amusement = $this->makeAmusement($owners->id);

        $playersFirst = $this->entry($player, $amusement);
        $this->assertNotNull($playersFirst['stamp']);

        $otherFirst = $this->entry($other, $amusement);
        $this->assertNotNull($otherFirst['stamp']);
    }

    public function test_rate_limit_is_per_amusement(): void
    {
        $owners = $this->makeGroup();
        $players = $this->makeGroup();
        $player = $this->makeUser($players->id);
        $a = $this->makeAmusement($owners->id);
        $b = $this->makeAmusement($owners->id);

        $atA = $this->entry($player, $a);
        $atB = $this->entry($player, $b);

        $this->assertNotNull($atA['stamp']);
        $this->assertNotNull($atB['stamp']);
    }
}
```

- [ ] **Step 8: Run StampRateLimitTest, expect failures**

Run: `cd apps/api && php artisan test tests/Feature/StampRateLimitTest.php`
Expected: FAIL — controller still always issues a stamp.

### 11d — Add OwnerDistributionTest

- [ ] **Step 9: Create `apps/api/tests/Feature/OwnerDistributionTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Amusement;
use App\Models\Group;
use App\Models\IdentityToken;
use App\Models\Stamptype;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class OwnerDistributionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['lion', 'dolphin', 'toucan', 'beetlebug', 'snake'] as $a) {
            Stamptype::forceCreate(['animal' => $a, 'metal' => null]);
            foreach (['silver', 'gold', 'platinum'] as $m) {
                Stamptype::forceCreate(['animal' => $a, 'metal' => $m]);
            }
        }
    }

    private function makeGroup(string $prefix = 'G'): Group
    {
        return Group::forceCreate(['name' => $prefix . '-' . Str::random(6)]);
    }

    private function makeUser(?int $groupId, float $balance = 0.0, string $name = 'P'): User
    {
        return User::forceCreate([
            'name' => $name . '-' . Str::random(6),
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function makeAmusement(int $groupId): Amusement
    {
        return Amusement::forceCreate([
            'group_id' => $groupId,
            'name' => 'A-' . Str::random(6),
            'url' => 'https://example.com',
            'api_key' => (string) Str::uuid(),
            'type' => 'game',
        ]);
    }

    public function test_each_owner_gets_equal_share_on_entry(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $o1 = $this->makeUser($owners->id, 10.00, 'O1');
        $o2 = $this->makeUser($owners->id, 10.00, 'O2');
        $player = $this->makeUser($players->id, 100.00);
        $amusement = $this->makeAmusement($owners->id);

        $token = IdentityToken::issueFor($player);
        $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 4.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $o1->refresh();
        $o2->refresh();
        $this->assertEquals(12.00, $o1->balance);
        $this->assertEquals(12.00, $o2->balance);
    }

    public function test_each_owner_gets_share_on_in_game_purchase(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $o1 = $this->makeUser($owners->id, 0, 'O1');
        $player = $this->makeUser($players->id, 100.00);
        $amusement = $this->makeAmusement($owners->id);

        $token = IdentityToken::issueFor($player);
        $entry = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 1.00,
            'api_key' => $amusement->api_key,
        ])->json();

        $this->postJson('/transactions', [
            'continuation_token' => $entry['continuation_token'],
            'amount' => 0.50,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $o1->refresh();
        $this->assertEquals(1.50, $o1->balance);
    }

    public function test_rounding_drops_fractional_cent(): void
    {
        // 5.00 / 3 = 1.6666... → round(_, 2) = 1.67 per member;
        // total credited = 5.01 (one extra cent), but assertion checks per-member.
        // Per spec, "örar slängs" is tolerated.
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $o1 = $this->makeUser($owners->id, 0, 'O1');
        $o2 = $this->makeUser($owners->id, 0, 'O2');
        $o3 = $this->makeUser($owners->id, 0, 'O3');
        $player = $this->makeUser($players->id, 100.00);
        $amusement = $this->makeAmusement($owners->id);

        $token = IdentityToken::issueFor($player);
        $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 5.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $o1->refresh(); $o2->refresh(); $o3->refresh();
        $this->assertEquals(1.67, $o1->balance);
        $this->assertEquals(1.67, $o2->balance);
        $this->assertEquals(1.67, $o3->balance);
    }

    public function test_payout_does_not_touch_owner_balances(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $o1 = $this->makeUser($owners->id, 0, 'O1');
        $player = $this->makeUser($players->id, 100.00);
        $amusement = $this->makeAmusement($owners->id);

        $token = IdentityToken::issueFor($player);
        $entry = $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 2.00,
            'api_key' => $amusement->api_key,
        ])->json();

        $o1->refresh();
        $balanceAfterEntry = $o1->balance;
        $this->assertEquals(2.00, $balanceAfterEntry);

        $this->postJson("/transactions/{$entry['id']}/payout", [
            'amount' => 4.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $o1->refresh();
        $this->assertEquals($balanceAfterEntry, $o1->balance);
    }

    public function test_amusement_balance_tracks_full_amount_despite_distribution(): void
    {
        $owners = $this->makeGroup('owners');
        $players = $this->makeGroup('players');
        $this->makeUser($owners->id, 0);
        $this->makeUser($owners->id, 0);
        $player = $this->makeUser($players->id, 100.00);
        $amusement = $this->makeAmusement($owners->id);

        $token = IdentityToken::issueFor($player);
        $this->postJson('/transactions', [
            'identity_token' => $token->token,
            'amount' => 3.00,
            'api_key' => $amusement->api_key,
        ])->assertStatus(201);

        $amusement->refresh();
        $this->assertEquals(3.00, $amusement->amusement_balance);
    }
}
```

- [ ] **Step 10: Run OwnerDistributionTest, expect failures**

Run: `cd apps/api && php artisan test tests/Feature/OwnerDistributionTest.php`
Expected: FAIL — controller does not distribute yet.

### 11e — Implement the controller

- [ ] **Step 11: Replace `TransactionController.php`**

Full new contents of `apps/api/app/Http/Controllers/TransactionController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayoutTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Amusement;
use App\Models\ContinuationToken;
use App\Models\IdentityToken;
use App\Models\Stamp;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    private const RATE_LIMIT_MINUTES = 3;

    public function store(StoreTransactionRequest $request): JsonResponse
    {
        $data = $request->validated();

        $amusement = Amusement::where('api_key', $data['api_key'])->first();
        if (!$amusement) {
            return response()->json(['message' => 'Invalid api_key'], 401);
        }

        $usingIdentity = filled($data['identity_token'] ?? null);

        if ($usingIdentity) {
            $identityToken = IdentityToken::where('token', $data['identity_token'])->first();
            if (!$identityToken || !$identityToken->isValid()) {
                return response()->json(['message' => 'Invalid or expired identity token'], 401);
            }
            $user = $identityToken->user;
        } else {
            $continuation = ContinuationToken::where('token', $data['continuation_token'])->first();
            if (!$continuation || !$continuation->isValid()) {
                return response()->json(['message' => 'Invalid or expired continuation token'], 401);
            }
            if ($continuation->amusement_id !== $amusement->id) {
                return response()->json(['message' => 'Continuation token does not belong to this amusement'], 403);
            }
            $user = $continuation->user;
        }

        if ($user->balance < $data['amount']) {
            return response()->json(['message' => 'Insufficient balance'], 402);
        }

        return DB::transaction(function () use (
            $request,
            $user,
            $amusement,
            $data,
            $usingIdentity,
            $identityToken ?? null,
            $continuation ?? null,
        ) {
            $amount = (float) $data['amount'];

            $user->decrement('balance', $amount);
            $amusement->increment('amusement_balance', $amount);

            $this->distributeToOwners($amusement, $amount);

            if ($usingIdentity) {
                $identityToken->update(['consumed_at' => now()]);

                $recentStamped = Transaction::where('user_id', $user->id)
                    ->where('amusement_id', $amusement->id)
                    ->whereNotNull('stamp_id')
                    ->where('created_at', '>', now()->subMinutes(self::RATE_LIMIT_MINUTES))
                    ->exists();

                $stamp = $recentStamped ? null : Stamp::generate($user->id);

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

                $token = ContinuationToken::issueFor($user, $amusement);
            } else {
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'amusement_id' => $amusement->id,
                    'stamp_id' => null,
                    'amount' => $amount,
                    'type' => 'in_game_purchase',
                ]);

                $continuation->extend();
                $token = $continuation;
                $stamp = null;
            }

            return response()->json([
                'id' => $transaction->id,
                'stamp' => $stamp,
                'continuation_token' => $token->token,
                'continuation_expires_at' => $token->expires_at->toIso8601String(),
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

        if ($original->type === 'in_game_purchase') {
            return response()->json(['message' => 'In-game purchases cannot be paid out'], 400);
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
                'id' => $payout->id,
                'original_transaction_id' => $original->id,
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

        $share = round($amount / $n, 2);
        if ($share <= 0) {
            return;
        }

        foreach ($members as $member) {
            $member->increment('balance', $share);
        }
    }
}
```

- [ ] **Step 12: Run all the new feature tests**

Run: `cd apps/api && php artisan test tests/Feature/TransactionTest.php tests/Feature/InGameTransactionTest.php tests/Feature/StampRateLimitTest.php tests/Feature/OwnerDistributionTest.php`
Expected: all passing.

- [ ] **Step 13: Run the full suite**

Run: `cd apps/api && php artisan test`
Expected: every test passing — VP tests don't exist yet and no other suite is affected.

- [ ] **Step 14: Commit**

```bash
git add apps/api/app/Http/Controllers/TransactionController.php \
        apps/api/tests/Feature/TransactionTest.php \
        apps/api/tests/Feature/InGameTransactionTest.php \
        apps/api/tests/Feature/StampRateLimitTest.php \
        apps/api/tests/Feature/OwnerDistributionTest.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
feat(api): unified /transactions with continuation tokens, owner distribution, and stamp rate limit

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 12: SettleController + tests

**Files:**
- Create: `apps/api/tests/Feature/SettleTest.php`
- Create: `apps/api/app/Http/Controllers/SettleController.php`
- Modify: `apps/api/routes/api.php`

### 12a — Write SettleTest

- [ ] **Step 1: Create `apps/api/tests/Feature/SettleTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Amusement;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class SettleTest extends TestCase
{
    use RefreshDatabase;

    private function makeGroup(string $name = 'G', bool $isAdmin = false): Group
    {
        return Group::forceCreate(['name' => $name . '-' . Str::random(6), 'is_admin' => $isAdmin]);
    }

    private function makeUser(?int $groupId, float $balance = 0.0): User
    {
        return User::forceCreate([
            'name' => 'P-' . Str::random(6),
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => $balance,
            'is_active' => true,
        ]);
    }

    private function makeAmusement(int $groupId, float $balance): Amusement
    {
        return Amusement::forceCreate([
            'group_id' => $groupId,
            'name' => 'A-' . Str::random(6),
            'url' => 'https://example.com',
            'api_key' => (string) Str::uuid(),
            'type' => 'game',
            'amusement_balance' => $balance,
        ]);
    }

    public function test_non_admin_caller_returns_403_and_no_side_effects(): void
    {
        $admin = $this->makeGroup('admin', true);
        $owners = $this->makeGroup('owners', false);
        $caller = $this->makeUser($owners->id, 100.00);
        $member = $this->makeUser($owners->id, 100.00);
        $a = $this->makeAmusement($owners->id, -10.00);

        $res = $this->actingAs($caller)->postJson('/settle');

        $res->assertStatus(403);
        $a->refresh();
        $member->refresh();
        $caller->refresh();
        $this->assertEquals(-10.00, $a->amusement_balance);
        $this->assertNull($a->settled_at);
        $this->assertEquals(100.00, $member->balance);
        $this->assertEquals(100.00, $caller->balance);
    }

    public function test_admin_settles_negative_amusement_by_splitting_debt(): void
    {
        $admin = $this->makeGroup('admin', true);
        $owners = $this->makeGroup('owners', false);
        $caller = $this->makeUser($admin->id, 1000.00);
        $o1 = $this->makeUser($owners->id, 50.00);
        $o2 = $this->makeUser($owners->id, 50.00);
        $a = $this->makeAmusement($owners->id, -20.00);

        $res = $this->actingAs($caller)->postJson('/settle');

        $res->assertStatus(200);
        $o1->refresh();
        $o2->refresh();
        $this->assertEquals(40.00, $o1->balance);
        $this->assertEquals(40.00, $o2->balance);
        $a->refresh();
        $this->assertEquals(-20.00, $a->amusement_balance, '', 0.0001);
        $this->assertNotNull($a->settled_at);
    }

    public function test_admin_does_not_alter_non_negative_amusements(): void
    {
        $admin = $this->makeGroup('admin', true);
        $owners = $this->makeGroup('owners', false);
        $caller = $this->makeUser($admin->id, 0);
        $o1 = $this->makeUser($owners->id, 100.00);
        $a = $this->makeAmusement($owners->id, 5.00);

        $res = $this->actingAs($caller)->postJson('/settle');
        $res->assertStatus(200);

        $o1->refresh();
        $this->assertEquals(100.00, $o1->balance);
        $a->refresh();
        $this->assertEquals(5.00, $a->amusement_balance);
        $this->assertNotNull($a->settled_at);
    }

    public function test_second_settle_is_a_noop(): void
    {
        $admin = $this->makeGroup('admin', true);
        $owners = $this->makeGroup('owners', false);
        $caller = $this->makeUser($admin->id, 0);
        $o1 = $this->makeUser($owners->id, 100.00);
        $a = $this->makeAmusement($owners->id, -10.00);

        $this->actingAs($caller)->postJson('/settle')->assertStatus(200);

        $o1->refresh();
        $balanceAfterFirst = $o1->balance;

        $second = $this->actingAs($caller)->postJson('/settle');
        $second->assertStatus(200);

        $o1->refresh();
        $a->refresh();
        $this->assertEquals($balanceAfterFirst, $o1->balance);
        $this->assertEquals(-10.00, $a->amusement_balance);
    }

    public function test_member_balance_can_go_negative(): void
    {
        $admin = $this->makeGroup('admin', true);
        $owners = $this->makeGroup('owners', false);
        $caller = $this->makeUser($admin->id, 0);
        $o1 = $this->makeUser($owners->id, 5.00);
        $a = $this->makeAmusement($owners->id, -20.00);

        $this->actingAs($caller)->postJson('/settle')->assertStatus(200);

        $o1->refresh();
        $this->assertEquals(-15.00, $o1->balance);
    }

    public function test_response_includes_per_amusement_details(): void
    {
        $admin = $this->makeGroup('admin', true);
        $owners = $this->makeGroup('owners', false);
        $caller = $this->makeUser($admin->id, 0);
        $this->makeUser($owners->id, 0);
        $this->makeUser($owners->id, 0);
        $negative = $this->makeAmusement($owners->id, -10.00);
        $positive = $this->makeAmusement($owners->id, 5.00);

        $res = $this->actingAs($caller)->postJson('/settle');
        $res->assertStatus(200);

        $details = collect($res->json('details'))->keyBy('amusement_id');
        $this->assertEquals(5.00, $details[$negative->id]['deducted_per_member']);
        $this->assertEquals(0, $details[$positive->id]['deducted_per_member']);
        $this->assertSame(2, $details[$negative->id]['member_count']);
    }
}
```

- [ ] **Step 2: Run SettleTest — expect 404 / route missing**

Run: `cd apps/api && php artisan test tests/Feature/SettleTest.php`
Expected: FAIL — route `/settle` does not exist (404), or controller missing.

### 12b — Implement SettleController + route

- [ ] **Step 3: Create `apps/api/app/Http/Controllers/SettleController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Amusement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SettleController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user()->load('group');

        if (!$user->group?->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $details = [];

        DB::transaction(function () use (&$details) {
            $amusements = Amusement::whereNull('settled_at')->with('group.users')->get();
            foreach ($amusements as $amusement) {
                $members = $amusement->group?->users ?? collect();
                $memberCount = $members->count();

                $deducted = 0.0;
                if ($amusement->amusement_balance < 0 && $memberCount > 0) {
                    $debt = abs((float) $amusement->amusement_balance);
                    $deducted = round($debt / $memberCount, 2);
                    foreach ($members as $member) {
                        $member->decrement('balance', $deducted);
                    }
                }

                $amusement->update(['settled_at' => now()]);

                $details[] = [
                    'amusement_id' => $amusement->id,
                    'amusement_name' => $amusement->name,
                    'amusement_balance' => (float) $amusement->amusement_balance,
                    'deducted_per_member' => $deducted,
                    'member_count' => $memberCount,
                ];
            }
        });

        return response()->json([
            'amusements_settled' => count($details),
            'details' => $details,
        ]);
    }
}
```

- [ ] **Step 4: Add route in `apps/api/routes/api.php`**

Inside the `Route::middleware('auth:sanctum')->group(...)` block, alongside `Route::post('/reset', [ResetController::class, 'store']);`, add:

```php
use App\Http\Controllers\SettleController;
// ...
Route::post('/settle', [SettleController::class, 'store']);
```

(The `use` statement goes at the top of the file with the other controller imports.)

- [ ] **Step 5: Run SettleTest**

Run: `cd apps/api && php artisan test tests/Feature/SettleTest.php`
Expected: 6 passing.

- [ ] **Step 6: Run the full suite**

Run: `cd apps/api && php artisan test`
Expected: all passing.

- [ ] **Step 7: Commit**

```bash
git add apps/api/app/Http/Controllers/SettleController.php apps/api/routes/api.php apps/api/tests/Feature/SettleTest.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
feat(api): admin-only /settle distributes amusement debt across members

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 13: VP penalty in /leaderboard and /stamps

**Files:**
- Create: `apps/api/tests/Feature/VpPenaltyTest.php`
- Modify: `apps/api/app/Http/Controllers/LeaderboardController.php`
- Modify: `apps/api/app/Http/Controllers/StampController.php`

### 13a — Write VpPenaltyTest

- [ ] **Step 1: Create `apps/api/tests/Feature/VpPenaltyTest.php`**

```php
<?php

namespace Tests\Feature;

use App\Models\Amusement;
use App\Models\Group;
use App\Models\Stamp;
use App\Models\Stamptype;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class VpPenaltyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['lion', 'dolphin', 'toucan', 'beetlebug', 'snake'] as $a) {
            Stamptype::forceCreate(['animal' => $a, 'metal' => null]);
            foreach (['silver', 'gold', 'platinum'] as $m) {
                Stamptype::forceCreate(['animal' => $a, 'metal' => $m]);
            }
        }
    }

    private function makeGroup(): Group
    {
        return Group::forceCreate(['name' => 'G-' . Str::random(6)]);
    }

    private function makeUser(int $groupId): User
    {
        return User::forceCreate([
            'name' => 'P-' . Str::random(6),
            'group_id' => $groupId,
            'startcode' => (string) Str::uuid(),
            'access_key' => Hash::make((string) Str::uuid()),
            'balance' => 0,
            'is_active' => true,
        ]);
    }

    private function makeAmusement(int $groupId, float $balance = 0): Amusement
    {
        return Amusement::forceCreate([
            'group_id' => $groupId,
            'name' => 'A-' . Str::random(6),
            'url' => 'https://example.com',
            'api_key' => (string) Str::uuid(),
            'type' => 'game',
            'amusement_balance' => $balance,
        ]);
    }

    private function giveMetalSet(User $u): void
    {
        foreach (['silver', 'gold', 'platinum'] as $m) {
            $animal = 'lion';
            $type = Stamptype::where('animal', $animal)->where('metal', $m)->firstOrFail();
            // Use distinct animals so this also helps form an animal set if more stamps were added.
            Stamp::create(['user_id' => $u->id, 'stamptype_id' => $type->id]);
        }
    }

    public function test_leaderboard_returns_0_vp_when_group_has_negative_amusement(): void
    {
        $group = $this->makeGroup();
        $user = $this->makeUser($group->id);
        $this->giveMetalSet($user);
        $this->makeAmusement($group->id, -0.01);

        $res = $this->actingAs($user)->getJson('/leaderboard');
        $res->assertStatus(200);

        $row = collect($res->json('vp_leaders'))->firstWhere('name', $user->name);
        $this->assertSame(0, $row['total_vp']);
    }

    public function test_leaderboard_returns_normal_vp_when_no_negative_amusement(): void
    {
        $group = $this->makeGroup();
        $user = $this->makeUser($group->id);
        $this->giveMetalSet($user);
        $this->makeAmusement($group->id, 10.00);

        $res = $this->actingAs($user)->getJson('/leaderboard');
        $res->assertStatus(200);

        $row = collect($res->json('vp_leaders'))->firstWhere('name', $user->name);
        $this->assertSame(40, $row['total_vp']);
    }

    public function test_stamps_index_returns_0_total_vp_under_penalty(): void
    {
        $group = $this->makeGroup();
        $user = $this->makeUser($group->id);
        $this->giveMetalSet($user);
        $this->makeAmusement($group->id, -1.00);

        $res = $this->actingAs($user)->getJson('/stamps');
        $res->assertStatus(200);
        $this->assertSame(0, $res->json('total_vp'));
    }

    public function test_stamps_index_returns_normal_total_vp_without_penalty(): void
    {
        $group = $this->makeGroup();
        $user = $this->makeUser($group->id);
        $this->giveMetalSet($user);
        $this->makeAmusement($group->id, 0);

        $res = $this->actingAs($user)->getJson('/stamps');
        $res->assertStatus(200);
        $this->assertSame(40, $res->json('total_vp'));
    }

    public function test_penalty_persists_after_settle(): void
    {
        $admin = Group::forceCreate(['name' => 'admin-' . Str::random(6), 'is_admin' => true]);
        $group = $this->makeGroup();
        $user = $this->makeUser($group->id);
        $this->giveMetalSet($user);
        $this->makeAmusement($group->id, -1.00);

        $caller = $this->makeUser($admin->id);
        $this->actingAs($caller)->postJson('/settle')->assertStatus(200);

        $res = $this->actingAs($user)->getJson('/stamps');
        $res->assertStatus(200);
        $this->assertSame(0, $res->json('total_vp'));
    }
}
```

- [ ] **Step 2: Run VpPenaltyTest, expect failures**

Run: `cd apps/api && php artisan test tests/Feature/VpPenaltyTest.php`
Expected: FAIL — controllers do not zero VP yet (tests expect 0; current code returns 40).

### 13b — Patch LeaderboardController

- [ ] **Step 3: Modify `apps/api/app/Http/Controllers/LeaderboardController.php`**

Change the `$vpLeaders` builder to call the new penalty method. Final shape:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Amusement;
use App\Models\User;
use App\Services\VictoryPointsCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $moneyLeaders = User::with('group')
            ->orderByDesc('balance')
            ->get()
            ->map(fn($u) => [
                'name'    => $u->name,
                'group'   => $u->group?->name,
                'balance' => round($u->balance, 2),
            ])
            ->values();

        $vpLeaders = User::with([
                'stamps' => fn($q) => $q->whereNull('exchanged_at'),
                'group.amusements',
            ])
            ->get()
            ->map(fn($u) => [
                'name'     => $u->name,
                'group'    => $u->group?->name,
                'total_vp' => $u->hasNegativeGroupAmusement()
                    ? 0
                    : VictoryPointsCalculator::compute($u->stamps),
            ])
            ->sortByDesc('total_vp')
            ->values();

        $voteWinners = Amusement::withCount('votes')
            ->with('group')
            ->orderByDesc('votes_count')
            ->get()
            ->map(fn($a) => [
                'name'  => $a->name,
                'group' => $a->group?->name,
                'votes' => $a->votes_count,
            ])
            ->values();

        return response()->json([
            'money_leaders' => $moneyLeaders,
            'vp_leaders'    => $vpLeaders,
            'vote_winners'  => $voteWinners,
        ]);
    }
}
```

### 13c — Patch StampController

- [ ] **Step 4: Modify `apps/api/app/Http/Controllers/StampController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Stamp;
use App\Services\VictoryPointsCalculator;
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
            : VictoryPointsCalculator::compute($stamps);

        return response()->json([
            'data' => $stamps,
            'total_vp' => $totalVp,
        ]);
    }
}
```

- [ ] **Step 5: Run VpPenaltyTest, expect pass**

Run: `cd apps/api && php artisan test tests/Feature/VpPenaltyTest.php`
Expected: 5 passing.

- [ ] **Step 6: Run the full suite**

Run: `cd apps/api && php artisan test`
Expected: all passing.

- [ ] **Step 7: Commit**

```bash
git add apps/api/app/Http/Controllers/LeaderboardController.php \
        apps/api/app/Http/Controllers/StampController.php \
        apps/api/tests/Feature/VpPenaltyTest.php
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
feat(api): zero VP on leaderboard/stamps when group has negative amusement

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Task 14: Update centralbank-api.yaml

**Files:**
- Modify: `docs/centralbank-api.yaml`

This task is doc-only; no tests change. Read the current contents and apply these edits.

- [ ] **Step 1: Rewrite the request body schema for `POST /transactions`**

Locate the path `/transactions: → post: → requestBody.content.application/json.schema` and replace its `properties` block so exactly one of `identity_token` or `continuation_token` is required, plus `amount` and `api_key`. Use an `oneOf` over the two token fields:

```yaml
requestBody:
  required: true
  content:
    application/json:
      schema:
        type: object
        required: [amount, api_key]
        properties:
          identity_token:
            type: string
            format: uuid
            description: >
              Short-lived (5 min, single-use) identity token from the URL
              query parameter. Use this on the first transaction of a
              visit. Consumed on use. Cannot be combined with
              continuation_token.
          continuation_token:
            type: string
            format: uuid
            description: >
              Multi-use session token returned by a previous identity-token
              transaction. Use this for subsequent (in-game) payments
              during the same visit. 30-minute sliding TTL. Cannot be
              combined with identity_token.
          amount:
            type: number
            multipleOf: 0.01
            minimum: 0.01
            description: Amount to deduct in euros (2-decimal precision).
            example: 1.95
          api_key:
            type: string
            format: uuid
            description: The calling amusement's api_key.
        oneOf:
          - required: [identity_token]
          - required: [continuation_token]
```

- [ ] **Step 2: Update the response schema for `POST /transactions`**

Locate `components.schemas.TransactionResponse` and extend it:

```yaml
TransactionResponse:
  type: object
  properties:
    id:
      type: integer
      description: Transaction ID. Save this — it is required for payout.
    stamp:
      nullable: true
      allOf:
        - $ref: "#/components/schemas/Stamp"
      description: >
        Newly minted stamp, or null if the call was an in-game purchase
        (continuation token) or was rate-limited (identity token used,
        but a stamped transaction for this (user, amusement) pair exists
        within the last 3 minutes).
    continuation_token:
      type: string
      format: uuid
      description: >
        Multi-use session token bound to (user, amusement). Use this for
        subsequent (in-game) payments during this visit. Returned on
        every successful call.
    continuation_expires_at:
      type: string
      format: date-time
      description: When the continuation token expires (sliding 30 min).
```

- [ ] **Step 3: Extend the `TransactionListItem.type` enum to include `in_game_purchase`**

Find the existing enum value in `TransactionListItem`:

```yaml
type:
  type: string
  enum: [fee, payout, owner_revenue, exchange]
```

Replace with:

```yaml
type:
  type: string
  enum: [fee, in_game_purchase, payout, owner_revenue, exchange]
```

- [ ] **Step 4: Rewrite the "User flow (amusements)" section in the `info.description` block**

Replace it with:

```
### User flow (amusements)
1. User clicks an amusement link in the Tivoli frontend. The frontend
   calls `POST /identity-tokens` to mint a short-lived (5 min, single-use)
   token bound to the logged-in user.
2. The frontend redirects to the amusement with `?identity_token=<token>`
   in the query string.
3. The amusement reads the token from the URL, then immediately rewrites
   the URL (e.g. `history.replaceState`) to scrub the token.
4. When the user starts paying, the amusement calls `POST /transactions`
   with `{ identity_token, amount, api_key }`. The central bank consumes
   the identity token, deducts the amount from the user, credits the
   amusement, distributes the amount equally across the amusement's group
   members' personal balances, mints a stamp (subject to a 3-minute
   anti-farming rate limit per (user, amusement) pair), and returns
   `{ id, stamp, continuation_token, continuation_expires_at }`.
5. The amusement keeps `continuation_token` in memory for the duration
   of the visit. For mid-game payments, the amusement calls
   `POST /transactions` with `{ continuation_token, amount, api_key }`.
   These are recorded as `in_game_purchase` and never mint a stamp; the
   continuation token's TTL slides forward.
6. Amusement runs its own game logic.
7. If the user wins, the amusement calls `POST /transactions/{id}/payout`
   with `{ amount, api_key }`, where `id` is the original
   identity-token-initiated transaction. Central bank deducts from the
   amusement's tracker and credits the user. Owners are NOT debited at
   payout time. In-game-purchase transactions cannot be paid out.

For attractions (rides), only step 4 is needed — no payout, and
continuation tokens are unused if there are no mid-visit charges.
```

- [ ] **Step 5: Update `POST /transactions/{id}/payout` — add a 400 case**

Add this response under the existing payout responses:

```yaml
"400":
  description: Cannot pay out an in-game purchase transaction
  content:
    application/json:
      schema:
        $ref: "#/components/schemas/Error"
        example:
          message: "In-game purchases cannot be paid out"
```

- [ ] **Step 6: Reintroduce `POST /settle` with the new semantics**

If the previous backend cleanup removed `/settle` from the YAML, add it back. If it's still there, replace its description and response schema with the new behaviour:

```yaml
/settle:
  post:
    tags: [Results]
    operationId: settle
    summary: End-of-showdown settlement (admin only)
    description: >
      Called **once** at the end of the showdown by an admin-group member.
      For each amusement whose `amusement_balance` is negative, the
      absolute amount is split equally across the amusement's group
      members and deducted from their personal balances (members may go
      negative). `amusement_balance` itself is NOT zeroed — it remains
      negative as a historical record and continues to trigger the VP
      penalty. `amusements.settled_at` is set, making subsequent calls
      a no-op for that amusement.
    security: [{ accessKeyAuth: [] }]
    responses:
      "200":
        description: Settlement summary
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/SettlementResponse"
      "401":
        $ref: "#/components/responses/Unauthorized"
      "403":
        description: Caller's group is not an admin group
        content:
          application/json:
            schema:
              $ref: "#/components/schemas/Error"
              example:
                message: "Forbidden"
```

And rewrite the `SettlementResponse` schema:

```yaml
SettlementResponse:
  type: object
  properties:
    amusements_settled:
      type: integer
      description: Number of amusements processed in this call.
    details:
      type: array
      items:
        type: object
        properties:
          amusement_id:
            type: integer
          amusement_name:
            type: string
          amusement_balance:
            type: number
            multipleOf: 0.01
            description: Balance at settlement time (negative = deficit). Not zeroed.
          deducted_per_member:
            type: number
            multipleOf: 0.01
            description: Per-member deduction applied (0 if non-negative or no members).
          member_count:
            type: integer
```

- [ ] **Step 7: Document the VP penalty in `GET /leaderboard` and `GET /stamps`**

In the description of `GET /leaderboard` (in the "VP calculation" section), append:

```
**VP penalty:** if any amusement in the user's group has
`amusement_balance < 0` at the time `/leaderboard` is read, that user's
`total_vp` is forced to 0 regardless of stamp holdings. This persists
after `/settle` (the tracker is not zeroed).
```

In the description of `GET /stamps`, append a similar paragraph:

```
**VP penalty:** if any amusement in the user's group has
`amusement_balance < 0`, `total_vp` is returned as 0 regardless of
stamp holdings. This is the same rule applied by `GET /leaderboard`.
```

- [ ] **Step 8: Commit**

```bash
git add docs/centralbank-api.yaml
git -c commit.gpgsign=false commit -m "$(cat <<'EOF'
docs: update centralbank-api.yaml for unified /transactions, /settle, VP penalty

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>
EOF
)"
```

---

## Self-Review

Spec coverage check against `docs/superpowers/specs/2026-05-19-transaction-flow-design.md`:

- D1 (single `/transactions`): Task 11.
- D2 (oneOf token validation): Task 10.
- D3 (continuation_tokens table + lifecycle): Tasks 4, 6, 11.
- D4 (stamps minted only via identity_token): Task 11 (controller branch on token type).
- D4b (3-min rate limit on identity-token stamps): Task 11 (`$recentStamped` query) + Task 11c tests.
- D5 (type from token, not stamp outcome): Task 11 (controller sets type per branch).
- D6 (income → owners; tracker += amount): Task 11 (`distributeToOwners` + `increment('amusement_balance')`).
- D7 (round 2 dec, örar slängs): Task 11 (`round($amount / $n, 2)`) + Task 11d test.
- D8 (payouts from amusement_balance, owners untouched): Task 11 (payout method unchanged in shape) + Task 11d test.
- D9 (VP penalty live trigger): Tasks 9, 13.
- D10 (`/settle` semantics): Task 12.
- D11 (members may go negative): Task 12 test `test_member_balance_can_go_negative`.
- D12 (`/settle` rebuilt with new semantics): Task 12 + Task 14.
- Migration order from spec ("Migration order" section, items 1–5): Tasks 1–5 in the same order.
- All tests listed in the spec's "Test plan" map to a file in Tasks 11–13.
- Documentation updates from the spec's "Documentation updates" section: Task 14.

Placeholder scan: no TBD/TODO/handwavy lines remain. Every code step contains the actual content to write.

Type consistency: `ContinuationToken::issueFor` and `::extend` are defined in Task 6 and used in Task 11; signatures match. `User::hasNegativeGroupAmusement` is defined in Task 9 and used in Task 13; signatures match. `Amusement.settled_at` is added in Task 5, cast in Task 8, and read in Tasks 12 (`whereNull('settled_at')`) and 13; consistent.

Open implementation choices from the spec ("Open implementation choices" section): all resolved inline — rate-limit check is inline in `TransactionController`; owner distribution is a private method on `TransactionController` rather than a service; `ContinuationToken` is an Eloquent model.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-05-19-transaction-flow.md`. Two execution options:

1. **Subagent-Driven (recommended)** — I dispatch a fresh subagent per task, review between tasks, fast iteration.
2. **Inline Execution** — Execute tasks in this session using executing-plans, batch execution with checkpoints.

Which approach?
