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

### Doctors (M8)

| Table | Notes |
| --- | --- |
| `doctor_profiles` | One live row per doctor, started blank on first read. Professional fields, `languages` and `availability` (`[{ day, start, end }]`, Africa/Lagos) as JSONB with a GIN index on `languages`, `photo_media_id`, the three fees, `follow_up_fee_kobo`, `session_minutes`, `chat_window_hours`, `bank_name`, `bank_account_number` (encrypted with `APP_KEY`) and `bank_account_last4` for the mask, `is_available_now`, `verification_status`, `verification_notes`, `submitted_at` (the review queue's order), `reviewed_at`, and the running rating (`rating_total`, `rating_count`, `rating_mean`) the directory sorts on. Erasure cascades |
| `doctor_documents` | One live row per profile and `kind`, created at the first upload: `status`, `media_id` (a private `doctor_document` upload; null for bank details, which are the payout fields), `uploaded_at`, `rejection_reason`, `reviewed_at`. A new upload replaces the file and returns the row to `uploaded` |

### Guide (M13)

| Table | Notes |
| --- | --- |
| `organisations` | A pharmacy or laboratory (`kind`): its application while `pending`, then the organisation its portal accounts belong to. `name`, `state`, `city`, `address`, `licence_number` (unique among live rows, case-insensitive), `licence_media_id`, the contact (`contact_name`, `contact_phone` local form, `contact_email` lower case), `status` (`pending`, `approved`, `suspended`), `notes` (the suspension reason), `applied_at`, `reviewed_at`. Only `approved` is listed in the app. Its `uuid` is the pharmacy's or laboratory's id in every contract |
| `pharmacies` | A pharmacy organisation's store settings, made on approval (`organisation_id` unique): `logo_media_id`, `cover_media_id`, `delivery_fee_kobo` (₦1,500 to start), `free_delivery_over_kobo`, `delivery_minutes`, `rating` and `rating_count` (no route rates an order yet) |
| `drugs` | A pharmacy's medicines (`zDrug`): `organisation_id`, name, brand, `nafdac_number` (unique per pharmacy among live rows, case-insensitive), `category`, `dosage_form`, `dosage_strength`, `description`, `price_kobo`, `requires_prescription`, `in_stock`, `image_media_id`. Removing soft deletes, refused while an open order holds it |
| `orders` | One pharmacy's share of a checkout: `reference` (unique), `checkout_id` (shared by the siblings one payment covers), `patient_id`, `organisation_id`, `status`, `fulfilment`, subtotal, delivery fee and total in kobo, `address` JSONB (null for a pickup), `payment_status` and `payment_channel` (mirrors of the payment, written on `PaymentSucceeded` and `PaymentRefunded`, so a pharmacy's list filters paid orders in SQL), the three points `placed_at`, `ready_at`, `completed_at`, `estimated_delivery_at`, `cancelled_at`, `cancelled_by`, `cancel_reason`, `rider` JSONB |
| `order_items` | A line as sold: `drug_id` (the medicine, kept when it is removed), `drug_name`, `dosage_strength`, `price_kobo`, `quantity`, `requires_prescription` |
| `cart_items` | Not soft deleted (working state). A patient's cart line: `patient_id`, `drug_id`, `quantity`, unique per patient and medicine |
| `users` (extended) | `organisation_id`: a pharmacy or laboratory portal account's organisation, null for the team and app accounts |

### Guide (M13)

| `guide_conversations` | Not soft deleted. One per patient (`user_id` unique, cascades on erasure), `started_at`, `last_message_at` (indexed; idle 30 days is pruned daily). Starting over deletes the row |
| `guide_messages` | Not soft deleted. `conversation_id` (cascades), `sender` (`user`, `guide`), `body` (encrypted with `APP_KEY`), `actions` and `suggestions` JSONB, `urgency`, `sent_at`. The newest 100 per conversation are kept. Medical data: never logged |
### Payments (M9)

Money records: erasure nulls `payments.user_id` and keeps the rows. Statuses change only through
`Settlement` (pending to paid, failed or abandoned) and `Refunds` (shares, and paid to refunded).

| Table | Notes |
| --- | --- |
| `payments` | One gateway transaction: `reference` (ours, unique), `user_id`, `purpose`, `channel` (null until known; `counter` never reaches the gateway), `status`, `amount_kobo` (the sum of its shares), `authorization_url` and `access_code` while pending, `paid_at`, `failure_reason` (Paystack's `gateway_response`), `checked_at` (last verify call) |
| `payment_targets` | One target's share: `purpose` and `target_id` (the target's public id, no foreign key since the target's table belongs to another module), `target_reference` and `partner_name` copied for the desk, `amount_kobo`, `refunded_kobo`, `state` (`standing`, `released`, `refund_pending`, `refund_failed`, `refunded`). Indexed on `(purpose, target_id)` |
| `payment_refunds` | A refund of one share: `amount_kobo`, `status` (`pending`, `processed`, `failed`), `gateway_refund_id`, `requested_at` (when Paystack accepted the request), `processed_at`, `failure_reason` |

### Subscriptions (M5)

| Table | Notes |
| --- | --- |
| `subscriptions` | One live row per patient once the team records premium: `user_id`, `premium_until` (a Lagos calendar day, indexed for the premium count), the latest `reference`, `recorded_by` (the admin, null once erased). Premium holds while `premium_until` is today or later in Lagos; nothing is written when it lapses. `is_premium` is read from here, never stored on the user. Erasing the patient removes the row |
| `subscription_payments` | Every recording: `user_id` (null once the patient is erased, so the reference stays used), `reference` (unique among live rows, checked across trashed ones too), `premium_until`, `recorded_by`, `recorded_at` |

### Notifications (M7)

| Table | Notes |
| --- | --- |
| `app_notifications` | A user's notification history (`zNotification`): `category`, `title`, `body`, `target_id` (the record a tap opens), `read_at` (the first read), an optional `dedupe_key` unique per user among live rows, so a repeated event writes nothing. Indexed `(user_id, created_at, id)` for the newest-first page and partially on unread rows for the count. Missed-dose titles name the medicine: never logged. Erasure removes the rows with the user |
| `notification_preferences` | A saved choice per category: `push`, `sms`, `in_app`, `(user_id, category)` unique among live rows. A category with no row is on, SMS off |

Domain tables are added here as each milestone lands.
