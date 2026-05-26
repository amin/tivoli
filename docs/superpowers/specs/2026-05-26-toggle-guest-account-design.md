# Toggle Guest Account from Admin Panel — Design

**Date:** 2026-05-26
**Status:** Approved

## Problem

The shared `Guest` account (a `User` named `Guest`, seeded by `GuestSeeder`) can
be turned on or off only by editing the database or seeder by hand. Admins need
to do this from the web Admin panel. When guest login is off, the player-facing
guest entry points should not be shown.

## Approach

Reuse the existing `users.is_active` flag — the same field `storeGuest` already
checks — so no schema change is needed. Add a small API surface to read and
write the `Guest` account's active state, gated to admins for writes, and wire
the web app to it.

## API (`apps/api`)

New `GuestController` with two actions and two routes.

```php
// routes/api.php

// Public — login page & home read this (pre-auth)
Route::get('/guest', [GuestController::class, 'show']);

// Admin only — inside the existing auth:sanctum group
Route::patch('/guest', [GuestController::class, 'update']);
```

### `GET /guest` (public)

Returns the single source of truth consumed by both the login page and the
admin panel:

```json
{ "active": true }
```

`active` is `true` when a user named `guest` (case-insensitive) exists **and**
has `is_active = true` — the exact condition `AuthSessionController::storeGuest`
uses to allow guest login.

### `PATCH /guest` (admin only)

Body:

```json
{ "is_active": true }
```

- Admin-gated using the same pattern as `ResetController` / `SettleController`:
  `$user = $request->user()->load('group'); if (!$user->group?->is_admin) return 403;`
- Validates `is_active` as a required boolean.
- Loads the `Guest` user; returns `404 { "message": "Guest account not found" }`
  if absent.
- Sets `is_active`, saves, and returns `{ "active": <bool> }`.

## Web (`apps/web`)

### Shared hook: `useGuestAvailable`

A small hook that calls `GET /guest` and exposes `{ available, loading, refresh }`,
so the login page and home page don't duplicate the fetch.

### `Admin.tsx`

Add a "Guest login" row to `.admin-actions`:

- On mount, `GET /guest` to load current state.
- Render status ("Guest login: On" / "Off") with a button to flip it via
  `PATCH /guest { is_active: !current }`.
- Reflect the new state from the PATCH response; reuse the existing
  loading/error styling in the component.

### `login.tsx`

- On mount, read guest availability via `useGuestAvailable`.
- Hide the entire `guestLoginRow` ("or / Login as Guest") when `available` is
  `false`.
- **Hide until resolved** (do not show the button before the check returns) to
  avoid a dead-button flash.

### `home.tsx`

- Read guest availability via `useGuestAvailable`.
- When unavailable and a logged-out user clicks an amusement, skip the
  `GuestWarningModal` and route to `/login` instead of offering
  "Continue as guest".

## Testing

### API (TDD, follows `apps/api/tests` patterns)

- `GET /guest` returns `active: true` when Guest is active.
- `GET /guest` returns `active: false` when Guest is inactive.
- `GET /guest` returns `active: false` when no Guest user exists.
- `PATCH /guest` as admin toggles `is_active` on and off (200, correct body).
- `PATCH /guest` as non-admin returns 403.
- `PATCH /guest` when no Guest user exists returns 404.
- `PATCH /guest` with a missing/invalid `is_active` returns 422.

### Web

- Manual verification of the three states: guest on (button shown, toggle reads
  On), guest off (button hidden on login, home routes to `/login`, toggle reads
  Off), and the admin toggle round-trip.

## Out of scope

- General admin user management (only the `Guest` account is toggled here).
- Any change to the guest account's balance, group, or other fields.
