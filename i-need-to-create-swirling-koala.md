# Terms & Conditions acceptance on submit (onboarding + pre-enrollment)

## Context
Before a family's final submit, the SPA must show a modal with a terms PDF. The family has to scroll to the end and tick "I agree and accept the terms and conditions". The submit then carries the acceptance, and the API stores when it was accepted and which PDF version.

This repo is API-only (the Vue SPA is separate). The modal, the scroll-to-end gate and the checkbox are SPA work. The API:
1. serves the PDF and its current version,
2. requires the acceptance on both submit endpoints,
3. persists `terms_accepted_at` and `terms_version`.

The server cannot verify scrolling. It only enforces `accepted = true` plus a current version.

Decisions made with the user:
- Both flows share one PDF.
- The PDF lives in this repo and is served by an endpoint.
- Save the date and the terms version. IP and user agent are not saved.
- Pre-enrollment re-accepts in every campaign.

## Changes

### 1. PDF + version config
- Add the PDF at `resources/terms/terms-and-conditions.pdf` (checked in, so it ships with the code). The user supplies the file.
- New `config/terms.php`: `version` (e.g. `'2027-01'`, from `env('TERMS_VERSION')`) and `path`. Bump `version` whenever the PDF changes.

### 2. Public endpoints (`routes/api.php`, no auth, `throttle:60,1`, outside the family/admin groups)
- `GET /api/terms/current` returns `{version, pdfUrl}`.
- `GET /api/terms/current/pdf` streams the file inline (`response()->file(..., ['Content-Type' => 'application/pdf'])`).
- New `app/Http/Controllers/TermsController.php`.
- The endpoints must be public because onboarding families only have an invite token.

### 3. Migration (new file in `database/migrations/`)
Add nullable `terms_accepted_at` (timestamp) and `terms_version` (string, 32) to:
- `family_invites`: acceptance at onboarding submit.
- `families`: copied from the invite, so the record lives with the family.
- `pre_enrollment_family_drafts`: per campaign and family, next to `submitted_at`.

Update `$fillable` and `$casts` in `app/Models/FamilyInvite.php`, `Family.php` and `PreEnrollmentFamilyDraft.php`.

### 4. Shared validation
New small rule set (e.g. `app/Support/TermsAcceptance.php`) that both flows reuse:
- `termsAccepted`: `required|accepted`
- `termsVersion`: `required|in:{config('terms.version')}`

A stale version returns 422 with a clear message, so the SPA refetches `/terms/current` and re-shows the modal. Spanish messages go next to the existing ones.

### 5. Onboarding
- `app/Services/Onboarding/FamilyOnboardingService.php`:
  - Merge the terms rules into `rules()` (line ~42) and the messages into `messages()`.
  - In `submit()` (line ~144), inside the existing `DB::transaction`, set `terms_accepted_at = now()` and `terms_version` on the Family (`createFamily`, line ~207) and on the invite update.
- `OnboardingController::submit` (line ~74) needs no structure change. It already uses `rules()`.

### 6. Pre-enrollment
- `app/Http/Controllers/Family/PreEnrollmentController.php::submit` (line ~203) currently has no request body.
  - Validate the two terms fields first, before the completeness checks.
  - When it sets `submitted_at` on the family draft, also set `terms_accepted_at = now()` and `terms_version`.
  - Re-set them if the director reopened the submission and the family submits again.
- Include `termsAcceptedAt` in the `show()` and `receipt()` payloads if useful, which is a small addition.

### 7. Docs
Update `docs/API.md`:
- the two new endpoints,
- the new required `termsAccepted` and `termsVersion` body fields on both submit endpoints,
- the 422 behavior.

Also describe the SPA contract:
1. Fetch `/terms/current`.
2. Open the modal with the PDF.
3. Enable the checkbox only after the scroll reaches the end.
4. Send `termsAccepted: true, termsVersion` with the submit.

## Tests (follow existing patterns)
- `tests/Feature/Onboarding/OnboardingSubmitTest.php` (base `OnboardingTestCase::payload()`):
  - Add the terms fields to the base payload.
  - Add tests for a missing or false acceptance returning 422, a stale version returning 422, and the success case storing `terms_accepted_at` and `terms_version` on the invite and the family.
- `tests/Feature/Family/PreEnrollmentFormTest.php`:
  - Update the existing submit tests to send the terms fields.
  - Add tests for missing or stale terms returning 422 (and nothing being persisted), and for acceptance being stored on the family draft.
- New `tests/Feature/TermsControllerTest.php`: the metadata and the PDF response (200, `application/pdf`).
- Use `Carbon::setTestNow` to assert the timestamp.

## Verification
1. `php artisan migrate`, then `php artisan test`, then `vendor/bin/pint --test`.
2. Manually:
   - `curl /api/terms/current` and `/api/terms/current/pdf`.
   - `POST /api/onboarding/submit` without the terms returns 422, and with them returns 201. Check the `families` and `family_invites` rows.
   - Repeat for `/api/family/pre-enrollment/{id}/submit` and check `pre_enrollment_family_drafts`.
