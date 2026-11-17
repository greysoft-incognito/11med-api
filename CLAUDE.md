# CLAUDE.md: 11med API

Read this file fully at the start of every session, before touching code.

---

## 1. What we are building

11med is a Nigeria-focused health platform. This repository is its **real API**: the server the
mobile app and the web portals call instead of the mocks they run against today.

| Client | Repo | Roles | Contract |
| --- | --- | --- | --- |
| Mobile app | `../11med` (React Native, Expo) | `patient`, `doctor` (caregiving is a capability of a patient, not a role) | `src/api/contracts/*.ts`, `docs/backend-handover.md` |
| Web portals | `../11med-web` (Next.js) | `pharmacy`, `laboratory`, `admin`, plus the public application and emergency page | `src/api/contracts/*.ts`, `docs/backend-handover.md` |

**The clients are the authority on the API.** Their zod contracts are the shapes (one schema per
endpoint, parsed on every response, so a mismatch is rejected on the device); their handover
documents are the behaviour; their mocks (`src/api/mocks`) are the tie-breaker when the prose is
ambiguous. Where the two handovers meet, the app's is the authority on the record and the portals'
on the transition the portal performs. Read the product rules in `../11med/CLAUDE.md` before a
milestone that touches them.

If a client checkout is missing, stop and log an `HT` item; do not reconstruct a contract from memory.

**Scope: the API only.** No screens. No dose reminders (the phone schedules them). No call media
(a provider carries it; we mint rooms and tokens). Premium is recorded by the team, never sold.

The approved proposal, with the domain model and the milestone order, is the Claude Doc
"11Med API: proposal". `plan.md` is where milestone scope and status live.

---

## 2. Stack

| Layer | Choice |
| --- | --- |
| Framework | Laravel 13 from the `toneflix/laravel-op` starter kit, version in `app/Constants/Op.php` |
| PHP | 8.4 on the servers and in CI (8.3 is the floor) |
| Database | PostgreSQL only, 14 or later (14 on the test server and in CI). `php artisan test` runs on `elevenmed_test` |
| Auth | Sanctum personal access tokens (15 minutes) plus our own rotating refresh tokens |
| Roles | `spatie/laravel-permission`: `patient`, `doctor`, `pharmacy`, `laboratory`, `admin` |
| Responses | `toneflix/laravel-resource-modifier`; every controller method returns an API resource |
| Settings | `toneflix/laravel-dbconfig` for values an operator may change at runtime |
| Files | Object storage through Laravel's filesystem; local disk in development |
| SMS | `toneflix/kudisms-notification` (OTP, caregiver invites), logged until enabled |
| Payments | Paystack (`toneflix/paystack-php` from the kit) for orders, lab bookings, consultations |
| Push | APNs and FCM directly, native tokens, never Expo's push service |
| Queue | `database` locally, Redis in production. Timers run on the scheduler, never on read |
| Tests | PHPUnit 12, feature test per route, unit tests per engine |
| Style | Laravel Pint with `pint.json` (`fn(` without a space, spaces around `.`) |

The kit's own routes (`routes/api/*.php`, under `/api`) are left in place; ours live under
`/api/v1` in `routes/v1/*.php`. Extend the kit, never fork its internals.

---

## 3. Layout

```
app/
  Enums/                 one enum per closed set, values as the contracts spell them
  Events/<Module>/       domain events; side effects (push, audit) listen to them
  Exceptions/            ApiException (code, status, field, meta), ApiExceptionRenderer
  Http/Controllers/<Module>/   thin: validate, one service call, return a resource
  Http/Requests/<Module>/      one ApiRequest per writing route, rules mirror the zod schema
  Http/Resources/        one resource (+ collection) per contract shape
  Http/Paging/           ListQuery (cursor, limit) and Page
  Models/                domain models extend App\Models\Model (public uuid, soft deletes)
  Services/<Module>/     the engines; the only writers of their module's tables
  Support/               Envelope and small pure helpers
config/elevenmed.php     timezone, client checkout paths, mock-only routes
lang/en/errors.php       the sentence for every error code
routes/v1/*.php          one file per module, loaded under /api/v1
tests/Contract/          routes.json (every client route), pending-routes.json (not built yet)
```

Modules: Identity, Profile, Caregiving, Medications, Readings, Notifications, Subscriptions, Media,
Doctors, Consultations, Partners, Pharmacy, Laboratories, Payments, Guide. A module owns its tables;
another module calls its service, never its models.

---

## 4. The wire format (the clients parse it)

- **Success**: `{ success: true, data, message, timestamp }`. `ApiResource` and `ApiCollection`
  put the body under `data` and add the rest; `->withMessage()` sets `message`.
- **Error**: `{ success: false, error: { code, message, field?, meta? }, timestamp }`, rendered by
  `ApiExceptionRenderer` for everything under `/api/v1`. `code` is UPPER_SNAKE and is the contract;
  `message` is a readable English sentence from `lang/en/errors.php` that a screen may show.
- **Validation**: 400 `VALIDATION_FAILED` with `field` = the first failing input, named as in the
  request body, because the forms map it straight onto that input.
- **Status codes**: 400 validation, 401 `UNAUTHENTICATED` or `TOKEN_EXPIRED` (refresh, do not sign
  out), 403 another role's area, 404 not found **and someone else's record**, 409 duplicate, 413 and
  415 uploads, 422 a business rule refused it, 429 `RATE_LIMITED` with `meta.retry_after_ms`.
- **Keys** are snake_case exactly as the contracts spell them.
- **Ids** the clients see are UUIDs: every domain table has an internal bigint `id` and a public
  `uuid` (time-ordered v7, `$table->publicId()`), the route key; resources expose `uuid` as `id`.
- **Lists**: `cursor` and `limit` (default 25, cap 100) up, `{ items, next_cursor, total }` back,
  through `ListQuery::from($request)->paginate($query)`. The query's order must end on a unique
  column. Only a small list read whole (the test catalogue, a price list) is a plain array.
- **Money** is integer kobo, never naira, never a float.
- **Time**: instants are ISO 8601 UTC with milliseconds (`2026-11-16T09:00:00.000Z`); calendar
  dates `YYYY-MM-DD`; times of day `HH:MM`. Calendar logic (slots, dose days, "today" on a summary)
  runs in Africa/Lagos (`config('elevenmed.timezone')`). The `pgsql` connection is pinned to UTC.
- **Rate limits and budgets** come back with the remaining budget, so the client can show it.

---

## 5. Domain rules the API must hold

The clients enforce these in the UI; the server is the authority against a client that bypasses it.

1. **Ownership.** A patient's records are the patient's; a caregiver reads schedules only, never
   reports. A portal account sees only its organisation. Anything else is 404, never 403.
2. **The free tier** (one `Entitlements` service): 3 active medications, 1 caregiver, 7 days of
   history and readings; report and insights premium only (403 `PREMIUM_REQUIRED`). A medication
   prescribed through the app (`prescribed_by_doctor_id`) is exempt from the cap. `is_premium` is
   derived from the subscription record, never stored on the user.
3. **Dosing.** Auto-miss at 60 minutes; at most 2 snoozes of 15 minutes; every skip counts against
   compliance; a pause generates no doses. Applying a prescription is idempotent.
4. **Consultations** have two deadlines (accept, then join) that run on the scheduler, not on read,
   and both refund in full. An unpaid request is never shown to a doctor.
5. **Orders and lab bookings** are paid before the pharmacy or laboratory sees them (a pickup paid
   at the counter is the one exception), move one stage at a time through the stages the app shows,
   and refund on cancellation. The Paystack webhook is the authority on payment, never the client.
6. **Media**: a record accepts a URL only from the same account's own upload for that purpose
   (422 `MEDIA_NOT_FOUND`); EXIF is stripped again on receipt; medical files are served by signed,
   short-lived URLs.
7. **Never log medical data**: conditions, medications, history, allergies, readings, guide text.
8. **Push** titles and bodies say what happened, never what is wrong. Every push writes the user's
   notification history row and carries its id.
9. **Consent** at registration and at profile sharing is recorded, versioned and timestamped.
10. **Account erasure** removes the person, keeps only what law requires (severed from them), clears
    the caregiver rows on other patients' lists, revokes their devices, and frees the phone number.

---

## 6. How the work runs

- Milestones run in `plan.md` order, sub-section by sub-section. Read the client contracts and
  handover sections a sub-section names before writing it, and cite them.
- Do not stop for routine questions: decide, record it in §8 or `progress.md`, continue.
- A non-blocking problem goes to `ISSUE.md`; a credential or a call only the owner can make goes to
  `human_todo.md`; a place where the real API needs a client change goes to `frontend_todo.md`.
  Never diverge from a contract silently.
- **Git** (the owner's rules): a branch per milestone named `feat/<milestone>` (or `fix/`, `chore/`),
  Conventional Commits without a scope tag (`feat: …`), authored as `3m1n3nc3`, no AI attribution
  lines anywhere (no `Co-Authored-By`, no "Generated with", no session links). One commit per
  sub-section; never fold unrelated features into one commit. A pull request per milestone; the
  owner merges. Never force-push a shared branch.
- **Definition of done** for a sub-section: `vendor/bin/pint --test` passes; `php artisan test`
  passes on PostgreSQL; every new route has a feature test for the happy path, each documented error
  code and each refused role; the route has left `tests/Contract/pending-routes.json`; migrations run
  from empty; `docs/data-model.md`, `plan.md` and `progress.md` are current.

### Coordination files

| File | Update when |
| --- | --- |
| `plan.md` | A sub-section changes status (`[ ]`, `[~]`, `[x]`, `[!]`) |
| `progress.md` | After every sub-section; newest entry at the top; never edit old entries |
| `ISSUE.md` | An issue is found (`ISS-nnn`), and again when resolved; never delete |
| `human_todo.md` | Something needs the owner (`HT-nnn`), marked blocking or not; tick when cleared |
| `frontend_todo.md` | The real API needs a client change (`FE-nnn`), naming the client and route |
| `docs/data-model.md` | A migration adds or changes a table |
| `docs/SETUP.md` | Anything new is needed to run the API: a service, credential, env variable, server setting, runtime setting or command. In the same PR |

---

## 7. Coding conventions

From agrolog-backend, which sets this house style, plus the owner's project rules.

- **Controllers are thin**: resolve the validated request, call one service method, return a
  resource. No business rule in a controller. Query building for a list lives in the service or a
  query class, not the controller.
- **Every controller method returns an API resource** (`php artisan make:resource`, which uses
  `stubs/`). For a non-200 status chain `->response()->setStatusCode(HttpStatus::CREATED->value)`.
  Streams (a PDF, a CSV) are the only exception.
- **Resources take a plain variable**, nothing else: load, then wrap.
- **Errors are thrown, never returned**: `throw ApiException::unprocessable('ORDER_STARTED')`. Give
  every new code its sentence in `lang/en/errors.php`. A model may name its not-found code with
  `public const NOT_FOUND = 'ORDER_NOT_FOUND';`.
- **Form Requests** extend `ApiRequest` and mirror the zod schema rule for rule.
- **State machines**: one class per lifecycle is the only code that changes a status, and refuses
  with the contract's code. **`Payable`** is the one interface payments know.
- **Services** take models and arrays, return models, throw `ApiException`. Prefer OOP; reuse the
  domain abstraction that already owns a policy rather than writing a parallel one.
- **Every potentially unbounded result is paged or processed in bounded batches** (`chunkById`,
  `lazyById`). Joins, counts, audiences and exports are done in SQL, never by loading models and
  filtering in PHP.
- **Models** extend `App\Models\Model` (public uuid, `SoftDeletes`) with explicit `$fillable` and
  casts for enums, dates and JSONB. Records are soft deleted; a unique column on a soft-deleted
  table is a partial unique index `WHERE deleted_at IS NULL`. Account erasure is the exception: it
  removes personal data for real (§5.10).
- **Migrations**: one per table or change; PostgreSQL types (`jsonb`, `timestamptz`, `numeric`),
  indexes on every filtered column, foreign keys on every reference.
- **No `env()` outside `config/`.**
- **Fluent chains, one call per line**, indented once under the root; a chain that need not break
  stays whole; a call taking a closure or long arguments lists one argument per line. Blueprint
  column definitions stay on one line.
- **Blank lines** separate setup, validation, actions and the return when they are distinct blocks;
  a multi-line assignment stands alone.
- **Comments** say what a thing does as it stands, briefly, or are not written. No history, no
  warnings about how it might break.
- Classes under roughly 200 lines. A new Composer dependency is noted in `progress.md` with why.

---

## 8. Decisions log

Add to this list; do not remove entries.

| Date | Decision | Why |
| --- | --- | --- |
| 2026-11-16 | Laravel 13 from `toneflix/laravel-op` 4.0.2; PostgreSQL only; agrolog-backend's coding style | Owner's call |
| 2026-11-16 | The wire format is the clients' (§4), not agrolog's: success envelope with `data`, UPPER_SNAKE codes with readable messages, 400 `VALIDATION_FAILED` with `field`, snake_case, cursor paging, kobo, 404 for others' records | Both clients already parse it; proposal accepted |
| 2026-11-16 | Validation uses the app's code, `VALIDATION_FAILED`; the portals' mock says `VALIDATION`, and neither client branches on it (FE-001) | One vocabulary; the larger client's spelling |
| 2026-11-16 | 401 is `TOKEN_EXPIRED` for an expired access token and `UNAUTHENTICATED` otherwise, for both clients; the app refreshes on any 401 | The portals branch on `TOKEN_EXPIRED`; the app reads the status only |
| 2026-11-16 | Public ids are a `uuid` column (v7) beside a bigint `id`, not UUID primary keys as the proposal said | The kit's Sanctum, Spatie, wallet and notification tables key users by bigint morphs; one pattern for every table beats two |
| 2026-11-16 | Soft deletes on every domain model (owner's rule); erasure under NDPR still removes personal data | Owner's call; deletion that only hides personal data would be the lie the app's handover §3.1 warns of |
| 2026-11-16 | A dose's `scheduled_for` is a real UTC instant computed from the schedule's `HH:MM` in Africa/Lagos (FE-002) | App spec item 12; proposal decision 1 |
| 2026-11-16 | Every app list is paged with the portals' `{ items, next_cursor, total }` (FE-003) | Owner's rule on unbounded results; proposal decision 2 |
| 2026-11-16 | Compliance takes an optional `date`, the patient's local day, defaulting to today in Lagos (FE-004) | App spec item 13; proposal decision 3 |
| 2026-11-16 | Erasure is built with the retained set and period in config, marked pending legal (HT-004) | App spec item 19; proposal decision 7 |
| 2026-11-16 | Allergy cross-reactivity uses a curated `drug_classes` table checked at prescribing, seed to be reviewed by a clinician | App spec item 23; proposal decision 8 |
| 2026-11-16 | Deploys: `.github/workflows/deploy.yml`, a push to `staging` or `production` (or a manual run) deploys to that GitHub environment's server after CI. `SSH_USERNAME`, `SSH_PASSWORD`, `SSH_KEY`, `SSH_HOST`, `SSH_PORT`, `DEPLOY_PATH` may be repository secrets with per-environment overrides; `SSH_KEY`, when set, is used instead of the password | Owner's call; agrolog's deploy, folded into one workflow |
| 2026-11-16 | The route ratchet reads both clients' mock routes (`php artisan contract:routes`) into `tests/Contract/routes.json`, less the mock-only routes in `config/elevenmed.php`; building a route means deleting its line from `pending-routes.json` | One real endpoint list, checked on every test run |
| 2026-11-17 | Access tokens are Sanctum tokens that expire in 15 minutes; refresh tokens are our own table (SHA-256 of a 64-character secret, 30 days, one family per sign-in, rotated on use). A spent refresh token presented again revokes the family. A failed refresh is `AUTH_SESSION_EXPIRED` for the app and `INVALID_REFRESH` for the portals, as each client spells it. Refresh tokens are credentials, not records: revoked while live and pruned a week after they end, with no soft delete | App handover §7, portal mock; a soft-deleted secret would only be a secret kept longer |
| 2026-11-17 | Phones are stored in the local `0…` form and compared there, as the app's mock does; the gateway gets E.164 | One spelling per number; `+234…` and `0…` are the same account |
| 2026-11-17 | A number held by an account that never verified its phone is released to the next sign-up (the abandoned row is removed); a verified holder is 409 `PHONE_ALREADY_REGISTERED` | An abandoned registration would otherwise block its owner, and numbers are reissued in Nigeria |
| 2026-11-17 | One live code per target (`phone:…`, `email:<id>`), shared by phone verification and password reset as in the mock; resends, forgot-password and email resends count against 3 per 30 minutes; `OTP_FIXED_CODE` works outside production only | App spec §2.1.1; staging needs sign-up before SMS is enabled (HT-002) |
| 2026-11-17 | Lockouts are per phone number whether or not it has an account, and a password reset clears them, including the hard lock; there is no email unlock route (FE-007) | No contract has one; the reset proves the number |
| 2026-11-17 | Consent is recorded at registration and phone linking from an optional `consent` in the body, or the configured policy version when absent (FE-006) | App handover §3.2; the app already holds the record and has nowhere to send it |
| 2026-11-17 | Erasure deletes the user row for real and announces `AccountErasing` inside the transaction for each module to remove its own rows; consent records and an `account_erasures` row survive without the person (HT-004) | NDPR; owner's soft-delete rule does not apply to erasure (§7) |
| 2026-11-17 | The kit's `/api/auth/*` and `/api/account/*` routes are removed; its `/api/admin/*` routes need the `admin` role | ISS-003: they bypassed `/api/v1` and erasure |
| 2026-11-17 | One `media` table and route for every purpose. Records store the media row, never the URL; `MediaLibrary::claim()` is the one ownership check and `url()` makes the URL at response time, public for pictures and a 15-minute signed link for attachments, results and licences | App handover §12, portal handover §3: a signed URL stored on a record would lapse |
| 2026-11-17 | Pictures are re-encoded from their pixels and their header size must equal the `width` and `height` sent; one stored size, no thumbnails, since every contract carries one `url` (ISS-004) | App handover §3.5 and §12 |
| 2026-11-18 | The profile, QR and caregiving routes need the patient role; another account's id is 404. The state and LGA are checked against the app's bundled list, copied to `resources/data/nigerian-lgas.php` | App spec §6.5 (caregiving is a patient capability), handover §5.5 and §7 |
| 2026-11-18 | An emergency token is found by its SHA-256 and kept encrypted for the owner; each open writes a time-only access row and bumps the count; 30 opens an hour per token, 60 per address; `no-store` | App handover §3.3, portal handover §7 |
| 2026-11-18 | Caregiver lists are paged like every app list (FE-003); the caregiver cap is the first gate in `Entitlements` | Owner's paging rule; one place for free-tier gates (§5.2) |
| 2026-11-17 | A PDF with active content (`/JavaScript`, `/JS`, `/Launch`, `/EmbeddedFile`, `/RichMedia`) is 415 until a scanner exists (HT-011) | App handover §12: a PDF can carry scripts |
| 2026-11-25 | One `organisations` table is a partner's application and, once approved, its organisation; `users.organisation_id` links the portal accounts; its uuid is the pharmacy's or laboratory's id everywhere. Kind-specific settings live in the Pharmacy and Laboratories modules' own tables, created by listeners on `PartnerApproved`; `PartnerSuspended` and `PartnerReinstated` let them delist | Portal handover §6; one module owns each table |
| 2026-11-25 | A partner's contact email may not already be an account, at application and at approval (409 `EMAIL_TAKEN`, FE-110); suspension and reinstatement email the partner's accounts, and a suspended partner still signs in to finish what is in progress | Portal handover §6 "What the mock fakes" |
| 2026-11-25 | A record's picture is changed through `MediaLibrary::replace()`: the same URL keeps the file, null drops it, anything else must be the caller's own upload for the purpose; a dropped file is discarded | One ownership rule for every picture a portal edits (logos, covers, medicine pictures) |
| 2026-11-25 | Cart lines are working state and deleted for real when removed; orders keep a `payment_status` mirror written only by the payment events, so a pharmacy's list never joins the payments table | Owner's rule that a module owns its tables; soft-deleted cart lines would only be clutter |
| 2026-11-17 | Every setup requirement (services, credentials, env variables, server and runtime settings, one-off commands) lives in `docs/SETUP.md`, updated in the PR that adds it; `DEPLOY.md` covers the deploy itself | Owner's rule: nothing lives only in chat |
| 2026-11-19 | Interfaces one module provides to others (`Entitlements`, `Notifier`, `Payable`/`PayableTargets`/`Payments`, `PrescribedSchedules`, `CurrentMedications`) are declared up front with `#[Bind]` stand-ins; the owning milestone replaces the binding, never the signature without telling its callers | M5 to M13 run in parallel (owner's call) |
