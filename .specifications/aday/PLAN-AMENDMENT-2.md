# PLAN-AMENDMENT-2

**Story:** aday-photo-publishing
**Branch:** feature/aday-photo-publishing
**Date:** 2026-08-24
**Status:** PENDING APPROVAL
**Reason:** Two files committed but absent from IMPL-PLAN-react.md.

---

## Files to Add

### 1. `tsconfig.json`

| Field | Value |
|---|---|
| Path | `tsconfig.json` |
| Change | Added `"ES2022.Intl"` to `lib` array |
| Reason | `Intl.supportedValuesOf('timeZone')` used by `TimezoneSelect.tsx` requires this TypeScript lib entry. Without it `tsc --noEmit` errors with TS2339. |
| TDD path | N/A — config file, no tests required |

### 2. `src/context/AuthContext.test.tsx`

| Field | Value |
|---|---|
| Path | `src/context/AuthContext.test.tsx` |
| Change | Adds 6 tests for `AuthContext` and `useAuth` |
| Reason | Committed in the scaffold batch commit alongside `AuthContext.tsx`. Test file was omitted from the IMPL-PLAN test table by oversight. |
| TDD path | Path A — tests authored before implementation within same session |

---

## Impact Assessment

- No new API endpoints required
- No new dependencies
- No scope change to any other file
- Both files already committed on the feature branch; amendment retroactively documents them
