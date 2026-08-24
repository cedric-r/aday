# US-6: Photographer Index

**Feature**: aday-photo-publishing

## User Story

**As a** visitor  
**I can** browse an alphabetical index of all participants and visit individual photographer pages  
**So that** I can find and follow specific photographers

## Acceptance Criteria

1. **Given** index page visited, **When** it loads, **Then** all `validated` users listed A–Z by `name`, each with a link to their individual page
2. **Given** individual photographer page visited, **When** it loads, **Then** photographer's name, Substack URL, and all their photos + descriptions shown (reverse chronological)
3. **Given** Substack URL stored, **When** shown on index or individual page, **Then** displayed as a clickable external link

## Assumptions (approved defaults)

| # | Assumption |
|---|---|
| Substack URL | Displayed publicly on both index page and individual photographer page. Opens in new tab. |
| Visibility | Only `status = validated` users appear in the index. Pending users are not listed. |
| Public access | Index and photographer pages are publicly accessible — no login required. |

## Backend Implementation (PHP)

**Files**:
- `api/photographers.php`:
  - `GET` → list all validated photographers, ordered by `name ASC`
  - `GET ?username=X` → single photographer profile + their photos

**`GET /api/photographers.php`** response:
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
```sql
SELECT username, name, substack_url,
       (SELECT COUNT(*) FROM photos WHERE user_id = u.id) AS photo_count
FROM users u
WHERE status = 'validated'
ORDER BY name ASC COLLATE NOCASE
```

**`GET /api/photographers.php?username=X`** response:
```json
{
  "username": "alice",
  "name": "Alice Smith",
  "substack_url": "https://alice.substack.com",
  "photos": [
    {
      "id": 42,
      "filename": "a1b2.jpg",
      "description": "Morning light over the harbour...",
      "posted_at": "2024-10-05T08:14:22Z"
    }
  ]
}
```
```sql
SELECT p.id, p.filename, p.description, p.posted_at
FROM photos p
WHERE p.user_id = (SELECT id FROM users WHERE username = :username AND status = 'validated')
ORDER BY p.posted_at DESC
```
Returns 404 if username not found or not validated.

## Frontend Implementation (React)

**Files**:
- `src/pages/IndexPage.tsx` — alphabetical photographer list
- `src/pages/PhotographerPage.tsx` — individual photographer view

**`IndexPage`**:
- Fetches `GET /api/photographers.php` on mount
- Renders A–Z grouped list (group by first letter of `name`)
- Each entry: `{name}` (link to `/photographers/{username}`) + Substack link icon/text
- Shows photo count as a small badge

**`PhotographerPage`** (route: `/photographers/:username`):
- Fetches `GET /api/photographers.php?username={username}` on mount
- Header: photographer name, Substack link (`target="_blank" rel="noopener noreferrer"`)
- Gallery: `PhotoCard` components (reused from US-5) in reverse chronological order
- 404 page if API returns 404

**Routing**: React Router routes:
- `/` → `HomePage`
- `/index` → `IndexPage`
- `/photographers/:username` → `PhotographerPage`
- `/register` → `RegisterPage`
- `/login` → `LoginPage`
- `/post` → `PostPage` (authenticated)
- `/admin` → `AdminPage` (admin-authenticated)

## Test Coverage

**Unit (PHP)**:
- Returns only `validated` users, sorted A–Z (case-insensitive)
- `pending` users absent from list
- `?username=X` returns profile + photos for validated user
- `?username=X` returns 404 for pending user
- `?username=X` returns 404 for unknown username
- Photo count correct in list response

**Unit (React)**:
- `IndexPage` renders grouped A–Z list
- Substack links open in new tab
- `PhotographerPage` renders photos using `PhotoCard`
- 404 case renders error state

**Edge Cases**:
- Photographer with 0 photos → still listed in index, individual page shows empty gallery
- Name starting with non-ASCII character → sorted to end (SQLite `COLLATE NOCASE` is ASCII-only; document limitation)
- Very long Substack URL → truncated display, full URL in href

## Technical Risks

- **Non-ASCII sort order**: SQLite `COLLATE NOCASE` is ASCII-only; names starting with accented characters (é, ñ) may sort unexpectedly. Mitigate — document as known limitation; acceptable for 1-day event

## Implementation Phases

1. `api/photographers.php` (list + single)
2. `IndexPage` + A–Z grouping
3. `PhotographerPage` (reuses `PhotoCard`)
4. Routing configuration
5. Tests
