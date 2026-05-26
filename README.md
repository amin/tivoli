<div align="center">

<img src="apps/web/src/assets/loopland_transparent.svg" alt="Loopland" width="160" />

# 🎡 Loopland

**Your digital amusement park** — play games, earn stamps, trade them for currency, and top the leaderboard.

[![License: MIT](https://img.shields.io/badge/License-MIT-22c55e.svg)](LICENSE)
![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)
![React 19](https://img.shields.io/badge/React-19-61DAFB?logo=react&logoColor=black)
![TypeScript](https://img.shields.io/badge/TypeScript-3178C6?logo=typescript&logoColor=white)
![Vite 8](https://img.shields.io/badge/Vite-8-646CFF?logo=vite&logoColor=white)
![Tailwind 4](https://img.shields.io/badge/Tailwind-4-06B6D4?logo=tailwindcss&logoColor=white)
![Turborepo](https://img.shields.io/badge/Turborepo-EF4444?logo=turborepo&logoColor=white)

</div>

---

A digital amusement park. Visitors activate an account, browse and play the
park's **amusements** (games and attractions), earn **stamps** by playing, trade
stamp sets for in‑game **currency**, vote for their favourites, and compete on an
end‑of‑showdown **leaderboard**. Amusements are third‑party apps that plug into
the park's "Centralbank" API to authenticate players and award stamps.

This is a [Turborepo](https://turborepo.dev) monorepo with two apps:

| App | Stack | Role |
|-----|-------|------|
| `apps/api` | Laravel 13 · PHP 8.4 · Sanctum · SQLite | The **Centralbank** REST API — users, groups, amusements, transactions, stamps, exchanges, votes, identity tokens, leaderboard |
| `apps/web` | React 19 · TypeScript · Vite 8 · Tailwind 4 · React Router 7 | The park's single‑page web app (the player‑facing storefront) |

The full API contract lives in [`docs/centralbank-api.yaml`](docs/centralbank-api.yaml) (OpenAPI 3).

## ⚙️ How it works

- **Players** activate with a first name + startcode (`POST /activate`), which
  returns a permanent `access_key`. They then sign in with that key, or use a
  throwaway **guest** session (`POST /login/guest`).
- **Players belong to a group**, and can register their own amusements
  (`POST /amusements`) under that group — each new amusement is issued a unique
  `api_key` (shown only once) so it can start awarding stamps to players.
- **Amusements** authenticate by sending their `api_key` in the request body of
  the transaction endpoints. To act on behalf of a player they also pass a
  short‑lived **identity token** that the web app mints (`POST /identity-tokens`)
  and forwards in the amusement's URL — so a game can greet the player and award
  stamps without ever seeing their credentials.
- The SPA authenticates against the API with **Sanctum cookie sessions** (CSRF
  token + credentialed fetch), so the two apps run on separate origins in dev.

## 🎟️ Stamps, exchanges & scoring

Every stamp has an **animal** (one of five) and optionally a **metal**
(silver / gold / platinum). Players trade matching sets for currency via
`POST /exchanges`:

| Set | Composition | Payout |
|-----|-------------|--------|
| `metal` | one silver + one gold + one platinum | 10 |
| `animal` | one stamp of each of the five animals | 7 |
| `non_metal` | three non‑metal stamps | 3 |

Unexchanged stamps also count toward **victory points** on the end‑game
leaderboard: each metal set is worth **40 VP**, each animal set **25 VP**, and
leftover loose stamps score by triangular number (`n·(n+1)/2`). A player whose
group runs a net‑negative amusement forfeits their VP.

## 🏆 Game lifecycle (admin)

Admins — members of a group flagged `is_admin` — run the end‑of‑showdown flow
(exposed in the web app's Admin panel):

- `GET /leaderboard` — returns `money_leaders` (currency balance),
  `vp_leaders` (victory points), and `vote_winners` (amusements ranked by votes).
- `POST /settle` — closes out unsettled amusements and reclaims each amusement's
  total payouts evenly from its owning group's members.
- `POST /reset` — wipes stamps, votes, transactions, and identity tokens to start
  a fresh round.

## 📋 Requirements

- **Node.js** 20+
- **pnpm** 10+ (pinned via `packageManager`; install with `npm i -g pnpm` or Corepack)
- **PHP** 8.4+ with extensions: `pdo`, `pdo_sqlite`, `sqlite3`, `pdo_pgsql`, `mbstring`, `xml`, `intl`
  (SQLite is the default for local dev; PostgreSQL is used in production)
- **Composer** 2+

## 🛠️ Setup

```bash
pnpm install                      # install JS deps for the whole workspace

cd apps/api
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed        # creates schema + seeds stamptypes, users, amusements
```

Both apps ship a `.env.example` to copy from (`apps/api/.env.example`,
`apps/web/.env.example`). The API's is preconfigured for local SPA auth: it
allows the Vite dev origin (`localhost:5173`) as a Sanctum stateful domain and
CORS origin.

### Environment variables

| App | Variable | Purpose |
|-----|----------|---------|
| web | `VITE_API_URL` | Base URL of the Centralbank API. Unset in dev (defaults to `http://localhost:8000`); set to the deployed API origin in production. |
| api | `APP_URL` | Public URL the API is served from. |
| api | `DB_CONNECTION` | `sqlite` for local dev, `pgsql` in production (with the usual `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD`). |
| api | `SANCTUM_STATEFUL_DOMAINS` | Comma‑separated hosts that receive stateful session cookies — must include the web app's origin (e.g. `localhost:5173`). |
| api | `CORS_ALLOWED_ORIGINS` | Comma‑separated origins allowed to send credentialed requests. No wildcard when using cookies. |
| api | `SESSION_DOMAIN` | Cookie domain; `null` for local dev, the shared parent domain in production. |

## 🚀 Running

From the repo root, Turborepo starts both apps at once:

```bash
pnpm dev      # API at http://localhost:8000  +  web at http://localhost:5173
pnpm build    # production build of all apps
pnpm lint     # lint all apps
pnpm test     # run all app test suites
```

- Web app: <http://localhost:5173> — talks to the API at `http://localhost:8000`
  (override with `VITE_API_URL`).
- API: <http://localhost:8000> — `GET /` returns a status payload.

You can also run a single app via its own scripts, e.g. `pnpm --filter web dev`
or, inside `apps/api`, `php artisan serve` and `php artisan test`.

## 🗂️ Layout

```
loopland/
├── apps/
│   ├── api/        # Laravel "Centralbank" API
│   └── web/        # React + Vite SPA
├── docs/           # OpenAPI spec (centralbank-api.yaml)
├── turbo.json
├── pnpm-workspace.yaml
└── package.json    # root turbo scripts
```

## 📄 License

MIT — see [LICENSE](LICENSE).
