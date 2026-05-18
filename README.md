# Tivoli (Centralbank)

Turborepo monorepo. The only app for now is `apps/api`, a Laravel 12 application.

## Requirements

- **Node.js** 20+
- **pnpm** 10+ (the repo pins `packageManager` in `package.json`; install with `npm install -g pnpm` or via Corepack)
- **PHP** 8.2+ with the following extensions: `pdo`, `pdo_sqlite`, `sqlite3`, `mbstring`, `xml`, `intl`
- **Composer** 2+

## Layout

```
tivoli/
├── apps/
│   └── api/        # Laravel application
├── package.json    # root, turbo scripts
├── pnpm-workspace.yaml
└── turbo.json
```

## Setup

```bash
pnpm install
cd apps/api
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
```

## Running

From the repo root:

```bash
pnpm dev      # Vite (5173) + php artisan serve (8000), via concurrently
pnpm build    # Vite production build
```

App runs at <http://localhost:8000>. Vite HMR runs at <http://localhost:5173>.

## API

Base URL: `http://localhost:8000/api`

### Stamps

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/stamps?user_id=` | List a user's unexchanged stamps |
| `POST` | `/stamps` | Generate a new stamp for a user |

**POST `/stamps`** body:
```json
{ "user_id": 1 }
```

### Exchanges

Trade a set of stamps for in-game currency.

| Method | Path | Description |
|--------|------|-------------|
| `POST` | `/exchanges` | Exchange a stamp set for currency |

**POST `/exchanges`** body:
```json
{
  "user_id": 1,
  "set_type": "metal",
  "stamp_ids": [1, 2, 3]
}
```

Set types and their exchange rates:

| `set_type` | Stamps required | Payout |
|------------|-----------------|--------|
| `metal` | 3 (one silver, one gold, one platinum) | 10 |
| `animal` | 5 (one of each animal) | 7 |
| `non_metal` | 3 (3 different animals, no metal stamps) | 3 |

### Victory Points

| Method | Path | Description |
|--------|------|-------------|
| `GET` | `/victory-points?user_id=` | Calculate a user's current victory point total |

Returns a breakdown of VP from metal sets (40 VP each), animal sets (25 VP each), and loose metal stamps (triangular number scoring).
