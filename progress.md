# Progress

Newest first. Never edit an old entry.

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
