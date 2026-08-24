# Tasks for US-2: Admin — User Management & Event Config

---

## Phase 1: One-Time Setup Page
**Agent:** backend-dev

### Tasks
1. Create `setup.php`
   - [ ] On GET: check `settings.setup_complete` → if set, return 403 "Setup already complete"
   - [ ] Render form: username + password fields
   - [ ] On POST: validate username (3–30 chars, alphanumeric+underscore) and password (min 8 chars)
   - [ ] Insert admin user: `status = validated`, `is_admin = 1`, `password_hash` bcrypt
   - [ ] Write `setup_complete = 1` to `settings`
   - [ ] Redirect to `/admin/` with success flash

2. Create `scripts/reset_admin.php` — CLI only
   - [ ] Refuse to run if called via HTTP (check `php_sapi_name() !== 'cli'`)
   - [ ] Delete `setup_complete` row from `settings`
   - [ ] Print confirmation

---

## Phase 2: Auth Guard
**Agent:** backend-dev

### Tasks
3. Extend `lib/Auth.php`
   - [ ] `requireAdmin(): array` — starts session, loads user by `$_SESSION['user_id']`, asserts `is_admin = 1`; sends JSON 403 if not; returns user row
   - [ ] `requireValidated(): array` — asserts `status = validated`; returns user row
   - [ ] `currentUser(): ?array` — returns user row or null (no side-effects)

---

## Phase 3: Admin API Endpoints
**Agent:** backend-dev

### Tasks
4. Create `api/admin/users.php`
   - [ ] All methods: call `Auth::requireAdmin()` first
   - [ ] `GET` → return all users (id, username, name, email, timezone, status, is_admin, created_at); ordered by name ASC
   - [ ] `POST {username, name, substack_url, email, password, timezone, is_admin}` → insert user with `status = validated`; return 201
   - [ ] `PUT ?id=N {name, email, timezone, status, is_admin}` → update; return 200; block deleting own admin flag
   - [ ] `DELETE ?id=N` → delete; 400 if deleting self; 400 if deleting last admin (check count of `is_admin = 1` rows)

5. Create `api/admin/validate.php`
   - [ ] `GET ?token=X` — compute expected HMAC: `hash_hmac('sha256', $userId, APP_SECRET)` for each pending user
   - [ ] Compare with `hash_equals`; 401 if no match
   - [ ] Set matched user `status = validated`
   - [ ] Redirect to `/admin/?validated=1`

6. Create `api/admin/settings.php`
   - [ ] `GET` → return `{event_date: '...'}` (null if not set)
   - [ ] `POST {event_date: 'YYYY-MM-DD'}` → validate format; upsert into `settings`; return 200

---

## Phase 4: Frontend Admin UI
**Agent:** frontend-dev

### Tasks
7. Create `src/pages/AdminPage.tsx` — tabbed layout: Users | Event Date | Submissions (US-3)

8. Create `src/components/admin/UserTable.tsx`
   - [ ] Fetch `GET /api/admin/users.php` on mount
   - [ ] Columns: username, name, email, timezone, status badge, is_admin toggle, actions
   - [ ] "Approve" button (visible for `status = pending`): `PUT` with `{status: 'validated'}`
   - [ ] "Edit" button → opens `UserModal`
   - [ ] "Delete" button → confirm dialog; disabled for own account

9. Create `src/components/admin/UserModal.tsx`
   - [ ] Mode: Add (POST) or Edit (PUT)
   - [ ] Fields: username (add only), name, substack_url, email, password (add only), timezone, is_admin checkbox
   - [ ] Inline validation; submit on confirm

10. Create `src/components/admin/EventDatePanel.tsx`
    - [ ] Fetch current event date on mount
    - [ ] Date picker input; on save: `POST /api/admin/settings.php`
    - [ ] Warning if date is in the past

---

## Phase 5: Tests
**Agent:** testing

### Tasks
11. PHP unit tests (`tests/SetupTest.php`)
    - [ ] First visit: form renders
    - [ ] Submit valid → admin user created, `setup_complete` set, redirect
    - [ ] Second visit → 403

12. PHP unit tests (`tests/AdminUsersTest.php`)
    - [ ] `requireAdmin` blocks unauthenticated → 403
    - [ ] `requireAdmin` blocks non-admin → 403
    - [ ] GET list returns all users
    - [ ] POST creates validated user
    - [ ] DELETE self → 400
    - [ ] DELETE last admin → 400
    - [ ] HMAC token validates → status = validated
    - [ ] Invalid HMAC → 401
    - [ ] Event date upsert → row in settings

13. React unit tests
    - [ ] `UserTable` renders status badges correctly
    - [ ] "Approve" button absent for validated users
    - [ ] `UserModal` (Add) submits correct payload
    - [ ] `EventDatePanel` pre-fills existing date; shows past-date warning

---

## Technical Notes

- **Order**: Phase 1 (setup.php) must run before any admin session exists. Phase 2 (Auth) is a dependency for all Phase 3 endpoints.
- **Coordination**: `lib/Auth.php` is shared with US-4 and US-7 — define all three helpers here to avoid rework.
- **HMAC token**: `APP_SECRET` must be in `.env` (set in US-1 Phase 1). Validate token on both `user_id` and current timestamp is NOT used — token is permanent (acceptable for 1-day event scope).
- **Legacy-risk**: `DELETE` endpoint must query remaining admin count in same transaction to prevent race condition (SQLite serialises writes in WAL).
