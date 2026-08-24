# Tasks for US-6: Photographer Index

---

## Phase 1: Backend
**Agent:** backend-dev

### Tasks
1. Create `api/photographers.php` — GET (public)
   - [ ] No params: query all `status = validated` users ordered `name ASC COLLATE NOCASE`; include `photo_count` subquery
   - [ ] `?username=X`: query single user (validated only); return profile + photos ordered `posted_at DESC`; 404 if not found or not validated
   - [ ] Response shape (list): `[{username, name, substack_url, photo_count}]`
   - [ ] Response shape (single): `{username, name, substack_url, photos: [{id, filename, description, posted_at}]}`

---

## Phase 2: Frontend — Index & Photographer Pages
**Agent:** frontend-dev

### Tasks
2. Create `src/pages/IndexPage.tsx`
   - [ ] Fetch `GET /api/photographers.php` on mount
   - [ ] Group results by first letter of `name` (uppercase)
   - [ ] Render A–Z section headers; each entry: `{name}` (link to `/photographers/{username}`) + Substack external link + photo count badge
   - [ ] Loading state; empty state ("No participants yet")

3. Create `src/pages/PhotographerPage.tsx`
   - [ ] Route: `/photographers/:username`
   - [ ] Fetch `GET /api/photographers.php?username={username}` on mount
   - [ ] On 404: render `NotFoundPage`
   - [ ] Header: photographer name, Substack link (`target="_blank" rel="noopener noreferrer"`)
   - [ ] Gallery: `<PhotoCard>` per photo (reused from US-5); reverse chronological
   - [ ] Empty gallery state: "No photos yet"

4. Configure React Router routes (if not already done in US-7)
   - [ ] `/` → `HomePage`
   - [ ] `/index` → `IndexPage`
   - [ ] `/photographers/:username` → `PhotographerPage`
   - [ ] `/register` → `RegisterPage`
   - [ ] `/login` → `LoginPage`
   - [ ] `/post` → `PostPage` (protected)
   - [ ] `/admin` → `AdminPage` (admin-protected)
   - [ ] `*` → `NotFoundPage`

5. Create `src/pages/NotFoundPage.tsx`
   - [ ] Simple 404 message with link back to home

---

## Phase 3: Tests
**Agent:** testing

### Tasks
6. PHP unit tests (`tests/PhotographersTest.php`)
   - [ ] List returns only `validated` users, A–Z (case-insensitive)
   - [ ] Pending users absent
   - [ ] `photo_count` correct
   - [ ] `?username=X` returns profile + photos for validated user
   - [ ] `?username=X` returns 404 for pending user
   - [ ] `?username=X` returns 404 for unknown username
   - [ ] User with 0 photos: appears in list, individual page returns empty photos array

7. React unit tests
   - [ ] `IndexPage` renders A–Z grouped list with section headers
   - [ ] Substack links have `target="_blank"`
   - [ ] `PhotographerPage` renders photos via `PhotoCard`
   - [ ] `PhotographerPage` renders `NotFoundPage` on 404

---

## Technical Notes

- **Order**: Depends on `photos` table (US-4) and `PhotoCard` component (US-5). Routing can be set up in parallel with US-7 — coordinate to avoid merge conflict on router config file.
- **Sort limitation**: `COLLATE NOCASE` is ASCII-only in SQLite; names with leading accented characters sort after Z. Document as known limitation.
- **`PhotoCard` reuse**: Import from `src/components/PhotoCard.tsx` (US-5). Do not duplicate.
- **Coordination point**: If US-7 is developed in parallel, agree on a single `src/App.tsx` router file — one agent owns it.
