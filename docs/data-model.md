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

### Subscriptions (M5)

| Table | Notes |
| --- | --- |
| `subscriptions` | One live row per patient once the team records premium: `user_id`, `premium_until` (a Lagos calendar day, indexed for the premium count), the latest `reference`, `recorded_by` (the admin, null once erased). Premium holds while `premium_until` is today or later in Lagos; nothing is written when it lapses. `is_premium` is read from here, never stored on the user. Erasing the patient removes the row |
| `subscription_payments` | Every recording: `user_id` (null once the patient is erased, so the reference stays used), `reference` (unique among live rows, checked across trashed ones too), `premium_until`, `recorded_by`, `recorded_at` |

### Medications and readings (M6)

| Table | Notes |
| --- | --- |
| `medications` | A schedule (`zMedication`): drug, strength, form, `dose_quantity` (numeric) and `dose_unit`, `frequency_type`, `specific_times` (JSONB `HH:MM`, read in Africa/Lagos), `start_date`, `end_date`, the reminder flags, `paused_at`, and `settled_through`: the instant up to which every dose has a `dose_logs` row. Doses after it are derived from the schedule. `prescribed_by_doctor_id` (nulled if the doctor's account is erased) and `prescription_id` (the consultation's prescription, indexed; applying is idempotent on it) mark a prescribed schedule, which the cap never counts. Medical data: never logged |
| `dose_logs` | One live row per `(medication_id, scheduled_for)` (partial unique index): `state`, `skip_reason`, `logged_at` (null when the job marked it missed), `snooze_count`. Written by the patient, or by `medications:settle-doses` sixty minutes past due. A retraction soft deletes the row and, for a dose past its hour, writes a `missed` one in its place. `user_id` is denormalised for the per-patient reads |
| `readings` | A home reading (`zReading`): `type`, then `systolic`, `diastolic`, `pulse` for blood pressure, `mmol_l` and `context` for sugar, `kg` for weight (the other columns null), `taken_at`, `note`. Indexed by `(user_id, taken_at)` and `(user_id, type, taken_at)` |

All three cascade on account erasure.

### Notifications (M7)

| Table | Notes |
| --- | --- |
| `app_notifications` | A user's notification history (`zNotification`): `category`, `title`, `body`, `target_id` (the record a tap opens), `read_at` (the first read), an optional `dedupe_key` unique per user among live rows, so a repeated event writes nothing. Indexed `(user_id, created_at, id)` for the newest-first page and partially on unread rows for the count. Missed-dose titles name the medicine: never logged. Erasure removes the rows with the user |
| `notification_preferences` | A saved choice per category: `push`, `sms`, `in_app`, `(user_id, category)` unique among live rows. A category with no row is on, SMS off |

### Doctors (M8)

| Table | Notes |
| --- | --- |
| `doctor_profiles` | One live row per doctor, started blank on first read. Professional fields, `languages` and `availability` (`[{ day, start, end }]`, Africa/Lagos) as JSONB with a GIN index on `languages`, `photo_media_id`, the three fees, `follow_up_fee_kobo`, `session_minutes`, `chat_window_hours`, `bank_name`, `bank_account_number` (encrypted with `APP_KEY`) and `bank_account_last4` for the mask, `is_available_now`, `verification_status`, `verification_notes`, `submitted_at` (the review queue's order), `reviewed_at`, and the running rating (`rating_total`, `rating_count`, `rating_mean`) the directory sorts on. Erasure cascades |
| `doctor_documents` | One live row per profile and `kind`, created at the first upload: `status`, `media_id` (a private `doctor_document` upload; null for bank details, which are the payout fields), `uploaded_at`, `rejection_reason`, `reviewed_at`. A new upload replaces the file and returns the row to `uploaded` |

### Payments (M9)

Money records: erasure nulls `payments.user_id` and keeps the rows. Statuses change only through
`Settlement` (pending to paid, failed or abandoned) and `Refunds` (shares, and paid to refunded).

| Table | Notes |
| --- | --- |
| `payments` | One gateway transaction: `reference` (ours, unique), `user_id`, `purpose`, `channel` (null until known; `counter` never reaches the gateway), `status`, `amount_kobo` (the sum of its shares), `authorization_url` and `access_code` while pending, `paid_at`, `failure_reason` (Paystack's `gateway_response`), `checked_at` (last verify call) |
| `payment_targets` | One target's share: `purpose` and `target_id` (the target's public id, no foreign key since the target's table belongs to another module), `target_reference` and `partner_name` copied for the desk, `amount_kobo`, `refunded_kobo`, `state` (`standing`, `released`, `refund_pending`, `refund_failed`, `refunded`). Indexed on `(purpose, target_id)` |
| `payment_refunds` | A refund of one share: `amount_kobo`, `status` (`pending`, `processed`, `failed`), `gateway_refund_id`, `requested_at` (when Paystack accepted the request), `processed_at`, `failure_reason` |

### Consultations (M10)

Erasing a patient force-deletes their consultations that are not completed, with their messages and
prescriptions; a completed one keeps its row for the doctor's payout with `patient_id` null, the
complaint emptied and the review dropped. Erasing a doctor nulls `doctor_id` and keeps the copied name.

| Table | Notes |
| --- | --- |
| `consultations` | `patient_id`, `doctor_id` (both null on erasure), `doctor_name` and `doctor_specialty` copied at booking, `session_type`, `status` (changed only by `ConsultationLifecycle`), `complaint`, `fee_kobo`, `scheduled_for` (the slot, or the booking time for now), `scheduled`, `length_minutes`, `follow_up_of_id`, `expires_at` (the accept or join deadline the scheduler enforces), `paid_at` (set once by the payment; an unpaid row is never shown to a doctor), `started_at`, `ended_at`, `profile_shared`, `rating`, `review`, `rated_at`, `call_provider`, `call_room`, `payout_id`. A generated `inbox_rank` orders the doctor's inbox (partial index on paid rows). A partial unique index on `(doctor_id, scheduled_for)` for live scheduled rows holds a slot once |
| `consultation_messages` | `consultation_id`, `sender` (`patient` or `doctor`), `body`, `attachment_media_id` (a claimed `message_attachment` upload), `sent_at`. Medical data: never logged |
| `prescriptions` | One live row per consultation: `doctor_id`, `doctor_name` and `mdcn_number` copied at issue, `diagnosis_summary`, `drugs` (JSONB, `zPrescribedDrug` items), `issued_at`, `expires_at` (30 days), `applied_at` once added to the patient's schedule, after which it cannot be replaced |
| `drug_classes`, `drug_class_members` | Seeded by `DrugClassSeeder` (HT-009): a class's `code`, `name` and `reviewed_at`; its members' lower-case names, generic and brand, indexed for the allergy check |
| `payouts` | One per doctor per run: `doctor_name`, `bank_name` and `bank_account_masked` copied, `gross_kobo`, `commission_percent` as it was, `commission_kobo`, `net_kobo`, `consultation_count`, `status` (`pending`, `paid`, `failed`), `period_start`, `period_end`, `paid_at`, `note` (the transfer reference or the failure reason). A generated `status_rank` orders the desk. A failed payout releases its consultations to the next |

### Guide (M13)

| Table | Notes |
| --- | --- |
| `guide_conversations` | Not soft deleted. One per patient (`user_id` unique, cascades on erasure), `started_at`, `last_message_at` (indexed; idle 30 days is pruned daily). Starting over deletes the row |
| `guide_messages` | Not soft deleted. `conversation_id` (cascades), `sender` (`user`, `guide`), `body` (encrypted with `APP_KEY`), `actions` and `suggestions` JSONB, `urgency`, `sent_at`. The newest 100 per conversation are kept. Medical data: never logged |

Domain tables are added here as each milestone lands.
