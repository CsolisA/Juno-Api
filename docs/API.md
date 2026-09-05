# API Reference

Snapshot of the backend API for the Vue frontend. All routes are registered in `routes/api.php` under the `/api` prefix (e.g. `/api/family/login`). Every response is JSON; the app forces JSON error rendering for any request matching `api/*` (`bootstrap/app.php`).

There are no dedicated Form Request or API Resource classes for most of this API — controllers validate inline and return hand-built arrays. Treat the shapes below as an accurate snapshot, not a guaranteed typed contract; if a field seems missing, check the controller.

## Authentication

Two disjoint actor types authenticate against the **same** Sanctum token mechanism but are never interchangeable:

| Actor | Model | Login endpoint | Guard middleware |
|---|---|---|---|
| Admin (kinder staff) | `AdminUser` | `POST /admin/login` | `admin` (`EnsureAdminGuard`) |
| Family (parent/guardian) | `Family` | `POST /family/login` | `family` (`EnsureFamilyGuard`) |

Both middlewares run **after** `auth:sanctum` and simply check `instanceof AdminUser` / `instanceof Family` on the resolved user, aborting `403` otherwise. So an admin token will get `403` on any `/family/*` route and vice versa — there's no shared "user" concept across the two.

### `POST /admin/login` (public)
Request: `{ "email": string, "password": string }`
Response `200`: `{ "admin_user": { ...AdminUser fields... }, "token": "plain-text-sanctum-token" }`
Errors: `422` on wrong credentials (`email` field) or inactive account (`admin_user.status === false`).

### `POST /family/login` (public)
Request: `{ "user": string, "password": string }` — `user` is a login username, not necessarily an email.
Response `200`: `{ "family": { ...Family fields... }, "token": "plain-text-sanctum-token" }`
Errors: `422` on wrong credentials (`user` field).

### `GET /me` (auth:sanctum, either actor)
Returns the raw resolved user model as JSON (`AdminUser` or `Family`, whichever token was sent). Prefer the actor-specific endpoints below when you know which portal you're in.

### `GET /admin/me` (auth:sanctum)
Response: `{ "admin_user": { ... } }`. `403` if the token doesn't belong to an `AdminUser`.

### `POST /logout` (auth:sanctum, either actor)
Revokes the current token (`currentAccessToken()->delete()`). Response: `204` no content.

### `POST /family/password/forgot` (public)
Request: `{ "user": string }`. Always returns a generic `200` message regardless of whether the account exists (no user enumeration). Emails a reset link to every active guardian with an email on file.

### `POST /family/password/reset` (public)
Request: `{ "token": string, "newPassword": string (min 8) }`. `422` on invalid/expired token.

### `PATCH /family/password` (auth:sanctum + family)
Request: `{ "currentPassword": string, "newPassword": string (min 8) }`. `422` if `currentPassword` is wrong.

## Vue integration notes

**Axios setup** — one instance is enough even though there are two actor types, since only one is ever logged in at a time in a given browser session:

```js
// api.js
import axios from 'axios'

const api = axios.create({ baseURL: '/api' })

api.interceptors.request.use((config) => {
  const token = localStorage.getItem('token')
  if (token) config.headers.Authorization = `Bearer ${token}`
  return config
})

api.interceptors.response.use(
  (res) => res,
  (err) => {
    if (err.response?.status === 401) {
      localStorage.removeItem('token')
      // redirect to the appropriate login route
    }
    return Promise.reject(err)
  }
)

export default api
```

Store the `token` from the login response and the actor type (`admin` vs `family`) together (e.g. in Pinia + `localStorage`), since the SPA needs to know which portal/login screen to show on a `403`/`401`.

**Optimistic concurrency on pre-enrollment autosave** — `PATCH .../family` and `PATCH .../students/{studentId}` (below) require a `revision` number and return `409` with the server's current `{ message, revision, draftPayload }` if it's stale. On `409`, discard the pending local edit's revision, merge the server's `draftPayload` into your local state, and let the user retry the save with the new `revision` — don't just blindly retry with the same stale number, it will `409` again.

## Public endpoints (no auth)

| Method | Path | Purpose |
|---|---|---|
| GET | `/kinder/branding` | Returns `{ name, mainColor, secondColor, fontName }` for the (single-tenant) kinder. Use to theme the login/marketing pages before a user logs in. |
| POST | `/admin/login` | See Authentication. |
| POST | `/family/login` | See Authentication. |
| POST | `/family/password/forgot` | See Authentication. |
| POST | `/family/password/reset` | See Authentication. |

⚠️ `KinderBrandingController` hardcodes `Kinder::first()` — it assumes a single-tenant deployment, not per-domain resolution.

## Catalog endpoints (`auth:sanctum`, shared by admin & family)

Read-only lookups, all `GET`, all under `/catalogs/*`.

| Path | Query params | Response shape |
|---|---|---|
| `/catalogs/grades` | — | `[{ id, name, order, isFinal }]` ordered by `order` |
| `/catalogs/schedules` | `gradeId?` (int) | `[{ id, code, name, description, startTime, endTime, daysPerWeek }]`. If `gradeId` given, filters to schedules allowed for that grade (via `schedule_grade` pivot); if the grade has no pivot rows at all, **all** schedules are returned. |
| `/catalogs/provinces` | — | `[{ id, name }]` ordered by display order |
| `/catalogs/cantons` | `provinceId` (required int, must exist) | `[{ id, name }]` for that province |
| `/catalogs/relationships` | — | `[{ value, label }]` static list (abuelo, abuela, tío, tía, hermano, hermano_mayor, padrastro, madrastra, niñera, otro) |
| `/catalogs/nationalities` | — | `[{ value, label }]` static list (Costarricense, Nicaragüense, Venezolana, Colombiana, Estadounidense, Otra) |
| `/catalogs/education-levels` | — | `[{ value, label }]` static list (primaria, secundaria, tecnico, universidad, posgrado) |
| `/catalogs/marital-statuses` | — | `[{ value, label }]` static list (soltero, casado, union_libre, divorciado, viudo) |
| `/catalogs/blood-types` | — | `[{ value, label }]` static list (O+, O-, A+, A-, B+, B-, AB+, AB-) |
| `/catalogs/academic-years` | — | **Admin-only** (`admin` middleware, despite the `catalogs` prefix). `[{ id, year }]` for the admin's kinder, newest first. |

## Family portal endpoints (`auth:sanctum` + `family`, prefix `/family`)

### `GET /family/me` / `PATCH /family/me`
`show` returns a `FamilyResource`: `{ id, lastNameOne, lastNameTwo, user, aboutUs, createdAt }` (password never exposed).
`update` accepts `{ lastNameOne?, lastNameTwo? }` (both optional strings, max 255) and returns the same shape.

### `GET /family/students`
Returns `[{ id, name, lastname, lastNameTwo, transportType, group: { id, grade: {id,name}, academicYear: {id,year}, professor: {id,name,phone}|null, assistant: {id,name}|null } | null }]` for the current academic year.

⚠️ **Known bug**: `transportType` always returns `null`. The `transport_type` column was moved from `students` to `enrollments`, but this controller wasn't updated to read from the student's current enrollment. Don't build UI that depends on this field yet.

### Authorized contacts (pickup/emergency contacts per student)
| Method | Path | Notes |
|---|---|---|
| GET | `/family/students/{student}/authorized` | `403` if the student doesn't belong to the caller's family. Returns `AuthorizedResource[]`. |
| POST | `/family/students/{student}/authorized` | Body: `{ name, lastName, lastNameTwo, phone, related }` (required strings), `pickUp?, livesWithChild?, communication?` (bool), `ocupation?` (nullable string — note the misspelling, matches the DB column). |
| DELETE | `/family/students/{student}/authorized/{authorized}` | **Soft-deactivates** (`status → false`), does not delete the row. |

`AuthorizedResource` shape: `{ id, name, lastName, lastNameTwo, phone, related, pickUp, livesWithChild, ocupation, communication, status: "active"|"inactive" }`.

⚠️ `index` does **not** filter out deactivated contacts — the frontend must filter on `status === "active"` itself. ⚠️ The DB table also has `photo`, `is_emergency_contact`, `province`, `canton`, `address` columns that this API doesn't expose or accept at all (v1 scope).

### `GET /family/history`
Returns `{ familySince: "date", students: [{ id, name, enrollmentHistory: [{ academicYear, grade }] }] }`, history sorted oldest-first per student.

## Family pre-enrollment wizard (`auth:sanctum` + `family`, prefix `/family/pre-enrollment`)

This is the main feature area. Flow: admin opens a campaign → family gets an email → family fills in a shared household section plus one section per (non-excluded) child → family submits → admin reviews/approves.

1. **`GET /pre-enrollment/active`** — dashboard check. Returns `null` if there's nothing to act on (no open/recently-closed campaign, or every child is excluded). Otherwise:
   ```json
   { "campaignId": 1, "name": "Pre-Matrícula 2027", "dueDate": "2026-12-01",
     "status": "not_started|in_progress|submitted|closed_without_submitting",
     "completionPercent": 40, "studentsPending": 1 }
   ```

2. **`GET /pre-enrollment/{campaignId}`** — full form bundle. `404` if the family has no student in this campaign (prevents guessing other families' campaign IDs).
   ```json
   { "campaignId": 1, "name": "...", "status": "open", "dueDate": "2026-12-01",
     "household": { "revision": 3, "draftPayload": { "guardians.mother.name": {"value": "...", "sourceValue": "..."} } },
     "students": [ { "formId": 10, "studentId": 55, "name": "Juan Pérez López",
       "targetGrade": {"id":4,"name":"Prekinder"}|null, "status": "in_progress",
       "revision": 2, "completionPercent": 60, "draftPayload": {...}, "hasReportedIssue": false } ] }
   ```
   `draftPayload` entries are always `{ value, sourceValue }` — `sourceValue` is the pre-filled baseline from existing records, `value` is the current (possibly edited) value.

3. **`PATCH /pre-enrollment/{campaignId}/family`** — autosave the household section. Body: `{ revision: number, changes: { "dot.path": newValue, ... } }`. `422` if the campaign isn't `open` or its due date has passed. `409` (with fresh `{ message, revision, draftPayload }`) on stale `revision`. On success: `{ revision, savedAt }`.

4. **`PATCH /pre-enrollment/{campaignId}/students/{studentId}`** — autosave one student's section. Body: `{ revision: number, changes: {...}, reportIssue?: string }`. Same `409`/`422` behavior. `reportIssue` sets a note visible to admin and flags `hasReportedIssue`. Response: `{ revision, completionPercent, savedAt }`.

5. **`POST /pre-enrollment/{campaignId}/submit`** — submits every included child at once (not per-student). `422` validation errors:
   - `students`: any included student's form is below 100% complete (lists which)
   - `household`: no guardian (mother or father) has a fully complete block
   On success: snapshots all drafts, writes an audit trail of the changes, sets each form's status to `submitted`. Response: `{ submittedAt }`.

6. **`GET /pre-enrollment/{campaignId}/receipt`** — confirmation view: `{ campaignId, name, students: [{ studentId, name, status, submittedAt }] }`.

## Admin pre-enrollment endpoints (`auth:sanctum` + `admin`, prefix `/admin/pre-enrollment/campaigns`)

Cross-tenant access (wrong kinder) returns `404`, not `403`, everywhere in this section.

### Campaigns
| Method | Path | Notes |
|---|---|---|
| GET | `/campaigns` | List campaigns for the admin's kinder, newest first. |
| POST | `/campaigns` | Body: `{ academicYearId: int, name?, dueDate?, enforceDueDate?: bool, notes? }`. Creates one `PreEnrollmentForm` per active enrollment in the source academic year, computing grade progression. `422` if a draft/open campaign already exists for that year, or no source year can be resolved. `201` on success. |
| GET | `/campaigns/{id}` | Adds a `stats` object: totals, counts by status/target grade, family-progress buckets, `canClose`. |
| POST | `/campaigns/{id}/open` | `422` unless status is `draft`. Pre-fills every form's/household's draft, emails `PreEnrollmentCampaignOpenedNotification` to every family with a reachable guardian email. |
| POST | `/campaigns/{id}/close` | `422` if already `closed`/`archived`. Marks any still-pending forms `not_submitted`. |

Response shape (`formatCampaign`) shared across these: `{ id, name, status, academicYear: {id, year}, dueDate, enforceDueDate, openedAt, closedAt, createdAt }`.

### Selection & readiness
| Method | Path | Response |
|---|---|---|
| GET | `/campaigns/{id}/selection` | `{ campaign: {...}, families: [{ familyId, familyName, fullyBlocked, students: [{ formId, studentId, name, currentGrade, targetGrade, isGraduating, isExcluded, exclusionReason }] }] }` |
| GET | `/campaigns/{id}/readiness` | `{ ready: number, blocked: [{ familyId, reason: "no_email", students: ["name", ...] }] }` |

### Exclusion
| Method | Path | Notes |
|---|---|---|
| PATCH | `/campaigns/{id}/forms/{formId}/exclusion` | Body: `{ isExcluded: bool, reason?: string, reasonDetail?: string }`. `reason` required if excluding; one of `pending_debt`, `behavior`, `confirmed_withdrawal`, `other` (`graduating` can't be set manually). `422` if the student's current grade `isFinal` (graduating students). |
| POST | `/campaigns/{id}/forms/bulk-exclusion` | Body: `{ formIds: int[], isExcluded, reason?, reasonDetail? }`. Graduating students are silently skipped, not errored. Response: `{ updated: count }`. |

### Forms
| Method | Path | Notes |
|---|---|---|
| GET | `/campaigns/{id}/forms` | Query filters: `status?, gradeId?, targetGradeId?, hasChanges?, search?`. Paginated (25/page): `{ data: [...], currentPage, lastPage, total }`. |
| GET | `/campaigns/{id}/forms/{formId}` | Diff view: `{ formId, status, familyNotes, hasReportedIssue, currentGrade, targetGrade, fields: { "dot.path": { current, proposed, changed } } }`. |
| PATCH | `/campaigns/{id}/forms/{formId}` | Body: `{ changes: { "dot.path": newValue } }`. Admin correction after submission; audited. Returns the same shape as `GET .../{formId}`. |
| PATCH | `/campaigns/{id}/forms/{formId}/target-grade` | Body: `{ targetGradeId: int }`. Manual override. Returns `{ formId, targetGrade: {id, name} }`. |
| POST | `/campaigns/{id}/forms/{formId}/approve` | `422` unless status is `submitted`. Creates a projected `Enrollment` from the snapshot. Returns `{ formId, status: "applied", enrollmentId }`. |
| POST | `/campaigns/{id}/forms/{formId}/reopen` | `422` unless status is `submitted`. Body: `{ note: string }` (shown to the family). Returns `{ formId, status: "in_progress" }`. |
| GET | `/campaigns/{id}/forms/{formId}/changes` | Full audit trail: `[{ fieldPath, oldValue, newValue, actorType: "family"|"admin", actorId, createdAt }]`. |

## Enum reference

| Enum | Values |
|---|---|
| `PreEnrollmentCampaignStatus` | `draft`, `open`, `closed`, `archived` |
| `PreEnrollmentFormStatus` | `pending`, `in_progress`, `submitted`, `approved`, `applied`, `not_submitted`, `excluded` |
| `ExclusionReason` | `pending_debt`, `behavior`, `confirmed_withdrawal`, `other`, `graduating` (system-set only, not settable via the exclusion endpoints) |
| `ActorType` | `family`, `admin` |
| `GuardianRole` | `mother`, `father`, `other` |
| `IdType` | `cedula` (default), `dimex`, `passport` |

## Known issues / gotchas

1. **`GET /family/students` → `transportType` always `null`** — stale field, moved to `enrollments`, controller not updated.
2. **Authorized-contact endpoints** don't expose/accept `photo`, `is_emergency_contact`, `province`, `canton`, `address` even though the DB has them (v1 scope).
3. **Deactivated authorized contacts still appear** in `GET /family/students/{student}/authorized` — filter by `status` client-side; `DELETE` never hard-deletes.
4. **`GET /kinder/branding`** assumes a single tenant (`Kinder::first()`), not per-domain.
5. **No typed Form Request/Resource layer** for most endpoints — response shapes here are a snapshot of current controller code, not a guaranteed contract.
6. **Cross-tenant/cross-family access returns `404`, not `403`** — don't special-case `403` handling for these authorization checks in the SPA.