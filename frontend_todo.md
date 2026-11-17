# Client changes

Where the real API needs a client to change. Grouped by client; each names the route and the
milestone that introduced it. The clients tick them; never delete.

## Portals (`11med-web`)

- [ ] **FE-001 · Validation code.** Every 400 validation refusal is `VALIDATION_FAILED`, the app's
  spelling; the portals' mock answers `VALIDATION`. No screen branches on it, so only the mock and
  the handover's §8 table change. (M1)
- [ ] **FE-012 · Results and licence links lapse.** `lab_results` and `partner_licence` URLs are
  signed for 15 minutes (403 `LINK_EXPIRED` after), re-signed each time the record is read. Open
  them from a fresh read. Store pictures are plain URLs. A licence URL is accepted by one
  application only. (M3.2)

- [ ] **FE-051 · Subscription records exist only once recorded.** `GET /portal/admin/subscriptions`
  lists patients the team has recorded premium for, not every patient; `POST` finds any verified
  patient account by phone and creates the record. A lapsed record lists as `plan: 'free'` with its
  last `premium_until`. The reference is unique across every recording ever made, not only the
  current one. (M5.1)

## App (`11med`)

- [ ] **FE-002 · `scheduled_for` is an instant.** A dose's `scheduled_for` is a real UTC instant
  computed in Africa/Lagos, so `formatScheduledTime` must read it as local time like
  `formatLoggedTime`, and the mock must compose it the same way. Spec item 12. (M6)
- [ ] **FE-003 · Paged lists.** Every list comes back as `{ items, next_cursor, total }` with `cursor`
  and `limit` going up: notifications, consultations, the doctor's inbox, orders, lab bookings and
  requests, readings, dose history, the doctor, pharmacy, laboratory and drug directories,
  caregivers. (each list's milestone)
- [ ] **FE-004 · Compliance date.** `GET /medications/:userId/compliance` accepts `date`
  (`YYYY-MM-DD`, the patient's local day); send it. Spec item 13. (M6)

- [ ] **FE-006 · Send consent with the account.** `POST /auth/register` and `POST /auth/social/phone`
  accept an optional `consent: { policy_version, accepted_at }`, the record `src/lib/consent.ts`
  holds. Send it and clear the pending record on success. Without it the server records the
  current policy version (`CONSENT_POLICY_VERSION`, `2026-07-01`) at the time of the request. (M2.3)
- [ ] **FE-007 · The hard lock clears by phone.** After ten failed sign-ins the API answers 423
  `AUTH_ACCOUNT_LOCKED` with `requires_email_unlock: true`, as the mock does, but no contract has an
  email unlock route. A password reset by phone code (`forgot-password`, `reset-password`) clears
  the lock. The locked screen should point there, not to email. (M2.3)
- [ ] **FE-008 · Deleting a social-only account.** `DELETE /auth/account` confirms with the password,
  as the contract says, and an account made through Google or Apple has none, so it is refused 422
  `PASSWORD_INCORRECT`. The delete screen should send such a user through forgot-password first,
  which sets one over the phone code. (M2.8)
- [ ] **FE-009 · Social sign-in refusals.** `POST /auth/social` answers 401 `SOCIAL_TOKEN_INVALID`
  for a token that fails verification and 422 `SOCIAL_UNAVAILABLE` (field `provider`) while the
  server has no audience configured for that provider. The mock knew neither. (M2.5)

- [ ] **FE-010 · Attachment links lapse.** A `message_attachment` URL is signed for 15 minutes; after
  that it answers 403 `LINK_EXPIRED`. Re-read the message for a fresh URL rather than keeping the
  old one, and do not let `expo-image` key its cache on the signature (use `cacheKey` set to the
  media id). Profile and doctor photos are plain URLs. (M3.2)
- [ ] **FE-011 · Upload refusals the mock does not make.** The type comes from the bytes, not the
  name (415); a PDF with scripts or embedded files is 415; a picture whose pixels differ from the
  `width` and `height` sent is 422 `IMAGE_NOT_RESIZED`. `src/lib/images.ts` already sends the
  encoded size, so nothing changes unless that does. (M3.1)
- [ ] **FE-013 · Profile and caregiving are patient routes.** A doctor account gets 403 on
  `profile/*` and `medications/caregivers*`; the doctor app never calls them, so nothing should
  change unless it does. A move to another state clears `lga` unless the same request sends one in
  that state. (M4)
- [ ] **FE-014 · Caregiver lists are paged.** `GET /medications/caregivers` and
  `GET /medications/caregiving` answer `{ items, next_cursor, total }` (FE-003). (M4.3)
- [ ] **FE-080 · Doctor documents are uploaded first.** `POST /doctor/documents` cannot read a
  phone's `file://` URI. Upload the image to `POST /media` with the new purpose `doctor_document`
  (add it to `MEDIA_PURPOSES`; images only, served by signed links), then send
  `{ kind, file: { uri: <the returned url>, width, height } }`. A URL that is not the doctor's own
  `doctor_document` upload is 422 `MEDIA_NOT_FOUND` on `file.uri`; a malformed body is 400
  `VALIDATION_FAILED` with its field rather than the mock's `NO_FILE`. (M8.1)
- [ ] **FE-081 · Doctor submission refusals.** `POST /doctor/profile/submit` also answers 422
  `DOCTOR_APPROVED` or `DOCTOR_SUSPENDED` (neither resubmits), returns a pending submission
  unchanged so the 48-hour queue position holds, and lists in `meta.missing` a document that was
  rejected and not uploaded again, and `fees` when any of the three is unset. `PATCH
  /doctor/profile` refuses a changed `mdcn_number` once submitted, unless rejected: 422
  `MDCN_NUMBER_LOCKED` on `mdcn_number`. A time window whose `end` is not after its `start` is 400
  on `availability.N.end`. `POST /doctor/profile/review` is not built: the admin portal decides,
  so the development panel has nothing to call against the real API. (M8.1)
- [ ] **FE-082 · The doctor directory is paged.** `GET /doctors` answers
  `{ items, next_cursor, total }` (FE-003), still available first and then by rating. (M8.3)

## Portals (`11med-web`), doctors

- [ ] **FE-083 · Doctor applications.** `GET /portal/admin/doctors` is paged and lists only
  applications submitted at least once, oldest submission first; a draft is not an application.
  Each document's `url` is a signed link that lapses after 15 minutes (as FE-012), null for bank
  details, which are the doctor's payout fields. Suspending or rejecting also turns the doctor's
  `is_available_now` off. (M8.2)

## App (`11med`), consultations

- [ ] **FE-100 · Prescribing checks allergies.** `POST /doctor/consultations/{id}/prescription`
  refuses a drug that matches one of the patient's drug allergies by name or by drug class: 422
  `ALLERGY_CONFLICT` on `drugs`, with `meta.conflicts` a list of `{ drug_name, allergy_name,
  severity, drug_class }` (only `drug_name` and `drug_class` when the patient did not share their
  profile). The doctor's screen should show them and offer to send again with
  `acknowledge_allergy_conflicts: true`, which issues it. A prescription the patient already added
  to their schedule cannot be replaced: 409 `PRESCRIPTION_APPLIED`. The route answers 201, as a
  new prescription replaces an unapplied one. (M10.3)
- [ ] **FE-101 · Consultation lists are paged.** `GET /consultations`, `GET /doctor/consultations`
  and both `messages` routes answer `{ items, next_cursor, total }` (FE-003). Consultations come
  newest first; the doctor's inbox puts pending requests first, then confirmed, then in progress;
  messages come oldest first, so the next page is newer messages. `GET /doctor/earnings` carries the
  newest 50 payouts. (M10.1, M10.2, M10.3, M10.5)
- [ ] **FE-102 · Booking refusals the mock does not have.** `POST /consultations` also answers 422
  `SESSION_TYPE_UNAVAILABLE` on `session_type` when the doctor set no fee for it, and 422
  `DOCTOR_UNAVAILABLE` when the patient already has a confirmed or running session with that
  doctor. It takes an optional `channel` for the checkout, as `POST /payments` does. A consultation
  whose fee is 0 is paid at once and reaches the doctor without a checkout. A message's
  `attachment_url` must be the sender's own `message_attachment` upload, or 422 `MEDIA_NOT_FOUND`;
  the doctor's message answers 201, as the patient's does. (M10.1, M10.3)
- [ ] **FE-103 · The patient does not issue prescriptions.** The mock's patient-side
  `POST /consultations/{id}/prescription` is not built (mock-only); the doctor's route issues them.
  A call answers 422 `CALL_UNAVAILABLE` when no call provider is configured. A doctor suspended
  while a booking waits cancels it: the patient is refunded and told by push, and the
  consultation reads `cancelled`. (M10.3, M10.4)

## Portals (`11med-web`), payouts

- [ ] **FE-104 · Payouts are paged and settle with a reference or a reason.**
  `GET /portal/admin/payouts` answers `{ items, next_cursor, total }`, pending first, then by period
  end, newest first; `status` filters. `POST /portal/admin/payouts/{id}/settle` takes
  `{ decision: "paid", reference }` (4 to 64 characters, the bank transfer's reference) or
  `{ decision: "failed", reason }` (4 to 200), which comes back as `note`. A failed payout returns
  its consultations to the next one. A settled payout is 422 `PAYOUT_SETTLED`. (M10.5)

- [ ] **FE-050 · A recorded premium does not renew.** `GET /subscriptions/:userId` answers
  `status: 'active'`, `plan_id: 'premium'`, `current_period_end` (the start of the day after the
  recorded end date, in Lagos) and `cancels_at_period_end: true`, because the team records a fixed
  end date. The status screen shows "Renews {date}" for any active plan; it should say premium runs
  until that date when `cancels_at_period_end` is true. A lapsed record reads `status: 'none'`. (M5.1)

- [ ] **FE-070 · The notification list is paged.** `GET /notifications/:userId` answers
  `{ items, next_cursor, total, unread_count, has_more }` and takes `cursor` and `limit` (FE-003).
  `unread_count` counts every unread notification, not only the page; `has_more` is
  `next_cursor !== null`. The bell and history screen should page with the cursor. (M7.1)
- [ ] **FE-071 · Android push data arrives as strings.** FCM data values must be strings, so on
  Android `zPushPayload` comes as string fields with `target_id` left out when null, and the whole
  payload again as JSON under `body`. iOS sends it at the top level and under `body` as JSON
  objects. `readPushPayload` should parse `data.body` when it is a JSON string, else accept an
  absent `target_id` as null. To confirm on a device once HT-006 is cleared. (M7.2)

## Both

- [ ] **FE-005 · Routes no contract has yet.** Consent records (registration: FE-006 adds it to the body; profile sharing),
  the QR access log, and the Paystack webhook (server-only, no client change). Contracts to be added
  when their milestones land. (M2, M4, M9) The webhook is `POST payments/webhook/paystack` (M9).
