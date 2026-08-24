# React Frontend Retrospective — aday-photo-publishing

**Branch:** feature/aday-photo-publishing → main
**Date:** 2026-08-24
**Stories delivered:** US-1, US-2, US-3, US-4, US-5, US-6, US-7
**Test count at merge:** 88 tests, 16 test files
**Final coverage:** 87.08% branches / 96.6% stmts / 97.7% lines
**Review cycles:** 4 (+ 2 Gate D rounds)

---

## What Went Well

**TDD Path A throughout.** Every component had its failing tests written first within each session turn. The red → green → refactor discipline held even under time pressure, which is why the overall coverage remained above threshold throughout.

**Zod-first API contract adherence.** Building from `API-CONTRACT.md` directly to Zod schemas before writing any UI code kept the API boundary clean. All 422 / 409 / 401 / 403 / 404 paths were handled in the UI because the schemas forced explicit enumeration of all response shapes.

**`AuthContext` as the foundation.** Implementing US-7 first — specifically `AuthContext` + `ProtectedRoute` — before any other story was the correct call. Every subsequent component could be tested in isolation by simply mocking `useAuth()`.

**RTK Query baseline unused cleanly.** The store and `baseApi` were scaffolded correctly but `fetch` was used directly for simplicity on a PHP backend without a JSON:API wrapper. No RTK Query hooks were needed, and the scaffold didn't get in the way.

**Coverage-driven branch additions.** The two rounds of coverage analysis identified genuinely untested paths (SubmissionsPanel polling guard, RegisterPage 422 envelope, PostPage 403 refresh) rather than synthetic padding — each added test reflects a real user scenario.

---

## What Could Be Improved

**Bundled commits.** All 29 planned files were committed in a single batch (`e4989ce`) rather than per-story commits. This made the TDD Path A audit harder — a written statement was required instead of commit history as evidence. Going forward: commit per story with separate red-phase and green-phase commits even within the same session.

**`error`/`helperText` omissions in `UserModal`.** Four TextFields shipped without error display wiring — a mechanical oversight caught in Gate D user testing, not in the self-check. The pattern from `username` (which had it correctly) should have been applied uniformly to all fields at authoring time. Checklist: every `register()`-backed TextField needs `error` + `helperText`.

**`window.confirm` / `window.location` instead of `globalThis`.** MINOR finding from the code reviewer. Habit from browser-first code. Constitutional ban: always use `globalThis` for globals in components — it signals intentional environment awareness.

**`LoginResponseSchema` missing `timezone` at authoring time.** The `timezone` field was added to `MeAuthenticatedSchema` correctly but the same field needed to be added to `LoginResponseSchema` and the `setUser` call in `AuthContext` — a consistency gap surfaced by `tsc` only after the PHP Developer committed. Pattern: when adding a field to a session response, always grep for all schemas that represent the same session payload and update all of them atomically.

**`logo.png` placeholder was 1×1 pixel.** The asset existed but was invisible. A visible placeholder (SVG icon or styled text) should have been committed from the start rather than deferred to Gate D. For future stories: any UI element that requires a real asset should ship with a clearly visible placeholder from day one.

**`PostPage` dead-code ternary.** `user?.status ? 'UTC' : 'UTC'` slipped through self-check and was caught in review. TypeScript strict mode does not catch semantic no-ops. Code review by a second set of eyes is the right backstop — or a linting rule for identical branches.

---

## Patterns to Carry Forward

| Pattern | Detail |
|---|---|
| One commit per story | `feat(us-N): story title` — red commit then green commit per story |
| Uniform `error`+`helperText` on every `register()` TextField | Add to component authoring checklist |
| `globalThis` over `window` for all global calls | Lint rule or reviewer checklist |
| Sync all session-payload schemas atomically | `me.php` = `login.php` = same shape — one grep, one PR diff |
| Visible placeholder for every unrealised asset | Ship SVG/text logo on day one |
| `fireEvent.submit` for forms with `type="email"` inputs in tests | jsdom native constraint validation blocks `userEvent.click(submitBtn)` |
| Guard `isPollingRef` pattern for all polling components | Prevents parallel in-flight fetches without `useEffect` cleanup complexity |
