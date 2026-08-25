# Development Guide

## Project Directory Tree

```
aday/
├── api/                    PHP API endpoints (one file per route group)
│   ├── admin/              Admin-only endpoints
│   │   ├── export.php
│   │   ├── settings.php
│   │   ├── submissions.php
│   │   ├── users.php
│   │   ├── validate.php
│   │   └── wrapup.php
│   ├── captcha-question.php
│   ├── login.php
│   ├── logout.php
│   ├── me.php
│   ├── photographers.php
│   ├── photos.php
│   ├── register.php
│   ├── stats.php
│   └── status.php
├── config/                 PHP configuration
│   ├── captcha.php         50-question pool
│   ├── db.php              PDO singleton (WAL mode)
│   ├── env.php             .env loader + env() helper
│   └── session.php         Cookie flags (Secure, HttpOnly, SameSite=Lax),
│                           session idle timeout, Origin/Sec-Fetch-Site check
├── data/                   SQLite database (gitignored, must be writable)
├── dist/                   Built React SPA (gitignored — output of npm run build)
├── docs/                   This documentation
├── lib/                    PHP library classes
│   ├── Auth.php
│   ├── Exif.php
│   ├── Exporter.php
│   ├── FileUpload.php
│   ├── Mailer.php
│   ├── Response.php
│   ├── ResponseException.php
│   ├── Thumbnails.php
│   ├── UploadException.php
│   ├── Validate.php
│   ├── WindowCheck.php
│   └── WrapUp.php
├── logs/                   Runtime logs (gitignored; logs/mail.log)
├── migrations/             Schema migration scripts
│   ├── 001_create_users.php
│   ├── 003_create_photos.php
│   ├── 004_add_photo_fields.php
│   ├── 005_add_hidden.php
│   ├── 006_add_validation_nonce.php
│   └── run.php
├── public/                 Static assets copied by Vite
│   ├── documentyourlife.png  Site logo (header + empty state)
│   └── assets/logo.png       Small logo asset
├── scripts/                CLI utility scripts
│   ├── reset_admin.php
│   └── send_wrapup.php
├── src/                    React + TypeScript source
│   ├── App.tsx             Route definitions
│   ├── main.tsx            Entry point (providers)
│   ├── theme.ts            MUI theme
│   ├── components/         Shared + admin UI components
│   ├── context/            AuthContext
│   ├── pages/              Page-level components
│   ├── schemas/            Zod validation schemas
│   ├── store/              RTK store + baseApi
│   └── setupTests.ts       Vitest global setup
├── tests/                  PHPUnit test files
├── uploads/                Photo storage (gitignored; must be writable)
├── vendor/                 Composer dependencies (gitignored)
├── .env.example
├── .env                    (gitignored)
├── .htaccess               Apache SPA routing, security, upload limits (prod)
├── composer.json
├── embed.php               /embed SPA shell (cache-busted, prod + dev)
├── eslint.config.js
├── index.html              Vite HTML template
├── package.json
├── photo-meta.php          Server-rendered og/twitter tags for /photos/:id
├── phpstan.neon
├── phpunit.xml
├── router.php              PHP built-in server router (dev only)
├── setup.php               One-time admin bootstrap
├── tsconfig.json
└── vite.config.ts
```

---

## Running Tests

### PHP — PHPUnit

```bash
# All tests
vendor/bin/phpunit

# Specific test file
vendor/bin/phpunit tests/RegisterTest.php

# With verbose output
vendor/bin/phpunit --testdox

# With coverage (HTML report — requires Xdebug or pcov)
vendor/bin/phpunit --coverage-html coverage/php
```

Test environment variables are set in `phpunit.xml`:
- `APP_ENV=testing`
- `DB_PATH=:memory:` (in-memory SQLite — each test suite starts fresh)
- `APP_SECRET=test-secret-key-for-phpunit-0123456789abcdef` (≥ 32 bytes, matching the production fail-closed requirement)

Coverage target: **85% lines and branches** for `config/`, `lib/`, `api/`.

### React — Vitest

```bash
# Run all tests once
npm test

# Watch mode
npm run test:watch

# With coverage report (lcov + terminal summary)
npm run test:coverage
```

Coverage thresholds (enforced in `vite.config.ts`):
- Lines: **85%**
- Branches: **85%**

Coverage report written to `coverage/lcov-report/index.html`.

### PHPStan — Static Analysis

```bash
vendor/bin/phpstan analyse
```

Level: **6** (configured in `phpstan.neon`).  
Paths analysed: `config/`, `lib/`, `api/`, `migrations/`.

Fix PHPStan findings before committing — CI treats any error as a failure.

### TypeScript Type Check

```bash
npm run typecheck
```

Strict mode is enabled in `tsconfig.json`. No `any` types, no `@ts-ignore` suppressions.

### ESLint

```bash
npm run lint
```

Zero warnings tolerated (`--max-warnings 0`).

---

## Adding a New API Endpoint

1. Create `api/{name}.php` (or `api/admin/{name}.php` for admin routes).
2. Include session config and auth guard at the top:

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/session.php';

session_start();

use lib\Auth;
use lib\Response;

$user = Auth::requireValidated(); // or Auth::requireAdmin()

// Route by method
match ($_SERVER['REQUEST_METHOD']) {
    'GET'  => handleGet($user),
    'POST' => handlePost($user),
    default => Response::error('Method not allowed', 405),
};
```

3. Use `Response::json()`, `Response::created()`, `Response::error()` for output.
4. Add route to `router.php` if the file is outside `api/`.
5. Write PHPUnit tests in `tests/` covering:
   - Happy path
   - Auth guard (unauthenticated → 401/403)
   - Validation failures
   - Edge cases

---

## Adding a New React Page

1. Create `src/pages/MyPage.tsx`:

```tsx
import { Typography, Box } from '@mui/material';

export default function MyPage() {
  return (
    <Box sx={{ p: 3 }}>
      <Typography variant="h4">My Page</Typography>
    </Box>
  );
}
```

2. Add the route to `src/App.tsx`:

```tsx
import MyPage from './pages/MyPage';
// ...
<Route path="/my-page" element={<MyPage />} />
```

3. Add a menu item in `src/components/Nav.tsx` if needed.

**MUI patterns:**
- Use `sx` prop with theme tokens for spacing and colour:
  ```tsx
  sx={{ p: 2, color: 'text.secondary', bgcolor: 'background.paper' }}
  ```
- Import components from `@mui/material`, not from sub-paths.
- Use `theme.ts` for the colour palette — do not hardcode hex values in components.

**Path alias:** `@/` maps to `src/`:
```tsx
import { useAuth } from '@/context/AuthContext';
```

---

## Adding a New React Component

1. Create `src/components/MyComponent.tsx`.
2. Write `src/components/MyComponent.test.tsx` alongside it:

```tsx
import { render, screen } from '@testing-library/react';
import MyComponent from './MyComponent';

describe('MyComponent', () => {
  it('renders correctly', () => {
    render(<MyComponent label="hello" />);
    expect(screen.getByText('hello')).toBeInTheDocument();
  });
});
```

3. Export from the component file directly — no barrel index files required unless the component has sub-files.

---

## TDD Convention

All new PHP files and React components follow the **red → green → refactor** cycle:

1. **Red commit** — write a failing test for the behaviour you intend to add.  
   Commit message format: `test: [failing] <what the test checks>`

2. **Green commit** — write the minimum implementation to make the test pass.  
   Commit message format: `feat: <what was implemented>`

3. **Refactor** — clean up without breaking tests; commit with `refactor: ...`.

For existing code without tests (Path B), add characterisation tests first:
1. Commit characterisation tests that verify current behaviour.
2. Then make your change + new TDD tests.

The red commit must appear **before** the implementation commit in git history.
