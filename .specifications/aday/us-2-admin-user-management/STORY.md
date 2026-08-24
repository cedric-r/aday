# US-2: Admin — User Management & Event Config

**Feature**: aday-photo-publishing

## User Story

**As an** admin user  
**I can** validate/manage users and set the event date  
**So that** only approved photographers participate and the event window is correctly defined

## Acceptance Criteria

1. **Given** admin visits admin page, **When** they approve a pending user, **Then** user `status` → `validated` and they may log in
2. **Given** admin visits admin page, **When** they add/edit/delete a user, **Then** changes persist immediately
3. **Given** admin sets event date, **When** saved, **Then** `settings.event_date` updated and all timezone-window calculations use this date
4. **Given** logged-in admin, **When** any page renders, **Then** "Admin" menu item is visible and links to admin page
5. **Given** non-admin user or unauthenticated visitor, **When** they navigate to `/admin`, **Then** 403 or redirect to login

## Assumptions (approved defaults)

| # | Assumption |
|---|---|
| Admin bootstrap | One-time setup page (`/setup.php`) creates the first admin account (username + password chosen at setup time). Page locks itself permanently after first admin is created (writes a `setup_complete` row to `settings`). No `.env` credentials required. |
| Auth | Cookie sessions only; session stores `user_id`. Admin check: load user row, verify `is_admin = 1`. |
| Email token | Validation link from US-1 admin email: `GET /admin/validate.php?token=<hmac>`. HMAC = `hash_hmac('sha256', $userId, APP_SECRET)`. Sets `status = validated`. Single-use by design (re-clicking has no effect once already validated). |

## Data Model

Uses `users` and `settings` tables defined in US-1.

No additional tables. Multiple admins: set `is_admin = 1` on any user row via admin UI.

## Backend Implementation (PHP)

**Files**:
- `setup.php` — one-time setup page; renders form (username + password); on submit: checks `settings.setup_complete` absent, inserts admin user, writes `setup_complete = 1` to `settings`, redirects to `/admin`. If `setup_complete` row exists, returns 403/redirect immediately.
- `admin/index.php` — admin dashboard (requires admin session); renders React admin SPA shell
- `api/admin/users.php` — REST-ish endpoint:
  - `GET` → list all users (id, username, name, email, timezone, status, is_admin, created_at)
  - `POST` → create user (admin-created users get `status = validated` automatically)
  - `PUT ?id=N` → update user fields
  - `DELETE ?id=N` → delete user (cannot delete self)
- `api/admin/validate.php` — `GET ?token=X` → validate HMAC, set user status = validated; redirect to admin page with success message
- `api/admin/settings.php`:
  - `GET` → return `{event_date}`
  - `POST {event_date: 'YYYY-MM-DD'}` → upsert into `settings`
- `lib/Auth.php` — `requireAdmin()` helper: checks session, loads user, asserts `is_admin`; sends 403 JSON or redirects

**Security**:
- All `api/admin/*` endpoints call `Auth::requireAdmin()` at top
- CSRF: include `X-Requested-With: XMLHttpRequest` check (React fetch always sends it); sufficient for SPA pattern
- Token validation: HMAC checked in constant-time (`hash_equals`)
- Password for admin-created users: admin sets it; stored as bcrypt hash

## Frontend Implementation (React)

**Component**: `src/pages/AdminPage.tsx`

**Sections**:

### User Management Panel
- Table: username, name, email, timezone, status (badge), is_admin (toggle), actions (Edit | Delete | Approve)
- "Approve" button visible only for `status = pending` rows → calls `PUT /api/admin/users.php?id=N` with `{status: 'validated'}`
- "Add User" modal: same fields as registration form minus captcha; status defaults to `validated`
- "Edit" modal: pre-filled; can change all fields except username
- "Delete": confirm dialog; disabled for own account

### Event Date Panel
- Date picker; on save: `POST /api/admin/settings.php`
- Shows current event date; highlights if already passed

### State management
- RTK Query (or simple `useState` + fetch) — keep lightweight given vanilla stack
- Optimistic updates for status changes

## Test Coverage

**Unit (PHP)**:
- `setup.php` creates admin when no `setup_complete` setting exists
- `setup.php` returns 403 when `setup_complete` already set
- `requireAdmin()` blocks non-admin → 403
- `requireAdmin()` blocks unauthenticated → 403
- User list returns all rows
- Create user → inserted with `status = validated`
- Delete self → 400
- HMAC token validates correctly → status updated
- Invalid HMAC → 401
- Event date upsert → row present in settings

**Unit (React)**:
- User table renders with correct status badges
- Approve button absent for validated users
- Add user modal submits correct payload
- Event date panel pre-fills existing date

**Edge Cases**:
- Delete last admin → should be blocked (check: if `is_admin = 1` and only one admin row, refuse)
- Event date set to past → admin sees warning but save is allowed
- Validation token replayed → idempotent (already validated, no error)

## Technical Risks

- **CSRF on non-SPA pages**: Admin is SPA — `X-Requested-With` header check mitigates; no need for token-based CSRF for this scope
- **First admin lost**: If setup page was completed but credentials forgotten, provide a CLI reset script (`scripts/reset_admin.php`) that deletes the `setup_complete` setting row, allowing `/setup.php` to run again

## Implementation Phases

1. `setup.php` (one-time admin bootstrap, lock mechanism)
2. `Auth.php` + admin session guard
3. User management API endpoints
4. Settings API endpoint
5. Validate-token endpoint
6. React admin UI (user table + event date panel)
7. Tests
