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

### Profile and caregiving (M4)

| Table | Notes |
| --- | --- |
| `medical_profiles` | One live row per patient. The core (`date_of_birth`, `gender`, `blood_type`, `genotype`, `state_of_residence`, `drug_allergies` JSONB), the optional `lga` (checked against the state), the emergency contact as columns (`contact_phone` local form, `contact_verified_at`), height and weight, `photo_media_id`, `existing_conditions`, `past_surgeries` and `food_allergies` as JSONB, `allergy_notes`, and the HMO as columns (`hmo_expiry_date` indexed for M7's expiry warnings). Medical data: never logged |
| `qr_tokens` | One current token per patient (`revoked_at IS NULL`): `token_hash` (SHA-256, unique), `token` (encrypted), `issued_at`, `opened_count`, `last_opened_at`, `revoked_at` |
| `qr_accesses` | Not soft deleted. One row per open of the emergency page: `qr_token_id`, `opened_at`, and nothing about the reader |
| `caregivers` | A patient's invitation (`patient_id`, `phone` local form, `invite_code`, both unique among live rows) and, once accepted, `caregiver_user_id`, `status` `active`, `accepted_at`. Revoking soft deletes; erasing either account removes the row |

### Partners and pharmacy (M11)

| Table | Notes |
| --- | --- |
| `organisations` | A pharmacy or laboratory (`kind`): its application while `pending`, then the organisation its portal accounts belong to. `name`, `state`, `city`, `address`, `licence_number` (unique among live rows, case-insensitive), `licence_media_id`, the contact (`contact_name`, `contact_phone` local form, `contact_email` lower case), `status` (`pending`, `approved`, `suspended`), `notes` (the suspension reason), `applied_at`, `reviewed_at`. Only `approved` is listed in the app. Its `uuid` is the pharmacy's or laboratory's id in every contract |
| `users` (extended) | `organisation_id`: a pharmacy or laboratory portal account's organisation, null for the team and app accounts |

Domain tables are added here as each milestone lands.
