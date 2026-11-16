# Progress

Newest first. Never edit an old entry.

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
