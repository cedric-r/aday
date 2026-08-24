# A Day In The Life

A dynamic photo publishing system for a single 1-day event — photographers across timezones post throughout their local day; visitors see a live, auto-refreshing feed.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Vanilla PHP 8.3, SQLite 3 (WAL mode) |
| Frontend | React 18, TypeScript strict, MUI v5, Zod, React Hook Form, RTK Query, React Router v7 |
| Testing | PHPUnit 11, PHPStan level 6 (PHP) · Vitest 4 + Testing Library (React, 85% coverage threshold) |
| Build | Vite 8, npm |
| Email | PHPMailer (localhost:25 open relay) |

## Prerequisites

- PHP 8.3+ with extensions: `pdo_sqlite`, `zip`, `fileinfo`, `mbstring`
- Composer
- Node 18+

## Quick Start

```bash
# 1. Clone / copy project
cd /path/to/project

# 2. Install PHP dependencies
composer install

# 3. Install Node dependencies
npm install

# 4. Configure environment
cp .env.example .env
# Edit .env — set APP_SECRET, SMTP_FROM, ADMIN_EMAIL

# 5. Run database migrations
php migrations/run.php

# 6. Create first admin (one-time setup page)
# Open http://localhost:8765/setup.php in your browser

# 7. Build React frontend
npm run build

# 8. Start PHP development server
php -S localhost:8765 router.php
```

Visit **http://localhost:8765** to see the app.

## Demo Admin Credentials

| Username | Password |
|---|---|
| `admin` | `Admin1234!` |

## Documentation

See [`docs/`](docs/) for full documentation:

| File | Contents |
|---|---|
| [`docs/setup.md`](docs/setup.md) | Prerequisites, `.env` reference, migrations, server, deployment |
| [`docs/architecture.md`](docs/architecture.md) | System overview, directory structure, request flow, auth |
| [`docs/api-reference.md`](docs/api-reference.md) | All 19 API endpoints with request/response shapes |
| [`docs/user-guide.md`](docs/user-guide.md) | Registration, posting photos, browsing the feed |
| [`docs/admin-guide.md`](docs/admin-guide.md) | Admin panel, user management, submissions, export |
| [`docs/development.md`](docs/development.md) | Running tests, coverage, PHPStan, conventions, TDD workflow |
