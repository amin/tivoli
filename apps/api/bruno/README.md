# Tivoli Centralbank — Bruno Collection

Manual / exploratory API testing for the Centralbank Laravel API.

## Setup

1. Install [Bruno](https://www.usebruno.com/).
2. Open this folder (`apps/api/bruno`) as a collection in Bruno.
3. Select the `local` environment.
4. Run the API: `cd apps/api && php artisan serve`.
5. Seed the database (or use existing data) to get a user `startcode` and an amusement `api_key`. Set them as env vars:
   - `startcode`: the seeded user's startcode (UUID).
   - `apiKey`: an amusement's `api_key` (UUID).

## Flow

The requests are sequenced so you can run them top-to-bottom:

1. **Activate** — exchanges `startcode` for `access_key`. Captured into env.
2. **Login** *(optional)* — verifies the access_key returns the user profile.
3. **Get User** — fetches the authenticated user.
4. **Issue Identity Token** — captures `identity_token` into env.
5. **Show Identity Token** — resolves the token to user info.
6. **Store Transaction** — pays the amusement; on success captures `transactionId`.
7. **Payout** — refunds (winnings) from the amusement to the user.
8. **List Stamps** — current stamps + total VP.
9. **Get Leaderboard** — final rankings.
10. **Settle** *(admin only)* — closes the event.

After **Settle**, further `/transactions` and `/payout` calls return 409. The
collection includes those as separate "after-settle" requests if you want
to verify the lock.

## Env vars

Most variables are captured automatically by post-response scripts. You
only need to set `startcode` and `apiKey` manually.

## Gitignore

Bruno writes session state to `apps/api/bruno/.bruno/`. Add to root
`.gitignore`:

```
apps/api/bruno/.bruno/
```
