# API Reference

All requests/responses use `Content-Type: application/json; charset=utf-8` unless noted (multipart for photo upload).

Session cookie: `aday_session` — Secure, HttpOnly, SameSite=Lax.

**Error envelope** (all error responses):
```json
{ "error": "Human-readable message." }
```
Validation failures:
```json
{ "errors": { "field_name": "Error message." } }
```

---

## Authentication

### GET /api/me.php

Returns current session user. **Always returns 200** — never 401 (avoids browser auth dialogs).

| Auth required | No |
|---|---|

**Response — authenticated (200)**
```json
{
  "authenticated": true,
  "username": "alice",
  "name": "Alice Smith",
  "is_admin": false,
  "status": "validated",
  "timezone": "Europe/London"
}
```

**Response — unauthenticated (200)**
```json
{ "authenticated": false }
```

---

### POST /api/login.php

| Auth required | No |
|---|---|
| Content-Type | `application/json` or `application/x-www-form-urlencoded` |

**Request body**
```json
{ "username": "alice", "password": "s3cr3tpass" }
```

**Response — 200 OK**
```json
{
  "username": "alice",
  "name": "Alice Smith",
  "is_admin": false,
  "status": "validated",
  "timezone": "Europe/London"
}
```

**Errors**
| Code | Condition |
|---|---|
| 400 | Missing username or password |
| 401 | Wrong credentials |
| 403 | Invalid credentials or account pending approval (generic — does not reveal which) |
| 405 | Non-POST request |
| 429 | Too many failed attempts (session throttled after 5) |

---

### POST /api/logout.php

| Auth required | No (safe to call when not logged in) |
|---|---|

**Response — 200 OK**
```json
{ "message": "Logged out." }
```

---

## Registration

### GET /api/captcha-question.php

Returns a random captcha question and stores the index in the session.

| Auth required | No |
|---|---|

**Response — 200 OK**
```json
{ "index": 12, "question": "What is 6 × 7?" }
```

> Submit `index` as `captcha_index` with the registration form. The answer is **never** in the response.

---

### POST /api/register.php

| Auth required | No |
|---|---|
| Content-Type | `application/json` or `application/x-www-form-urlencoded` |

**Request body**
```json
{
  "username":       "alice",
  "name":           "Alice Smith",
  "substack_url":   "https://alice.substack.com",
  "email":          "alice@example.com",
  "password":       "s3cr3tpass",
  "timezone":       "Europe/London",
  "captcha_answer": "42"
}
```

Validation rules:
- `username`: 3–30 chars, `[a-zA-Z0-9_]`, unique
- `password`: min 8 chars
- `timezone`: must be in `DateTimeZone::listIdentifiers()`
- `substack_url`: optional; must be a valid `http(s)` URL (other schemes rejected)
- `captcha_answer`: trimmed + lowercased comparison

**Response — 201 Created**
```json
{ "message": "Registration submitted. Awaiting admin approval." }
```

**Errors**
| Code | Condition |
|---|---|
| 405 | Non-POST request |
| 409 | `{ "field": "username|email", "error": "..." }` — duplicate |
| 422 | `{ "errors": { "field": "message" } }` — validation failure |
| 423 | Registration closed (today is the event date) |

---

## Admin — User Management

All `/api/admin/*` endpoints require an admin session.  
Non-admin or unauthenticated → **403**.

### GET /api/admin/users.php

Returns all users ordered by name ASC. Password hash is never included.

**Response — 200 OK**
```json
[
  {
    "id": 1,
    "username": "alice",
    "name": "Alice Smith",
    "email": "alice@example.com",
    "substack_url": "https://alice.substack.com",
    "timezone": "Europe/London",
    "status": "validated",
    "is_admin": 0,
    "created_at": "2026-08-24 07:00:00"
  }
]
```

---

### POST /api/admin/users.php

Create a user (status = `validated` automatically).

**Request body**
```json
{
  "username":     "bob",
  "name":         "Bob Jones",
  "substack_url": "https://bob.substack.com",
  "email":        "bob@example.com",
  "password":     "s3cr3tpass",
  "timezone":     "America/New_York",
  "is_admin":     0
}
```

**Response — 201 Created**
```json
{ "message": "User created." }
```

---

### PUT /api/admin/users.php?id=N

Update a user. Include only fields to change.

**Request body**
```json
{
  "name":         "Alice Example",
  "email":        "new@example.com",
  "timezone":     "UTC",
  "status":       "validated",
  "is_admin":     0,
  "substack_url": "",
  "password":     "newpassword"
}
```

> `substack_url`: empty string clears the field (sets to null).  
> `password`: omit or send empty to keep existing; if set, must be ≥ 8 chars.

**Response — 200 OK**
```json
{ "message": "User updated." }
```

**Errors**
| Code | Condition |
|---|---|
| 400 | Deleting own admin flag as last admin |
| 404 | User not found |
| 422 | Validation failure |

---

### DELETE /api/admin/users.php?id=N

Delete a user.

**Response — 200 OK**
```json
{ "message": "User deleted." }
```

**Errors**
| Code | Condition |
|---|---|
| 400 | Deleting self |
| 400 | Deleting last admin |
| 404 | User not found |

---

### GET /api/admin/validate.php?id=N&expires=TS&token=T

Approve a pending user via the HMAC link sent in the registration email.

Token = `hash_hmac('sha256', "id|email|expires|nonce", APP_SECRET)` — bound to
the user's email, an expiry (72h), and a single-use nonce stored on the user
(consumed on success). The endpoint fails closed (500) when `APP_SECRET` is
missing/short/placeholder.

**Response — 302 redirect** to `/admin/?validated=1`  
(200 JSON in test/API mode)

If the user is **already validated** (e.g. the single-use link was consumed by
a first click or an email link-scanner prefetch), the endpoint is *idempotent*:
it returns the same success redirect instead of an error — and does **not**
re-send the approval email.

On a real `pending → validated` transition, the participant is emailed an
approval confirmation (see `Mailer::sendUserValidated()`).

**Errors**
| Code | Condition |
|---|---|
| 400 | Missing id or token |
| 401 | Invalid HMAC, expired link, or user still `pending` with no/consumed nonce |

---

### GET /api/admin/settings.php

**Response — 200 OK**
```json
{
  "event_date": "2026-08-24",
  "allow_late_submissions": false
}
```
> `event_date` is `null` if not configured. `allow_late_submissions` defaults to `false`.

---

### POST /api/admin/settings.php

**Request body**
```json
{
  "event_date": "2026-08-24",
  "allow_late_submissions": true
}
```
> `allow_late_submissions` is optional — when omitted it is stored as `false`.

**Response — 200 OK**
```json
{
  "message": "Event date saved.",
  "event_date": "2026-08-24",
  "allow_late_submissions": true
}
```

**Errors**
| Code | Condition |
|---|---|
| 422 | Invalid date format (must be YYYY-MM-DD) |

---

## Admin — Submissions

### GET /api/admin/submissions.php

All photo submissions, newest first. Each row includes `highlight` (0/1),
`hidden` (0/1), and `thumb_url` (nullable).

**Response — 200 OK**
```json
[
  {
    "id": 1,
    "username": "alice",
    "name": "Alice Smith",
    "filename": "abc123.jpg",
    "description": "Morning light",
    "posted_at": "2026-08-24 09:00:00",
    "highlight": 0,
    "hidden": 0,
    "thumb_url": "/uploads/alice/thumbs/abc123.jpg"
  }
]
```

Image URL: `/uploads/{username}/{filename}` (thumbnail when `/thumbs/`)

---

### POST /api/admin/submissions.php

Set one or both admin flags for a photo: `highlight` (home-page strip) and/or
`hidden` (unlisted from all public surfaces).

**Request body**
```json
{ "id": 42, "highlight": true }
```
or
```json
{ "id": 42, "hidden": true }
```

**Response — 200 OK**
```json
{ "message": "Photo updated.", "id": 42, "highlight": true, "hidden": false }
```

> Values must be JSON booleans (`true`/`false`) or `1`/`0`. The string
> `"false"` is **rejected** (400) rather than coerced to `true`.

**Errors**
| Code | Condition |
|---|---|
| 400 | Missing or invalid id/highlight/hidden |
| 404 | Photo not found |

---

### DELETE /api/admin/submissions.php?id=N

Delete a submission — removes the DB row, and the original + thumbnail files from disk.

**Response — 200 OK**
```json
{ "message": "Submission deleted." }
```

**Errors**
| Code | Condition |
|---|---|
| 400 | Missing or invalid id |
| 404 | Photo not found |

---

### POST /api/admin/wrapup.php

Send the post-event wrap-up email **once** to every validated participant.
Guarded by a `wrapup_sent` settings row claimed atomically (double-send race
between the admin button and a cron job is impossible); if the mail relay
fails, the claim is released so the next attempt can retry.
`scripts/send_wrapup.php` is the cron-friendly CLI equivalent (exits 1 on
send failure).

| Auth required | Yes — admin |
|---|---|

**Response — 200 OK**
```json
{
  "message": "Wrap-up email sent to 12 participant(s).",
  "status": "sent",
  "count": 12
}
```

`status` is one of `sent` | `already_sent` | `no_recipients` | `failed`.

---

### GET /api/my-export.php

Self-service download: streams a ZIP of **the session user's own** visible
photos (originals at the archive root plus a `descriptions.txt` manifest with
descriptions and timestamps).

| Auth required | Yes — validated user |
|---|---|

**Response — 200 OK**
- `Content-Type: application/zip`
- `Content-Disposition: attachment; filename="my-documentyourlife.zip"`

**Errors**
| Code | Condition |
|---|---|
| 401 | Not authenticated |
| 403 | Account not validated |
| 404 | No photos on disk for this user |
| 503 | ZipArchive extension not available |

---

## Registration extras

`POST /api/register.php` also accepts an optional `bio` (≤ 500 chars, shown
on the public photographer profile). Admins can set/clear `bio` through
`POST`/`PUT /api/admin/users.php`.

---

### GET /api/admin/export.php

Exports all photos. The default (`?format=zip`) streams a ZIP of all photos
grouped by photographer. Pass `?format=csv` for a metadata CSV
(`aday-metadata.csv`) or `?format=json` for a JSON array — both include
timezone, gear, EXIF, and highlight fields.

**Response — 200 OK** (zip)
- `Content-Type: application/zip`
- `Content-Disposition: attachment; filename="aday-all-photos.zip"`

ZIP structure:
```
alice/
  abc123.jpg
  def456.png
  descriptions.txt   ← "abc123.jpg: Morning light\ndef456.png: ...\n"
bob/
  ghi789.webp
  descriptions.txt
```

**Errors**
| Code | Condition |
|---|---|
| 500 | ZipArchive extension not loaded |

---

## Photos

### POST /api/photos.php

Upload a photo. Multipart form data.

| Auth required | Yes — validated user |
|---|---|
| Content-Type | `multipart/form-data` |

**Request fields**
| Field | Type | Notes |
|---|---|---|
| `photo` | file | JPEG/PNG/WEBP, max 15 MB, max 8000 px / 40 MP |
| `description` | string | Optional, max 2000 characters |
| `gear` | string | Optional gear note (camera/lens/film), max 200, for manual/film setups |

EXIF (make/model/focal/aperture/shutter/ISO) is captured automatically from
digital uploads; stored in the `exif_*` columns. A square 320px thumbnail is
generated best-effort (GD) and served from `/uploads/{username}/thumbs/` when
available.

**Response — 201 Created**
```json
{
  "id": 42,
  "filename": "abc123.jpg",
  "posted_at": "2026-08-24 09:14:22"
}
```

Image served at: `/uploads/{username}/{filename}`

**Errors**
| Code | Condition |
|---|---|
| 401 | Not authenticated |
| 403 | Account not validated |
| 403 | Posting window closed — not the event date (or after it, when late submissions are disabled) |
| 422 | Wrong MIME type or file exceeds 15 MB |
| 422 | Image dimensions exceed 8000 px / 40 MP (rejected before decode) |
| 503 | Event date not configured |

---

### GET /api/photos.php

Public feed with cursor pagination.

| Auth required | No |
|---|---|

**Query parameters**
| Param | Type | Description |
|---|---|---|
| `before` | cursor | Paginate older: `posted_at\|id` composite (legacy plain timestamp still works) |
| `after` | cursor | Poll newer: `posted_at\|id` composite (newest-first response) |
| `limit` | int | Default 20, max 50 |
| `photo` | int | Return a single photo by id (lightbox deep-link) |
| `highlight` | flag | Return only admin-highlighted photos (max 50) |
| `photographer` | username | Return only that (validated) photographer's photos — used by per-photographer embeds |
| `hour` | 0–23 | "Follow the sun": photos whose **local** time (in the photographer's own timezone) falls in hour H; ordered east → west; each photo carries a `local_time` field (`HH:MM`) |
| `random` | flag | One random public photo ("surprise me") |

No params → latest 20 photos, newest first. Hidden (unlisted) photos are never
included in any public response. The hour filter scans the most recent 1000
photos and returns at most `limit` matches.

Each photo includes (nullable/0-1 where applicable): `highlight`, `gear`,
`exif_make`, `exif_model`, `exif_focal`, `exif_aperture`, `exif_shutter`,
`exif_iso`, `thumb_url` (thumbnail path when available, else `null`).

**Response — 200 OK**
```json
{
  "photos": [
    {
      "id": 42,
      "username": "alice",
      "name": "Alice Smith",
      "substack_url": "https://alice.substack.com",
      "filename": "abc123.jpg",
      "thumb_url": "/uploads/alice/thumbs/abc123.jpg",
      "description": "Morning light in the garden",
      "posted_at": "2026-08-24 09:14:22",
      "highlight": 0,
      "gear": "Canon EOS 5D · 50mm",
      "exif_make": "Canon",
      "exif_model": "EOS 5D",
      "exif_focal": "50mm",
      "exif_aperture": "f/1.8",
      "exif_shutter": "1/125s",
      "exif_iso": "400"
    }
  ],
  "next_cursor": "2026-08-24 08:30:00|42"
}
```

> `next_cursor` = `postedAt|id` of the oldest photo in the response (composite — no same-second drops), or `null`.  
> Use `?before=<next_cursor>` for the next page.  
> Use `?after=<latest_seen_posted_at>|<latest_seen_id>` for incremental polling. Ignore `next_cursor` in polling mode.

---

## Status

### GET /api/status.php

Public. Returns event date and posting window status.

| Auth required | No |
|---|---|

**Response — authenticated (200)**
```json
{
  "window_open": true,
  "event_date": "2026-08-24",
  "allow_late_submissions": false,
  "message": "Posting window is open"
}
```

**Response — unauthenticated (200)**
```json
{
  "window_open": null,
  "event_date": "2026-08-24",
  "allow_late_submissions": false,
  "message": "Posting window status unknown (not authenticated)."
}
```

**Response — event not configured (200)**
```json
{
  "window_open": false,
  "event_date": null,
  "allow_late_submissions": false,
  "message": "Event not yet scheduled"
}
```

---

## Stats

### GET /api/stats.php

Public. Total photo count + hourly posting histogram (UTC, last 48 hours).

| Auth required | No |
|---|---|

**Response — 200 OK**
```json
{
  "total": 42,
  "photographers": 34,
  "timezones": 19,
  "by_hour": [
    { "hour": "2026-08-24 08:00", "count": 3 },
    { "hour": "2026-08-24 09:00", "count": 7 }
  ]
}
```
> `by_hour` always has exactly 48 entries (zero-filled buckets). `total`
> counts all non-hidden photos; `photographers`/`timezones` count validated
> photographers with at least one visible photo and their distinct timezones.

---

## Photographers

### GET /api/photographers.php

All validated photographers, A–Z (case-insensitive by name).

| Auth required | No |
|---|---|

**Response — 200 OK**
```json
[
  {
    "username": "alice",
    "name": "Alice Smith",
    "substack_url": "https://alice.substack.com",
    "photo_count": 7
  }
]
```

---

### GET /api/admin/email.php?scope=validated|all

Recipient-count preview (no email is sent). `scope=validated` (default) counts
validated participants; `scope=all` counts every registered user. Admins are
always excluded; duplicate/empty emails are dropped.

| Auth required | Yes — admin |
|---|---|

**Response — 200 OK**
```json
{ "scope": "validated", "recipients": 12 }
```

### POST /api/admin/email.php

Send a one-off broadcast email (e.g. an event reminder) to the selected
audience. Body (JSON or form-encoded):

| Field | Required | Constraints |
|---|---|---|
| `subject` | yes | 1–200 chars, single line (no newlines — header-injection guard) |
| `body` | yes | 1–5000 chars |
| `scope` | no | `validated` (default) or `all` |

| Auth required | Yes — admin |
|---|---|

**Response — 200 OK**
```json
{ "status": "sent", "recipients": 12, "sent": 12, "failed": 0 }
```
`status` is `sent` (with `sent`/`failed` counts) or `no_recipients`.

**Errors** — 403 (non-admin), 405 (wrong method), 422 (validation).

---

### GET /api/photographers.php?username=alice

Single photographer profile + all their photos. The response includes the
photographer's optional `bio`.

| Auth required | No |
|---|---|

**Response — 200 OK**
```json
{
  "username": "alice",
  "name": "Alice Smith",
  "substack_url": "https://alice.substack.com",
  "photos": [
    {
      "id": 42,
      "filename": "abc123.jpg",
      "thumb_url": "/uploads/alice/thumbs/abc123.jpg",
      "description": "Morning light in the garden",
      "posted_at": "2026-08-24 09:14:22",
      "highlight": 0,
      "gear": null,
      "exif_make": null
    }
  ]
}
```

> Photo objects carry the same fields as `GET /api/photos.php` (full EXIF
> block + `thumb_url`). Hidden photos are never included.

**Errors**
| Code | Condition |
|---|---|
| 404 | Username not found or user is not validated |

---

## Static Files

Photos are served directly by the web server (no PHP proxy):

```
/uploads/{username}/{filename}
```

Ensure the `uploads/` directory is readable by the web server process.
