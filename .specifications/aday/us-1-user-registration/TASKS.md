# Tasks for US-1: User Registration

---

## Phase 1: DB Migrations & Config
**Agent:** backend-dev

### Tasks
1. Create `migrations/001_create_users.php`
   - [ ] Define `users` table (id, username, name, substack_url, email, password_hash, timezone, status, is_admin, created_at)
   - [ ] Define `settings` table (key, value)
   - [ ] Run and verify schema creation

2. Create `config/db.php` — PDO singleton
   - [ ] Open SQLite file (`data/aday.sqlite`)
   - [ ] Enable WAL mode (`PRAGMA journal_mode=WAL`)
   - [ ] Set error mode to `ERRMODE_EXCEPTION`

3. Create `config/env.php` — `.env` loader
   - [ ] Load `.env` via `parse_ini_file`
   - [ ] Expose `env(string $key, $default = null)` helper

4. Create `.env.example`
   - [ ] Include: `SMTP_FROM`, `ADMIN_EMAIL`, `APP_SECRET`, `DB_PATH`

5. Create `config/captcha.php`
   - [ ] Return array of exactly 50 `['q' => ..., 'a' => ...]` entries
   - [ ] Mix: arithmetic (e.g. "What is 7 + 5?"), logic (e.g. "Which is larger: 4 or 9?"), general (e.g. "How many days in a week?")

---

## Phase 2: Backend Registration
**Agent:** backend-dev

### Tasks
6. Create `lib/Mailer.php`
   - [ ] Configure PHPMailer: host=localhost, port=25, no auth, `SMTP_FROM` from env
   - [ ] Method `sendAdminValidation(array $user): void` — sends to `ADMIN_EMAIL`
   - [ ] Email body: user details + link to `GET /admin/validate.php?token=<hmac>`
   - [ ] Catch exceptions; log to `logs/mail.log`; do not rethrow (registration succeeds regardless)

7. Create `api/captcha-question.php` — GET
   - [ ] Select random index from captcha pool (0–49)
   - [ ] Store index in `$_SESSION['captcha_index']`
   - [ ] Return `{index, question}` — never return the answer

8. Create `api/register.php` — POST (JSON or form)
   - [ ] Check registration open: if `event_date` setting == today (server date) → 423
   - [ ] Validate all fields (username format, email format, timezone in `DateTimeZone::listIdentifiers()`, password length)
   - [ ] Validate captcha: compare `$_POST['captcha_answer']` (trimmed, lowercased) against `captcha.php[$_SESSION['captcha_index']]['a']`
   - [ ] Check uniqueness: username, email → 409 with field name on conflict
   - [ ] Hash password: `password_hash($pass, PASSWORD_BCRYPT)`
   - [ ] Insert user (`status = 'pending'`)
   - [ ] Call `Mailer::sendAdminValidation`
   - [ ] Return 201 `{message: 'Registration submitted. Awaiting admin approval.'}`

---

## Phase 3: Frontend Registration Form
**Agent:** frontend-dev

### Tasks
9. Create `src/pages/RegisterPage.tsx`
   - [ ] On mount: `GET /api/captcha-question.php` → store `{index, question}` in state
   - [ ] Fields: username, name, substack_url, password, email, timezone (select), captcha_answer
   - [ ] Show registration-closed banner if API returns 423
   - [ ] On submit: POST form data + `captcha_index` to `/api/register.php`
   - [ ] On 201: show success message "Check your email"
   - [ ] On 409: highlight duplicate field (username or email)
   - [ ] On 422: show field-level validation errors

10. Create `src/components/TimezoneSelect.tsx`
    - [ ] Populate options from `Intl.supportedValuesOf('timeZone')`
    - [ ] Group by continent prefix (split on `/`)
    - [ ] Default to browser timezone: `Intl.DateTimeFormat().resolvedOptions().timeZone`

---

## Phase 4: Tests
**Agent:** testing

### Tasks
11. PHP unit tests (`tests/RegisterTest.php`)
    - [ ] Valid registration → 201, user in DB with `status = pending`
    - [ ] Duplicate username → 409
    - [ ] Duplicate email → 409
    - [ ] Invalid timezone → 422
    - [ ] Wrong captcha answer → 422
    - [ ] Registration closed (event_date = today) → 423
    - [ ] `Mailer::sendAdminValidation` called once on success
    - [ ] Password stored as bcrypt hash (not plaintext)

12. React unit tests (`src/pages/RegisterPage.test.tsx`)
    - [ ] Fetches and displays captcha question on mount
    - [ ] Submits correct payload including captcha_index
    - [ ] Shows success message on 201
    - [ ] Shows closed banner on 423
    - [ ] Shows duplicate-field error on 409

---

## Technical Notes

- **Implementation order**: Phase 1 → Phase 2 → Phase 3 → Phase 4. DB must exist before any API test.
- **Captcha session**: `session_start()` must be called before `api/captcha-question.php` and `api/register.php`.
- **`logs/` directory**: Must be writable; add to `.gitignore`. Create in Phase 1.
- **`data/` directory**: Must be writable by web server; add to `.gitignore`. Document in README.
- **Legacy-risk**: `parse_ini_file` is sensitive to special characters in values; document escaping rules in `.env.example`.
- **PHP `upload_max_filesize` and `post_max_size`**: Must be ≥15 MB in `php.ini`; document in README (relevant for US-4 but set up in Phase 1 README).
