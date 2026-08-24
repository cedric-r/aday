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
| `DB_PATH` | Yes | Relative path to SQLite file. Default: `data/aday.sqlite`. Directory must be writable. |
| `SMTP_FROM` | Yes | Sender address for admin notification emails |
| `ADMIN_EMAIL` | Yes | Destination for registration validation emails |

> **Special characters in `.env` values**: If any value contains `;`, `#`, `=`, `{`, or `}`, wrap it in double quotes:  
> `APP_SECRET="my-secret#value;here"`

**`php.ini` requirements** (cannot be set in PHP code):
```ini
upload_max_filesize = 16M
post_max_size       = 17M
```

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

The deployment target for this project is `C:\Users\cedric.raguenaud\Downloads\adaydemo\`.

Files to copy:

```
api/              PHP API endpoints
lib/              PHP libraries
config/           PHP config files
migrations/       Migration scripts
scripts/          CLI scripts
setup.php         First-run admin setup
router.php        PHP built-in server entry point
dist/             Built React SPA (from npm run build)
uploads/          Photo storage (create if absent; must be writable)
data/             SQLite database (create if absent; must be writable)
.env              Environment config (NOT .env.example)
composer.json
composer.lock
vendor/           PHP dependencies (or run composer install on target)
```

### Step-by-Step

**1. Build the React app**

```bash
npm run build
```

**2. Copy project files to deployment target**

```bash
# Windows (robocopy)
robocopy . C:\Users\cedric.raguenaud\Downloads\adaydemo ^
  /E /XD .git node_modules .specifications coverage src ^
  /XF .env.example *.md tsconfig*.json vite.config.ts eslint.config.js ^
       package*.json phpstan.neon phpunit.xml

# Or manually copy the required directories listed above
```

**3. Configure `.env` on the target**

```bash
cd C:\Users\cedric.raguenaud\Downloads\adaydemo
copy .env.example .env
# Edit .env — set APP_SECRET, SMTP_FROM, ADMIN_EMAIL, APP_ENV=production
```

**4. Install PHP dependencies on target** (if `vendor/` not copied)

```bash
composer install --no-dev --optimize-autoloader
```

**5. Create writable directories**

```bash
mkdir data
mkdir uploads
mkdir logs
```

**6. Run migrations**

```bash
php migrations/run.php
```

**7. Run first-admin setup**

```bash
php -S localhost:8765 router.php
# Open http://localhost:8765/setup.php
```

**8. Start the server**

```bash
php -S localhost:8765 router.php
```

---

### `router.php` — PHP Built-In Server Entry Point

`router.php` is the entry point for `php -S`. It:

1. If the URL starts with `/api/` or matches a known PHP file (`/setup.php`, etc.) → routes to the PHP file.
2. If the URL matches a file in `dist/` (JS, CSS, images) → serves it directly.
3. Otherwise → serves `dist/index.html` (SPA fallback for React Router client-side routing).

```php
<?php
// router.php — simplified reference
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (str_starts_with($uri, '/api/') || in_array($uri, ['/setup.php', '/router.php'])) {
    return false; // let PHP built-in server handle
}

if ($uri !== '/' && file_exists(__DIR__ . '/dist' . $uri)) {
    return false; // serve static asset
}

include __DIR__ . '/dist/index.html';
```

---

### Production Web Server (nginx / Apache)

For production deployments replacing the PHP built-in server, configure your web server to:

1. **Route `/api/*` requests to PHP** via FastCGI / php-fpm.
2. **Serve `/uploads/*` as static files** from the `uploads/` directory.
3. **Serve `/dist/assets/*` as static files** (long cache headers recommended).
4. **SPA fallback**: any other path not matching a real file → serve `dist/index.html`.

**nginx example**:
```nginx
server {
    listen 443 ssl;
    root /var/www/adaydemo;

    # Static assets with cache
    location /dist/assets/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Uploaded photos
    location /uploads/ {
        try_files $uri =404;
    }

    # PHP API
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # SPA fallback
    location / {
        try_files $uri /dist/index.html;
    }
}
```

**Apache (`.htaccess`)**:
```apache
RewriteEngine On

# Skip real files and API
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_URI} !^/api/
RewriteCond %{REQUEST_URI} !^/uploads/

# SPA fallback
RewriteRule ^ /dist/index.html [L]
```
