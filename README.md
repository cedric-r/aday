# Document Your Life

A dynamic photo publishing system for a single 1-day event — photographers across timezones post throughout their local day; visitors see a live, auto-refreshing feed.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Vanilla PHP 8.3+, SQLite 3 (WAL mode) |
| Frontend | React 18, TypeScript strict, MUI v5, Zod, React Hook Form, RTK Query, React Router v7 |
| Testing | PHPUnit 11, PHPStan level 6 (PHP) · Vitest 4 + Testing Library (React, 85% coverage threshold) |
| Build | Vite 8, npm |
| Email | PHPMailer (localhost:25 open relay) |

## Prerequisites

- PHP 8.3+ with extensions: `pdo_sqlite`, `zip`, `fileinfo`, `mbstring`
- Composer
- Node 18+

## Quick Start (local dev)

```bash
# 1. Clone / copy project
cd /path/to/project

# 2. Install PHP dependencies
composer install

# 3. Install Node dependencies
npm install

# 4. Configure environment
cp .env.example .env
# Edit .env — set APP_SECRET, SMTP_FROM, ADMIN_EMAIL.
# For local HTTP, set APP_ENV=development (disables the Secure cookie flag).

# 5. Run database migrations
php migrations/run.php

# 6. Build React frontend (required before the dev server starts)
npm run build

# 7. Start the PHP development server (SPA routing handled by router.php)
php -S localhost:8765 router.php
```

Visit **http://localhost:8765** to see the app.

> The first admin account is created via the one-time setup page at
> **http://localhost:8765/setup.php** (choose any username/password). It locks
> permanently after the first admin is created.

## Production Deployment

The production target for this project is **https://aday.photoni.st** (Apache on vps4, `/var/www/photoni.st/aday`).

- The React SPA is built locally (`npm run build`) and deployed together with the PHP files.
- Apache routing (SPA fallback, `/api` → PHP, static `/assets`), security rules, and upload limits are handled by the `.htaccess` in the repo root — no extra server config is needed for the site itself.
- `.env` must set `APP_ENV=production` and an absolute `DB_PATH` on the server.

See [`docs/setup.md`](docs/setup.md) for the full deployment walkthrough.

## Documentation

See [`docs/`](docs/) for full documentation:

| File | Contents |
|---|---|
| [`docs/setup.md`](docs/setup.md) | Prerequisites, `.env` reference, migrations, dev server, production deployment |
| [`docs/architecture.md`](docs/architecture.md) | System overview, directory structure, request flow, auth |
| [`docs/api-reference.md`](docs/api-reference.md) | All API endpoints with request/response shapes |
| [`docs/user-guide.md`](docs/user-guide.md) | Registration, posting photos, browsing the feed |
| [`docs/admin-guide.md`](docs/admin-guide.md) | Admin panel, user management, submissions, export |
| [`docs/development.md`](docs/development.md) | Running tests, coverage, PHPStan, conventions, TDD workflow |