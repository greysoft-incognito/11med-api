# Progress

Newest first. Never edit an old entry.

## 2026-11-25: M11.1 Partner applications

- `POST partners/apply` (public, 201, five an hour per address): the portal's `zApplyPartnerRequest`
  plus server bounds; the state must be one of the 37. 409 `LICENCE_APPLIED` on `licence_number`
  (case-insensitive, among live rows), 409 `EMAIL_TAKEN` on `contact_email` when the address already
  signs in (FE-110), 422 `MEDIA_NOT_FOUND` for a licence this form did not upload or another
  application claimed.
- `GET portal/admin/partners` (paged, `kind`, `status`, `q` over name, licence, state and contact,
  oldest application first) and `POST portal/admin/partners/{id}/review`: `approved` from pending,
  `suspended` with notes from approved, `reinstated` from suspended; 422 `PARTNER_NOT_PENDING`,
  `PARTNER_NOT_APPROVED`, `PARTNER_NOT_SUSPENDED`; 404 `PARTNER_NOT_FOUND`.
- Approval creates the contact's portal account with no password, linked by
  `users.organisation_id`, and emails the week-long welcome link through `PortalAccounts::welcome()`;
  `dev_set_password_link` is null. `PortalAccountResource` now carries `organisation_id` and
  `organisation_name`. Suspension and reinstatement email the partner's accounts.
- Seam for M12: `App\Models\Organisation`, `Organisation::listed()`, `Organisations::of()` and
  `updateListing()`, and the `PartnerApproved`, `PartnerSuspended`, `PartnerReinstated` events.

## 2026-11-18: M4.3 Caregivers, M4 done

- `POST medications/caregivers/invite` (201): the number in local form, a six-character code from
  the mock's alphabet, unique among live rows, and an SMS with the store link and the code
  (`APP_DOWNLOAD_URL`). 409 `CAREGIVER_ALREADY_INVITED` on `phone` comes before 422
  `CAREGIVER_LIMIT_REACHED` (`meta.limit`, `meta.current`), as the mock orders them; the cap is
  `Entitlements::caregiverLimit()`, 1 free and 5 premium, counting pending and active. Ten
  invitations a day per account, since each is a paid SMS.
- `POST caregivers/accept` (ten tries an hour): 404 `INVITE_NOT_FOUND`, 409 `INVITE_ALREADY_USED`
  (also when the accepter already cares for that patient), 422 `INVITE_SELF`, all on `code`;
  answers `zCaredForPatient` and dispatches `CaregiverAccepted` for M7 to tell the patient.
- `GET caregivers` and `GET caregiving` are paged (FE-003); `DELETE caregivers/{id}` soft deletes
  at once, only the patient's own (404 `CAREGIVER_NOT_FOUND`), and frees the number to be invited
  again. `Caregivers::canRead()` is what M6's schedule routes will ask.
- Caregiving routes need the patient role: a caregiver is a patient account (spec §6.5). Erasing
  either side removes the row through its foreign keys.
- `App\Services\Subscriptions\Entitlements` starts here with the caregiver cap; M5 adds the rest.
- M4 is done: 36 of 154 contract routes built, 118 pending. Next: M5 Subscriptions and
  entitlements, on the owner's go-ahead.

## 2026-11-18: M4.1 and M4.2 Medical profile and the emergency QR page

- `POST profile` (201, 409 `PROFILE_EXISTS`), `GET` and `PATCH profile/{userId}`, patient only.
  Another account's id is 404 `NOT_FOUND`; one's own without a profile, 404 `PROFILE_NOT_FOUND`.
- Rules mirror `zCreateProfileRequest` and `zUpdateProfileRequest`, plus server bounds the zod
  schemas leave open (lengths, list sizes, no birth date in the future). The state must be one of
  the 37 and the area one of its LGAs (`resources/data/nigerian-lgas.php`, generated from the app's
  bundled list; handover §5.5); a move to another state drops an area the request did not restate.
- A changed emergency contact number reads `is_verified: false`; no contract has a route to verify
  it yet (ISS-005). `profile_photo_url` is claimed through `MediaLibrary`, and a replaced or removed
  photo is discarded.
- `GET profile/{userId}/qr` makes the token on first ask; `POST profile/qr/revoke` replaces it and
  the old one is refused at once. The token is 40 random characters, found by its SHA-256 and kept
  encrypted so the app can show it again.
- `GET profile/view?token=` is public: the portals' `zEmergencyRecord`, the safe subset only, with
  `Cache-Control: no-store`. Each open increments `opened_count`, sets `last_opened_at` (the app's
  `last_accessed_at`) and writes a `qr_accesses` row with the time and nothing about who. 30 opens
  an hour per token and 60 per address. 404 `QR_NOT_FOUND` for an unknown or replaced token.
- `medications` on the page is empty until M6 implements `CurrentMedications`.

## 2026-11-17: M3.2 Ownership, signed links, orphans, M3 done

- `MediaLibrary::claim($owner, $url, $purpose, $field)` is the rule every record that carries a
  file will call (M4 profile photo, M8 doctor photo, M10 attachments, M11 store and laboratory
  pictures, results and the licence): the URL must name the caller's own upload for that purpose,
  signed or not, else 422 `MEDIA_NOT_FOUND` on `$field`. A licence has no owner and is claimed by one
  application only. Claiming sets `claimed_at`.
- Records keep the media row, not the URL: `MediaLibrary::url()` writes the URL when a response is
  written, so a signed link is always fresh. Private purposes (`message_attachment`, `lab_results`,
  `partner_licence`) are served by `GET media/{id}/file`, a signed route that lapses in 15 minutes
  (403 `LINK_EXPIRED` after), `nosniff`, a sandboxing CSP, private cache. Store pictures and photos
  are plain public URLs.
- Orphans: `Media` is `Prunable`; `model:prune` runs hourly and removes unclaimed uploads older than
  a day with their files. `discard()` removes a file a record drops; `ForgetErasedMedia` removes an
  erased account's uploads and files after the erasure commits.
- M3 Media is done: 25 of 154 contract routes built, 129 pending. Next: M4 Profile and caregiving,
  on the owner's go-ahead.

## 2026-11-17: M3.1 `POST media`

- One route for all ten purposes (the app's three, the portals' seven). The purpose decides the
  role (patient, doctor, pharmacy, laboratory; a licence needs no account), the types (pictures;
  PDF for attachments, results and licences) and whether the file is private.
- The bearer is read without the auth middleware so the licence can arrive signed out; every other
  purpose without one is 401 (`TOKEN_EXPIRED` when it lapsed), another role's purpose 403.
- The bytes decide: `UploadInspector` sniffs the type (415 `UNSUPPORTED_MEDIA_TYPE` on `file`),
  measures (413 `FILE_TOO_LARGE` over 5 MB, read or declared, and for a body over PHP's limit), reads
  a picture's size from its header before decoding it, and refuses one over 1600 px or whose size
  differs from the `width` and `height` sent (422 `IMAGE_NOT_RESIZED`). The picture is re-encoded
  from its pixels with GD, so no EXIF or GPS survives (app handover §3.5). A PDF must start `%PDF-`
  and carry no `/JavaScript`, `/JS`, `/Launch`, `/EmbeddedFile` or `/RichMedia` (415); a real
  scanner is HT-011.
- Stored at `media/<purpose>/<uuid>.<ext>` on `MEDIA_PUBLIC_DISK` or `MEDIA_PRIVATE_DISK`. Uploads
  are limited to 120 an hour per account and 10 an hour per address for a licence (429
  `RATE_LIMITED`).
- `intervention/image` ^3.11 is now required directly; it was already installed through the kit's
  `toneflix/laravel-fileable`, and the re-encode depends on it.

## 2026-11-17: M2.8 Account erasure, M2 done

- `DELETE auth/account` confirms with the password (422 `PASSWORD_INCORRECT` on `password`), then
  in one transaction writes an `account_erasures` row, dispatches `AccountErasing`, deletes tokens,
  notifications, roles, codes and the lockout, and removes the user for real. Devices, social
  identities, refresh tokens and password links cascade; consent records survive with `user_id`
  null. The phone number is free at once.
- The retained set lives in `config/elevenmed.php` pending a legal answer (HT-004).
- M2 Identity is done: 24 of 154 contract routes built, 130 pending. Next: M3 Media, on the owner's go-ahead.

## 2026-11-17: M2.7 Portal sign-in

- `POST portal/auth/sign-in` (201), `GET session`, `POST sign-out`, `refresh`, `forgot`,
  `set-password` through `PortalAccounts`. Only pharmacy, laboratory and admin accounts sign in;
  wrong details are 401 `INVALID_CREDENTIALS`, five tries a minute per address and email.
- Sign-out ends the pair even with an expired access token. A refresh answers the full session; a
  reused refresh token is 401 `INVALID_REFRESH` and ends the family.
- `forgot` emails a one-hour link to `PORTALS_URL/set-password?token=…` and answers
  `{ sent: true, dev_link: null }` for any address. `set-password` (422 `RESET_INVALID` on
  `token`) sets the password, confirms the email, ends other sessions and signs in.
- `php artisan app:create-admin <email> <name>` opens a team account and emails (and prints) a
  week-long first-password link; M11 reuses `PortalAccounts::welcome()` for approved partners.
- `organisation_id` and `organisation_name` are null until M11 links accounts to organisations.

## 2026-11-17: M2.6 Devices

- `PUT devices/{installId}` upserts with the row locked and answers 200 every time; an install
  another account holds moves to the caller. `DELETE devices/{installId}` removes only the
  caller's own and answers `{ install_id }` either way.
- `ForgetEndedDevices` listens to `SessionsEnded`, so a sign-out, a revoked family, a reset or a
  password change drops the installs of the sessions it ended (app handover §8.1).
- Removing a token APNs or FCM reports invalid is M7's, with the senders.

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
