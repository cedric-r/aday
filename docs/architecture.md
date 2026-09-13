# Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────┐
│                          Browser                            │
│  React 18 SPA (MUI, Zod, RTK Query, React Router v7)       │
│  built to dist/ — served via Apache .htaccess (prod)      │
│  or router.php (dev)                                       │
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
  photos.php             GET/POST — feed (cursor, single, highlights, photographer) + upload (gear + EXIF + thumb)
  photographers.php      GET  — index list or single profile
  stats.php              GET  — total count + photographer/timezone breadth + 48h hourly histogram
  my-export.php          GET  — participant's own ZIP download (validated session)
  messages.php           GET  — notifications feed (validated session; read-only)
  status.php             GET  — event window status
  admin/
    users.php            GET/POST/PUT/DELETE — user management
    validate.php         GET  — approve user via HMAC link (id|email|expiry|nonce, single-use, fail-closed)
    settings.php         GET/POST — event date + late-submissions toggle
    submissions.php      GET/POST/DELETE — submissions + highlight/hidden toggles (strict booleans)
    wrapup.php            POST — send the post-event wrap-up email once
    email.php             GET/POST — recipient-count preview + one-off broadcast email
    messages.php          GET/POST/DELETE — notifications for the Notifications tab (+ email to participants)
    export.php            GET  — ZIP (photos) or CSV/JSON metadata export

lib/
  Auth.php               requireAdmin() / requireValidated() / currentUser()
  Mailer.php             PHPMailer wrapper (localhost:25, no auth)
  FileUpload.php         MIME validation + secure file save
  Exif.php               EXIF extraction (make, model, focal, aperture, shutter, ISO)
  Thumbnails.php         GD square thumbnail generator (best-effort, 320px, dims capped)
  WrapUp.php             One-shot post-event participant email (guarded by settings row)
  Mailer.php             PHPMailer wrapper — validation links, wrap-up, broadcast (sendBroadcast)
  Validate.php           Shared http/https URL validator (substack_url)
  WindowCheck.php        Timezone-aware posting-window check (event-date-only, or open-ended for late submitters)
  Exporter.php           ZipArchive builder — per-photographer subfolders
  Response.php           json() / error() / created() helpers
  ResponseException.php  Exception carrying HTTP status + message
  UploadException.php    Thrown by FileUpload on validation failure

config/
  db.php        PDO singleton — SQLite, WAL mode; relative DB_PATH resolved against project root (Apache-safe)
  env.php       Loads .env via parse_ini_file; exposes env() helper
  session.php   Cookie params (Secure, HttpOnly, SameSite=Lax), 2h idle timeout,
                Origin/Sec-Fetch-Site CSRF check on state-changing requests
                (compares the full origin authority — host *and* non-default
                port — against HTTP_HOST, so same-origin writes work on any
                port while cross-port/cross-host callers are rejected)
  captcha.php   Returns array of 50 ['q','a'] question pairs

migrations/
  001_create_users.php   users + settings tables
  003_create_photos.php  photos table + indexes
  004_add_photo_fields.php  highlight + gear + EXIF columns
  005_add_hidden.php     hidden (unlist) flag
  006_add_validation_nonce.php  single-use nonce for admin validation links
  007_add_bio.php        users.bio (photographer blurb)
  008_add_messages.php   messages table (Notifications tab)
  run.php                CLI runner — executes all migrations in order

scripts/
  reset_admin.php        CLI only — deletes setup_complete so setup.php can re-run
  send_wrapup.php        CLI only — one-shot post-event email to participants (cron-safe)

setup.php                One-time admin bootstrap page (locks after first use)
router.php               PHP built-in server entry point — serves dist/ SPA or routes API
embed.php                /embed SPA shell (frameable; cache-busted)
photo-meta.php           Server-rendered og/twitter tags for /photos/:id
```

---

## Frontend Structure

```
src/
  main.tsx               App entry — wraps in AuthProvider + MUI ThemeProvider + Router
  App.tsx                Route definitions (React Router v7)
  theme.ts               MUI createTheme — GitHub-inspired colour palette

  pages/
    HomePage.tsx          / — stats strip + highlights strip + live photo feed; event-date empty state
    IndexPage.tsx         /index — A–Z photographer list
    PhotographerPage.tsx  /photographers/:username — individual profile
    PhotoPage.tsx         /photos/:id — standalone photo page (shareable, social cards)
    RegisterPage.tsx      /register — registration form + captcha + optional bio
    LoginPage.tsx         /login — login form
    PostPage.tsx          /post — photo upload form (protected; gear field)
    NotificationsPage.tsx /notifications — messages from the organisers (validated users; read-only)
    AdminPage.tsx         /admin — admin dashboard (admin-protected, 6 tabs)
    EmbedPage.tsx         /embed — header-less gallery for iframes (photographer + theme params)
    NotFoundPage.tsx      * — 404

  components/
    Header.tsx            Logo + Nav
    Nav.tsx               Menu items (auth-aware via useAuth)
    PhotoCard.tsx         Single photo — image, name, description, gear/EXIF, highlight badge
    PhotoFeed.tsx         Polling list; cards ⇄ grid toggle; lightbox + ?photo=N deep link
    PhotoLightbox.tsx     Full-screen viewer — prev/next, Esc, arrow keys
    StatsStrip.tsx        "N photos so far" + 48h hourly pulse
    HighlightsStrip.tsx   Horizontal strip of admin-highlighted photos
    PostingWindowBanner   Open/closed indicator + countdown
    ProtectedRoute.tsx    Auth guard; redirects to /login or /
    TimezoneSelect.tsx    IANA timezone picker grouped by region
    admin/
      UserTable.tsx       User list with status badges + actions
      UserModal.tsx       Add/Edit user modal (validated substack_url + timezone)
      EventDatePanel.tsx    Event date + late-submissions toggle
      EmailPanel.tsx        Broadcast email composer (scope, preview count, confirm)
      MessagePanel.tsx      Notification composer + sent list (delete/retract)
      SubmissionsPanel.tsx  Submission monitor: highlight/hide toggles, delete, export (ZIP/CSV/JSON), wrap-up email
      EmbedPanel.tsx        Embed snippet generator (photographer + theme → iframe/link)

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
1. Browser loads https://aday.photoni.st/ (or http://localhost:8765/ in dev)
   → Apache .htaccess (dev: router.php) serves dist/index.html — the React SPA shell

2. React mounts → AuthContext fetches GET /api/me.php
   → sets user state (null if not logged in)

3. User navigates to /post
   → ProtectedRoute checks user state
   → if null: redirect to /login

4. User POSTs a photo to /api/photos.php
   → PHP: session check → window check (event date ± late submissions) → file validation → DB insert → 201
   → React: shows success flash

5. Home page polls GET /api/photos.php?after=<posted_at>|<id> every 60 s
   → PHP: SQL query with composite (posted_at, id) > cursor → returns new photos
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
    thumbs/
      {uuid}.jpg      ← square 320px GD thumbnail (same basename) when generated

- Filenames: bin2hex(random_bytes(16)) + extension derived from MIME type
- MIME validated server-side via finfo_file (not extension sniffing)
- Max size: 15 MB (FileUpload::validate + php.ini); max dimensions 8000px/40MP
  (rejected before decode — decompression-bomb defence)
- Thumbnails generated best-effort with GD; the app falls back to the original
  when GD is absent (lib/Thumbnails.php)
- Served as static files — no PHP proxy
- Directory created on first post (mkdir recursive, 0755)
- uploads/ is gitignored; data/aday.sqlite is gitignored
```

---

## Database Schema

```sql
CREATE TABLE users (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    username        TEXT UNIQUE NOT NULL,
    name            TEXT NOT NULL,
    substack_url    TEXT,
    email           TEXT UNIQUE NOT NULL,
    password_hash   TEXT NOT NULL,
    timezone        TEXT NOT NULL,
    status          TEXT NOT NULL DEFAULT 'pending',   -- pending | validated | disabled
    is_admin        INTEGER NOT NULL DEFAULT 0,
    validation_nonce TEXT,                             -- single-use nonce for email links (migration 006)
    created_at      TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
    -- rows: event_date, setup_complete, allow_late_submissions, wrapup_sent
);

CREATE TABLE photos (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id        INTEGER NOT NULL REFERENCES users(id),
    filename       TEXT NOT NULL,
    description    TEXT NOT NULL,
    posted_at      TEXT NOT NULL DEFAULT (datetime('now')),
    highlight      INTEGER NOT NULL DEFAULT 0,   -- admin-picked (home strip)
    hidden         INTEGER NOT NULL DEFAULT 0,   -- unlisted from all public surfaces
    gear           TEXT,                          -- optional gear note (film setups)
    exif_make      TEXT, exif_model     TEXT,
    exif_focal     TEXT, exif_aperture  TEXT,
    exif_shutter   TEXT, exif_iso       TEXT
);
CREATE INDEX idx_photos_posted_at ON photos(posted_at DESC);
CREATE INDEX idx_photos_user_id   ON photos(user_id);

CREATE TABLE messages (                            -- migration 008
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    subject    TEXT NOT NULL,
    body       TEXT NOT NULL,
    created_by INTEGER,                            -- users.id of the admin who sent it
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
    -- read in-app on /notifications by every validated user; each new row is
    -- also emailed to validated participants (best-effort, Mailer::sendBroadcast)
);
```
