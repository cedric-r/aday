# Tasks for US-7: Navigation & Header

---

## Phase 1: Backend Auth Endpoints
**Agent:** backend-dev

### Tasks
1. Configure session cookie flags in `config/session.php` (included before all `session_start()` calls)
   - [ ] `session_set_cookie_params(['secure' => true, 'httponly' => true, 'samesite' => 'Lax'])`
   - [ ] `session_name('aday_session')`
   - [ ] Include this file at top of every API entry point

2. Create `api/me.php` — GET (public)
   - [ ] Start session; load user by `$_SESSION['user_id']` if set
   - [ ] Authenticated: return `{authenticated: true, username, name, is_admin, status}`
   - [ ] Unauthenticated: return `{authenticated: false}`
   - [ ] 200 in both cases (not 401)

3. Create `api/login.php` — POST `{username, password}`
   - [ ] Load user by username; if not found → 401 `{error: 'Invalid credentials'}`
   - [ ] `password_verify($password, $user['password_hash'])` → 401 if false
   - [ ] If `status !== 'validated'` → 403 `{error: 'Account pending approval'}`
   - [ ] `session_regenerate(true)`; `$_SESSION['user_id'] = $user['id']`
   - [ ] Return 200 + `{username, name, is_admin, status}`

4. Create `api/logout.php` — POST
   - [ ] `session_start(); session_destroy()`
   - [ ] Return 200 `{message: 'Logged out'}`

---

## Phase 2: Frontend Auth Context & Layout
**Agent:** frontend-dev

### Tasks
5. Create `src/context/AuthContext.tsx`
   - [ ] On mount: `GET /api/me.php` → set `user` state (null if not authenticated)
   - [ ] `login(username, password): Promise<void>` — POST to `/api/login.php`; on 200 update user state; rethrow on 401/403 with error message
   - [ ] `logout(): Promise<void>` — POST to `/api/logout.php`; set user = null; navigate to `/`
   - [ ] Export `useAuth()` hook

6. Create `src/components/Header.tsx`
   - [ ] Logo: `<img src="/assets/logo.png" alt="A Day In The Life" />`; link to `/`
   - [ ] Render `<Nav />`

7. Create `src/components/Nav.tsx`
   - [ ] Use `useAuth()` to get current user
   - [ ] Always show: Home (`/`), Index (`/index`)
   - [ ] Show "Log in" (`/login`) when `user === null`
   - [ ] Show "Post" (`/post`) when `user !== null && !user.is_admin`
   - [ ] Show "Admin" (`/admin`) when `user?.is_admin === true`
   - [ ] Show "Log out" (button → `logout()`) when `user !== null`
   - [ ] Use React Router `<NavLink>` with active class

8. Create `src/pages/LoginPage.tsx`
   - [ ] Fields: username, password
   - [ ] On submit: call `AuthContext.login()`
   - [ ] On 403: show "Your account is pending approval"
   - [ ] On 401: show "Invalid username or password"
   - [ ] On success: redirect to `location.state?.from ?? '/'`

9. Create `src/components/ProtectedRoute.tsx`
   - [ ] Props: `{children, requireAdmin?: boolean}`
   - [ ] If `isLoading`: show spinner
   - [ ] If `user === null`: `<Navigate to="/login" state={{from: location}} />`
   - [ ] If `requireAdmin && !user.is_admin`: `<Navigate to="/" />`
   - [ ] Otherwise: render children

10. Wrap routes in `src/App.tsx`
    - [ ] Wrap `<App>` in `<AuthProvider>`
    - [ ] `/post` → `<ProtectedRoute><PostPage /></ProtectedRoute>`
    - [ ] `/admin` → `<ProtectedRoute requireAdmin><AdminPage /></ProtectedRoute>`
    - [ ] All other routes: public

11. Add `public/assets/logo.png` placeholder
    - [ ] Create `public/assets/` directory
    - [ ] Add placeholder PNG (any image; document that it should be replaced)

---

## Phase 3: Tests
**Agent:** testing

### Tasks
12. PHP unit tests (`tests/AuthTest.php`)
    - [ ] `me.php` returns user data for valid session
    - [ ] `me.php` returns `{authenticated: false}` with no session
    - [ ] `login.php` valid credentials → 200 + user object + session set
    - [ ] `login.php` wrong password → 401
    - [ ] `login.php` unknown username → 401
    - [ ] `login.php` pending user → 403
    - [ ] `logout.php` destroys session

13. React unit tests
    - [ ] `Nav` shows "Log in" when unauthenticated
    - [ ] `Nav` shows "Post" + "Log out" for authenticated non-admin
    - [ ] `Nav` shows "Admin" + "Log out" for admin; "Post" absent
    - [ ] `LoginPage` shows 403 message for pending account
    - [ ] `ProtectedRoute` redirects to `/login` when unauthenticated
    - [ ] `ProtectedRoute` redirects to `/` when non-admin accessing admin route
    - [ ] Logout clears user state and navigates to `/`

---

## Technical Notes

- **Order**: Phase 1 (backend auth) is a hard dependency for all other stories' session handling. Build first. `config/session.php` must be included in US-1, US-2, US-4 endpoints retroactively.
- **Coordination**: `src/App.tsx` router (routes + `AuthProvider` wrapper) is the single most conflict-prone file. US-6 and US-7 both touch it — agree on ownership. Recommendation: US-7 agent owns `App.tsx`; US-6 agent adds routes via PR comment or branch merge.
- **`me.php` always 200**: Do not return 401 for unauthenticated — React calls it on every page load; a 401 triggers browser auth dialogs in some configurations.
- **Session cookie `Secure` flag**: Requires HTTPS in production. For local dev without HTTPS, set `secure = false` in `.env.example` with a dev-only override in `config/session.php`.
