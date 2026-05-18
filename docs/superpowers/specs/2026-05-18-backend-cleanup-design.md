# Backend cleanup — Laravel API

Date: 2026-05-18
Branch: `fix/backend-cleanup`

## Purpose

Clean up the Laravel API at `apps/api/` along three axes:

1. Remove dead code (unused controllers, stub endpoints, stray imports, scaffolding tests).
2. Move every inline `$request->validate(...)` call into a dedicated `FormRequest` class so validation lives in one consistent place.
3. Consolidate single-purpose controllers into resource-shaped controllers where it improves cohesion.

The work must not change any route URL, response shape, or test that the SPA at `apps/web/` already depends on.

## Scope

In scope: files under `apps/api/app/Http/Controllers/`, `apps/api/app/Http/Requests/`, `apps/api/routes/api.php`, and `apps/api/tests/`.

Out of scope: models, middleware, migrations, the database, the SPA, and any behavioural change that is not a direct consequence of consolidation.

## Audit (current state)

| Concern | Files |
| --- | --- |
| Inline validation in controllers | `AuthSessionController::store`, `TransactionController::store`, `TransactionController::payout`, `ExchangeController::store`, `VictoryPointsController::show` |
| Single-purpose controllers that belong on a resource controller | `StoreAmusementController`, `ActivateUserController`, `UpdateUserInfoController` |
| Inline closure in routes | `GET /user` is a 15-line closure in `routes/api.php` |
| Dead code | `VictoryPointsController.php` (unused by SPA, duplicates VP logic), `SettleController.php` (returns 501), `tests/Feature/ExampleTest.php`, `tests/Unit/ExampleTest.php`, stale `use App\Http\Controllers\LoginController;` import |
| Lack of cohesion | VP calculation duplicated in `VictoryPointsController` and `LeaderboardController` (resolved by deleting `VictoryPointsController`) |
| Untracked file | `apps/api/tests/Feature/IdentityTokenShowTest.php` |

## Plan (4 commits)

### Commit 1 — Track new test

Stage and commit `apps/api/tests/Feature/IdentityTokenShowTest.php` unchanged. Establishes a clean working tree before any restructuring.

### Commit 2 — Remove dead code

Delete:
- `apps/api/app/Http/Controllers/VictoryPointsController.php`
- `apps/api/app/Http/Controllers/SettleController.php`
- `apps/api/tests/Feature/ExampleTest.php`
- `apps/api/tests/Unit/ExampleTest.php`

Edit `apps/api/routes/api.php`:
- Remove `use App\Http\Controllers\LoginController;` (stale — class does not exist).
- Remove `use App\Http\Controllers\SettleController;` and the `POST /settle` route.
- Remove `use App\Http\Controllers\VictoryPointsController;` and the `GET /victory-points` route.

### Commit 3 — Extract FormRequest classes

Create:

| File | Replaces inline validation in |
| --- | --- |
| `app/Http/Requests/LoginRequest.php` | `AuthSessionController::store` |
| `app/Http/Requests/StoreTransactionRequest.php` | `TransactionController::store` |
| `app/Http/Requests/PayoutTransactionRequest.php` | `TransactionController::payout` |
| `app/Http/Requests/StoreExchangeRequest.php` | `ExchangeController::store` |

Each follows the existing style under `app/Http/Requests/`: `authorize(): bool { return true; }`, plus a `rules(): array` method that holds the same rules currently inline. Controllers change to typehint the new request and call `$request->validated()`.

Rules to preserve verbatim:

- `LoginRequest`: `name` required string max:50; `access_key` required string uuid.
- `StoreTransactionRequest`: `identity_token` required string; `amount` required numeric min:0; `api_key` required string.
- `PayoutTransactionRequest`: `amount` required numeric min:0; `api_key` required string.
- `StoreExchangeRequest`: `user_id` required integer exists:users,id; `set_type` required string in:metal,animal,non_metal; `stamp_ids` required array; `stamp_ids.*` integer.

### Commit 4 — Consolidate controllers

**Fold `StoreAmusementController` into `AmusementController`:**
- Move `store(StoreAmusementRequest $request)` (and its `use` line) into `AmusementController`.
- Update `routes/api.php`: `POST /amusements` points at `AmusementController@store`.
- Delete `apps/api/app/Http/Controllers/StoreAmusementController.php` and remove its `use` from the routes file.

**Create `UserController`:**
- `UserController::store(ActivateUserRequest $request)` — body identical to current `ActivateUserController::store`. Route: `POST /activate`.
- `UserController::show(Request $request)` — body identical to the current inline closure for `GET /user` in `routes/api.php`. Route: `GET /user`.
- `UserController::update(UpdateUserInfoRequest $request)` — body identical to current `UpdateUserInfoController::update`. Route: `PATCH /user`.
- Delete `ActivateUserController.php` and `UpdateUserInfoController.php`.
- Update `routes/api.php`: replace the three lines / closure with route entries pointing at `UserController`.

Controllers left untouched after this commit: `AmusementController`, `AuthSessionController` (Auth namespace), `ExchangeController`, `GroupController`, `IdentityTokenController`, `LeaderboardController`, `ResetController`, `StampController`, `TransactionController`, `UserController`, `VoteController`.

## Invariants

- Every route the SPA calls (verified against `apps/web/src/**`) keeps its URL, HTTP method, and JSON response shape: `/activate`, `/login`, `/logout`, `/csrf-token`, `/user` (GET/PATCH), `/identity-tokens`, `/identity-tokens/{token}`, `/transactions`, `/transactions/{id}/payout`, `/amusements` (all sub-routes), `/stamps`, `/exchanges`, `/votes`, `/leaderboard`, `/reset`, `/groups`, `/groups/{id}`.
- Validation rules are preserved verbatim — no new rules added, none relaxed.
- Existing tests (`AmusementCreationTest`, `TransactionTest`, `IdentityTokenShowTest`) pass unmodified after each commit.

## Verification

After each of commits 2–4, run `composer test` from `apps/api/` and confirm green before moving to the next commit. Commit 1 needs no test run since it adds only a previously-untracked file.
