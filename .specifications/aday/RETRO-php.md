# PHP Developer Retrospective — A Day In The Life

**Story:** aday-photo-publishing (US-1 through US-7 PHP API layers)
**Branch:** `feature/aday-photo-publishing` → merged to `main`
**Date:** 2026-08-24
**Agent:** PHP Developer

---

## What Went Well

### Foundation first paid off
Building `config/env.php`, `config/db.php`, `config/session.php`, `lib/Response.php`, and `lib/ResponseException.php` before writing a single endpoint made every subsequent file predictable. The `respond()` helper unified status codes across all 19 endpoints with no duplication.

### TDD Path A held throughout
All 12 test files were written before their implementations, keeping the red→green cycle honest. The `TestHelper` class (output buffering + superglobal injection) gave reliable, fast test isolation without needing a running web server or subprocess spawning.

### In-memory SQLite + `TestHelper::resetDb()`
Using `:memory:` SQLite with a per-test `resetDb()` call made tests completely deterministic and ~14 seconds for 80 tests — fast enough that the full suite ran after every task. No state leaked between tests.

### Early API-CONTRACT drop unblocked React Developer
Publishing `API-CONTRACT.md` after Phase 2 (before phases 3–6 were complete) gave the React Developer a stable contract for auth, registration, and navigation endpoints. This parallelised frontend and backend work effectively.

### WindowCheck timezone injection
Designing `WindowCheck::isPostingOpen()` to accept an injectable `$now: DateTimeImmutable` from the start made timezone edge-case tests trivial (e.g. UTC-12 user still within posting window when UTC clock has rolled to next day).

### ZipArchive empty-file fallback
Discovering that Windows ZipArchive does not write a file when `close()` is called with zero entries (a platform-specific behaviour) and handling it with a 22-byte minimal-ZIP fallback avoided a fragile test skip.

---

## What Could Be Improved

### `lib/Response.php` and `lib/ResponseException.php` not in IMPL-PLAN
These two foundational files were written before the IMPL-PLAN was formally approved, and were discovered missing from scope only at Code Review. A pre-implementation pass over the plan to check for shared infrastructure files would have caught this before the first commit.

### TDD red-phase commits not separated in original run
The first pass bundled tests with implementation in the same commit. While tests were written first in authoring order, the git history did not prove it. Going forward: always make a standalone test commit (even if it is a fatal-error commit) before the implementation commit, so the sequence is verifiable in history.

### `test_delete_last_admin_returns_400` was doing too much
The original test contained a nested `TestHelper::resetDb()` call and tested two distinct behaviours (non-last-admin deletion succeeds; self-delete of last admin fails). This was flagged by Code Review. Each test method should exercise exactly one scenario; `setUp()` owns all state reset.

### Mailer not designed for testability from the start
`Mailer` was `final` with no injection hook, requiring a post-review change to add `make()`/`setTestInstance()`. Designing non-trivial service classes as non-`final` with a static factory from the start avoids this rework.

### PHPStan found `static` vs `self` type mismatch after Mailer refactor
Changing `new static()` to `new self()` and `static(Mailer)|null` to `Mailer|null` was a one-line fix but required an extra commit. Running PHPStan locally after each file change (not just at self-check) would catch this immediately.

---

## Patterns to Carry Forward

| Pattern | Why |
|---|---|
| `TestHelper` output-buffering + superglobal injection | Only viable TDD approach for vanilla PHP endpoint files; reusable across projects |
| `respond()` throws `ResponseException` in `APP_ENV=testing` | Prevents `exit` from killing PHPUnit process; apply to any vanilla PHP project using require-based routing |
| Migration closure pattern (`return static function(PDO $db): void`) | Avoids `function run()` redeclaration when multiple migration files are loaded in the same PHP process |
| `WindowCheck` with injectable `$now` | Makes any time-dependent logic fully testable without mocking system clock |
| Static factory (`ClassName::make()` + `setTestInstance()`) on service classes | Clean testability hook; prefer over constructor injection when the class is loaded via file include |
| Early API-CONTRACT drop after auth phase | Unblocks parallel frontend work; commit a partial contract with `Status: PARTIAL` rather than waiting for full backend completion |
| 22-byte fallback for empty ZipArchive on Windows | Platform-specific: Windows does not write ZIP file on `close()` with zero entries; write EOCD record manually |
| PHPStan after every file, not just at self-check | Catches type errors immediately rather than accumulating them for the final pass |

---

## Metrics

| Metric | Value |
|---|---|
| PHP files delivered | 29 (API + lib + config + migrations) |
| Test files | 12 |
| Test methods | 80 |
| Assertions | 178 |
| Review cycles | 3 (Cycle 1: 6 findings; Cycle 2: 1 Gate D fix; Cycle 3: approved) |
| Plan amendments | 1 (lib/Response.php + lib/ResponseException.php) |
| PHPStan errors at final check | 0 |
| CVEs | 0 |
