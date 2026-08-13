# API Request: Family Dashboard Endpoints

**From:** Frontend (Nuxt portal)
**Scope:** Read/write endpoints needed to power `/portal` for family-authenticated users
**Auth:** All endpoints below (except Kinder branding) assume an authenticated family session via Sanctum SPA cookie auth, scoped to the logged-in `family` record. No `familyId` should be accepted as a request param — resolve it from the session to prevent one family querying another's data.

---

## 1. GET `/api/kinder/branding`

Public (or at least not family-auth-gated) — used to theme the login/portal shell before/after auth.

**Response**
```json
{
  "name": "Jardín Infantil Sorpresita",
  "mainColor": "#...",
  "secondColor": "#...",
  "fontName": "..."
}
```

---

## 2. GET `/api/family/me`

Returns the logged-in family's own profile.

**Response**
```json
{
  "id": 1,
  "lastNameOne": "...",
  "lastNameTwo": "...",
  "user": "...",
  "aboutUs": "...",
  "createdAt": "..."
}
```
Note: excludes `password`, obviously.

---

## 3. PATCH `/api/family/me`

Update editable family profile fields.

**Request**
```json
{
  "lastNameOne": "...",
  "lastNameTwo": "..."
}
```
Password change should likely be a separate endpoint (`/api/family/password`) rather than bundled here, so we can enforce current-password confirmation.

---

## 4. GET `/api/family/students`

List all children linked to the logged-in family, with enough joined data to avoid N+1 calls from the frontend.

**Response**
```json
[
  {
    "id": 10,
    "name": "...",
    "lastname": "...",
    "lastNameTwo": "...",
    "transportType": "microbus",
    "group": {
      "id": 3,
      "grade": { "id": 1, "name": "Materno" },
      "academicYear": { "id": 2026, "year": "2026" },
      "professor": { "id": 5, "name": "...", "phone": "..." },
      "assistant": { "id": 6, "name": "..." }
    }
  }
]
```
This resolves `Student` → `studentGroup` → `groups` → (`grades`, `academicYear`, `adminUser` x2) in one call.

---

## 5. GET `/api/family/students/{studentId}/authorized`

Returns authorized pickup contacts for one child. Backend should verify the student belongs to the requesting family before returning data (403 otherwise).

**Response**
```json
[
  {
    "id": 22,
    "name": "...",
    "lastName": "...",
    "lastNameTwo": "...",
    "phone": "...",
    "related": "Tío",
    "pickUp": true,
    "livesWithChild": false,
    "ocupation": "...",
    "communication": true,
    "status": "active"
  }
]
```

---

## 6. POST `/api/family/students/{studentId}/authorized`

Add a new authorized pickup contact for a child. Takes effect immediately — no staff approval step required.

**Request**
```json
{
  "name": "...",
  "lastName": "...",
  "lastNameTwo": "...",
  "phone": "...",
  "related": "...",
  "pickUp": true,
  "livesWithChild": false,
  "ocupation": "...",
  "communication": true
}
```
`photo` is intentionally omitted for now — that field will be worked out later once the upload approach is decided.

---

## 7. DELETE `/api/family/students/{studentId}/authorized/{authorizedId}`

Remove/deactivate an authorized contact — also immediate, no approval step. Recommend soft-delete (`status: inactive`) rather than hard delete, for audit purposes.

---

## Notes on academic year scope

Endpoint 4 (`GET /api/family/students`) should return **only the current academic year's** group/teacher/transport info per child — not historical groups.

Since the school year runs February–December (not calendar year), "current" can't be safely derived from just the system date/year. Confirmed: add `startDate` and `endDate` to the `academicYear` table — this also allows staff to start preparing data for the *next* academic year while the current one is still active, without ambiguity about which one is "current" for the dashboard.

Enrollment history (which years/groups a child has been in) is a separate concern — see endpoint 8 below.

---

## 8. GET `/api/family/history`

Powers a "member since" section on the dashboard — how long this family has been part of the institution, separate from current-year details.

**Response**
```json
{
  "familySince": "2019-03-01",
  "students": [
    {
      "id": 10,
      "name": "...",
      "enrollmentHistory": [
        { "academicYear": "2024", "grade": "Pre-Kinder" },
        { "academicYear": "2025", "grade": "Kinder" },
        { "academicYear": "2026", "grade": "Transición" }
      ]
    }
  ]
}
```
`familySince` comes from `family.created_at`. Per-student history comes from that student's `studentGroup` records joined across `academicYear`.

---

## 9. Password reset (family)

Families need self-service reset. Suggested flow:

**POST `/api/family/password/forgot`**
Request a reset link/code, sent to the mother's and/or father's email — not a family-account-level email.
```json
{ "user": "..." }
```
This surfaces a schema gap: the current `family` table is a single generic record (no separate mother/father sub-records), while the paper enrollment form and the whiteboard notes both treat mother and father as distinct people with their own email/phone. For the reset email to reach the right guardian(s), the schema likely needs a `guardians` (or similar) table — one or two rows per family, each with their own name/email/phone — rather than storing email at the family level. Flagging this before we build the form, since it affects more than just password reset (it also matches the `NOMBRE DE LA MADRE` / `NOMBRE DEL PADRE` sections on the enrollment sheet that aren't modeled yet either).

**POST `/api/family/password/reset`**
Complete the reset using the token from the email/link.
```json
{ "token": "...", "newPassword": "..." }
```

**PATCH `/api/family/password`**
For a logged-in family changing their password voluntarily (not a forgot-password flow).
```json
{ "currentPassword": "...", "newPassword": "..." }
```

Staff-side password reset (director resets staff, another director resets a director, DB-level fallback for the top) is an admin-panel concern, not part of the family portal — flagging that it'll need its own request doc when the staff dashboard is scoped.

---

## Open questions for backend

- Whether to add a `guardians` table (mother/father as distinct records with their own email) now, or keep it minimal for v1 and only store enough to route the reset email correctly.

---

## Out of scope for this request

Billing/payment status, emergency contacts (Emergencia 1/2), and document-submission checklist are shown on the paper enrollment form but have no corresponding tables yet. Flagging in case these are wanted for a future dashboard phase — would need schema additions first.
