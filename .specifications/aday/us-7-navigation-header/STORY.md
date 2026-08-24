# US-7: Navigation & Header

**Feature**: aday-photo-publishing

## User Story

**As a** visitor  
**I can** use a consistent header and navigation menu across the site  
**So that** I can navigate between Home, Index, Login, and Admin

## Acceptance Criteria

1. **Given** any page, **When** it renders, **Then** header contains logo and menu items: Home, Index, Log in (or Log out if authenticated)
2. **Given** logged-in admin user, **When** any page renders, **Then** "Admin" menu item also appears in the menu
3. **Given** authenticated non-admin user, **When** any page renders, **Then** "Post" menu item appears (link to photo posting page); Admin item absent
4. **Given** visitor clicks "Log out", **When** confirmed, **Then** session destroyed and redirect to home page

## Assumptions (approved defaults)

| # | Assumption |
|---|---|
| Auth | Cookie sessions only. Site runs on HTTPS — session cookie must be set with `Secure` + `HttpOnly` + `SameSite=Lax` flags. Session contains `user_id`; header component fetches `GET /api/me.php` to determine auth state + role. |
| Logo | Image file at `public/assets/logo.png`. Placeholder used during development. |
| Admin item | Visible only to users with `is_admin = 1`. |

## Backend Implementation (PHP)

**Files**:
- `api/me.php` — `GET` → returns current session user (or `{authenticated: false}`)
- `api/logout.php` — `POST` → destroys session, returns 200
- `api/login.php` — `POST {username, password}` → validates credentials, creates session, returns user object

**`GET /api/me.php`** responses:
```json
// authenticated
{"authenticated": true, "username": "alice", "name": "Alice Smith", "is_admin": false, "status": "validated"}

// unauthenticated
{"authenticated": false}
```

**`POST /api/login.php`**:
```
1. Load user by username
2. password_verify($pass, $user->password_hash)
3. Check status = 'validated' → 403 "Account pending approval" if not
4. session_regenerate(true)
5. $_SESSION['user_id'] = $user->id
6. Cookie flags: Secure, HttpOnly, SameSite=Lax (set via session_set_cookie_params before session_start)
7. Return 200 + user object
```

**`POST /api/logout.php`**:
```php
session_start();
session_destroy();
return 200;
```

## Frontend Implementation (React)

**Files**:
- `src/components/Header.tsx` — logo + `Nav` component
- `src/components/Nav.tsx` — menu items, auth-aware
- `src/context/AuthContext.tsx` — provides `{user, isLoading, logout}` to all pages
- `src/pages/LoginPage.tsx` — login form

**`AuthContext`**:
```tsx
// On app mount: GET /api/me.php → set user state
// Provides: user | null, isLoading, login(username, password), logout()
// login() → POST /api/login.php → update user state
// logout() → POST /api/logout.php → set user = null → navigate to /
```

**`Nav` menu items** (conditional):
| Item | Shown when |
|------|-----------|
| Home | always |
| Index | always |
| Log in | `user === null` |
| Post | `user !== null && !user.is_admin` |
| Admin | `user?.is_admin === true` |
| Log out | `user !== null` |

**`LoginPage`**:
- Fields: username, password
- On submit: calls `AuthContext.login()`
- On 403 (pending): shows "Account pending approval" message
- On 401 (wrong credentials): shows "Invalid username or password"
- On success: redirects to home (or previous page via `location.state`)

**Active link styling**: Use React Router `NavLink` with active class for current route.

## Test Coverage

**Unit (PHP)**:
- `me.php` returns user data for valid session
- `me.php` returns `{authenticated: false}` for no session
- `login.php` valid credentials → 200 + session set
- `login.php` wrong password → 401
- `login.php` pending user → 403
- `login.php` unknown username → 401
- `logout.php` destroys session

**Unit (React)**:
- `Nav` shows "Log in" when unauthenticated
- `Nav` shows "Log out" + "Post" when authenticated non-admin
- `Nav` shows "Log out" + "Admin" when admin
- `LoginPage` shows 403 message for pending user
- Logout clears user state and navigates home

**Edge Cases**:
- Session expired mid-session: `me.php` returns `{authenticated: false}` → AuthContext clears user → protected pages redirect to login
- Admin user visits `/post` directly → redirect to `/admin` (or allow — `/post` checks `requireValidated`, not admin-specific)

## Technical Risks

- **`me.php` called on every page mount**: Adds 1 round-trip on load. Acceptable for this scale; cache in React state for session duration.

## Implementation Phases

1. `api/login.php` + `api/logout.php` + `api/me.php`
2. `AuthContext` + session fetch
3. `Header` + `Nav` components
4. `LoginPage`
5. Protected route wrapper (redirects unauthenticated users from `/post` and `/admin`)
6. Tests
