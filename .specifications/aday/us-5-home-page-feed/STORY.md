# US-5: Home Page — Live Feed

**Feature**: aday-photo-publishing

## User Story

**As a** visitor  
**I can** view all photos in reverse chronological order on the home page  
**So that** I see the event stream as it unfolds

## Acceptance Criteria

1. **Given** home page is open, **When** a new photo is posted, **Then** it appears in the feed within 1 minute (no full page reload)
2. **Given** multiple photographers post, **When** home page loads, **Then** all photos are interleaved in reverse `posted_at` order
3. **Given** more than 20 photos exist, **When** user scrolls to bottom, **Then** older photos load (infinite scroll or "Load more")

## Assumptions (approved defaults)

| # | Assumption |
|---|---|
| Refresh | React fetches new photos via `GET /api/photos.php?after=<last_id>` every 60 seconds using `setInterval`. Only the photo list re-renders — no full page reload. |
| Initial load | First render fetches latest 20 photos (`GET /api/photos.php?limit=20`). Older photos loaded via cursor pagination (`?before=<iso8601>&limit=20`). |
| Public access | Home page is publicly accessible — no login required. |

## Backend Implementation (PHP)

Uses `GET /api/photos.php` defined in US-4 (cursor pagination by `posted_at`).

**Additional param for polling new photos**:
- `GET /api/photos.php?after=<iso8601>&limit=50` → photos posted AFTER the timestamp (for incremental poll)

```sql
-- "after" variant (new photos since last check):
SELECT ... FROM photos p JOIN users u ON u.id = p.user_id
WHERE p.posted_at > :after
ORDER BY p.posted_at DESC
LIMIT :limit
```

Response shape (both variants):
```json
{
  "photos": [
    {
      "id": 42,
      "username": "alice",
      "name": "Alice Smith",
      "substack_url": "https://alice.substack.com",
      "filename": "a1b2.jpg",
      "description": "Morning light over the harbour...",
      "posted_at": "2024-10-05T08:14:22Z"
    }
  ],
  "next_cursor": "2024-10-05T07:00:00Z"
}
```

## Frontend Implementation (React)

**Files**:
- `src/pages/HomePage.tsx` — main feed page
- `src/components/PhotoCard.tsx` — single photo card (image, photographer name + substack link, description, timestamp)
- `src/components/PhotoFeed.tsx` — list of `PhotoCard`; manages state and polling

**Polling logic** (in `PhotoFeed`):
```tsx
const [photos, setPhotos] = useState<Photo[]>([]);
const latestTimestamp = useRef<string | null>(null);

useEffect(() => {
  fetchInitial(); // GET /api/photos.php?limit=20

  const id = setInterval(async () => {
    if (!latestTimestamp.current) return;
    const newPhotos = await fetchAfter(latestTimestamp.current);
    if (newPhotos.length > 0) {
      setPhotos(prev => [...newPhotos, ...prev]);
      latestTimestamp.current = newPhotos[0].posted_at;
    }
  }, 60_000);

  return () => clearInterval(id);
}, []);
```

**"Load more"** (pagination):
- Button at bottom of feed: `GET /api/photos.php?before=<oldest_posted_at>&limit=20`
- Appends to bottom of `photos` array

**`PhotoCard`** layout:
- Image: `<img src="/uploads/{username}/{filename}" loading="lazy" />`
- Photographer: link to `/photographers/{username}` + Substack URL (external link)
- Description: full text (no truncation on home page)
- Timestamp: formatted in visitor's local time via `Intl.DateTimeFormat`

## Test Coverage

**Unit (PHP)**:
- `?after=T` returns only photos posted after T
- `?before=T&limit=20` returns correct page
- Empty feed → `{photos: [], next_cursor: null}`
- Photos from multiple users interleaved by posted_at

**Unit (React)**:
- Initial fetch renders photo cards
- Polling inserts new photos at top without removing existing
- "Load more" appends older photos at bottom
- `PhotoCard` renders image, name, substack link, description, timestamp

**Edge Cases**:
- Poll fires when feed is empty → no state change, no error
- Two photos posted with identical `posted_at` → deterministic order (add `id DESC` as tiebreaker in SQL)
- Very long description → no truncation, scrolls naturally
- Image fails to load → `<img>` onerror shows placeholder

## Technical Risks

- **Clock skew between posts**: `posted_at` stored in UTC; all comparisons UTC — no skew risk
- **Poll overlap**: If previous poll is still in-flight when next fires, skip (use `isPolling` ref guard)

## Implementation Phases

1. `GET /api/photos.php?after=` variant (backend addition to US-4 endpoint)
2. `PhotoCard` component
3. `PhotoFeed` component with polling
4. `HomePage` page wrapper
5. "Load more" pagination
6. Tests
