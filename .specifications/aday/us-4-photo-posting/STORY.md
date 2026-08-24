# US-4: Photographer — Photo Posting

**Feature**: aday-photo-publishing

## User Story

**As a** validated photographer  
**I can** post photos with description text during my local event day  
**So that** my contributions appear on the shared home page

## Acceptance Criteria

1. **Given** it is the event date in the photographer's timezone, **When** they submit a photo + description, **Then** photo stored in `uploads/{username}/` and record inserted into `photos`
2. **Given** it is past 23:59:59 in the photographer's local timezone, **When** they attempt to post, **Then** submission rejected with "Posting window closed" message
3. **Given** submitted file exceeds 15 MB or is not JPEG/PNG/WEBP, **When** validated server-side, **Then** 422 returned with specific error
4. **Given** photo posted successfully, **When** home page next polls, **Then** photo appears in the feed

## Assumptions (approved defaults)

| # | Assumption |
|---|---|
| Posting window | Strict `00:00–23:59:59` in user's IANA timezone on the configured `event_date`. No grace period. Server enforces; client shows countdown/status. |
| File limits | Max 15 MB. Accepted MIME types: `image/jpeg`, `image/png`, `image/webp`. Validated by server (not just extension). |
| Storage | Files saved to `uploads/{username}/{uuid}.{ext}` relative to project root. Directory created on first post if absent. |
| Auth | Must be `status = validated` and logged in. Pending users cannot post. |

## Data Model

```sql
CREATE TABLE photos (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id),
    filename    TEXT NOT NULL,       -- e.g. "a1b2c3d4.jpg" within uploads/{username}/
    description TEXT NOT NULL,
    posted_at   TEXT NOT NULL DEFAULT (datetime('now'))   -- UTC ISO8601
);
CREATE INDEX idx_photos_posted_at ON photos(posted_at DESC);
```

Photo URL served via `GET /uploads/{username}/{filename}` (static file or `serve.php` proxy).

## Backend Implementation (PHP)

**Files**:
- `api/photos.php`:
  - `POST` (multipart) → validate session, validate window, validate file, save, insert DB row
  - `GET` → paginated list of all photos (home feed); params: `?before=<iso8601>&limit=20`
- `api/user/photos.php` — `GET ?username=X` → photos for a single photographer (index page)
- `lib/Auth.php` — `requireValidated()` helper (extends US-2 `requireAdmin()` pattern)
- `lib/WindowCheck.php` — `isPostingOpen(string $timezone, string $eventDate): bool`
  - Creates `DateTime` in user's timezone; checks date == `event_date` and time ≤ 23:59:59
- `lib/FileUpload.php` — validates MIME (via `finfo_file`), size, generates UUID filename, moves to target dir

**POST `/api/photos.php` flow**:
```
1. Auth::requireValidated()
2. Load event_date from settings → 503 if not set
3. WindowCheck::isPostingOpen(user.timezone, event_date) → 403 + message if closed
4. FileUpload::validate($_FILES['photo']) → 422 on failure
5. FileUpload::save($file, $username) → returns filename
6. INSERT INTO photos (user_id, filename, description, posted_at) VALUES (...)
7. Return 201 + {id, filename, posted_at}
```

**GET `/api/photos.php`** (home feed):
```sql
SELECT p.id, p.filename, p.description, p.posted_at,
       u.username, u.name, u.substack_url
FROM photos p JOIN users u ON u.id = p.user_id
WHERE p.posted_at < :before   -- cursor pagination
ORDER BY p.posted_at DESC
LIMIT :limit
```

**MIME validation** (server-side):
```php
$allowed = ['image/jpeg', 'image/png', 'image/webp'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['photo']['tmp_name']);
if (!in_array($mime, $allowed, true)) { /* 422 */ }
```

## Frontend Implementation (React)

**Components**:
- `src/pages/PostPage.tsx` — photo posting form
- `src/components/PostingWindowBanner.tsx` — shows open/closed status + time remaining

**PostPage flow**:
1. On mount: `GET /api/status.php` (or derive from event_date + user timezone) to determine if window is open
2. If closed: show banner; hide form
3. If open: show form — file picker (accept="image/jpeg,image/png,image/webp"), description textarea, submit button
4. On submit: `FormData` POST to `/api/photos.php`
5. On 201: show success + link to home page
6. On 403: show "Posting window closed"
7. On 422: show field-level error

**`PostingWindowBanner`**: Computes remaining time client-side using `Intl.DateTimeFormat` with user's timezone (stored in session/user profile). Shows countdown HH:MM:SS until midnight.

**Status endpoint** (lightweight):
- `GET /api/status.php` → `{window_open: bool, event_date: 'YYYY-MM-DD', message: string}`

## Test Coverage

**Unit (PHP — `WindowCheck`)**:
- Event date today in user TZ, time 10:00 → open
- Event date today in user TZ, time 23:59:59 → open
- Event date today in user TZ, time 00:00:00 (next day) → closed
- Different date than event_date → closed
- Timezone edge: UTC-12 user posting when UTC is already next day → open if local is still event_date

**Unit (PHP — `FileUpload`)**:
- Valid JPEG → accepted
- Valid PNG → accepted
- Valid WEBP → accepted
- GIF (image/gif) → 422
- File size 15.001 MB → 422
- File size 15 MB exactly → accepted
- Extension spoofed (file.jpg but MIME = image/gif) → 422

**Unit (PHP — `api/photos.php`)**:
- Unauthenticated → 401
- Pending user → 403
- Window closed → 403
- Valid post → 201, row in DB, file on disk

**Unit (React)**:
- Closed window → form hidden, banner shown
- File type filter on picker
- 422 error shows message

**Edge Cases**:
- `event_date` not set in settings → `api/photos.php` returns 503 "Event not configured"
- Upload directory not writable → 500 + error log entry
- Two simultaneous posts from same user → both succeed (different UUID filenames)

## Technical Risks

- **Timezone edge cases** (DST transitions on event day): Use PHP `DateTimeImmutable` with explicit IANA tz; DST handled automatically
- **File system permissions**: `uploads/` must be writable by web server; document in README
- **SQLite WAL contention on peak posting**: WAL mode + brief retry loop (3 attempts, 100ms sleep) in insert path

## Implementation Phases

1. DB migration (`photos` table + index)
2. `WindowCheck.php` + `FileUpload.php` (pure logic, fully testable)
3. `api/photos.php` (POST + GET)
4. `api/status.php`
5. React `PostPage` + `PostingWindowBanner`
6. Static file serving for `uploads/`
7. Tests
