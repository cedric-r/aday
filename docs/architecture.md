# Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────┐
│                          Browser                            │
│  React 18 SPA (MUI, Zod, RTK Query, React Router v7)       │
│  served from  dist/index.html  via router.php               │
└────────────────────┬───────────────────────────────────────┘
                     │ fetch / FormData (HTTPS + session cookie)
┌────────────────────▼───────────────────────────────────────┐
│                    PHP 8.3 API layer                        │
│   api/*.php   api/admin/*.php                               │
│   lib/  (Auth · Mailer · FileUpload · WindowCheck ·        │
│           Exporter · Response · ResponseException)          │
│   config/  (db · env · session · captcha)                   │
└────────────────────┬───────────────────────────────────────┘
                     │ PDO
┌────────────────────▼───────────────────────────────────────┐
│              SQLite 3 — WAL mode                            │
│              data/aday.sqlite                               │
└────────────────────────────────────────────────────────────┘

Static files:
  /uploads/{username}/{filename}  served directly by web server
  /dist/assets/*                  JS/CSS bundles from Vite build
```

---

## Backend Structure

```
api/
  captcha-question.php   GET  — random captcha question (session)
  register.php           POST — new user registration
  login.php              POST — authenticate; create session
  logout.php             POST — destroy session
  me.php                 GET  — current session user (always 200)
  photos.php             GET/POST — feed + photo upload
  photographers.php      GET  — index list or single profile
  status.php             GET  — event window status
  admin/
    users.php            GET/POST/PUT/DELETE — user management
    validate.php         GET  — approve user via HMAC email link
    settings.php         GET/POST — event date
    submissions.php      GET/DELETE — all submissions
    export.php           GET  — stream ZIP archive

lib/
  Auth.php               requireAdmin() / requireValidated() / currentUser()
  Mailer.php             PHPMailer wrapper (localhost:25, no auth)
  FileUpload.php         MIME validation + secure file save
  WindowCheck.php        Timezone-aware posting window check
  Exporter.php           ZipArchive builder — per-photographer subfolders
  Response.php           json() / error() / created() helpers
  ResponseException.php  Exception carrying HTTP status + message
  UploadException.php    Thrown by FileUpload on validation failure

config/
  db.php        PDO singleton — opens data/aday.sqlite, WAL mode
  env.php       Loads .env via parse_ini_file; exposes env() helper
  session.php   Sets cookie params (Secure, HttpOnly, SameSite=Lax)
  captcha.php   Returns array of 50 ['q','a'] question pairs

migrations/
  001_create_users.php   users + settings tables
  003_create_photos.php  photos table + indexes
  run.php                CLI runner — executes all migrations in order

scripts/
  reset_admin.php        CLI only — deletes setup_complete so setup.php can re-run

setup.php                One-time admin bootstrap page (locks after first use)
router.php               PHP built-in server entry point — serves dist/ SPA or routes API
```

---

## Frontend Structure

```
src/
  main.tsx               App entry — wraps in AuthProvider + MUI ThemeProvider + Router
  App.tsx                Route definitions (React Router v7)
  theme.ts               MUI createTheme — GitHub-inspired colour palette

  pages/
    HomePage.tsx          / — live photo feed
    IndexPage.tsx         /index — A–Z photographer list
    PhotographerPage.tsx  /photographers/:username — individual profile
    RegisterPage.tsx      /register — registration form + captcha
    LoginPage.tsx         /login — login form
    PostPage.tsx          /post — photo upload form (protected)
    AdminPage.tsx         /admin — admin dashboard (admin-protected)
    NotFoundPage.tsx      * — 404

  components/
    Header.tsx            Logo + Nav
    Nav.tsx               Menu items (auth-aware via useAuth)
    PhotoCard.tsx         Single photo — image, name, description, timestamp
    PhotoFeed.tsx         Polling list; initial load + incremental fetch
    PostingWindowBanner   Open/closed indicator + countdown
    ProtectedRoute.tsx    Auth guard; redirects to /login or /
    TimezoneSelect.tsx    IANA timezone picker grouped by region
    admin/
      UserTable.tsx       User list with status badges + actions
      UserModal.tsx       Add/Edit user modal
      EventDatePanel.tsx  Event date picker
      SubmissionsPanel.tsx  Submission monitor + Export All button

  context/
    AuthContext.tsx       user state, login(), logout(), useAuth() hook

  schemas/               Zod schemas for API response validation
    auth.schema.ts
    photo.schema.ts
    photographer.schema.ts
    registration.schema.ts
    admin.schema.ts

  store/
    store.ts             Redux store configuration
    baseApi.ts           RTK Query createApi base

  setupTests.ts          Vitest global setup (jest-dom matchers)
```

---

## Request Flow

```
1. Browser loads http://localhost:8765/
   → router.php detects no /api/ prefix
   → serves dist/index.html (React SPA shell)

2. React mounts → AuthContext fetches GET /api/me.php
   → sets user state (null if not logged in)

3. User navigates to /post
   → ProtectedRoute checks user state
   → if null: redirect to /login

4. User POSTs a photo to /api/photos.php
   → PHP: session check → window check → file validation → DB insert → 201
   → React: shows success flash

5. Home page polls GET /api/photos.php?after=<timestamp> every 60 s
   → PHP: SQL query with posted_at > :after → returns new photos
   → React: prepends to PhotoFeed state (no page reload)
```

---

## Authentication

```
Login flow:
  POST /api/login.php {username, password}
  → password_verify()
  → session_regenerate_id(true)
  → $_SESSION['user_id'] = $id
  → Set-Cookie: aday_session (Secure, HttpOnly, SameSite=Lax)
  → 200 + user object

Session check (every protected endpoint):
  Auth::requireValidated() / Auth::requireAdmin()
  → session_start()
  → SELECT * FROM users WHERE id = $_SESSION['user_id']
  → assert status = validated (and is_admin = 1 for admin routes)
  → return user row or send 401/403

Client-side:
  AuthContext calls GET /api/me.php on mount (always 200)
  → caches user in React state for session duration
  → useAuth() hook provides user, login(), logout()
```

---

## File Storage

```
uploads/
  {username}/
    {uuid}.jpg
    {uuid}.png
    {uuid}.webp

- Filenames: bin2hex(random_bytes(16)) + extension derived from MIME type
- MIME validated server-side via finfo_file (not extension sniffing)
- Max size: 15 MB (enforced in FileUpload::validate + php.ini)
- Served as static files — no PHP proxy
- Directory created on first post (mkdir recursive, 0755)
- uploads/ is gitignored; data/aday.sqlite is gitignored
```

---

## Database Schema

```sql
CREATE TABLE users (
    id           INTEGER PRIMARY KEY AUTOINCREMENT,
    username     TEXT UNIQUE NOT NULL,
    name         TEXT NOT NULL,
    substack_url TEXT,
    email        TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    timezone     TEXT NOT NULL,
    status       TEXT NOT NULL DEFAULT 'pending',   -- pending | validated
    is_admin     INTEGER NOT NULL DEFAULT 0,
    created_at   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
    -- rows: event_date, setup_complete
);

CREATE TABLE photos (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id),
    filename    TEXT NOT NULL,
    description TEXT NOT NULL,
    posted_at   TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX idx_photos_posted_at ON photos(posted_at DESC);
CREATE INDEX idx_photos_user_id   ON photos(user_id);
```
