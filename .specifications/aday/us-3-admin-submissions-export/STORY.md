# US-3: Admin — Submission Monitor & Export

**Feature**: aday-photo-publishing

## User Story

**As an** admin user  
**I can** monitor live submissions and export photos + descriptions per photographer  
**So that** I can oversee the event and produce per-photographer packages at close

## Acceptance Criteria

1. **Given** admin visits submissions view, **When** photos are posted, **Then** admin sees all submissions updating in real time (≤1 min lag)
2. **Given** admin triggers export for a photographer, **When** download completes, **Then** a ZIP is downloaded containing that photographer's images and a `descriptions.txt`
3. **Given** admin triggers "Export All", **When** download completes, **Then** a ZIP containing one sub-folder per photographer is downloaded

## Assumptions (approved defaults)

| # | Assumption |
|---|---|
| Export format | Single ZIP containing one subfolder per photographer (`{username}/`). Each subfolder contains the photographer's image files + a `descriptions.txt` listing entries as `{filename}: {description}` ordered by `posted_at`. One download covers all photographers. |
| Monitor refresh | React polls `GET /api/admin/submissions.php` every 60 seconds (same pattern as home page). |
| Auth | All endpoints guarded by `Auth::requireAdmin()`. |

## Data Model

Uses `photos` table defined in US-4:

```sql
CREATE TABLE photos (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id     INTEGER NOT NULL REFERENCES users(id),
    filename    TEXT NOT NULL,       -- stored filename within uploads/{username}/
    description TEXT NOT NULL,
    posted_at   TEXT NOT NULL DEFAULT (datetime('now'))
);
```

## Backend Implementation (PHP)

**Files**:
- `api/admin/submissions.php` — `GET` → returns all photos joined with username, name; ordered by posted_at DESC
- `api/admin/export.php` — `GET` → streams single ZIP containing all photographers in subfolders
- `lib/Exporter.php` — builds ZipArchive with per-photographer subfolders + `descriptions.txt` per folder

**Export logic** (`Exporter`):
```
Single ZIP for all photographers:
  new ZipArchive → open temp file
  foreach photographer with photos:
    folder = "{username}/"
    foreach photo ordered by posted_at:
      addFile("uploads/{username}/{filename}", "{folder}{filename}")
    generate descriptions.txt:
      "{filename}: {description}\n" per photo, ordered by posted_at
    addFromString("{folder}descriptions.txt", $txt)
  close → stream with Content-Disposition: attachment; filename="aday-all-photos.zip"
```

**Submissions endpoint** response shape:
```json
[
  {
    "id": 1,
    "username": "alice",
    "name": "Alice Smith",
    "filename": "abc123.jpg",
    "description": "...",
    "posted_at": "2024-10-05T14:32:00Z"
  }
]
```

## Frontend Implementation (React)

**Component**: `src/pages/admin/SubmissionsPanel.tsx` (tab/section within AdminPage)

**Features**:
- Table: photographer name, username, filename (thumbnail if feasible), description snippet, posted_at
- Grouped view toggle: flat (all) or grouped by photographer
- Poll every 60 s via `setInterval` + fetch
- "Export All" button at top
- Export triggers `window.location = /api/admin/export.php` (browser handles download of single combined ZIP)

## Test Coverage

**Unit (PHP)**:
- Submissions endpoint returns all photos with joined user data
- Submissions endpoint requires admin → 403 for non-admin
- Export → single ZIP contains one subfolder per photographer with images + descriptions.txt
- Export → descriptions.txt lists correct filename/description pairs in posted_at order
- Export with no photos at all → empty ZIP (not error)
- Photographer with no photos → no subfolder for them in ZIP (or empty subfolder — omit by default)

**Unit (React)**:
- Table renders submission rows
- Export button href correct per user
- Polling re-fetches every 60 s

**Edge Cases**:
- Photographer with photos but some files deleted from disk → skip missing files, include entry in descriptions.txt with `[file missing]` note
- Very large descriptions → no truncation in descriptions.txt
- Export triggered before event starts → returns whatever exists (0 photos = empty ZIP)

## Technical Risks

- **Large ZIP streaming**: PHP `ZipArchive` writes temp file then streams; for very large events (unlikely for 1-day) memory is bounded. Mitigate — use `ob_end_clean()` + `readfile()` pattern.
- **Concurrent export requests**: Temp files are per-request; no shared state issue.

## Implementation Phases

1. `Exporter.php` (core ZIP logic, unit-testable in isolation)
2. `api/admin/submissions.php` endpoint
3. `api/admin/export.php` endpoint
4. React submissions panel + polling + export buttons
5. Tests
