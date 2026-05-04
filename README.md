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
