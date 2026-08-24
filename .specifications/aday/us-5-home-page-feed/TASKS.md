# Tasks for US-5: Home Page — Live Feed

---

## Phase 1: Backend — Polling Variant
**Agent:** backend-dev

### Tasks
1. Extend `api/photos.php` — GET `?after=<iso8601>` support
   - [ ] If `after` param present: `WHERE posted_at > :after ORDER BY posted_at ASC LIMIT :limit` (return newest-first in response by reversing in PHP)
   - [ ] Response: `{photos: [...], next_cursor: <oldest_posted_at> | null}`
   - [ ] Tiebreaker: `ORDER BY posted_at DESC, id DESC` for `before` variant; `posted_at ASC, id ASC` for `after` variant
   - [ ] No auth required (public endpoint)

---

## Phase 2: Frontend — Home Feed
**Agent:** frontend-dev

### Tasks
2. Create `src/components/PhotoCard.tsx`
   - [ ] Props: `{photo: Photo}` where `Photo = {id, username, name, substack_url, filename, description, posted_at}`
   - [ ] Image: `<img src="/uploads/{username}/{filename}" loading="lazy" alt={name} />`; `onerror` → show placeholder div
   - [ ] Photographer: link to `/photographers/{username}`
   - [ ] Substack: external link (`target="_blank" rel="noopener noreferrer"`)
   - [ ] Description: full text, no truncation
   - [ ] Timestamp: `Intl.DateTimeFormat(undefined, {dateStyle:'medium', timeStyle:'short'}).format(new Date(posted_at))`

3. Create `src/components/PhotoFeed.tsx`
   - [ ] Initial fetch: `GET /api/photos.php?limit=20` on mount
   - [ ] Store photos in state; store latest `posted_at` in `useRef`
   - [ ] Polling: `setInterval` every 60 000 ms; guard with `isPollingRef` to skip if previous poll in-flight
   - [ ] On poll response: prepend new photos to state; update `latestTimestamp` ref
   - [ ] Cleanup: `clearInterval` on unmount
   - [ ] "Load more" button at bottom: `GET /api/photos.php?before=<oldest_posted_at>&limit=20`; append to state

4. Create `src/pages/HomePage.tsx`
   - [ ] Render page title / intro text
   - [ ] Render `<PhotoFeed />`
   - [ ] No auth required

---

## Phase 3: Tests
**Agent:** testing

### Tasks
5. PHP unit tests (`tests/PhotoFeedTest.php`)
   - [ ] `?after=T` returns only photos posted after T, ordered correctly
   - [ ] `?before=T&limit=20` returns correct page in reverse chrono
   - [ ] Empty result → `{photos: [], next_cursor: null}`
   - [ ] Photos from multiple users interleaved by posted_at
   - [ ] Tiebreaker: two photos same timestamp → lower id comes second (DESC)

6. React unit tests
   - [ ] `PhotoCard` renders image, name link, substack link, description, timestamp
   - [ ] `PhotoCard` shows placeholder on image error
   - [ ] `PhotoFeed` renders cards from initial fetch
   - [ ] `PhotoFeed` poll prepends new cards without removing existing
   - [ ] `PhotoFeed` "Load more" appends older cards at bottom
   - [ ] `PhotoFeed` poll guard: second poll does not fire while first in-flight

---

## Technical Notes

- **Order**: Phase 1 extends the endpoint from US-4 Phase 3 — must be complete first. Phase 2 can start as soon as the GET endpoint (cursor variant) exists.
- **Polling guard**: Use `useRef<boolean>` (`isPolling`) set to true on fetch start, false on completion — prevents parallel in-flight requests.
- **`PhotoCard` reuse**: Exported and reused by `PhotographerPage` (US-6) and `SubmissionsPanel` (US-3 thumbnail).
- **Image URLs**: Served as static files from `uploads/`. No PHP proxy needed unless `.htaccess` restricts directory listing.
