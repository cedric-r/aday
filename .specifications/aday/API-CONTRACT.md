# API Contract — A Day In The Life

**Branch:** `feature/aday-photo-publishing`
**Version:** early drop (auth + registration endpoints stable)
**Date:** 2026-08-24
**Status:** FINAL — all 19 endpoints implemented and tested

All requests and responses use `Content-Type: application/json; charset=utf-8` unless noted.
Session is cookie-based (`aday_session`; Secure, HttpOnly, SameSite=Lax).

---

## Authentication Endpoints (US-7)

### GET /api/me.php

Returns the current session user. **Always 200** — never 401 (avoids browser auth dialogs).

**Response — authenticated**
```json
{
  "authenticated": true,
  "username": "string",
  "name": "string",
  "is_admin": false,
  "status": "validated",
  "timezone": "Europe/London"
}
```
> `timezone` is the user's IANA timezone identifier (e.g. `"UTC"`, `"America/New_York"`). Required by the React client to determine local posting window status.

**Response — unauthenticated**
```json
{ "authenticated": false }
```

---

### POST /api/login.php

**Request body** (`application/x-www-form-urlencoded` or `application/json`)
```json
{
  "username": "string",
  "password": "string"
}
```

**Response — 200 OK**
```json
{
  "username": "string",
  "name": "string",
  "is_admin": false,
  "status": "validated"
}
```

**Error Responses**
| Code | Condition |
|---|---|
| 400 | Missing username or password |
| 401 | Invalid credentials (wrong username or password) |
| 403 | Account pending approval |
| 405 | Non-POST request |

---

### POST /api/logout.php

No request body required.

**Response — 200 OK**
```json
{ "message": "Logged out." }
```

---

## Registration Endpoints (US-1)

### GET /api/captcha-question.php

Returns a random captcha question. Also stores the question index in the session.

**Response — 200 OK**
```json
{
  "index": 12,
  "question": "What is 6 × 7?"
}
```
> `index` must be submitted as `captcha_index` with the registration form.
> The answer (`a`) is **never** included in the response.

---

### POST /api/register.php

**Request body** (`application/x-www-form-urlencoded` or `application/json`)
```json
{
  "username": "string — 3–30 chars, alphanumeric + underscore",
  "name": "string — display name",
  "substack_url": "string — optional",
  "email": "string — valid email",
  "password": "string — min 8 chars",
  "timezone": "string — IANA timezone (e.g. Europe/London)",
  "captcha_answer": "string — answer from captcha question"
}
```

**Response — 201 Created**
```json
{ "message": "Registration submitted. Awaiting admin approval." }
```

**Error Responses**
| Code | Condition |
|---|---|
| 405 | Non-POST request |
| 422 | Validation failure — body: `{"errors": {"field": "message"}}` |
| 409 | Conflict — body: `{"field": "username|email", "error": "..."}` |
| 423 | Registration closed (today is the event date) |

---

## Admin Endpoints (US-2) — require admin session

### GET /api/admin/users.php

Returns all users ordered by name ASC. Password hash is never included.

**Response — 200 OK**
```json
[
  {
    "id": 1,
    "username": "alice",
    "name": "Alice Example",
    "email": "alice@example.com",
    "timezone": "Europe/London",
    "status": "validated",
    "is_admin": 0,
    "created_at": "2026-08-24 07:00:00"
  }
]
```

---

### POST /api/admin/users.php

Create a user directly (status = validated).

**Request body**
```json
{
  "username": "string",
  "name": "string",
  "substack_url": "string — optional",
  "email": "string",
  "password": "string — min 8 chars",
  "timezone": "string — IANA",
  "is_admin": 0
}
```

**Response — 201 Created**
```json
{ "message": "User created." }
```

---

### PUT /api/admin/users.php?id=N

Update a user's fields.

**Request body** (only include fields to change)
```json
{
  "name": "string",
  "email": "string",
  "timezone": "string",
  "status": "pending|validated",
  "is_admin": 0
}
```

**Response — 200 OK**
```json
{ "message": "User updated." }
```

**Error Responses**
| Code | Condition |
|---|---|
| 400 | Removing own admin flag; no fields supplied |
| 403 | Not admin |

---

### DELETE /api/admin/users.php?id=N

**Response — 200 OK**
```json
{ "message": "User deleted." }
```

**Error Responses**
| Code | Condition |
|---|---|
| 400 | Deleting own account; deleting last admin |
| 403 | Not admin |

---

### GET /api/admin/validate.php?id=N&token=X

Validates a user from the email link. HMAC token = `hash_hmac('sha256', userId, APP_SECRET)`.

**Response — 200 OK** (test mode) / **302 redirect** to `/admin/?validated=1` (production)
```json
{ "message": "User validated.", "username": "string" }
```

**Error Responses**
| Code | Condition |
|---|---|
| 400 | Missing id or token |
| 401 | Invalid HMAC token or unknown user |

---

### GET /api/admin/settings.php

**Response — 200 OK**
```json
{ "event_date": "2026-08-24" }
```
> `event_date` is `null` if not yet configured.

---

### POST /api/admin/settings.php

**Request body**
```json
{ "event_date": "YYYY-MM-DD" }
```

**Response — 200 OK**
```json
{ "message": "Event date saved.", "event_date": "2026-08-24" }
```

**Error Responses**
| Code | Condition |
|---|---|
| 403 | Not admin |
| 422 | Invalid date format |

---

## Admin Submissions Endpoints (US-3) — require admin session

### GET /api/admin/submissions.php

Returns all photos with user info, newest first.

**Response — 200 OK**
```json
[
  {
    "id": 1,
    "username": "alice",
    "name": "Alice Example",
    "filename": "abc123.jpg",
    "description": "Morning light",
    "posted_at": "2026-08-24 09:00:00"
  }
]
```

Image URL: `/uploads/{username}/{filename}`

---

### GET /api/admin/export.php

Streams a ZIP archive of all photos grouped by photographer.

**Response — 200 OK**
- `Content-Type: application/zip`
- `Content-Disposition: attachment; filename="aday-all-photos.zip"`

ZIP structure:
```
{username}/
  photo1.jpg
  photo2.jpg
  descriptions.txt   ← "filename: description\n" per photo, posted_at ASC
```

**Error Responses**
| Code | Condition |
|---|---|
| 403 | Not admin |
| 500 | ZipArchive extension not available |

---

## Photo Endpoints (US-4 / US-5) — POST requires validated session, GET is public

### POST /api/photos.php

Upload a photo. Multipart form data.

**Request** (`multipart/form-data`)
```
photo       — file field (JPEG/PNG/WEBP, max 15 MB)
description — string (no max length)
```

**Response — 201 Created**
```json
{
  "id": 1,
  "filename": "abc123.jpg",
  "posted_at": "2026-08-24 09:00:00"
}
```

Image served at: `/uploads/{username}/{filename}`

**Error Responses**
| Code | Condition |
|---|---|
| 401 | Unauthenticated |
| 403 | Account not validated, or posting window closed |
| 422 | Invalid file (wrong MIME type, exceeds 15 MB) |
| 503 | Event date not configured |

---

### GET /api/photos.php

Public feed endpoint with cursor pagination.

**Query Parameters**
| Param | Type | Description |
|---|---|---|
| `before` | ISO 8601 datetime | Return photos posted before this timestamp (newest-first page) |
| `after` | ISO 8601 datetime | Return photos posted after this timestamp (for polling — newest first in response) |
| `limit` | int | Max photos to return (default 20, max 50) |

If no params: returns latest 20 photos, newest first.

**Response — 200 OK**
```json
{
  "photos": [
    {
      "id": 1,
      "username": "alice",
      "name": "Alice Example",
      "substack_url": "https://alice.substack.com",
      "filename": "abc123.jpg",
      "description": "Morning light in the garden",
      "posted_at": "2026-08-24 09:00:00"
    }
  ],
  "next_cursor": "2026-08-24 08:30:00"
}
```

> `next_cursor` is the `posted_at` of the oldest photo in the response, or `null` if no more photos exist.
> Use `?before=<next_cursor>` to load the next page.
> Use `?after=<latest_posted_at>` for polling (returns new photos since that timestamp).
>
> **Polling note:** When using `?after=`, the React client should track the newest `posted_at` it has seen in a `useRef` and use that as the next `after` value. The `next_cursor` field reflects the oldest photo in the response and is not meaningful for the polling variant — clients should ignore it when polling.

---

## Status Endpoint (US-4)

### GET /api/status.php

Public. Returns the current event date and posting window status.

**Response — 200 OK (unauthenticated)**
```json
{
  "window_open": null,
  "event_date": "2026-08-24",
  "message": "Posting window status unknown (not authenticated)."
}
```
> `window_open` is `null` when unauthenticated (cannot compute without user timezone).

**Response — 200 OK (authenticated)**
```json
{
  "window_open": true,
  "event_date": "2026-08-24",
  "message": "Posting window is open"
}
```

**Response — event not configured**
```json
{
  "window_open": false,
  "event_date": null,
  "message": "Event not yet scheduled"
}
```

---

## Photographer Endpoints (US-6) — Public

### GET /api/photographers.php

Returns all validated photographers ordered A–Z (case-insensitive).

**Response — 200 OK**
```json
[
  {
    "username": "alice",
    "name": "Alice Example",
    "substack_url": "https://alice.substack.com",
    "photo_count": 3
  }
]
```

---

### GET /api/photographers.php?username=alice

Returns a single photographer's profile + photos.

**Response — 200 OK**
```json
{
  "username": "alice",
  "name": "Alice Example",
  "substack_url": "https://alice.substack.com",
  "photos": [
    {
      "id": 1,
      "filename": "abc123.jpg",
      "description": "Morning light",
      "posted_at": "2026-08-24 09:00:00"
    }
  ]
}
```

**Error Responses**
| Code | Condition |
|---|---|
| 404 | Username not found or not validated |

---

## Static File Serving

Photos are served as static files directly by the web server:

```
/uploads/{username}/{filename}
```

No PHP proxy — the web server must have directory access to `uploads/`.

---

## Common Error Envelope

All error responses use:
```json
{ "error": "Human-readable message." }
```

Or for validation failures:
```json
{ "errors": { "field_name": "Error message." } }
```

---

## Session

- Cookie name: `aday_session`
- Flags: Secure (prod), HttpOnly, SameSite=Lax
- Session fixation: `session_regenerate_id(true)` called on login (production only)
- No "remember me" / JWT — session-only for this event scope
