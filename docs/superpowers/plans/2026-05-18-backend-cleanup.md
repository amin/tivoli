# Backend cleanup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Clean up the Laravel API at `apps/api/` by removing dead code, moving every inline validation into a dedicated `FormRequest`, and folding single-purpose controllers into resource controllers — without changing any URL, response shape, or test contract the SPA depends on.

**Architecture:** Four ordered commits on the `fix/backend-cleanup` branch. Each commit is independently revertable: track new test → delete dead code → extract FormRequests → consolidate controllers. After each commit (except #1) the full test suite must be green before moving on.

**Tech Stack:** PHP 8.4, Laravel 13, Sanctum, PHPUnit 12 (`composer test`).

---

## File Structure

After all four commits the controller and request directories should look like:

```
apps/api/app/Http/Controllers/
├── AmusementController.php          # (modified — store() folded in)
├── Auth/AuthSessionController.php   # (modified — uses LoginRequest)
├── Controller.php
├── ExchangeController.php           # (modified — uses StoreExchangeRequest)
├── GroupController.php              # unchanged
├── IdentityTokenController.php      # unchanged
├── LeaderboardController.php        # unchanged
├── ResetController.php              # unchanged
├── StampController.php              # unchanged
├── TransactionController.php        # (modified — uses Store/Payout requests)
├── UserController.php               # NEW (merges Activate + UpdateUserInfo + inline /user GET)
└── VoteController.php               # unchanged

apps/api/app/Http/Requests/
├── ActivateUserRequest.php          # unchanged (now used by UserController)
├── LoginRequest.php                 # NEW
├── PayoutTransactionRequest.php     # NEW
├── RenameGroupRequest.php           # unchanged
├── StoreAmusementRequest.php        # unchanged
├── StoreExchangeRequest.php         # NEW
├── StoreTransactionRequest.php      # NEW
├── UpdateAmusementRequest.php       # unchanged
├── UpdateUserInfoRequest.php        # unchanged (now used by UserController)
└── VoteRequest.php                  # unchanged
```

**Deleted:** `VictoryPointsController.php`, `SettleController.php`, `StoreAmusementController.php`, `ActivateUserController.php`, `UpdateUserInfoController.php`, `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`.

---

## Task 1: Commit the untracked IdentityTokenShowTest

**Files:**
- Modify (stage): `apps/api/tests/Feature/IdentityTokenShowTest.php`

The file already exists on disk and already passes against the current code (it's a brand-new feature test for `IdentityTokenController::show`). The only action is to track it so subsequent commits have a clean working tree.

- [ ] **Step 1: Confirm the file is untracked**

Run: `git status --short apps/api/tests/Feature/IdentityTokenShowTest.php`
Expected: `?? apps/api/tests/Feature/IdentityTokenShowTest.php`

- [ ] **Step 2: Confirm the test passes against current code**

Run (from `apps/api/`): `composer test -- --filter IdentityTokenShowTest`
Expected: 4 tests pass.

- [ ] **Step 3: Stage and commit**

```bash
git add apps/api/tests/Feature/IdentityTokenShowTest.php
git commit -m "test(api): add feature tests for IdentityToken show endpoint

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>"
```

- [ ] **Step 4: Verify clean tree**

Run: `git status`
Expected: `nothing to commit, working tree clean`.

---

## Task 2: Remove the unused VictoryPointsController and route

**Files:**
- Delete: `apps/api/app/Http/Controllers/VictoryPointsController.php`
- Modify: `apps/api/routes/api.php`

The frontend never calls `/victory-points`; the VP calculation lives in `LeaderboardController::computeVP` and is the authoritative copy.

- [ ] **Step 1: Confirm no SPA or test references**

Run: `grep -rn "victory-points\|VictoryPointsController" apps/web/src apps/api/tests apps/api/routes apps/api/app`
Expected: only the matches inside `routes/api.php` and `app/Http/Controllers/VictoryPointsController.php` (no SPA hits, no test hits).

- [ ] **Step 2: Delete the controller**

```bash
rm apps/api/app/Http/Controllers/VictoryPointsController.php
```

- [ ] **Step 3: Edit `apps/api/routes/api.php`**

Remove the import (line near the top):

```php
use App\Http\Controllers\VictoryPointsController;
```

And remove the bottom block (the two lines under the `// Existing routes not in the spec (kept untouched)` comment):

```php
// Existing routes not in the spec (kept untouched)
Route::get('/victory-points', [VictoryPointsController::class, 'show']);
```

- [ ] **Step 4: Verify route is gone**

Run (from `apps/api/`): `php artisan route:list --path=victory-points`
Expected: empty output (no routes match).

---

## Task 3: Remove the unused SettleController stub and route

**Files:**
- Delete: `apps/api/app/Http/Controllers/SettleController.php`
- Modify: `apps/api/routes/api.php`

Returns `501 Not Implemented`. No SPA caller.

- [ ] **Step 1: Confirm no SPA references**

Run: `grep -rn "SettleController\|/settle" apps/web/src apps/api/tests`
Expected: empty output.

- [ ] **Step 2: Delete the controller**

```bash
rm apps/api/app/Http/Controllers/SettleController.php
```

- [ ] **Step 3: Edit `apps/api/routes/api.php`**

Remove the import:

```php
use App\Http\Controllers\SettleController;
```

And remove the route inside the `auth:sanctum` group (look under the `// Settlement & leaderboard` comment):

```php
Route::post('/settle', [SettleController::class, 'store']);
```

Update the surrounding comment from `// Settlement & leaderboard` to `// Leaderboard` since settlement is gone.

- [ ] **Step 4: Verify**

Run (from `apps/api/`): `php artisan route:list --path=settle`
Expected: empty output.

---

## Task 4: Remove stale LoginController import

**Files:**
- Modify: `apps/api/routes/api.php`

`use App\Http\Controllers\LoginController;` references a class that does not exist anywhere in the app — confirmed via `grep` earlier. Login is handled by `AuthSessionController`.

- [ ] **Step 1: Confirm the class file does not exist**

Run: `find apps/api/app -name "LoginController.php"`
Expected: empty output.

- [ ] **Step 2: Edit `apps/api/routes/api.php`**

Remove this single line:

```php
use App\Http\Controllers\LoginController;
```

- [ ] **Step 3: Verify autoload still maps cleanly**

Run (from `apps/api/`): `composer dump-autoload`
Expected: completes without warnings about `LoginController`.

---

## Task 5: Remove Laravel example tests

**Files:**
- Delete: `apps/api/tests/Feature/ExampleTest.php`
- Delete: `apps/api/tests/Unit/ExampleTest.php`

Default scaffolding. The Feature one only asserts `GET /` returns 200, which is covered by the application boot.

- [ ] **Step 1: Confirm they are framework boilerplate**

Run: `cat apps/api/tests/Feature/ExampleTest.php apps/api/tests/Unit/ExampleTest.php`
Expected: identical structure to the Laravel install templates (one assertion each, no app-specific logic).

- [ ] **Step 2: Delete both files**

```bash
rm apps/api/tests/Feature/ExampleTest.php apps/api/tests/Unit/ExampleTest.php
```

- [ ] **Step 3: Run the full suite to confirm nothing else depended on them**

Run (from `apps/api/`): `composer test`
Expected: all remaining tests pass — `AmusementCreationTest`, `IdentityTokenShowTest`, `TransactionTest`.

---

## Task 6: Commit the dead-code removal

**Files:** No new edits; this is purely a commit of the work from Tasks 2–5.

- [ ] **Step 1: Review staged changes**

Run: `git status && git diff apps/api/routes/api.php`
Expected: deletions of the four files listed plus the routes/imports edits.

- [ ] **Step 2: Stage and commit**

```bash
git add apps/api/app/Http/Controllers/VictoryPointsController.php \
        apps/api/app/Http/Controllers/SettleController.php \
        apps/api/tests/Feature/ExampleTest.php \
        apps/api/tests/Unit/ExampleTest.php \
        apps/api/routes/api.php
git commit -m "refactor(api): remove dead controllers, stub routes, and example tests

- Drop VictoryPointsController and /victory-points (unused; VP logic
  lives in LeaderboardController).
- Drop SettleController and /settle (returned 501; no caller).
- Drop default Laravel ExampleTests.
- Drop stale LoginController import in routes/api.php.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>"
```

- [ ] **Step 3: Verify suite still green post-commit**

Run (from `apps/api/`): `composer test`
Expected: all tests pass.

---

## Task 7: Create LoginRequest

**Files:**
- Create: `apps/api/app/Http/Requests/LoginRequest.php`

- [ ] **Step 1: Create the file**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:50'],
            'access_key' => ['required', 'string', 'uuid'],
        ];
    }
}
```

---

## Task 8: Wire LoginRequest into AuthSessionController

**Files:**
- Modify: `apps/api/app/Http/Controllers/Auth/AuthSessionController.php`

The current `store` calls `$request->validate([...])` inline. Replace the typehint and drop the inline `validate` call.

- [ ] **Step 1: Replace the file's `store` method**

Open `apps/api/app/Http/Controllers/Auth/AuthSessionController.php`.

Add this import alongside the existing imports:

```php
use App\Http\Requests\LoginRequest;
```

Change the `store` signature and its first lines from:

```php
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'name' => ['required', 'string', 'max:50'],
        'access_key' => ['required', 'string', 'uuid'],
    ]);

    $name = trim($validated['name']);
    $accessKey = $validated['access_key'];
```

To:

```php
public function store(LoginRequest $request): JsonResponse
{
    $validated = $request->validated();
    $name = trim($validated['name']);
    $accessKey = $validated['access_key'];
```

The rest of the method body, `destroy`, and the class-level imports stay exactly as they are. Do **not** remove `use Illuminate\Http\Request;` — the `destroy` method still typehints it.

- [ ] **Step 2: Run login-adjacent tests**

Run (from `apps/api/`): `composer test`
Expected: all tests pass (none currently exercise login directly, but `AmusementCreationTest` and `TransactionTest` use `actingAs` which doesn't go through the route).

---

## Task 9: Create StoreTransactionRequest

**Files:**
- Create: `apps/api/app/Http/Requests/StoreTransactionRequest.php`

- [ ] **Step 1: Create the file**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
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
            'identity_token' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0'],
            'api_key' => ['required', 'string'],
        ];
    }
}
```

---

## Task 10: Create PayoutTransactionRequest

**Files:**
- Create: `apps/api/app/Http/Requests/PayoutTransactionRequest.php`

- [ ] **Step 1: Create the file**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PayoutTransactionRequest extends FormRequest
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
            'amount' => ['required', 'numeric', 'min:0'],
            'api_key' => ['required', 'string'],
        ];
    }
}
```

---

## Task 11: Wire the new requests into TransactionController

**Files:**
- Modify: `apps/api/app/Http/Controllers/TransactionController.php`

- [ ] **Step 1: Add imports**

At the top of `TransactionController.php`, alongside the existing `use` statements, add:

```php
use App\Http\Requests\PayoutTransactionRequest;
use App\Http\Requests\StoreTransactionRequest;
```

- [ ] **Step 2: Replace `store`**

Replace the existing `store` method with:

```php
public function store(StoreTransactionRequest $request): JsonResponse
{
    $data = $request->validated();

    $amusement = Amusement::where('api_key', $data['api_key'])->first();

    if (!$amusement) {
        return response()->json(['message' => 'Invalid api_key'], 401);
    }

    $token = IdentityToken::where('token', $data['identity_token'])->first();

    if (!$token || !$token->isValid()) {
        return response()->json(['message' => 'Invalid or expired identity token'], 401);
    }

    $user = $token->user;

    if ($user->balance < $data['amount']) {
        return response()->json(['message' => 'Insufficient balance'], 402);
    }

    return DB::transaction(function () use ($user, $amusement, $data, $token) {
        $token->update(['consumed_at' => now()]);

        $user->decrement('balance', $data['amount']);
        $amusement->increment('amusement_balance', $data['amount']);

        $transaction = Transaction::create([
            'user_id' => $user->id,
            'amusement_id' => $amusement->id,
            'amount' => $data['amount'],
            'type' => 'fee',
        ]);

        $stamp = Stamp::generate($user->id);

        return response()->json([
            'id' => $transaction->id,
            'stamp' => $stamp,
        ], 201);
    });
}
```

- [ ] **Step 3: Replace `payout`**

Replace the existing `payout` method with:

```php
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
```

- [ ] **Step 4: Remove now-unused `Request` import if appropriate**

Check whether anything else in the controller still uses `Illuminate\Http\Request`. After this task, nothing does — remove that import line.

- [ ] **Step 5: Run the transaction tests**

Run (from `apps/api/`): `composer test -- --filter TransactionTest`
Expected: all 10 tests pass — in particular `test_missing_api_key_returns_422` still validates correctly via the new FormRequest.

---

## Task 12: Create StoreExchangeRequest

**Files:**
- Create: `apps/api/app/Http/Requests/StoreExchangeRequest.php`

- [ ] **Step 1: Create the file**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreExchangeRequest extends FormRequest
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
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'set_type' => ['required', 'string', 'in:metal,animal,non_metal'],
            'stamp_ids' => ['required', 'array'],
            'stamp_ids.*' => ['integer'],
        ];
    }
}
```

---

## Task 13: Wire StoreExchangeRequest into ExchangeController

**Files:**
- Modify: `apps/api/app/Http/Controllers/ExchangeController.php`

- [ ] **Step 1: Add import and update store**

Alongside the existing imports, add:

```php
use App\Http\Requests\StoreExchangeRequest;
```

Replace the `store` method signature and its first lines from:

```php
public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'user_id' => ['required', 'integer', 'exists:users,id'],
        'set_type' => ['required', 'string', 'in:metal,animal,non_metal'],
        'stamp_ids' => ['required', 'array'],
        'stamp_ids.*' => ['integer'],
    ]);
```

To:

```php
public function store(StoreExchangeRequest $request): JsonResponse
{
    $validated = $request->validated();
```

Everything else in the method body and the private helpers stays as-is.

- [ ] **Step 2: Drop the now-unused `Request` import**

`ExchangeController` no longer references `Illuminate\Http\Request`. Remove that `use` line.

- [ ] **Step 3: Run the suite**

Run (from `apps/api/`): `composer test`
Expected: all tests pass (no exchange tests today, but the suite must stay green and the request must autoload).

---

## Task 14: Commit the FormRequest extraction

**Files:** Commit of all changes from Tasks 7–13.

- [ ] **Step 1: Stage and commit**

```bash
git add apps/api/app/Http/Requests/LoginRequest.php \
        apps/api/app/Http/Requests/StoreTransactionRequest.php \
        apps/api/app/Http/Requests/PayoutTransactionRequest.php \
        apps/api/app/Http/Requests/StoreExchangeRequest.php \
        apps/api/app/Http/Controllers/Auth/AuthSessionController.php \
        apps/api/app/Http/Controllers/TransactionController.php \
        apps/api/app/Http/Controllers/ExchangeController.php
git commit -m "refactor(api): move inline validations into FormRequest classes

Adds LoginRequest, StoreTransactionRequest, PayoutTransactionRequest,
StoreExchangeRequest. Removes the corresponding inline \$request->validate
calls. No behaviour change.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>"
```

- [ ] **Step 2: Verify suite green**

Run (from `apps/api/`): `composer test`
Expected: all tests pass.

---

## Task 15: Fold StoreAmusementController into AmusementController

**Files:**
- Modify: `apps/api/app/Http/Controllers/AmusementController.php`
- Delete: `apps/api/app/Http/Controllers/StoreAmusementController.php`
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Add the new import to `AmusementController.php`**

Alongside the existing `use App\Http\Requests\UpdateAmusementRequest;` add:

```php
use App\Http\Requests\StoreAmusementRequest;
```

- [ ] **Step 2: Add the `store` method**

Insert this method into `AmusementController` immediately above `public function show` (so methods read in CRUD order: index, store, show, update, destroy, regenerateKey, transactions, stats):

```php
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
```

- [ ] **Step 3: Delete the standalone controller**

```bash
rm apps/api/app/Http/Controllers/StoreAmusementController.php
```

- [ ] **Step 4: Update routes/api.php**

In `apps/api/routes/api.php`:

Remove the import:

```php
use App\Http\Controllers\StoreAmusementController;
```

Change the route from:

```php
Route::post('/amusements', [StoreAmusementController::class, 'store']);
```

To:

```php
Route::post('/amusements', [AmusementController::class, 'store']);
```

- [ ] **Step 5: Run amusement tests**

Run (from `apps/api/`): `composer test -- --filter AmusementCreationTest`
Expected: all 7 tests pass.

- [ ] **Step 6: Full suite check**

Run (from `apps/api/`): `composer test`
Expected: all tests pass.

---

## Task 16: Create UserController consolidating activate, show, update

**Files:**
- Create: `apps/api/app/Http/Controllers/UserController.php`
- Delete: `apps/api/app/Http/Controllers/ActivateUserController.php`
- Delete: `apps/api/app/Http/Controllers/UpdateUserInfoController.php`
- Modify: `apps/api/routes/api.php`

- [ ] **Step 1: Create the new controller**

Write `apps/api/app/Http/Controllers/UserController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\ActivateUserRequest;
use App\Http\Requests\UpdateUserInfoRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserController extends Controller
{
    public function store(ActivateUserRequest $request): JsonResponse
    {
        $requestedName = trim($request->name);

        $user = User::whereRaw('LOWER(name) = ?', [Str::lower($requestedName)])
            ->first();

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($user->access_key !== null) {
            return response()->json(['message' => 'User already activated'], 400);
        }

        if ($user->startcode !== $request->startcode) {
            return response()->json(['message' => 'Invalid startcode'], 401);
        }

        $accessKey = Str::uuid()->toString();

        $user->access_key = Hash::make($accessKey);
        $user->save();

        return response()->json([
            'message' => 'Activation successful',
            'access_key' => $accessKey,
        ]);
    }

    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('group');
        $group = $user->group;

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'balance' => $user->balance,
            'stamp_count' => $user->stamps()->count(),
            'has_voted' => $user->vote()->exists(),
            'group' => $group ? [
                'id' => $group->id,
                'name' => $group->name,
                'is_admin' => (bool) $group->is_admin,
                'member_count' => $group->users()->count(),
            ] : null,
        ]);
    }

    public function update(UpdateUserInfoRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->update($request->validated());

        return response()->json([
            'message' => 'User info updated',
            'user' => $user,
        ]);
    }
}
```

- [ ] **Step 2: Delete the old controllers**

```bash
rm apps/api/app/Http/Controllers/ActivateUserController.php \
   apps/api/app/Http/Controllers/UpdateUserInfoController.php
```

- [ ] **Step 3: Update routes/api.php**

Open `apps/api/routes/api.php`.

Remove the two imports:

```php
use App\Http\Controllers\ActivateUserController;
use App\Http\Controllers\UpdateUserInfoController;
```

Add the new import (alphabetised with the rest):

```php
use App\Http\Controllers\UserController;
```

Replace `Route::post('/activate', [ActivateUserController::class, 'store']);` with:

```php
Route::post('/activate', [UserController::class, 'store']);
```

Replace the inline `Route::get('/user', function (Request $request) { ... });` closure (the 15-line block) with a single line:

```php
Route::get('/user', [UserController::class, 'show']);
```

Replace `Route::patch('/user', [UpdateUserInfoController::class, 'update']);` with:

```php
Route::patch('/user', [UserController::class, 'update']);
```

If `Illuminate\Http\Request` is no longer used anywhere in `routes/api.php`, remove its `use` line as well. (Check: after these edits the file should not reference `Request` directly.)

- [ ] **Step 4: Run the full suite**

Run (from `apps/api/`): `composer test`
Expected: all tests pass.

- [ ] **Step 5: Smoke-test the routes are registered**

Run (from `apps/api/`): `php artisan route:list --columns=method,uri,action | grep -E "(activate|^.*GET\s+user|^.*PATCH\s+user)"`
Expected: lists `POST /activate → UserController@store`, `GET /user → UserController@show`, `PATCH /user → UserController@update`.

---

## Task 17: Commit the controller consolidation

**Files:** Commit of all changes from Tasks 15–16.

- [ ] **Step 1: Stage and commit**

```bash
git add apps/api/app/Http/Controllers/AmusementController.php \
        apps/api/app/Http/Controllers/StoreAmusementController.php \
        apps/api/app/Http/Controllers/UserController.php \
        apps/api/app/Http/Controllers/ActivateUserController.php \
        apps/api/app/Http/Controllers/UpdateUserInfoController.php \
        apps/api/routes/api.php
git commit -m "refactor(api): fold single-purpose controllers into resource controllers

- StoreAmusementController -> AmusementController::store.
- ActivateUserController + UpdateUserInfoController + the inline
  GET /user closure -> UserController (store, show, update).
- routes/api.php now uses controller methods exclusively.

Co-Authored-By: Claude Opus 4.7 <noreply@anthropic.com>"
```

- [ ] **Step 2: Final verification**

Run (from `apps/api/`): `composer test`
Expected: all tests pass.

Run: `git log --oneline -6`
Expected (top to bottom): controller consolidation, FormRequest extraction, dead code removal, IdentityTokenShowTest commit, design spec commit, prior `4e4d1ae Merge pull request #29 ...`.

---

## Acceptance criteria

- `git log` on `fix/backend-cleanup` shows four new behaviour commits (Tasks 1, 6, 14, 17) plus the design spec commit already present.
- `composer test` is green at every commit boundary from Task 6 onwards.
- `apps/api/app/Http/Controllers/` no longer contains `VictoryPointsController.php`, `SettleController.php`, `StoreAmusementController.php`, `ActivateUserController.php`, or `UpdateUserInfoController.php`.
- `apps/api/app/Http/Requests/` contains the four new files: `LoginRequest.php`, `StoreTransactionRequest.php`, `PayoutTransactionRequest.php`, `StoreExchangeRequest.php`.
- `apps/api/routes/api.php` contains no inline closures (other than the `/` and `/csrf-token` one-liners), no inline `$request->validate(...)` calls anywhere in `app/Http/Controllers/**`, and no reference to `LoginController`, `SettleController`, `VictoryPointsController`, `StoreAmusementController`, `ActivateUserController`, or `UpdateUserInfoController`.
- SPA traffic against the API behaves identically (no route URL, HTTP method, or response shape changed).
