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

## Features

- **Live event feed** — auto-refreshes every minute; cursor pagination, **Lightbox** with keyboard nav, **slideshow** (space ▶/❚❚) and **shareable deep links** (`/?photo=N`)
- **"Follow the sun" hour filter** — pick a local hour (00–23) and see what every photographer was doing at that moment in *their* timezone, ordered east → west
- **Random photo** 🎲 — one-click "surprise me" from the public archive
- **Per-photo pages** (`/photos/:id`) with **OpenGraph/Twitter cards** for social sharing
- **Grid ⇄ Cards view** toggle with generated **thumbnails** (GD, square 320px)
- **Photos "so far" stats strip + 48 h posting pulse + photographer/timezone breadth** (`/api/stats.php`)
- **Admin highlights** (⭐ strip), **hide/unlist** (👁), metadata **CSV/JSON/ZIP export**
- **EXIF capture** (make/model/focal/aperture/shutter/ISO), optional **gear** field for film shooters, and an optional **photographer bio**
- **Self-service download** — participants grab a ZIP of their own photos from their profile page (`/api/my-export.php`)
- **Self-service delete** — participants remove their own photos from their profile page (`DELETE /api/photos.php?id=N`, ownership-enforced)
- **Substack / embed** — header-less `/embed` page (per-photographer filter, dark theme, admin-copy iframe snippet for iframe-friendly sites). On Substack itself (no raw iframes allowed) use the **card-style link previews** from the per-photo pages (`og:image`/`og:title`/`og:description`)
- **Registration validation emails** to DB admin accounts (single-use HMAC links, 72h expiry)
- **Approval confirmation email** to the participant the moment an admin validates them (link, admin edit, or admin create)
- **Post-event wrap-up email** to participants (admin button or cron `scripts/send_wrapup.php`)
- **Admin broadcast email** — one-off messages (event reminders) to validated participants or all registered users, recipient-count preview + confirm-before-send (`/api/admin/email.php`)
- **Security hardening** — prepared statements everywhere, http(s)-only URL validation, upload dimension caps, session fixation/CSRF defences, clickjacking CSP (embedding allowed only on `/embed`), fail-closed validation tokens

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
# Edit .env — set APP_SECRET (≥ 32 random bytes; never run it in production
# with the placeholder — validation links fail closed), SMTP_FROM, APP_URL.
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