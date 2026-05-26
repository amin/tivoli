# Toggle Guest Account Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let admins enable/disable the shared `Guest` account from the web Admin panel, and hide player-facing guest login when it is disabled.

**Architecture:** Reuse the existing `users.is_active` flag (no schema change). Add a public `GET /guest` returning `{ active }` plus an admin-only `PATCH /guest` to set `is_active` on the `Guest` user. The web app reads availability via a shared hook to hide the guest login button (login page) and skip the guest flow (home), and adds a toggle to the Admin panel.

**Tech Stack:** Laravel 13 / PHP 8.4 (PHPUnit feature tests, SQLite); React 19 / TypeScript / Vite.

---

## File Structure

**API (`apps/api`)**
- Create: `app/Http/Controllers/GuestController.php` — `show` (public status) + `update` (admin toggle).
- Modify: `routes/api.php` — register `GET /guest` (public) and `PATCH /guest` (auth group).
- Create: `tests/Feature/GuestToggleTest.php` — feature tests for both endpoints.

**Web (`apps/web`)**
- Create: `src/hooks/useGuestAvailable.ts` — shared hook calling `GET /guest`.
- Modify: `src/pages/login.tsx` — hide the guest login row when unavailable.
- Modify: `src/pages/home.tsx` — skip `GuestWarningModal`, route to `/login` when unavailable.
- Modify: `src/components/Admin.tsx` — guest toggle row (read state, flip via `PATCH /guest`).

---

## Task 1: `GET /guest` public status endpoint

**Files:**
- Create: `apps/api/app/Http/Controllers/GuestController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/GuestToggleTest.php`

- [ ] **Step 1: Write the failing tests**

Create `apps/api/tests/Feature/GuestToggleTest.php`:

```php
<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestToggleTest extends TestCase
{
    use RefreshDatabase;

    private function makeGuest(bool $isActive): User
    {
        return User::forceCreate([
            'name' => 'Guest',
            'group_id' => null,
            'startcode' => (string) Str::uuid(),
            'access_key' => null,
            'balance' => 90000,
            'is_active' => $isActive,
        ]);
    }

    private function makeUser(?int $groupId): User
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

    private function adminUser(): User
    {
        $group = Group::forceCreate(['name' => 'admin-' . Str::random(6), 'is_admin' => true]);
        return $this->makeUser($group->id);
    }

    public function test_status_is_true_when_guest_active(): void
    {
        $this->makeGuest(true);

        $this->getJson('/guest')
            ->assertStatus(200)
            ->assertJson(['active' => true]);
    }

    public function test_status_is_false_when_guest_inactive(): void
    {
        $this->makeGuest(false);

        $this->getJson('/guest')
            ->assertStatus(200)
            ->assertJson(['active' => false]);
    }

    public function test_status_is_false_when_no_guest_exists(): void
    {
        $this->getJson('/guest')
            ->assertStatus(200)
            ->assertJson(['active' => false]);
    }
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/api && php artisan test --filter=GuestToggleTest`
Expected: FAIL — route `/guest` not defined (404), assertions fail.

- [ ] **Step 3: Create the controller with `show`**

Create `apps/api/app/Http/Controllers/GuestController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GuestController extends Controller
{
    public function show(): JsonResponse
    {
        $active = User::whereRaw('LOWER(name) = ?', ['guest'])
            ->where('is_active', true)
            ->exists();

        return response()->json(['active' => $active]);
    }
}
```

- [ ] **Step 4: Register the public route**

In `apps/api/routes/api.php`, add the import near the other controller imports:

```php
use App\Http\Controllers\GuestController;
```

And add the public route after the `/amusements` public listing line:

```php
// ── Guest account status (public — login page reads this) ──────────────
Route::get('/guest', [GuestController::class, 'show']);
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `cd apps/api && php artisan test --filter=GuestToggleTest`
Expected: PASS (3 tests).

- [ ] **Step 6: Commit**

```bash
git add apps/api/app/Http/Controllers/GuestController.php apps/api/routes/api.php apps/api/tests/Feature/GuestToggleTest.php
git commit -m "Add public GET /guest status endpoint"
```

---

## Task 2: `PATCH /guest` admin toggle endpoint

**Files:**
- Modify: `apps/api/app/Http/Controllers/GuestController.php`
- Modify: `apps/api/routes/api.php`
- Test: `apps/api/tests/Feature/GuestToggleTest.php`

- [ ] **Step 1: Write the failing tests**

Append these methods to `apps/api/tests/Feature/GuestToggleTest.php` (inside the class):

```php
    public function test_admin_can_disable_guest(): void
    {
        $this->makeGuest(true);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->patchJson('/guest', ['is_active' => false])
            ->assertStatus(200)
            ->assertJson(['active' => false]);

        $this->assertFalse((bool) User::whereRaw('LOWER(name) = ?', ['guest'])->first()->is_active);
    }

    public function test_admin_can_enable_guest(): void
    {
        $this->makeGuest(false);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->patchJson('/guest', ['is_active' => true])
            ->assertStatus(200)
            ->assertJson(['active' => true]);

        $this->assertTrue((bool) User::whereRaw('LOWER(name) = ?', ['guest'])->first()->is_active);
    }

    public function test_non_admin_cannot_toggle_guest(): void
    {
        $this->makeGuest(true);
        $nonAdmin = $this->makeUser(null);

        $this->actingAs($nonAdmin)
            ->patchJson('/guest', ['is_active' => false])
            ->assertStatus(403);

        $this->assertTrue((bool) User::whereRaw('LOWER(name) = ?', ['guest'])->first()->is_active);
    }

    public function test_unauthenticated_cannot_toggle_guest(): void
    {
        $this->makeGuest(true);

        $this->patchJson('/guest', ['is_active' => false])
            ->assertStatus(401);
    }

    public function test_toggle_returns_404_when_no_guest_exists(): void
    {
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->patchJson('/guest', ['is_active' => true])
            ->assertStatus(404);
    }

    public function test_toggle_validates_is_active(): void
    {
        $this->makeGuest(true);
        $admin = $this->adminUser();

        $this->actingAs($admin)
            ->patchJson('/guest', [])
            ->assertStatus(422);
    }
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd apps/api && php artisan test --filter=GuestToggleTest`
Expected: FAIL — `PATCH /guest` not defined (the new tests get 405/404 instead of expected statuses).

- [ ] **Step 3: Add the `update` method to the controller**

Add to `apps/api/app/Http/Controllers/GuestController.php` inside the class:

```php
    public function update(Request $request): JsonResponse
    {
        $user = $request->user()->load('group');

        if (!$user->group?->is_admin) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
        ]);

        $guest = User::whereRaw('LOWER(name) = ?', ['guest'])->first();

        if (!$guest) {
            return response()->json(['message' => 'Guest account not found'], 404);
        }

        $guest->is_active = $validated['is_active'];
        $guest->save();

        return response()->json(['active' => (bool) $guest->is_active]);
    }
```

- [ ] **Step 4: Register the admin route**

In `apps/api/routes/api.php`, inside the `Route::middleware('auth:sanctum')->group(...)` block (near the `/reset` and `/settle` admin routes), add:

```php
    // Guest account toggle (admin only)
    Route::patch('/guest', [GuestController::class, 'update']);
```

- [ ] **Step 5: Run the full test file to verify all pass**

Run: `cd apps/api && php artisan test --filter=GuestToggleTest`
Expected: PASS (9 tests total).

- [ ] **Step 6: Commit**

```bash
git add apps/api/app/Http/Controllers/GuestController.php apps/api/routes/api.php apps/api/tests/Feature/GuestToggleTest.php
git commit -m "Add admin PATCH /guest toggle endpoint"
```

---

## Task 3: `useGuestAvailable` shared hook

**Files:**
- Create: `apps/web/src/hooks/useGuestAvailable.ts`

- [ ] **Step 1: Inspect an existing hook for conventions**

Run: `cat apps/web/src/hooks/useAmusements.ts`
Note the import style for `apiFetch` and the `useEffect` fetch pattern, and match it.

- [ ] **Step 2: Create the hook**

Create `apps/web/src/hooks/useGuestAvailable.ts`:

```ts
import { useCallback, useEffect, useState } from "react";
import { apiFetch } from "../lib/api";

type GuestStatus = {
  /** null while the initial status request is in flight. */
  available: boolean | null;
  refresh: () => Promise<void>;
};

export function useGuestAvailable(): GuestStatus {
  const [available, setAvailable] = useState<boolean | null>(null);

  const refresh = useCallback(async () => {
    try {
      const res = await apiFetch("/guest");
      if (!res.ok) {
        setAvailable(false);
        return;
      }
      const data = (await res.json()) as { active?: boolean };
      setAvailable(Boolean(data.active));
    } catch {
      setAvailable(false);
    }
  }, []);

  useEffect(() => {
    void refresh();
  }, [refresh]);

  return { available, refresh };
}
```

- [ ] **Step 3: Verify it type-checks**

Run: `cd apps/web && pnpm exec tsc --noEmit`
Expected: no errors.

- [ ] **Step 4: Commit**

```bash
git add apps/web/src/hooks/useGuestAvailable.ts
git commit -m "Add useGuestAvailable hook"
```

---

## Task 4: Hide guest login button on login page when unavailable

**Files:**
- Modify: `apps/web/src/pages/login.tsx`

- [ ] **Step 1: Import the hook**

In `apps/web/src/pages/login.tsx`, add to the imports:

```ts
import { useGuestAvailable } from "../hooks/useGuestAvailable";
```

- [ ] **Step 2: Read availability in the component**

Inside the `Login` component, near the other hooks (e.g. after `const navigate = useNavigate();` or alongside the other state), add:

```ts
  const { available: guestAvailable } = useGuestAvailable();
```

- [ ] **Step 3: Hide the guest row until available**

Wrap the existing `guestLoginRow` block (the `<div className="guestLoginRow">…</div>` containing the "or" divider and "Login as Guest" button) so it renders only when guest is confirmed available (hidden while `null`/loading):

```tsx
            {guestAvailable === true && (
              <div className="guestLoginRow">
                <span className="guestDivider">or</span>
                <button
                  type="button"
                  className="btn btn-link"
                  disabled={loginLoading}
                  onClick={loginAsGuest}
                >
                  {loginLoading ? "Logging in…" : "Login as Guest"}
                </button>
              </div>
            )}
```

- [ ] **Step 4: Verify it type-checks and builds**

Run: `cd apps/web && pnpm exec tsc --noEmit`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add apps/web/src/pages/login.tsx
git commit -m "Hide guest login button when guest account disabled"
```

---

## Task 5: Skip guest flow on home page when unavailable

**Files:**
- Modify: `apps/web/src/pages/home.tsx`

- [ ] **Step 1: Import the hook**

In `apps/web/src/pages/home.tsx`, add to the imports:

```ts
import { useGuestAvailable } from "../hooks/useGuestAvailable";
```

- [ ] **Step 2: Read availability in the component**

Inside `Home`, after `const navigate = useNavigate();`, add:

```ts
  const { available: guestAvailable } = useGuestAvailable();
```

- [ ] **Step 3: Route logged-out users to /login when guest is unavailable**

In `openAmusement`, replace the existing logged-out branch:

```ts
    if (!user) {
      setGuestPending(a);
      return;
    }
```

with:

```ts
    if (!user) {
      if (guestAvailable === false) {
        navigate("/login");
      } else {
        setGuestPending(a);
      }
      return;
    }
```

- [ ] **Step 4: Verify it type-checks**

Run: `cd apps/web && pnpm exec tsc --noEmit`
Expected: no errors.

- [ ] **Step 5: Commit**

```bash
git add apps/web/src/pages/home.tsx
git commit -m "Route to login instead of guest flow when guest disabled"
```

---

## Task 6: Guest toggle in the Admin panel

**Files:**
- Modify: `apps/web/src/components/Admin.tsx`

- [ ] **Step 1: Import the hook**

In `apps/web/src/components/Admin.tsx`, add to the imports:

```ts
import { useGuestAvailable } from "../hooks/useGuestAvailable";
```

- [ ] **Step 2: Add state and toggle handler**

Inside the `Admin` component, after the existing `useState` declarations, add:

```ts
  const { available: guestAvailable, refresh: refreshGuest } = useGuestAvailable();
  const [togglingGuest, setTogglingGuest] = useState(false);

  async function handleToggleGuest() {
    setTogglingGuest(true);
    setError(null);
    try {
      const res = await apiFetch('/guest', {
        method: 'PATCH',
        body: JSON.stringify({ is_active: !guestAvailable }),
      });
      if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        setError((data as { message?: string }).message ?? 'Failed to update guest account.');
        return;
      }
      await refreshGuest();
    } catch {
      setError('Network error.');
    } finally {
      setTogglingGuest(false);
    }
  }
```

- [ ] **Step 3: Render the toggle row**

In the `.admin-actions` block in `Admin.tsx`, add a guest toggle after the Scoreboard button (before the Settle block):

```tsx
          <div className="guest-toggle-row">
            <span className="guest-toggle-label">
              Guest login: {guestAvailable === null ? '…' : guestAvailable ? 'On' : 'Off'}
            </span>
            <button
              className="btn btn-secondary"
              onClick={handleToggleGuest}
              disabled={togglingGuest || guestAvailable === null}
            >
              {togglingGuest
                ? 'Updating…'
                : guestAvailable
                  ? 'Disable guest'
                  : 'Enable guest'}
            </button>
          </div>
```

- [ ] **Step 4: Verify it type-checks**

Run: `cd apps/web && pnpm exec tsc --noEmit`
Expected: no errors.

- [ ] **Step 5: Build the web app to confirm no regressions**

Run: `cd apps/web && pnpm build`
Expected: build succeeds.

- [ ] **Step 6: Commit**

```bash
git add apps/web/src/components/Admin.tsx
git commit -m "Add guest login toggle to Admin panel"
```

---

## Task 7: Manual verification & final checks

**Files:** none (verification only)

- [ ] **Step 1: Run the full API test suite**

Run: `cd apps/api && php artisan test`
Expected: all tests pass (including the new `GuestToggleTest`).

- [ ] **Step 2: Lint the web app**

Run: `cd apps/web && pnpm lint`
Expected: no new lint errors.

- [ ] **Step 3: Manual smoke test (start both apps with `pnpm dev`)**

Verify these three states:
1. Guest active: login page shows "Login as Guest"; Admin panel reads "Guest login: On"; clicking an amusement while logged out shows the guest warning modal.
2. From the Admin panel, click "Disable guest". Status flips to "Off".
3. Guest inactive: reload `/login` — the "or / Login as Guest" row is gone; on the home page, clicking an amusement while logged out routes to `/login`.
4. Re-enable from the Admin panel; confirm the guest button returns.

- [ ] **Step 4: Final note**

No commit needed — this task only verifies behavior. If any check fails, return to the relevant task above before finishing the branch.
