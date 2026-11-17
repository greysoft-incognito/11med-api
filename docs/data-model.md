# Data model

Every domain table has a bigint `id` (internal), a `uuid` (public, v7, the route key), timestamps
and `deleted_at`. Unique columns on soft-deleted tables are partial indexes `WHERE deleted_at IS NULL`.
Money columns are `bigint` kobo; instants are `timestamptz` in UTC.

## Tables

| Table | Module | Notes |
| --- | --- | --- |
| `users`, `personal_access_tokens`, `roles`, `permissions`, `model_has_roles`, … | Kit | From laravel-op; Identity (M2) extends `users` |
| `configurations`, `notification_templates`, `files`, `notifications` | Kit | Runtime settings, mail templates, uploads, database notifications |
| `wallets`, `wallets_logs`, `transactions` | Kit | Unused |

### Identity (M2)

Erasure removes the person's rows for real (`forceDelete`): `devices`, `social_identities`,
`refresh_tokens` and `password_links` cascade, `consent_records` keep their row with `user_id`
null, and later modules remove their own rows on `AccountErasing`.

| Table | Notes |
| --- | --- |
| `users` (extended) | `uuid`, `full_name`, `deleted_at`. `phone` (local `0…` form) and `lower(email)` are unique among live rows. `email` and `password` are nullable: portal accounts have no phone, social accounts may have no password. The role is the user's one Spatie role |
| `devices` | An app install (`install_id`, unique among live rows) with its platform, push provider and native token. Belongs to the user who registered it last |
| `refresh_tokens` | Not soft deleted (a credential). `token_hash` (SHA-256), `family` (one sign-in), `access_token_id` (the Sanctum token issued with it), `install_id`, `client` (`app` or `portal`), `expires_at`, `used_at`, `revoked_at`. Pruned daily a week after expiry or revocation |
| `otp_codes` | Not soft deleted. One live code per `target` (`phone:080…`, `email:<user id>`), `code_hash` (SHA-256), `attempts`, `resend` (counts against the 3 per 30 minutes), `expires_at`, `consumed_at`. Pruned after a day |
| `login_lockouts` | Not soft deleted. Failed sign-ins per `phone`, whether or not it has an account: `failures`, `locked_until`, `hard_locked_at`. Cleared by a successful sign-in or a password reset |
| `social_identities` | A Google or Apple `subject` bound to an account (`provider`, `subject` unique among live rows), `confirmed_at` set when the phone code comes back. Only a confirmed identity signs in |
| `password_links` | Not soft deleted. Emailed portal links: `token_hash`, `kind` (`reset`, an hour; `welcome`, a week), `expires_at`, `used_at`. Pruned a week after expiry |
| `account_erasures` | That an account was erased: `role`, `reason` (as given), `erased_at`. Nothing that names the person |
| `consent_records` | `context` (`register`, `social-link`, `profile-sharing`), `policy_version`, `accepted_at` as the client captured it, `recorded_at`, an optional `subject`. `user_id` is nulled on erasure, so the record survives severed |

### Media (M3)

| Table | Notes |
| --- | --- |
| `media` | An upload: `user_id` (null for a partner's licence, uploaded signed out; cascades on erasure, whose listener removes the files), `purpose`, `disk` and `path` (`media/<purpose>/<uuid>.<ext>`), `name` as uploaded, `content_type` as sniffed, `width` and `height` (null for a PDF), `size` in bytes as stored, `claimed_at` once a record takes it. Unclaimed rows older than a day are pruned hourly with their files |

Domain tables are added here as each milestone lands.
