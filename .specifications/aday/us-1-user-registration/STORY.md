# US-1: User Registration

**Feature**: aday-photo-publishing

## User Story

**As a** prospective photographer  
**I can** register with username, name, Substack URL, password, email, and timezone  
**So that** I can participate on event day

## Acceptance Criteria

1. **Given** registration is open, **When** user submits valid form + correct captcha, **Then** account created with `status = pending` and admin receives validation email
2. **Given** page loads, **When** captcha is shown, **Then** one question is selected at random from the 50-question pool in `config/captcha.php`
3. **Given** event day has arrived, **When** any visitor loads the registration page, **Then** form is hidden and "Registration closed" message shown

## Assumptions (approved defaults)

| # | Assumption |
|---|---|
| SMTP | Port 25 open relay, no authentication. Config: `SMTP_FROM` and `ADMIN_EMAIL` in `.env` only (no user/pass/host required beyond localhost). Use PHPMailer (SMTP transport, localhost:25) or `mail()` fallback. `.env.example` includes `SMTP_FROM` and `ADMIN_EMAIL` placeholders. |
| Captcha | Hardcoded array in `config/captcha.php` — 50 math/logic questions. Each entry: `['q' => '...', 'a' => '...']`. Answer comparison is case-insensitive trimmed string. |
| Session | Cookie sessions only. No "remember me". Site runs on HTTPS — session cookies set with `Secure` + `HttpOnly` + `SameSite=Lax`. |

## Data Model

```sql
-- users table (canonical definition — all stories share this)
CREATE TABLE users (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    username    TEXT UNIQUE NOT NULL,
    name        TEXT NOT NULL,
    substack_url TEXT NOT NULL,
    email       TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,       -- password_hash() / PASSWORD_BCRYPT
    timezone    TEXT NOT NULL,         -- IANA tz, e.g. "Europe/London"
    status      TEXT NOT NULL DEFAULT 'pending', -- pending | validated
    is_admin    INTEGER NOT NULL DEFAULT 0,
    created_at  TEXT NOT NULL DEFAULT (datetime('now'))
);

-- settings table (canonical definition — shared)
CREATE TABLE settings (
    key   TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
-- Row: ('event_date', 'YYYY-MM-DD')
```

## Backend Implementation (PHP)

**Files**:
- `api/register.php` — POST handler; validates input, checks captcha, inserts user, sends admin email
- `api/captcha-question.php` — GET; returns random question index + text (never returns answer)
- `config/captcha.php` — returns array of 50 `['q','a']` pairs
- `config/db.php` — returns PDO singleton (SQLite WAL mode)
- `config/env.php` — loads `.env` via `parse_ini_file`
- `lib/Mailer.php` — thin wrapper around PHPMailer (localhost:25, no auth) or `mail()`; sends admin validation email
- `migrations/001_create_users.php` — creates `users` table + seeds first admin
- `migrations/002_create_settings.php` — creates `settings` table

**Validation rules** (server-side, all required):
- `username`: 3–30 chars, alphanumeric + underscore, unique
- `name`: 1–100 chars
- `substack_url`: valid URL format
- `email`: valid, unique
- `password`: min 8 chars; stored as `password_hash($pass, PASSWORD_BCRYPT)`
- `timezone`: must exist in `DateTimeZone::listIdentifiers()`
- `captcha_answer`: matches stored answer for question index in session

**Admin email** (sent via `Mailer::sendAdminValidation`):
- To: `ADMIN_EMAIL` from `.env`
- Subject: "New registration: {username}"
- Body: user details + link to `admin/validate/{token}` (token = signed hash of user id)

**Registration-closed check** (in `api/register.php` and `register.php` page):
- Load `event_date` from `settings`; if `event_date` == today (server UTC date) → return 423 / show closed message
- Note: event_date comparison uses server date; the "event day" concept for the posting window (US-4) uses the *user's* timezone. For registration, a simple server-date check is sufficient.

## Frontend Implementation (React)

**Component**: `src/pages/RegisterPage.tsx`

**Fields**: username, name, substack_url, password, email, timezone (select from IANA list), captcha_answer

**Flow**:
1. On mount: `GET /api/captcha-question.php` → store `{index, question}` in state
2. On submit: `POST /api/register.php` with form data + `captcha_index`
3. On success: show "Check your email" message
4. If 423 from API: show registration-closed banner (also shown if backend returns `closed: true` on GET)

**Timezone select**: use `Intl.supportedValuesOf('timeZone')` or bundled IANA list; grouped by region.

## Test Coverage

**Unit (PHP)**:
- Valid registration → user inserted with `status = pending`
- Duplicate username → 409
- Duplicate email → 409
- Invalid timezone → 422
- Wrong captcha → 422
- Registration closed (event_date = today) → 423
- Mailer called once on success

**Unit (React)**:
- Renders captcha question fetched from API
- Submits correct payload
- Shows closed banner when API returns closed

**Edge Cases**:
- Captcha answer with leading/trailing whitespace → trimmed before compare
- Timezone list renders without crash (>500 entries)
- `event_date` not yet set → registration open (no row in settings = no event date = open)

## Technical Risks

- **PHPMailer SMTP auth failure at runtime**: Mitigate — `Mailer` catches exceptions and logs to `logs/mail.log`; registration still succeeds (user is created), admin can manually approve
- **SQLite concurrent inserts on popular registration window**: WAL mode handles this; unique constraints on username/email prevent duplicates

## Implementation Phases

1. DB migration + config files + `.env.example`
2. PHP backend (register, captcha endpoints, mailer)
3. React registration form
4. Closed-state behaviour
5. Tests
