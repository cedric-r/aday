# Tasks for US-4: Photographer — Photo Posting

---

## Phase 1: DB Migration
**Agent:** backend-dev

### Tasks
1. Create `migrations/003_create_photos.php`
   - [ ] Define `photos` table (id, user_id FK, filename, description TEXT, posted_at)
   - [ ] Create index: `idx_photos_posted_at ON photos(posted_at DESC)`
   - [ ] Create index: `idx_photos_user_id ON photos(user_id)`
   - [ ] Run and verify

---

## Phase 2: Backend Libraries
**Agent:** backend-dev

### Tasks
2. Create `lib/WindowCheck.php`
   - [ ] `isPostingOpen(string $ianaTimezone, string $eventDate): bool`
   - [ ] Create `DateTimeImmutable('now', new DateTimeZone($ianaTimezone))`
   - [ ] Compare `format('Y-m-d') === $eventDate` AND `format('H:i:s') <= '23:59:59'`
   - [ ] Return false if `$eventDate` is empty string

3. Create `lib/FileUpload.php`
   - [ ] `validate(array $fileEntry): void` — throws `UploadException` on failure
     - [ ] Check `$fileEntry['error'] === UPLOAD_ERR_OK`
     - [ ] Check size ≤ 15 MB (15 * 1024 * 1024 bytes)
     - [ ] Check MIME via `finfo_file(FILEINFO_MIME_TYPE)` against allowlist `['image/jpeg','image/png','image/webp']`
   - [ ] `save(array $fileEntry, string $username): string` — returns stored filename
     - [ ] Generate filename: `bin2hex(random_bytes(16)) . '.' . ext_from_mime($mime)`
     - [ ] Create `uploads/{username}/` if absent (`mkdir` with `0755`, recursive)
     - [ ] `move_uploaded_file` to target path
     - [ ] Return filename

---

## Phase 3: API Endpoints
**Agent:** backend-dev

### Tasks
4. Create `api/photos.php` — POST (multipart)
   - [ ] `Auth::requireValidated()` → 401/403 if not
   - [ ] Load `event_date` from settings → 503 `{error: 'Event not configured'}` if absent
   - [ ] `WindowCheck::isPostingOpen($user['timezone'], $eventDate)` → 403 `{error: 'Posting window closed'}` if false
   - [ ] `FileUpload::validate($_FILES['photo'])` → 422 with message on `UploadException`
   - [ ] `FileUpload::save($_FILES['photo'], $user['username'])` → save file
   - [ ] INSERT into `photos`; return 201 `{id, filename, posted_at}`

5. Extend `api/photos.php` — GET (home feed)
   - [ ] Params: `?before=<iso8601>&limit=20` (cursor pagination, newest first)
   - [ ] Params: `?after=<iso8601>&limit=50` (new photos since timestamp, for polling — US-5)
   - [ ] JOIN users; return `[{id, username, name, substack_url, filename, description, posted_at}]` + `next_cursor`
   - [ ] SQL tiebreaker: `ORDER BY posted_at DESC, id DESC`

6. Create `api/status.php` — GET (public)
   - [ ] Load `event_date` from settings
   - [ ] If not set: `{window_open: false, event_date: null, message: 'Event not yet scheduled'}`
   - [ ] If set: compute `window_open` for requesting user (if authenticated); return `{window_open, event_date, message}`
   - [ ] Unauthenticated request: `window_open = null` (cannot compute without timezone)

7. Configure static file serving for `uploads/`
   - [ ] Ensure `uploads/` is web-accessible (document in README / `.htaccess` rule if needed)
   - [ ] Add `uploads/` to `.gitignore` (images should not be committed)

---

## Phase 4: Frontend Photo Posting
**Agent:** frontend-dev

### Tasks
8. Create `src/components/PostingWindowBanner.tsx`
   - [ ] Props: `{eventDate: string, userTimezone: string, windowOpen: boolean}`
   - [ ] If closed: show "Posting window is closed" message
   - [ ] If open: show countdown to midnight in user's timezone using `Intl.DateTimeFormat` + `setInterval(1000)`

9. Create `src/pages/PostPage.tsx`
   - [ ] On mount: `GET /api/status.php`; if not authenticated redirect to login
   - [ ] Render `PostingWindowBanner`
   - [ ] If window open: show form — file picker (`accept="image/jpeg,image/png,image/webp"`), description textarea (no max length), submit button
   - [ ] On submit: build `FormData` with `photo` file + `description`; POST to `/api/photos.php`
   - [ ] Show file size warning client-side if >15 MB before submit
   - [ ] On 201: show success flash + link to home page
   - [ ] On 403: refresh banner to "Posting window closed"
   - [ ] On 422: show error message from API

---

## Phase 5: Tests
**Agent:** testing

### Tasks
10. PHP unit tests (`tests/WindowCheckTest.php`)
    - [ ] Event date today in TZ, time 10:00 → open
    - [ ] Event date today in TZ, time 23:59:59 → open
    - [ ] Event date today in TZ, time 00:00:00 next day → closed (test via mocked "now")
    - [ ] Different date → closed
    - [ ] Empty event_date → closed
    - [ ] UTC-12 user: local still event_date while UTC is next day → open

11. PHP unit tests (`tests/FileUploadTest.php`)
    - [ ] Valid JPEG → accepted
    - [ ] Valid PNG → accepted
    - [ ] Valid WEBP → accepted
    - [ ] GIF (image/gif) → UploadException
    - [ ] 15 MB exactly → accepted
    - [ ] 15 MB + 1 byte → UploadException
    - [ ] Extension spoofed (file.jpg but MIME = gif) → UploadException

12. PHP unit tests (`tests/PhotoPostTest.php`)
    - [ ] Unauthenticated → 401
    - [ ] Pending user → 403
    - [ ] Window closed → 403
    - [ ] Event not configured → 503
    - [ ] Valid post → 201, row in DB, file on disk

13. React unit tests
    - [ ] `PostingWindowBanner` shows countdown when open
    - [ ] `PostingWindowBanner` shows closed message when closed
    - [ ] `PostPage` hides form when window closed
    - [ ] `PostPage` shows client-side warning for oversized file

---

## Technical Notes

- **Order**: Phase 1 → Phase 2 → Phase 3 → Phase 4. `WindowCheck` and `FileUpload` are pure-logic libs — write and test in Phase 2 before the endpoint exists.
- **`WindowCheck` testing**: Inject a `$now` parameter to make it deterministically testable without mocking global time.
- **`php.ini`**: `upload_max_filesize = 16M`, `post_max_size = 17M` required. Document in README.
- **`uploads/` permissions**: Web server must own or have write permission. Document in README.
- **Legacy-risk**: `move_uploaded_file` is a PHP built-in — no library needed, but only works in actual HTTP upload context; unit-test `FileUpload::save` with a real temp file.
