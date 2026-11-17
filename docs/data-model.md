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

| Table | Notes |
| --- | --- |
| `users` (extended) | `uuid`, `full_name`, `deleted_at`. `phone` (local `0…` form) and `lower(email)` are unique among live rows. `email` and `password` are nullable: portal accounts have no phone, social accounts may have no password. The role is the user's one Spatie role |
| `devices` | An app install (`install_id`, unique among live rows) with its platform, push provider and native token. Belongs to the user who registered it last |
| `consent_records` | `context` (`register`, `social-link`, `profile-sharing`), `policy_version`, `accepted_at` as the client captured it, `recorded_at`, an optional `subject`. `user_id` is nulled on erasure, so the record survives severed |

Domain tables are added here as each milestone lands.
