# IMPL-PLAN-php.md — A Day In The Life: PHP Backend

**Story scope:** US-1 through US-4 (full) · US-5, US-6, US-7 (PHP API layers only)
**Branch:** `feature/aday-photo-publishing`
**Agent:** PHP Developer
**Date:** 2026-08-24
**Status:** APPROVED

---

## Stack Note

This project is **Vanilla PHP 8.3 + SQLite** (not Laravel). All commands run via Composer scripts or `php` CLI directly — no Artisan, no Sail. Tests use **PHPUnit 11** (installed via Composer). Static analysis via **PHPStan** (Larastan not applicable). Code style via **PHP-CS-Fixer** or equivalent.

---

## Shared Ownership

| Resource | Owner | Consumers |
|---|---|---|
| `config/session.php` | **PHP Developer (this plan)** | All API endpoints — included before every `session_start()` |
| `lib/Auth.php` | **PHP Developer (this plan)** | US-1, US-2, US-3, US-4, US-5, US-6, US-7 API files |
| `migrations/003_create_photos.php` | **PHP Developer (this plan)** | US-3, US-5, US-6 depend on `photos` table |

**Do not touch:** `src/App.tsx` (React Developer owns)

---

## Implementation Order (dependency-safe)

```
Phase 0 — Foundation (no story dep)
Phase 1 — US-1: User Registration
Phase 2 — US-7 backend + US-2: Auth, Login, Admin
Phase 3 — US-4: Photos table + Photo Posting
Phase 4 — US-5: Home Feed (extends photos endpoint)
Phase 5 — US-3: Submissions & Export (depends on photos table)
Phase 6 — US-6: Photographer Index
Phase 7 — API Contract
```

---

## Phase 0 — Foundation

**All files: Path A (new)**

### `composer.json`
- Require: `phpmailer/phpmailer:^6.9`
- Require-dev: `phpunit/phpunit:^11`
- PSR-4 autoload: `""` → `""`  (flat structure, no namespace)
- Scripts: `"test": "vendor/bin/phpunit --colors=always"`

### `config/env.php`
- Load `.env` via `parse_ini_file(__DIR__ . '/../.env', false, INI_SCANNER_RAW)`
- Helper: `env(string $key, mixed $default = null): mixed`
- If file missing: silently return defaults (allows test environments without `.env`)

### `config/db.php`
- `function db(): PDO`
- Opens `env('DB_PATH', 'data/aday.sqlite')`
- `PRAGMA journal_mode=WAL`
- `PRAGMA foreign_keys=ON`
- `PDO::ERRMODE_EXCEPTION`, `PDO::FETCH_ASSOC`
- Singleton pattern (static local variable)
- Creates `data/` directory if absent

### `config/session.php` *(SHARED — owned by this plan)*
- `session_name('aday_session')`
- `session_set_cookie_params(['secure' => true, 'httponly' => true, 'samesite' => 'Lax'])`
- Dev override: if `env('APP_ENV') === 'development'`, set `secure => false`
- **Included at top of every API entry point before `session_start()`**

### `.env.example`
```
APP_SECRET=change-me-to-a-random-string
APP_ENV=production
DB_PATH=data/aday.sqlite
SMTP_FROM=noreply@example.com
ADMIN_EMAIL=admin@example.com
# Note: values containing special chars (; # = {}) must be quoted
# e.g. APP_SECRET="p@ss;word"
```

### `.gitignore` (create/update)
- `data/`
- `uploads/`
- `logs/`
- `.env`
- `vendor/`

### `logs/` directory
- Created via `mkdir logs` at project root
- `logs/.gitkeep` committed (directory tracked, contents ignored)

### `migrations/run.php` — CLI migration runner
- `php_sapi_name() !== 'cli'` guard → exit 1
- Loads all `migrations/0*.php` in filename order
- Calls `run(db())` on each
- Prints status per migration

---

## Phase 1 — US-1: User Registration

**All files: Path A (new)**

### `migrations/001_create_users.php`
- Function: `run(PDO $db): void`
- Creates `users` table:
  ```sql
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  name TEXT NOT NULL,
  substack_url TEXT,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  timezone TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  is_admin INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL DEFAULT (datetime('now'))
  ```
- Creates `settings` table:
  ```sql
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
  ```
- Uses `CREATE TABLE IF NOT EXISTS` (idempotent)

### `config/captcha.php`
- Returns `array` of exactly 50 `['q' => string, 'a' => string]` entries
- Mix: 20 arithmetic, 20 logic, 10 general knowledge
- All answers: lowercase, no trailing whitespace
- Example entries:
  - `['q' => 'What is 7 + 5?', 'a' => '12']`
  - `['q' => 'Which is larger: 4 or 9?', 'a' => '9']`
  - `['q' => 'How many days in a week?', 'a' => '7']`

### `lib/Mailer.php`
**Class: `Mailer`**
- Constructor: reads `SMTP_FROM`, `ADMIN_EMAIL` from `env()`
- `sendAdminValidation(array $user): void`
  - Configures PHPMailer: Host=`localhost`, Port=25, no auth, CharSet=UTF-8
  - To: `ADMIN_EMAIL`
  - Subject: `"[A Day] Validate user: {$user['username']}"`
  - Body: user details (username, name, email, timezone) + approve link:
    `GET /api/admin/validate.php?id={id}&token={hmac}`
  - HMAC: `hash_hmac('sha256', (string)$user['id'], env('APP_SECRET'))`
  - On `PHPMailer\PHPMailer\Exception`: log to `logs/mail.log` via `error_log()`; do **not** rethrow

### `api/captcha-question.php` — GET
- `require_once '../config/session.php'`; `session_start()`
- `require_once '../config/captcha.php'`
- Select `$index = random_int(0, 49)`
- `$_SESSION['captcha_index'] = $index`
- `json_encode(['index' => $index, 'question' => $captcha[$index]['q']])`
- Never include `'a'` in response

### `api/register.php` — POST (JSON or form)
- `require_once '../config/session.php'`; `session_start()`
- Accept `application/json` or `application/x-www-form-urlencoded`
- **Check event open**: query `settings` for `event_date`; if equals `date('Y-m-d')` → 423
- **Validate fields** (422 with field-level errors on failure):
  - `username`: 3–30 chars, `/^[a-z0-9_]+$/i`
  - `email`: `filter_var($email, FILTER_VALIDATE_EMAIL)`
  - `timezone`: `in_array($tz, DateTimeZone::listIdentifiers())`
  - `password`: min 8 chars
  - `name`: non-empty
- **Validate captcha** (422 on failure):
  - `strtolower(trim($_POST['captcha_answer'])) === $captcha[$_SESSION['captcha_index']]['a']`
- **Uniqueness check** (409 with field name on conflict): username, email
- `password_hash($password, PASSWORD_BCRYPT)`
- `INSERT INTO users` with `status = 'pending'`
- `(new Mailer())->sendAdminValidation($user)`
- Return 201 `{"message": "Registration submitted. Awaiting admin approval."}`

### Tests: `tests/RegisterTest.php`
**Class: `RegisterTest extends TestCase`** — Path A
| Test method | Assertion |
|---|---|
| `test_valid_registration_returns_201` | Status 201; user row in DB with `status=pending` |
| `test_duplicate_username_returns_409` | Status 409; body contains `'username'` |
| `test_duplicate_email_returns_409` | Status 409; body contains `'email'` |
| `test_invalid_timezone_returns_422` | Status 422 |
| `test_wrong_captcha_returns_422` | Status 422 |
| `test_registration_closed_when_event_date_is_today_returns_423` | Status 423 |
| `test_mailer_called_once_on_success` | `Mailer::sendAdminValidation` invoked (mock) |
| `test_password_stored_as_bcrypt_hash` | `password_verify()` passes; not plaintext |

---

## Phase 2 — US-7 Backend + US-2: Auth, Session, Admin

**All files: Path A (new)**

### `lib/Auth.php`
**Class: `Auth`** *(owned by this plan — shared by all stories)*
- `static requireAdmin(): array`
  - Starts session; loads user by `$_SESSION['user_id']`
  - If not set or user not found → respond 403 JSON; `exit`
  - If `is_admin !== 1` → respond 403 JSON; `exit`
  - Returns user row
- `static requireValidated(): array`
  - Starts session; loads user by `$_SESSION['user_id']`
  - If not authenticated → respond 401 JSON; `exit`
  - If `status !== 'validated'` → respond 403 JSON; `exit`
  - Returns user row
- `static currentUser(): ?array`
  - No side-effects; returns user row or null
  - Does not call `session_start()` (caller's responsibility)

### `api/me.php` — GET (public, always 200)
- `require_once '../config/session.php'`; `session_start()`
- Load user via `Auth::currentUser()`
- Authenticated: `{"authenticated":true,"username","name","is_admin","status"}`
- Unauthenticated: `{"authenticated":false}`
- **Never returns 401** (browser auth dialog risk)

### `api/login.php` — POST `{username, password}`
- `require_once '../config/session.php'`; `session_start()`
- Load user by `username`; if not found → 401 `{"error":"Invalid credentials"}`
- `password_verify($password, $user['password_hash'])` → 401 if false
- If `status !== 'validated'` → 403 `{"error":"Account pending approval"}`
- `session_regenerate(true)`; `$_SESSION['user_id'] = $user['id']`
- Return 200 `{username, name, is_admin, status}`

### `api/logout.php` — POST
- `require_once '../config/session.php'`; `session_start()`; `session_destroy()`
- Return 200 `{"message":"Logged out"}`

### `setup.php` — GET/POST
- **GET**: Check `settings.setup_complete`; if set → 403 "Setup already complete"
- Render HTML form: username + password
- **POST**: Validate username (3–30 chars, alphanumeric+underscore) and password (≥8 chars)
- Insert admin: `status='validated'`, `is_admin=1`, bcrypt password
- Write `setup_complete = 1` to `settings`
- Redirect to `/admin/` with flash `?setup=1`

### `scripts/reset_admin.php` — CLI only
- Guard: `php_sapi_name() !== 'cli'` → print "CLI only" and exit
- DELETE `setup_complete` row from `settings`
- Print "Admin setup reset. Visit /setup.php to reconfigure."

### `api/admin/users.php` — GET/POST/PUT/DELETE
- All methods: `Auth::requireAdmin()` first
- **GET**: all users ordered `name ASC`; fields: `id, username, name, email, timezone, status, is_admin, created_at`
- **POST** `{username, name, substack_url, email, password, timezone, is_admin}`: insert with `status='validated'`; return 201
- **PUT** `?id=N {name, email, timezone, status, is_admin}`: update; block self-demotion of admin flag; return 200
- **DELETE** `?id=N`: 400 if deleting self; 400 if deleting last admin (COUNT check); delete; return 200

### `api/admin/validate.php` — GET `?id=N&token=X`
- Compute expected: `hash_hmac('sha256', (string)$id, env('APP_SECRET'))`
- Compare with `hash_equals()`; 401 if mismatch
- Set matched user `status='validated'`
- Redirect to `/admin/?validated=1`

### `api/admin/settings.php` — GET/POST
- `Auth::requireAdmin()`
- **GET**: return `{"event_date": "YYYY-MM-DD" | null}`
- **POST** `{event_date}`: validate format `Y-m-d`; upsert into `settings`; return 200

### Tests: `tests/SetupTest.php`
**Class: `SetupTest extends TestCase`** — Path A
| Test method | Assertion |
|---|---|
| `test_first_get_renders_form` | 200 response |
| `test_valid_post_creates_admin_and_redirects` | Admin user in DB; `setup_complete` set; redirect |
| `test_second_visit_returns_403` | Status 403 |

### Tests: `tests/AdminUsersTest.php`
**Class: `AdminUsersTest extends TestCase`** — Path A
| Test method | Assertion |
|---|---|
| `test_require_admin_blocks_unauthenticated` | 403 |
| `test_require_admin_blocks_non_admin` | 403 |
| `test_get_returns_all_users_ordered_by_name` | Array in name ASC order |
| `test_post_creates_validated_user` | Status 201; `status='validated'` in DB |
| `test_delete_self_returns_400` | Status 400 |
| `test_delete_last_admin_returns_400` | Status 400 |
| `test_hmac_token_validates_and_sets_status` | User status = validated; redirect |
| `test_invalid_hmac_returns_401` | Status 401 |
| `test_event_date_upsert_stores_in_settings` | Row present in `settings` |

### Tests: `tests/AuthTest.php`
**Class: `AuthTest extends TestCase`** — Path A
| Test method | Assertion |
|---|---|
| `test_me_returns_user_data_for_valid_session` | `authenticated=true`, correct fields |
| `test_me_returns_false_with_no_session` | `authenticated=false` |
| `test_login_valid_credentials_returns_200_and_sets_session` | Status 200; session has `user_id` |
| `test_login_wrong_password_returns_401` | Status 401 |
| `test_login_unknown_username_returns_401` | Status 401 |
| `test_login_pending_user_returns_403` | Status 403 |
| `test_logout_destroys_session` | Session empty after POST |

---

## Phase 3 — US-4: Photos Table + Photo Posting

**All files: Path A (new)**

### `migrations/003_create_photos.php`
- Function: `run(PDO $db): void`
- Creates `photos` table:
  ```sql
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  filename TEXT NOT NULL,
  description TEXT NOT NULL DEFAULT '',
  posted_at TEXT NOT NULL DEFAULT (datetime('now'))
  ```
- `CREATE INDEX IF NOT EXISTS idx_photos_posted_at ON photos(posted_at DESC)`
- `CREATE INDEX IF NOT EXISTS idx_photos_user_id ON photos(user_id)`

### `lib/UploadException.php`
**Class: `UploadException extends RuntimeException`**
- No additional methods; used as typed exception by `FileUpload::validate()`

### `lib/WindowCheck.php`
**Class: `WindowCheck`**
- `static isPostingOpen(string $ianaTimezone, string $eventDate, ?DateTimeImmutable $now = null): bool`
  - `$now` defaults to `new DateTimeImmutable('now', new DateTimeZone($ianaTimezone))` (injectable for testing)
  - Returns `false` if `$eventDate` is empty string
  - Returns `$now->format('Y-m-d') === $eventDate`

### `lib/FileUpload.php`
**Class: `FileUpload`**
- `static validate(array $fileEntry): void` — throws `UploadException`
  - `$fileEntry['error'] !== UPLOAD_ERR_OK` → throw
  - `$fileEntry['size'] > 15 * 1024 * 1024` → throw "File exceeds 15 MB limit"
  - MIME via `finfo_file($finfo, $fileEntry['tmp_name'], FILEINFO_MIME_TYPE)` against allowlist `['image/jpeg','image/png','image/webp']` → throw if not in list
- `static save(array $fileEntry, string $username): string` — returns stored filename
  - Reads MIME (same `finfo` call)
  - Ext map: `['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp']`
  - Filename: `bin2hex(random_bytes(16)) . '.' . $ext`
  - `mkdir("uploads/{$username}", 0755, true)` if absent
  - `move_uploaded_file($fileEntry['tmp_name'], "uploads/{$username}/{$filename}")` → throw if fails
  - Returns `$filename`
- Private: `static mimeFromFile(string $path): string`

### `api/photos.php` — POST (multipart) + GET (cursor pagination)
**POST:**
- `require_once '../config/session.php'`; `session_start()`
- `$user = Auth::requireValidated()`
- Load `event_date` from `settings`; if absent → 503 `{"error":"Event not configured"}`
- `WindowCheck::isPostingOpen($user['timezone'], $eventDate)` → 403 if false
- `FileUpload::validate($_FILES['photo'])` → 422 with `{"error": $e->getMessage()}` on `UploadException`
- `$filename = FileUpload::save($_FILES['photo'], $user['username'])`
- INSERT into `photos`; return 201 `{id, filename, posted_at}`

**GET:**
- No auth required (public)
- Params: `before` (ISO 8601, newest-first page), `after` (ISO 8601, new photos since), `limit` (int, max 50, default 20)
- `before` variant: `WHERE posted_at < :before ORDER BY posted_at DESC, id DESC LIMIT :limit`
- `after` variant: `WHERE posted_at > :after ORDER BY posted_at ASC, id ASC LIMIT :limit`; reverse result array before returning (newest first in response)
- JOIN `users` (username, name, substack_url)
- Response: `{"photos":[{id, username, name, substack_url, filename, description, posted_at}], "next_cursor": <oldest_posted_at>|null}`
- Initial (no params): same as `before` with no filter

### `api/status.php` — GET (public)
- `require_once '../config/session.php'`; `session_start()`
- Load `event_date` from `settings`
- If not set: `{"window_open":false,"event_date":null,"message":"Event not yet scheduled"}`
- If set and authenticated: compute `WindowCheck::isPostingOpen` for user's timezone
- If set and unauthenticated: `{"window_open":null,"event_date":"YYYY-MM-DD",...}`
- Message when closed: `"Posting window is closed"`; when open: `"Posting window is open"`

### Tests: `tests/WindowCheckTest.php`
**Class: `WindowCheckTest extends TestCase`** — Path A
| Test method | Assertion |
|---|---|
| `test_open_when_event_date_today_at_10am` | `true` |
| `test_open_when_event_date_today_at_235959` | `true` |
| `test_closed_when_event_date_today_but_now_is_next_day` | `false` (inject next-day `$now`) |
| `test_closed_when_different_date` | `false` |
| `test_closed_when_event_date_empty` | `false` |
| `test_utc_minus_12_user_open_while_utc_is_next_day` | `true` (inject UTC+0 as next day, TZ=Etc/GMT+12) |

### Tests: `tests/FileUploadTest.php`
**Class: `FileUploadTest extends TestCase`** — Path A
| Test method | Assertion |
|---|---|
| `test_valid_jpeg_accepted` | No exception thrown |
| `test_valid_png_accepted` | No exception thrown |
| `test_valid_webp_accepted` | No exception thrown |
| `test_gif_throws_upload_exception` | `UploadException` thrown |
| `test_exactly_15mb_accepted` | No exception thrown |
| `test_15mb_plus_one_byte_throws_upload_exception` | `UploadException` thrown |
| `test_spoofed_extension_gif_mime_throws` | `UploadException` thrown (MIME check, not extension) |

### Tests: `tests/PhotoPostTest.php`
**Class: `PhotoPostTest extends TestCase`** — Path A
| Test method | Assertion |
|---|---|
| `test_unauthenticated_returns_401` | Status 401 |
| `test_pending_user_returns_403` | Status 403 |
| `test_window_closed_returns_403` | Status 403; message "Posting window closed" |
| `test_event_not_configured_returns_503` | Status 503 |
| `test_valid_post_returns_201_and_stores_in_db` | Status 201; row in `photos`; file on disk |

---

## Phase 4 — US-5: Home Feed (extend `api/photos.php`)

**Modified file: `api/photos.php`** — Path A (already covered in Phase 3, GET variant)

No new PHP files. The `GET` handler with `?after=` and `?before=` cursor params is fully specified in Phase 3. This phase verifies correct ordering and the `after` variant for polling.

### Tests: `tests/PhotoFeedTest.php`
**Class: `PhotoFeedTest extends TestCase`** — Path A
| Test method | Assertion |
|---|---|
| `test_after_param_returns_only_photos_after_timestamp` | Only newer photos returned |
| `test_after_param_returns_newest_first` | `posted_at` descending in response |
| `test_before_param_returns_correct_page_reverse_chrono` | Correct page, newest first |
| `test_empty_result_returns_empty_array_and_null_cursor` | `{photos:[], next_cursor:null}` |
| `test_interleaved_photos_from_multiple_users_ordered_by_posted_at` | Correct interleave |
| `test_tiebreaker_same_timestamp_lower_id_comes_second_in_desc` | `id DESC` respected |

---

## Phase 5 — US-3: Submissions & Export

**All files: Path A (new)**

### `lib/Exporter.php`
**Class: `Exporter`**
- `buildZip(): string` — returns temp file path
  - Query all photos JOIN users where user has ≥1 photo; iterate `username ASC`
  - `new ZipArchive(); $zip->open(tempnam(sys_get_temp_dir(), 'aday_'), ZipArchive::CREATE)`
  - Per photographer: subfolder `{username}/`
  - Per photo (ordered `posted_at ASC`):
    - `$path = "uploads/{$username}/{$filename}"`
    - If `file_exists($path)`: `$zip->addFile($path, "{$username}/{$filename}")`
    - Else: log warning via `error_log()`; note `[file missing]` in descriptions
  - Build `$descriptions` string: `"{$filename}: {$description}\n"` (or `"{$filename}: [file missing]\n"`)
  - `$zip->addFromString("{$username}/descriptions.txt", $descriptions)`
  - `$zip->close()`; return temp path
- Private: `queryPhotographersWithPhotos(): array`
- Private: `queryPhotosByUser(int $userId): array`

### `api/admin/submissions.php` — GET
- `Auth::requireAdmin()`
- Query all photos JOIN users; ORDER BY `posted_at DESC`
- Return JSON: `[{id, username, name, filename, description, posted_at}]`

### `api/admin/export.php` — GET
- `Auth::requireAdmin()`
- `$path = (new Exporter())->buildZip()`
- `ob_end_clean()`
- Headers: `Content-Type: application/zip`, `Content-Disposition: attachment; filename="aday-all-photos.zip"`
- `readfile($path)`
- `unlink($path)` in `finally`

### Tests: `tests/ExporterTest.php`
**Class: `ExporterTest extends TestCase`** — Path A
| Test method | Assertion |
|---|---|
| `test_single_photographer_two_photos_produces_correct_zip_structure` | ZIP has `user/img1`, `user/img2`, `user/descriptions.txt` |
| `test_descriptions_txt_ordered_by_posted_at_asc` | Correct order and format |
| `test_two_photographers_two_subfolders` | Two subfolders in ZIP |
| `test_photographer_with_no_photos_has_no_subfolder` | Absent from ZIP |
| `test_missing_file_on_disk_adds_file_missing_note_and_does_not_crash` | Note in descriptions; no exception |
| `test_empty_event_produces_valid_empty_zip` | Valid ZIP, no entries |

### Tests: `tests/SubmissionsTest.php`
**Class: `SubmissionsTest extends TestCase`** — Path A
| Test method | Assertion |
|---|---|
| `test_get_submissions_without_admin_returns_403` | Status 403 |
| `test_get_submissions_returns_photo_and_user_data` | Correct JSON fields |
| `test_get_export_without_admin_returns_403` | Status 403 |
| `test_export_streams_zip_with_correct_content_type` | Header `application/zip` |

---

## Phase 6 — US-6: Photographer Index

**All files: Path A (new)**

### `api/photographers.php` — GET (public)
- **No params**: query all `status='validated'` users with `photo_count` subquery
  - `ORDER BY name ASC COLLATE NOCASE`
  - Response: `[{username, name, substack_url, photo_count}]`
- **`?username=X`**: query single validated user + their photos
  - 404 if not found or not validated
  - Response: `{username, name, substack_url, photos: [{id, filename, description, posted_at}]}` (photos `posted_at DESC`)
- Known limitation: `COLLATE NOCASE` is ASCII-only; accented leading chars sort after Z

### Tests: `tests/PhotographersTest.php`
**Class: `PhotographersTest extends TestCase`** — Path A
| Test method | Assertion |
|---|---|
| `test_list_returns_only_validated_users` | Pending users absent |
| `test_list_ordered_case_insensitive_by_name` | A–Z order |
| `test_photo_count_is_correct` | Correct count per user |
| `test_single_user_returns_profile_and_photos` | Correct shape |
| `test_pending_user_returns_404` | Status 404 |
| `test_unknown_username_returns_404` | Status 404 |
| `test_user_with_zero_photos_appears_in_list_with_empty_photos` | `photo_count=0`; `photos=[]` |

---

## Phase 7 — API Contract

### `.specifications/aday/API-CONTRACT.md`
Committed after all backend tasks pass self-check. Covers all endpoints below.

---

## Complete File Inventory

### New PHP Files

| File | Story | TDD Path | Class / Purpose |
|---|---|---|---|
| `composer.json` | Foundation | — | Dependencies (PHPMailer, PHPUnit) |
| `config/env.php` | Foundation | A | `env()` helper |
| `config/db.php` | Foundation | A | PDO singleton |
| `config/session.php` | Foundation / US-7 | A | Session cookie flags *(SHARED)* |
| `config/captcha.php` | US-1 | A | 50-question pool array |
| `migrations/run.php` | Foundation | A | CLI migration runner |
| `migrations/001_create_users.php` | US-1 | A | `users` + `settings` tables |
| `migrations/003_create_photos.php` | US-4 | A | `photos` table + indexes |
| `lib/Auth.php` | US-2 / US-7 | A | `requireAdmin`, `requireValidated`, `currentUser` *(SHARED)* |
| `lib/Mailer.php` | US-1 | A | `sendAdminValidation()` |
| `lib/WindowCheck.php` | US-4 | A | `isPostingOpen()` |
| `lib/FileUpload.php` | US-4 | A | `validate()`, `save()` |
| `lib/UploadException.php` | US-4 | A | Typed exception |
| `lib/Exporter.php` | US-3 | A | `buildZip()` |
| `setup.php` | US-2 | A | First-run admin setup |
| `scripts/reset_admin.php` | US-2 | A | CLI admin reset |
| `api/captcha-question.php` | US-1 | A | GET random captcha question |
| `api/register.php` | US-1 | A | POST user registration |
| `api/me.php` | US-7 | A | GET current session user |
| `api/login.php` | US-7 | A | POST authenticate |
| `api/logout.php` | US-7 | A | POST destroy session |
| `api/status.php` | US-4 | A | GET posting window status |
| `api/photos.php` | US-4 / US-5 | A | POST upload + GET cursor feed |
| `api/photographers.php` | US-6 | A | GET index + single profile |
| `api/admin/users.php` | US-2 | A | CRUD user management |
| `api/admin/validate.php` | US-2 | A | GET HMAC token validation |
| `api/admin/settings.php` | US-2 | A | GET/POST event date |
| `api/admin/submissions.php` | US-3 | A | GET all submissions |
| `api/admin/export.php` | US-3 | A | GET ZIP export |

### Modified / Config Files

| File | Change |
|---|---|
| `.env.example` | New — documents all required env vars |
| `.gitignore` | New — ignores `data/`, `uploads/`, `logs/`, `vendor/`, `.env` |
| `logs/.gitkeep` | New — tracks `logs/` directory |
| `.htaccess` | New — serve `uploads/` statically; route API requests (if Apache) |

### Test Files

| File | Story | TDD Path | Test count |
|---|---|---|---|
| `tests/bootstrap.php` | Foundation | A | Shared in-memory SQLite setup helper |
| `tests/RegisterTest.php` | US-1 | A | 8 tests |
| `tests/SetupTest.php` | US-2 | A | 3 tests |
| `tests/AdminUsersTest.php` | US-2 | A | 9 tests |
| `tests/AuthTest.php` | US-7 | A | 7 tests |
| `tests/WindowCheckTest.php` | US-4 | A | 6 tests |
| `tests/FileUploadTest.php` | US-4 | A | 7 tests |
| `tests/PhotoPostTest.php` | US-4 | A | 5 tests |
| `tests/PhotoFeedTest.php` | US-5 | A | 6 tests |
| `tests/ExporterTest.php` | US-3 | A | 6 tests |
| `tests/SubmissionsTest.php` | US-3 | A | 4 tests |
| `tests/PhotographersTest.php` | US-6 | A | 7 tests |

**Total PHP test methods: 73**

### Artefacts Published at End

| File | Location |
|---|---|
| `API-CONTRACT.md` | `.specifications/aday/API-CONTRACT.md` |

---

## API Endpoints Summary (for React Developer)

| Method | Path | Auth | Story |
|---|---|---|---|
| GET | `/api/captcha-question.php` | None | US-1 |
| POST | `/api/register.php` | None | US-1 |
| GET | `/api/me.php` | None (always 200) | US-7 |
| POST | `/api/login.php` | None | US-7 |
| POST | `/api/logout.php` | Session | US-7 |
| GET | `/api/status.php` | Optional session | US-4 |
| POST | `/api/photos.php` | Validated user session | US-4 |
| GET | `/api/photos.php` | None | US-4 / US-5 |
| GET | `/api/photographers.php` | None | US-6 |
| GET | `/api/photographers.php?username=X` | None | US-6 |
| GET | `/api/admin/users.php` | Admin session | US-2 |
| POST | `/api/admin/users.php` | Admin session | US-2 |
| PUT | `/api/admin/users.php?id=N` | Admin session | US-2 |
| DELETE | `/api/admin/users.php?id=N` | Admin session | US-2 |
| GET | `/api/admin/validate.php?id=N&token=X` | HMAC token | US-2 |
| GET | `/api/admin/settings.php` | Admin session | US-2 |
| POST | `/api/admin/settings.php` | Admin session | US-2 |
| GET | `/api/admin/submissions.php` | Admin session | US-3 |
| GET | `/api/admin/export.php` | Admin session | US-3 |

---

## Out-of-Scope (PHP Developer does NOT touch)

- `src/App.tsx` — React Developer owns
- Any file under `src/` (all React)
- `public/assets/logo.png` — React Developer
- PR creation — Team Lead

---

## Open Questions / Risks

1. **No framework routing** — each API file is a direct PHP entrypoint. `.htaccess` needed for pretty URLs? Currently using `.php` suffixed paths. Document decision.
2. **`upload_max_filesize`** — must be ≥16M in `php.ini`. Cannot enforce in PHP code; will document in README.
3. **ZipArchive extension** — must be enabled. Will add startup check in `api/admin/export.php` with 500 error if missing.
4. **`migration 002` gap** — TASKS specify `001` (users) and `003` (photos). `002` is unassigned. Leaving gap intentional (matching TASKS spec) to allow future Planner insertion.
5. **Test approach for API endpoints** — vanilla PHP files are not classes; tests will use PHP's built-in test request simulation via output buffering + `$_SERVER`/`$_POST`/`$_SESSION` manipulation, or a lightweight test helper that includes the PHP file in an isolated scope.
