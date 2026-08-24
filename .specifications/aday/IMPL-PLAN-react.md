# React Implementation Plan — A Day In The Life

**Status:** APPROVED
**Branch:** `feature/aday-photo-publishing`
**Agent:** React Developer
**Stories:** US-1, US-2, US-3, US-4, US-5, US-6, US-7

---

## Prerequisites

- [ ] `API-CONTRACT.md` published by PHP Developer — **HALT before writing any implementation code if absent**
- [ ] Gate C approved by Team Lead / user

---

## Implementation Order

AuthContext (US-7) is a hard dependency for all protected routes. Routing (App.tsx) is a hard dependency for navigation. Order must be followed.

| Order | Story | Rationale |
|-------|-------|-----------|
| 1 | Project scaffold | RTK store, router, MUI provider, test setup |
| 2 | US-7 | AuthContext, Header, Nav, Login, ProtectedRoute, App.tsx routing |
| 3 | US-1 | RegisterPage, TimezoneSelect |
| 4 | US-5 | PhotoCard (shared), PhotoFeed, HomePage |
| 5 | US-4 | PostingWindowBanner, PostPage |
| 6 | US-6 | IndexPage, PhotographerPage, NotFoundPage |
| 7 | US-2 | AdminPage, UserTable, UserModal, EventDatePanel |
| 8 | US-3 | SubmissionsPanel |

---

## Cross-Story Coordination

- **`src/App.tsx` ownership:** React Developer owns this file. All routes for all stories are defined here together (US-6 + US-7 both require routes). No two agents edit it separately.
- **`PhotoCard` reuse:** Created once in US-5 (`src/components/PhotoCard.tsx`). Referenced (not copied) by US-6 `PhotographerPage` and US-3 `SubmissionsPanel`.
- **AuthContext first:** `useAuth()` hook is imported by Nav, ProtectedRoute, PostPage, AdminPage — none of these can be implemented until `AuthContext.tsx` exists.
- **PHP Developer owns:** `config/session.php`, `Auth.php`, all API endpoints. This plan only consumes those endpoints.

---

## Files to Create

### 0. Project Scaffold

| File | Purpose | TDD Path |
|------|---------|----------|
| `src/store/store.ts` | Redux store with RTK, mount `baseApi` reducer + middleware | Path A |
| `src/store/baseApi.ts` | RTK Query `createApi` with `baseUrl: '/'` and `fetchBaseQuery` | Path A |
| `src/main.tsx` | Vite entry — wrap `<App>` in `<Provider store={store}>` | Path A |
| `src/setupTests.ts` | Jest/Vitest config — `@testing-library/jest-dom` imports | Path A |

### 1. Zod Schemas

All API responses are validated through Zod before use. Types are derived via `z.infer`.

| File | Schemas | TDD Path |
|------|---------|----------|
| `src/schemas/auth.schema.ts` | `MeResponseSchema`, `LoginResponseSchema`, `LogoutResponseSchema` | Path A |
| `src/schemas/registration.schema.ts` | `CaptchaQuestionSchema`, `RegisterFormSchema`, `RegisterResponseSchema` | Path A |
| `src/schemas/photo.schema.ts` | `PhotoSchema`, `PhotoFeedResponseSchema`, `PostPhotoResponseSchema`, `StatusResponseSchema` | Path A |
| `src/schemas/photographer.schema.ts` | `PhotographerSummarySchema`, `PhotographerListSchema`, `PhotographerDetailSchema` | Path A |
| `src/schemas/admin.schema.ts` | `AdminUserSchema`, `AdminUserListSchema`, `AdminSettingsSchema`, `SubmissionSchema`, `SubmissionListSchema` | Path A |

### 2. US-7 — Navigation, Auth, Routing

| File | Purpose | TDD Path |
|------|---------|----------|
| `src/context/AuthContext.tsx` | Auth state, `login()`, `logout()`, `useAuth()` hook, calls `GET /api/me.php` on mount | Path A |
| `src/components/Header.tsx` | Logo img + `<Nav />` | Path A |
| `src/components/Nav.tsx` | Conditional nav links via `useAuth()`, `<NavLink>` with active class | Path A |
| `src/components/ProtectedRoute.tsx` | Spinner → redirect `/login` → redirect `/` → render children | Path A |
| `src/pages/LoginPage.tsx` | RHF+Zod form, calls `AuthContext.login()`, 401/403 error messages, redirect-after-login | Path A |
| `src/App.tsx` | `BrowserRouter` + all routes (see Route Table below) + `<AuthProvider>` wrapper | Path A |
| `public/assets/logo.png` | Placeholder logo PNG | — |

**Route Table (owned by `src/App.tsx`):**

| Path | Component | Guard |
|------|-----------|-------|
| `/` | `HomePage` | Public |
| `/register` | `RegisterPage` | Public |
| `/login` | `LoginPage` | Public |
| `/index` | `IndexPage` | Public |
| `/photographers/:username` | `PhotographerPage` | Public |
| `/post` | `ProtectedRoute` → `PostPage` | Authenticated (non-admin) |
| `/admin` | `ProtectedRoute requireAdmin` → `AdminPage` | Admin only |
| `*` | `NotFoundPage` | Public |

### 3. US-1 — Registration

| File | Purpose | TDD Path |
|------|---------|----------|
| `src/pages/RegisterPage.tsx` | RHF+Zod form; fetches captcha on mount; handles 201/409/422/423 | Path A |
| `src/components/TimezoneSelect.tsx` | `Intl.supportedValuesOf('timeZone')`, grouped by continent, defaults to browser TZ | Path A |

**RegisterPage form schema fields:** `username`, `name`, `substack_url`, `password`, `email`, `timezone`, `captcha_answer`

### 4. US-5 — Home Feed

| File | Purpose | TDD Path |
|------|---------|----------|
| `src/components/PhotoCard.tsx` | Photo display — img, lazy load, error placeholder, photographer link, substack link, description, formatted timestamp | Path A |
| `src/components/PhotoFeed.tsx` | Initial fetch + 60s poll (`setInterval` + `isPollingRef` guard) + "Load more" pagination | Path A |
| `src/pages/HomePage.tsx` | Page title + `<PhotoFeed />` | Path A |

### 5. US-4 — Photo Posting

| File | Purpose | TDD Path |
|------|---------|----------|
| `src/components/PostingWindowBanner.tsx` | Closed message OR countdown to midnight via `Intl.DateTimeFormat` + `setInterval(1000)` | Path A |
| `src/pages/PostPage.tsx` | `GET /api/status.php` on mount; renders `PostingWindowBanner`; file picker + description form; `FormData` POST; client-side file size check; 201/403/422 handling | Path A |

### 6. US-6 — Photographer Index

| File | Purpose | TDD Path |
|------|---------|----------|
| `src/pages/IndexPage.tsx` | Fetch photographers list; A–Z grouped sections; loading + empty states | Path A |
| `src/pages/PhotographerPage.tsx` | Fetch `?username=X`; renders `<PhotoCard>` per photo; 404 → `NotFoundPage`; empty gallery state | Path A |
| `src/pages/NotFoundPage.tsx` | 404 message + home link | Path A |

### 7. US-2 — Admin UI

| File | Purpose | TDD Path |
|------|---------|----------|
| `src/pages/AdminPage.tsx` | Tabbed layout: Users \| Event Date \| Submissions | Path A |
| `src/components/admin/UserTable.tsx` | Fetch user list; status badges; Approve/Edit/Delete actions; confirm dialog for delete; disable delete self | Path A |
| `src/components/admin/UserModal.tsx` | Add (POST) or Edit (PUT) mode; RHF+Zod; inline validation; username + password for Add only | Path A |
| `src/components/admin/EventDatePanel.tsx` | Fetch current date; date picker; past-date warning; POST on save | Path A |

### 8. US-3 — Submissions Panel

| File | Purpose | TDD Path |
|------|---------|----------|
| `src/components/admin/SubmissionsPanel.tsx` | Fetch submissions; 60s poll; flat/grouped toggle; thumbnail via `<img>`; photo count; "Export All" button (`window.location.href`) | Path A |

---

## API Integrations

All endpoints consumed from API-CONTRACT.md (to be provided by PHP Developer). Zod schemas will be finalised against the contract before implementation.

| Endpoint | Method | Consumer | Auth |
|----------|--------|----------|------|
| `/api/me.php` | GET | `AuthContext` | Public |
| `/api/login.php` | POST | `AuthContext.login()` | Public |
| `/api/logout.php` | POST | `AuthContext.logout()` | Session |
| `/api/captcha-question.php` | GET | `RegisterPage` | Public |
| `/api/register.php` | POST | `RegisterPage` | Public |
| `/api/status.php` | GET | `PostPage` | Public |
| `/api/photos.php` | GET | `PhotoFeed`, `PhotographerPage` | Public |
| `/api/photos.php` | POST (multipart) | `PostPage` | Validated session |
| `/api/photographers.php` | GET | `IndexPage` | Public |
| `/api/photographers.php?username=X` | GET | `PhotographerPage` | Public |
| `/api/admin/users.php` | GET/POST/PUT/DELETE | `UserTable`, `UserModal` | Admin session |
| `/api/admin/settings.php` | GET/POST | `EventDatePanel` | Admin session |
| `/api/admin/submissions.php` | GET | `SubmissionsPanel` | Admin session |
| `/api/admin/export.php` | GET | `SubmissionsPanel` | Admin session (browser nav) |

---

## Tests

All test files use React Testing Library. No snapshot tests. Minimum 85% line + branch coverage on changed files.

| Test File | Story | Key Scenarios |
|-----------|-------|---------------|
| `src/pages/RegisterPage.test.tsx` | US-1 | Captcha fetch on mount; correct payload including captcha_index; 201 success; 423 closed banner; 409 field error |
| `src/components/TimezoneSelect.test.tsx` | US-1 | Grouped by continent; defaults to browser TZ |
| `src/components/admin/UserTable.test.tsx` | US-2 | Status badges; Approve absent for validated; Delete self disabled |
| `src/components/admin/UserModal.test.tsx` | US-2 | Add mode submits POST payload; Edit mode submits PUT payload; username/password hidden in edit mode |
| `src/components/admin/EventDatePanel.test.tsx` | US-2 | Pre-fills existing date; past-date warning shown |
| `src/components/admin/SubmissionsPanel.test.tsx` | US-3 | Renders rows from API; Export All href; 60s poll via mocked setInterval |
| `src/components/PostingWindowBanner.test.tsx` | US-4 | Countdown shown when open; closed message when closed |
| `src/pages/PostPage.test.tsx` | US-4 | Form hidden when window closed; client-side oversize warning; 201 success flash; 403 refreshes banner |
| `src/components/PhotoCard.test.tsx` | US-5 | Image, photographer link, substack link, description, formatted timestamp; error placeholder |
| `src/components/PhotoFeed.test.tsx` | US-5 | Initial fetch renders cards; poll prepends new; Load more appends older; poll guard prevents parallel requests |
| `src/pages/IndexPage.test.tsx` | US-6 | A–Z section headers; substack links `target="_blank"`; empty state |
| `src/pages/PhotographerPage.test.tsx` | US-6 | PhotoCard per photo; NotFoundPage on 404; empty gallery state |
| `src/components/Nav.test.tsx` | US-7 | Log in shown unauthenticated; Post + Log out for non-admin; Admin shown for admin; Post absent for admin |
| `src/pages/LoginPage.test.tsx` | US-7 | 401 message; 403 pending message; success redirects |
| `src/components/ProtectedRoute.test.tsx` | US-7 | Spinner while loading; redirect to /login unauthenticated; redirect to / non-admin on admin route |

---

## Constitution Compliance Checklist (pre-implementation)

- [ ] Zero `any` — strict TypeScript throughout
- [ ] Zero `var` — `const`/`let` only
- [ ] Zero default exports (framework entry points excepted)
- [ ] Zero `key={index}` — stable unique IDs (photo `id`, username, etc.)
- [ ] Zero inline `style={{ }}` — `sx` + design tokens only
- [ ] Zero `@ts-ignore`
- [ ] Zero empty `catch {}` — log + rethrow or handle
- [ ] Zero `console.log` in production code
- [ ] Zero snapshot tests
- [ ] All API responses validated through Zod before use
- [ ] All forms: React Hook Form + Zod schema-first
- [ ] ES2020+: `async/await`, `?.`, `??`, template literals, destructuring, named imports

---

## Self-Check Commands (before signalling READY FOR REVIEW)

```bash
npx eslint src --max-warnings 0
npx tsc --noEmit
npx jest
npx jest --coverage
npm audit --audit-level=high
```

---

## Notes

- `src/App.tsx` is React Developer's file. PHP Developer has no ownership here.
- Static `uploads/` path (`/uploads/{username}/{filename}`) for all image `<img>` tags — no proxy.
- `FormData` POST for photo upload (`/api/photos.php`) — RTK Query `fetchBaseQuery` passes through; do not set `Content-Type` header manually (browser sets boundary).
- `window.location.href` for export download is intentional (browser streams ZIP) — not an RTK Query call.
- `config/session.php` and PHP session cookie flags owned by PHP Developer; React side only reads the cookie implicitly via `GET /api/me.php`.
