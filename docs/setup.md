# Setup & Deployment

## Prerequisites

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.3+ | Extensions: `pdo_sqlite`, `zip`, `fileinfo`, `mbstring` |
| Composer | 2.x | `composer.json` in project root |
| Node.js | 18+ | npm included |

Check extensions are enabled:
```bash
php -m | grep -E 'pdo_sqlite|zip|fileinfo|mbstring'
```

---

## Installation

```bash
# Clone or copy project
cd C:\path\to\aday

# Install PHP dependencies
composer install

# Install Node dependencies
npm install
```

---

## `.env` Configuration

Copy the example and edit:

```bash
cp .env.example .env
```

| Variable | Required | Description |
|---|---|---|
| `APP_SECRET` | **Yes** | Long random string used for HMAC validation tokens. Generate: `openssl rand -hex 32` |
| `APP_ENV` | Yes | `production` (Secure cookie) or `development` (no Secure flag for local HTTP) |
| `DB_PATH` | Yes | SQLite file path. Relative paths resolve against the **project root** (e.g. `data/aday.sqlite` → `<project>/data/aday.sqlite`). Absolute paths also work. Default: `data/aday.sqlite`. Directory must be writable. |
| `SMTP_FROM` | Yes | Sender address for admin notification emails |
| `ADMIN_EMAIL` | Yes | Destination for registration validation emails |

> **Special characters in `.env` values**: If any value contains `;`, `#`, `=`, `{`, or `}`, wrap it in double quotes:  
> `APP_SECRET="my-secret#value;here"`

**`php.ini` requirements** (cannot be set in PHP code):
```ini
upload_max_filesize = 16M
post_max_size       = 17M
```

> Under Apache these can also be set per-directory in `.htaccess` (as done by
> the repo's `.htaccess`), so no global `php.ini` edit is needed on the server.

---

## Database Initialisation

```bash
php migrations/run.php
```

This creates `data/aday.sqlite` and runs all migrations in order:
- `001_create_users.php` — `users` + `settings` tables
- `003_create_photos.php` — `photos` table + indexes

The `data/` directory is created automatically if absent. It must be writable by the web server.

---

## First Admin (One-Time Setup)

After running migrations, visit `/setup.php` to create the first admin account:

```
http://localhost:8765/setup.php
```

- Choose a username and password.
- The page locks permanently after submission (`setup_complete` row written to `settings`).
- To reset (if credentials are lost): `php scripts/reset_admin.php` (CLI only).

---

## Build React Frontend

```bash
npm run build
```

Output is written to `dist/`. The `dist/index.html` is the SPA entry point served by `router.php`.

---

## Running the Development Server

```bash
php -S localhost:8765 router.php
```

`router.php` routes API requests to `api/*.php` and all other paths to `dist/index.html`.

> **Local HTTP note**: Set `APP_ENV=development` in `.env` to disable the `Secure` cookie flag when running on plain `http://`.

---

## Running Tests

See [`docs/development.md`](development.md) for full test commands.

Quick reference:
```bash
# PHP tests
vendor/bin/phpunit

# React tests
npm test

# React coverage
npm run test:coverage

# PHPStan
vendor/bin/phpstan analyse
```

---

## Deployment

### Overview

The production target for this project is **https://aday.photoni.st** — Apache
on vps4 (`192.168.233.9`), site root `/var/www/photoni.st/aday` (a
`webdeploy`-managed directory).

Files on the server (this is also the deploy payload):

```
api/              PHP API endpoints
lib/              PHP libraries
config/           PHP config files
migrations/       Migration scripts
scripts/          CLI scripts
setup.php         First-run admin setup
dist/             Built React SPA (from npm run build; not in git — built locally)
.htaccess         Apache routing/security/upload limits (SPA fallback)
.env              Environment config (NOT .env.example; created on the server)
composer.json
composer.lock
vendor/           PHP dependencies (composer install on the server)
data/             SQLite database (create if absent; must be writable)
uploads/          Photo storage (create if absent; must be writable)
logs/             Runtime logs (create if absent; must be writable)
```

Development-only files (`src/`, `tests/`, `docs/`, `public/`, `node_modules/`,
`package*.json`, `tsconfig*`, etc.) are **not** deployed.

### Step-by-Step (deployment host)

**1. Build the React app** (requires Node; the vps4 server has no Node):

```bash
npm ci
npm run build
```

**2. Stage the payload** (from the git tree + the fresh build):

```bash
rm -rf /tmp/aday-deploy && mkdir -p /tmp/aday-deploy
git archive HEAD | tar -x -C /tmp/aday-deploy
rsync -a --quiet dist/ /tmp/aday-deploy/dist/
# remove dev-only files (src, tests, docs, public, node_modules, package*, ...)
# see docs/development.md or the deploy notes for the exact trim list
```

**3. Ship and deploy via `webdeploy`** (no root needed — the `webdeploy` group
mechanism on vps4, see skill `webdeploy`):

```bash
tar -C /tmp/aday-deploy -czf /tmp/aday-deploy.tgz .
scp /tmp/aday-deploy.tgz cedric@192.168.233.9:/tmp/webdeploy.tgz
ssh cedric@192.168.233.9 "rm -rf /tmp/webdeploy && mkdir -p /tmp/webdeploy && \
    tar -C /tmp/webdeploy -xzf /tmp/webdeploy.tgz && \
    ~/bin/webdeploy.sh aday /tmp/webdeploy --reload"
```

`webdeploy.sh` installs files into `/var/www/photoni.st/aday` as the `cedric`
user (setgid `webdeploy`), then gracefully reloads Apache. It installs new
files but does **not** prune removed ones — if stale build assets accumulate
under `dist/`, sync the exact set with `rsync --delete` over the same
mechanism.

**4. Configure `.env` on the server** (first deploy only):

```bash
cd /var/www/photoni.st/aday
cat > .env <<EOF
APP_SECRET=<openssl rand -hex 32>
APP_ENV=production
DB_PATH=/var/www/photoni.st/aday/data/aday.sqlite
SMTP_FROM=noreply@aday.photoni.st
ADMIN_EMAIL=<event-owner-email>
EOF
chmod 640 .env
```

> Use an **absolute** `DB_PATH` on the server. Relative paths are resolved
> against the project root by `config/db.php`, which is safe under mod_php —
> but an absolute path removes any ambiguity.

**5. Install PHP dependencies on the server** (first deploy only):

```bash
cd /var/www/photoni.st/aday
composer install --no-dev --optimize-autoloader --no-interaction
```

**6. Create writable directories + run migrations** (first deploy only):

```bash
mkdir -p data uploads logs
chmod 2775 data uploads logs
php migrations/run.php
chmod 664 data/aday.sqlite   # group writable so www-data can write (WAL)
```

**7. Create the first admin** (first deploy only):

```bash
curl -sk -X POST -d 'username=<admin>' --data-urlencode 'password=<strong-password>' \
     https://aday.photoni.st/setup.php
```

The page locks permanently after the first admin. To reset:
```bash
php scripts/reset_admin.php   # CLI only
```

---

### Production Web Server (Apache)

Production uses Apache **mod_php** with the repo-root `.htaccess` — no vhost
edits needed for the app itself. The vhost (`aday.photoni.st`) sets
`DocumentRoot /var/www/photoni.st/aday` and serves the Let's Encrypt cert.

The `.htaccess` in the repo root handles everything:

1. **Upload limits** — `php_value upload_max_filesize 16M` + `post_max_size 17M`
   (defaults in `php.ini` are 2M/8M).
2. **Security** — denies `.env`, `composer.*`, `data/`, `logs/`, `vendor/`,
   `src/`, `tests/`, `api/data/`, etc.
3. **API + setup.php** — reaches mod_php directly (`RewriteRule .* - [L]`).
4. **Static aliases** — `/assets/*` → `/dist/assets/*`, `/documentyourlife.png`
   → `/dist/documentyourlife.png`.
5. **SPA fallback** — any other path not matching a real file →
   `/dist/index.html` (with `DirectoryIndex /dist/index.html`).

nginx equivalent (for reference) if the site ever moves:

```nginx
server {
    listen 443 ssl;
    root /var/www/aday;

    location /dist/assets/ { expires 1y; add_header Cache-Control "public, immutable"; }
    location /uploads/     { try_files $uri =404; }
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    location / { try_files $uri /dist/index.html; }
}
```

---

### `router.php` — PHP Built-In Server Entry Point (dev only)

`router.php` is the entry point for local development with `php -S`:

1. If the URL starts with `/api/` or matches a known PHP file (`/setup.php`,
   `/router.php`) → routes to the PHP file.
2. If the URL matches a file in `dist/` (JS, CSS, images) → serves it directly.
3. Otherwise → serves `dist/index.html` (SPA fallback for React Router).

```bash
php -S localhost:8765 router.php
```

> Production does **not** use `router.php` — Apache + `.htaccess` replace it.
