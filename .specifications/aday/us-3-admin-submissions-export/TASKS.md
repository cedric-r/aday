# Tasks for US-3: Admin — Submission Monitor & Export

---

## Phase 1: Backend — Submissions & Export
**Agent:** backend-dev

### Tasks
1. Create `lib/Exporter.php`
   - [ ] Method `buildZip(): string` — returns path to temp ZIP file
   - [ ] Query all photographers who have photos; iterate in username ASC order
   - [ ] For each photographer: create subfolder `{username}/` in ZIP
   - [ ] Add each image file: `addFile("uploads/{username}/{filename}", "{username}/{filename}")`
   - [ ] Generate `descriptions.txt` content: `{filename}: {description}\n` per photo ordered by `posted_at ASC`
   - [ ] Add `descriptions.txt` via `addFromString("{username}/descriptions.txt", $txt)`
   - [ ] Skip missing files on disk (log warning; add `[file missing]` note in descriptions.txt for that entry)
   - [ ] Close ZipArchive; return temp file path

2. Create `api/admin/submissions.php` — GET
   - [ ] Call `Auth::requireAdmin()`
   - [ ] Query all photos JOIN users; order by `posted_at DESC`
   - [ ] Return JSON array: `[{id, username, name, filename, description, posted_at}]`

3. Create `api/admin/export.php` — GET
   - [ ] Call `Auth::requireAdmin()`
   - [ ] Call `Exporter::buildZip()`
   - [ ] Stream ZIP: `Content-Type: application/zip`, `Content-Disposition: attachment; filename="aday-all-photos.zip"`
   - [ ] Use `ob_end_clean()` + `readfile($path)` pattern
   - [ ] Unlink temp file after stream

---

## Phase 2: Frontend — Submissions Panel
**Agent:** frontend-dev

### Tasks
4. Create `src/components/admin/SubmissionsPanel.tsx`
   - [ ] Fetch `GET /api/admin/submissions.php` on mount
   - [ ] Poll every 60 s via `setInterval`; insert new entries at top
   - [ ] Table columns: photographer name + username, description snippet (first 80 chars), posted_at (formatted local time), thumbnail (`<img>` 60×60)
   - [ ] Toggle: flat view (all) / grouped by photographer
   - [ ] "Export All" button at top → `window.location.href = '/api/admin/export.php'`
   - [ ] Show total photo count and per-photographer count in grouped view

---

## Phase 3: Tests
**Agent:** testing

### Tasks
5. PHP unit tests (`tests/ExporterTest.php`)
   - [ ] Single photographer with 2 photos → ZIP contains `{username}/img1.jpg`, `{username}/img2.jpg`, `{username}/descriptions.txt`
   - [ ] `descriptions.txt` lists entries in posted_at ASC order with correct `filename: description` format
   - [ ] Two photographers → ZIP contains two subfolders
   - [ ] Photographer with no photos → no subfolder (omitted)
   - [ ] Missing file on disk → `[file missing]` in descriptions.txt, no crash
   - [ ] Empty event (no photos at all) → valid empty ZIP

6. PHP unit tests (`tests/SubmissionsTest.php`)
   - [ ] `GET /api/admin/submissions.php` without admin session → 403
   - [ ] Returns correct photo+user data
   - [ ] `GET /api/admin/export.php` without admin session → 403
   - [ ] Export streams ZIP with correct Content-Type header

7. React unit tests (`src/components/admin/SubmissionsPanel.test.tsx`)
   - [ ] Renders submission rows from API response
   - [ ] "Export All" button href targets `/api/admin/export.php`
   - [ ] Polling re-fetches after 60 s (mock `setInterval`)

---

## Technical Notes

- **Order**: Depends on `photos` table (US-4 Phase 1) and `Auth::requireAdmin()` (US-2 Phase 2). Exporter can be built and unit-tested against fixture files independently.
- **Temp file**: Write to `sys_get_temp_dir()`, not to project directory. Clean up in `finally` block.
- **Streaming**: Must call `ob_end_clean()` to flush any previous output before `readfile()`; headers must not have been sent.
- **Legacy-risk**: PHP `ZipArchive` extension must be enabled; verify in `phpinfo()` or `extension_loaded('zip')` check at startup.
