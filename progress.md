# Progress

Newest first. Never edit an old entry.

## 2026-11-17: M2.5 Google and Apple sign-in

- `POST auth/social` verifies the ID token against the provider's published keys (cached an hour):
  signature, issuer, audience (`GOOGLE_WEB_CLIENT_ID`, `APPLE_BUNDLE_ID`, HT-003) and expiry. A
  confirmed identity gets a session; a new one gets a link token (15 minutes, single use, held in the
  cache by its hash). Without a configured audience the provider answers 422 `SOCIAL_UNAVAILABLE`; a
  token that fails is 401 `SOCIAL_TOKEN_INVALID`.
- `POST auth/social/phone` creates the account as registration does (no password, consent context
  `social-link`), copies the token's email with its own `email_verified` claim unless another
  account holds it, and binds the identity unconfirmed. The phone code confirms it.
- New dependency: `firebase/php-jwt` ^7.0, to verify RS256 tokens against a JWKS (6.x carries
  CVE-2025-45769).

## 2026-11-17: M2.4 Passwords and email

- `forgot-password` and `reset-password` over the phone code (`Passwords`): forgot shares the
  resend budget and answers alike for any number; reset refuses an unknown number as a wrong code,
  clears the lockout, confirms the phone and ends every session.
- `change-password` needs the current password (422 `PASSWORD_INCORRECT`, `PASSWORD_UNCHANGED`)
  and ends every session but the one making the change.
- `PATCH auth/email`, `email/verify`, `email/resend` (`EmailAddresses`): a new address is
  unconfirmed until its emailed code returns; the same address in any case sends nothing; 409
  `EMAIL_TAKEN`, 422 `NO_EMAIL`, 422 `EMAIL_VERIFIED`; the phone code's budget.

## 2026-11-17: M2.3 App sign-up and sign-in

- `POST auth/register`, `verify-otp`, `resend-otp`, `login`, `refresh`, `logout` and
  `GET auth/session`, through `AppAccounts`, `OneTimeCodes` and `LoginGuard`. Phones are stored in
  the local `0…` form, as the app's mock compares them.
- Codes: six digits, ten minutes, five tries, three resends in thirty minutes with `retry_after_ms`.
  `OTP_FIXED_CODE` lets staging run before SMS is enabled; production ignores it. SMS go through the
  kit's gateway when `SMS_ENABLED` is on and to the log otherwise (HT-002).
- Lockouts per number: 401 with `attempts_remaining`, 423 with `locked_for_ms` after five, 423 with
  `requires_email_unlock` after ten, cleared by a reset (FE-007).
- A number held by an account that never verified it is released to a new sign-up, so an abandoned
  registration cannot block its owner. Registration records consent (FE-006).
- Departure: `POST auth/refresh` was in no sub-section's list; it is built here with the rest.

## 2026-11-17: M2.2 Token pairs

- `SessionTokens` issues a pair (Sanctum access token, 15 minutes; refresh token, 30 days), rotates
  it with the row locked, treats a reused refresh token as theft and revokes the family, and
  revokes by refresh token, by access token or for a whole account. Ending a family dispatches
  `SessionsEnded` with its installs, for the device cleanup in 2.6.
- A 401 says `TOKEN_EXPIRED` when the bearer token exists and has expired (`BearerToken`).
- `HttpStatus::LOCKED` (423) and `ApiException::locked()` for the login lockout in 2.3.

## 2026-11-17: M2.1 Roles, users, devices, consent

- `Role` enum (patient, doctor, pharmacy, laboratory, admin) drives `permission-defs.php`; the kit's
  admin permissions are excluded from every role but admin. The seeder syncs roles everywhere and
  creates the demo admin only in local and testing.
- `users` gains `uuid`, `full_name` and soft deletes; phone and email are unique among live rows;
  email and password are nullable. The kit's `fullname()` accessor now reads `full_name` first,
  because PHP method names ignore case and it had shadowed the new column.
- `devices` and `consent_records` with their models. Factory states `app()` and `portal()`.

## 2026-11-16: M1 Foundation

- Scaffolded from `toneflix/laravel-op` 4.0.2 (Laravel 13.42). The kit's suite runs green on
  PostgreSQL 16 after two kit fixes (ISS-001, ISS-002).
- The wire format the clients parse: `Envelope`, `ApiResource`, `ApiCollection`, `ApiException`,
  `ApiExceptionRenderer` (everything under `/api/v1`), `ApiRequest`, `ListQuery` and `Page` for
  cursor paging, `lang/en/errors.php`. `App\Models\Model` gives every domain model a public uuid and
  soft deletes; `$table->publicId()` adds the column. `GET /api/v1/health` for the deploy check.
- The route ratchet: `php artisan contract:routes` reads 154 routes from the two clients' mocks
  (mock-only routes excluded); all 154 start in `pending-routes.json`.
- CI on PostgreSQL; `deploy.yml` with staging and production jobs, SSH by key or password.
- Decisions recorded in `CLAUDE.md` §8. Departure from the proposal: public ids are a uuid column
  beside a bigint key, not uuid primary keys, because the kit's tables key users by bigint.
- Next: M2 Identity, on the owner's go-ahead.
