# Progress

Newest first. Never edit an old entry.

## 2026-11-25: M11 The guide's store search

- `StoreDrugSearch` implements the guide's `StoreSearch` seam (M14) in SQL: the longest name of a
  listed medicine (approved pharmacy, not removed) found in the text, case-insensitive, names under
  three characters ignored. `NoStoreSearch` is removed.

## 2026-11-25: M11.4 Fulfilment, M11 done

- `GET portal/pharmacy/orders[/{id}]` (paged, newest first, `status` including `open`, `q` over
  the reference, the patient's name and phone in either form) lists only orders paid, refunded, or
  to be paid at the counter; anything else, or another pharmacy's, is 404 `ORDER_NOT_FOUND`.
  `prescription_verified` comes from `PrescribedDrugs`.
- `POST portal/pharmacy/orders/{id}/advance`: one stage at a time, `OrderBook` the only code that
  moves an order. A delivery handed to the rider needs `rider` (422 `RIDER_REQUIRED`) and takes
  `estimated_delivery_at`, else the pharmacy's `delivery_minutes` or 45; `ready_at` and
  `completed_at` are set as the steps are taken; collecting a counter pickup calls
  `Payments::collectAtCounter()`. 422 `ORDER_CANCELLED`, `ORDER_FINAL`.
- `POST portal/pharmacy/orders/{id}/cancel`: a reason of 3 to 200 characters, only before `ready`
  (422 `ORDER_STARTED`), `Payments::refund()` of that order's share.
- Each step and a pharmacy's cancellation push to the patient through `Notifier` (`OrderNotices`,
  category `order`, the mock's wording, no medicine named), deduplicated per order and status.
- M11 is done: 61 of 154 contract routes built (36 on main and 25 here), 93 pending.
  Next: the owner's review; M12 builds on `organisations`.

## 2026-11-25: M11.3 The app's store, cart and checkout

- `GET pharmacy/pharmacies[/{id}]` and `GET pharmacy/drugs[/{id}]`, patient role, approved
  pharmacies only. Ranked in SQL: the patient's profile state first, then rating with the unrated
  last, then name; paged by keyset over a derived table (FE-111). Drugs default to in stock and
  filter by `q`, `category`, `requires_prescription` and `pharmacy_id`.
- `GET` and `PATCH pharmacy/cart`: zero removes; 404 `DRUG_NOT_FOUND` for an unlisted medicine,
  422 `OUT_OF_STOCK`, 422 `CART_FULL` past 50 lines. Delivery is priced per pharmacy with its fee
  waived over its threshold; `blocked_items` from `PrescribedDrugs` (seam, M10 implements).
- `POST pharmacy/orders` (201, 20 an hour): one order per pharmacy sharing a `checkout_id`, lines
  copied as sold, cart emptied, and one payment started for all of them through `Payments`
  (`initialise`, or `openAtCounter` for a pickup paid at the counter). 422 `CART_EMPTY`,
  `PRESCRIPTION_REQUIRED` (`meta.blocked_items`), `OUT_OF_STOCK` (`meta.drug_ids`),
  `COUNTER_IS_FOR_PICKUP` on `channel`; 400 on `address` for a delivery without one.
- `GET pharmacy/orders[/{id}]` (paged, newest first) and `POST pharmacy/orders/{id}/cancel`: only
  while `placed` (422 `ORDER_CONFIRMED`), empty reason, `Payments::refund()`.
- `App\Models\Order` is `Payable`; `OrderPayables` resolves the named order then its unpaid
  siblings. `RecordOrderPayment` writes `payment_status` on `PaymentSucceeded` and
  `PaymentRefunded`, and sends the "Payment received" push through `Notifier`, deduplicated per
  payment reference.
- Erasing a patient removes their cart and orders through the foreign keys; the payment record is
  the Payments module's.

## 2026-11-25: M11.2 The pharmacy portal

- `GET` and `PATCH portal/pharmacy`, `GET portal/pharmacy/summary`, and `GET`, `POST`, `PATCH`,
  `DELETE portal/pharmacy/drugs[/{id}]`, pharmacy role, scoped to the account's organisation
  (404 `PHARMACY_NOT_FOUND` without one; another pharmacy's medicine is 404 `DRUG_NOT_FOUND`).
- Approval opens the store: `OpenPharmacyStore` listens to `PartnerApproved` and makes the
  `pharmacies` row with a ₦1,500 delivery fee. Name, state, city and address are the organisation's,
  written through `Organisations::updateListing()`; logo, cover and delivery terms are the store's.
- Pictures go through the new `MediaLibrary::replace()`: same URL keeps, null drops and discards,
  else 422 `MEDIA_NOT_FOUND` unless it is the account's own upload for that purpose.
- Medicines: the NAFDAC pattern from the portal's schema, 409 `DUPLICATE_NAFDAC` per pharmacy
  (case-insensitive), paged list with `q`, `category` and `stock` (`in` or `out`), removal refused
  with 422 `DRUG_IN_OPEN_ORDER` while an open order holds it.
- The summary counts in SQL over the orders a pharmacy may see, "today" in Lagos.
- The `orders`, `order_items` and `cart_items` tables land here because the summary and the removal
  rule read them; their routes follow in 11.3 and 11.4.

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

## 2026-11-20: M6 Medications and readings, done

- 6.1 `POST`, `PATCH`, `DELETE medications`, `pause`, `resume`, `GET medications/{userId}` (paged,
  FE-060). The cap is `Entitlements::medicationLimit()` from the seams PR, 422
  `MEDICATION_LIMIT_REACHED` with `meta.limit` and `meta.active`, counting active schedules the
  patient added; checked on create, resume and an edit that reopens an ended course.
  `Prescriptions` implements `PrescribedSchedules` for M10. `ScheduledMedications` replaces
  `NoCurrentMedications` on the emergency page. An accepted caregiver reads every `{userId}` route
  except the report; anyone else gets 404.
- 6.2 Doses come from `specific_times` in Africa/Lagos as UTC instants (FE-002). `GET doses?date=`,
  `POST doses/log`, `log-batch`, `DELETE doses/log/{doseId}` (FE-062). Two snoozes; the
  `medications:settle-doses` command, every minute, marks unanswered and snoozed doses missed sixty
  minutes past due, once, and dispatches `DoseMissed` for M7.
- 6.3 `history` (paged, settled doses, FE-063), `compliance` with `date` (FE-004), counted per
  Lagos day in SQL, null for a day with nothing due. `report` is premium (403 `PREMIUM_REQUIRED`),
  owner only, a dompdf PDF behind a 15-minute signed link (FE-064); files pruned hourly after a day.
- 6.4 `GET`, `POST`, `DELETE readings` (paged with `window_days`, FE-065), bounds as
  `READING_LIMITS`, `taken_at` at most five minutes ahead. `GET insights` (premium): the three
  formulas of handover §14, aggregated in SQL.
- New package: `barryvdh/laravel-dompdf` (the report's PDF), needs `ext-dom`; new env `REPORT_DISK`;
  the scheduler now has to run every minute (docs/SETUP.md).
- Built on the seams PR (#9) for `Entitlements` and `PrescribedSchedules`.
- 53 of 154 contract routes built.

## 2026-11-17: M8 Doctors

- 8.1 `GET` and `PATCH doctor/profile`, `POST doctor/documents`, `POST doctor/profile/submit`,
  doctor role only. The record starts blank on first read. The `PATCH` rules mirror
  `zUpdateDoctorProfileRequest`, plus server bounds and a window `end` after its `start`. The photo
  is claimed as `doctor_photo`. The bank account is encrypted and masked to its last four. Setting
  bank details marks the `bank_details` document uploaded. Documents are `doctor_document` uploads
  (a new private media purpose), named by URL in `file.uri` (FE-080). Submit answers 422
  `PROFILE_INCOMPLETE` with `meta.missing` in the app's names; approved and suspended doctors do
  not resubmit; the MDCN number is fixed once submitted (FE-081).
- 8.2 `GET portal/admin/doctors` (paged, submitted applications oldest first, `status`, `q` over
  name, MDCN number and phone in either form), the detail with signed document links, document
  review (a rejection reason of 12 to 300 characters) and the application decision. Both go
  through `DoctorVerification`, which is the portal mock's lifecycle and codes. Rejecting or
  suspending turns the live toggle off. `DoctorReviewed` and `DoctorDocumentReviewed` are
  dispatched for M7's push (ISS-080).
- 8.3 `GET doctors`, `doctors/{doctorId}` and `slots`, for patients and doctors. The directory is
  approved doctors only and paged (FE-082), with filters by specialty, language (JSONB
  containment), available now and a search over name and specialty. It is ordered in SQL by
  availability, then the running rating, then id. A doctor the viewer is engaged with reads as
  unavailable through the `EngagedDoctors` seam. Slots come from `DoctorSlots`, in Africa/Lagos,
  less the `HeldSlots` seam. `DoctorDirectory::bookable()`, `DoctorSlots::offers()` and
  `DoctorRatings::record()` are what M10 calls.
- With M7 merged, `NotifyDoctorOfReview` tells the doctor of each decision and each rejected
  document through the `Notifier` (ISS-080, doctor side).
- `POST doctor/profile/review` was added to the mock-only list, so the inventory is 153 routes.
- No new service or variable. `APP_KEY` now also protects payout account numbers (SETUP §3).
- M8 is done: its 11 routes have left `pending-routes.json`.

## 2026-11-27: M13.1 The guide's conversation (admin summary and M13.2 open)

- `GET guide/conversation` (opened with a greeting that names 112 on first read),
  `POST guide/conversation/messages` (`{ body }`, 1 to 1000 characters, 60 an hour, answers the
  whole thread) and `DELETE guide/conversation` (start over). Patient role only; `no-store`.
- `App\Services\Guide`: `EmergencyCheck` runs before anything else and wins; `RuleResponder`
  (with `PlatformRules`, `TestRules`, `SymptomRules`) is the app mock's rule engine, ported;
  `ReplyGuard` keeps only allow-listed actions and puts the 112 call first on an emergency, so a
  future model behind `GuideResponder` cannot route anywhere else.
- `StoreSearch` (M11) and `TestCatalogue` (M12) are seams with stand-ins: no drug names yet, every
  test code accepted.
- Retention: bodies encrypted; start over and erasure delete for real; idle 30 days pruned daily;
  newest 100 messages kept. Guide text is never logged.
- Not built: `GET portal/admin/summary`, which counts M5, M8, M10 and M11 tables; it lands once
  those merge. M13.2 waits on HT-001, HT-002, HT-005. 39 of 154 contract routes built on this
  branch.

## 2026-11-23: M9 Payments

- 9.1 `POST payments` (201, ten a minute per patient) and `GET payments/{reference}`, patient only.
  `{ purpose, target_id }` is resolved by the purpose's `PayableTargets` (the seams of PR #9, named in
  `elevenmed.payments.targets`) through `PayableResolver`; until a module lands, its purpose answers
  its 404 (`ORDER_NOT_FOUND`, `LAB_BOOKING_NOT_FOUND`, `CONSULTATION_NOT_FOUND`). The module's
  `Payable::assertPayable()` throws its own 422 (`ORDER_CANCELLED`, `BOOKING_CANCELLED`,
  `CONSULTATION_CLOSED`); 422 `ALREADY_PAID` is checked here as well.
- `GatewayPayments` implements the `Payments` seam and replaces `NoPayments` in its `#[Bind]`. One
  payment covers every unpaid target `resolve()` returns (an order's checkout), with one
  `payment_targets` row per target holding its share, reference and partner. Restarting abandons
  any pending payment for those targets. The reference is ours (`11M-` and 16 characters). Modules
  may start a payment themselves with `initialise()` (checkout, booking) and read it with
  `summaryFor()` or, for lists, `summariesFor()` in one query.
- Paystack is called through `PaymentGateway` (`PaystackGateway`, Laravel's HTTP client rather than
  the kit's `toneflix/paystack-php`, so tests fake it with `Http::fake`): `transaction/initialize`
  with kobo, NGN, the channel as a hint, the callback with the reference, and metadata naming the
  purpose and target ids only. An account with no email is sent as `<uuid>@customers.11med.ng`,
  not its phone number. No key: 422 `PAYMENTS_UNAVAILABLE`; Paystack down: 503
  `PAYMENT_GATEWAY_UNAVAILABLE` (adds `HttpStatus::SERVICE_UNAVAILABLE`). `counter` never reaches
  Paystack and is only for orders (422 `COUNTER_IS_FOR_PICKUP` otherwise); `collectAtCounter()`
  marks it paid on collection.
- `GET payments/{reference}` reads; a pending gateway payment is checked with `transaction/verify`
  at most every 15 seconds, since a late webhook would otherwise leave the app's poll pending.
  Paystack's own verify is authoritative; the client's word never is.
- 9.2 `POST payments/webhook/paystack`, signature checked (HMAC SHA-512, 401 `INVALID_SIGNATURE`):
  `charge.success` settles (ignored when short of the amount); `refund.processed` and `refund.failed`
  close a refund. Unknown references are acknowledged. A charge on an abandoned payment still counts;
  a second charge for targets already paid is refunded in full at once.
- `payments:sweep` every five minutes: gateway payments pending over an hour are verified once more,
  then settled or abandoned; refunds never sent are sent again. Counter payments are never swept.
- `Payments::refund(Payable, ?kobo)`: a paid share is refunded by its amount through a queued
  `RequestRefund` (retried with backoff) and reads `refunded` only when Paystack confirms; a sibling
  share stays `paid`; the payment is `refunded` once no share stands. A pending share is released
  (reads `abandoned`), and the payment abandoned when none is left. A refused refund leaves the share
  paid (`refund_failed`) for the team.
- `GET portal/admin/payments`: one row per order or booking share (consultations are not on the
  desk, as the contract's enum says), `status` per share and `q` over reference, target, partner,
  patient name and phone, in SQL, paged, newest first. `amount_kobo` is the share, not the checkout
  total the mock shows, so the rows add up to what was taken. An erased patient's payments are kept
  with `user_id` null (a money record) and drop off the desk, which needs a patient's name.
- Events, once per target and after commit, carrying the target's `Payable` as its module resolves
  it: `PaymentSucceeded` and `PaymentRefunded` (the seams), plus `PaymentFailed` and
  `PaymentAbandoned`. No push is sent here: the receipt and the doctor's request push belong to the
  target's module.
- The seams in PR #9 were amended with M9's additions (`paymentReference`, `paymentPartnerName`,
  `initialise`, `summariesFor`, a partial `refund`), agreed with the M10, M11 and M12 threads.
- ISS-090 (the `elevenmed://` callback on Paystack's page); HT-007 now lists the exact settings.
  4 routes built (3 contract routes left `pending-routes.json`; the webhook has no client contract,
  FE-005).
## 2026-11-21: M7 Notifications and push

- 7.1: `GET notifications/{userId}` pages the history newest first (FE-003) and adds the contract's
  `unread_count` (all unread, not the page's) and `has_more` (FE-070). `PATCH read/{id}` keeps the
  first read time; `PATCH read-all` is one `UPDATE`. `GET` and `PATCH preferences` answer every
  category in the contract's order, saved or defaulted (push and in-app on, SMS off), so a category
  added later appears for a user who saved before it existed (app handover §8.3); a PATCH saves only
  the categories it names. Patient and doctor accounts; another account's id is 404. Tables
  `app_notifications` (the kit already has `notifications`) and `notification_preferences`.
- 7.2: `DeliveringNotifier` replaces `SilentNotifier` as the `Notifier` binding. It reads the
  category's preference, writes the history row when in-app is on (a repeated `dedupe_key` writes
  and pushes nothing, by a partial unique index and `ON CONFLICT DO NOTHING`), and queues
  `DeliverPush` after commit when push is on and the caller did not pass `push: false`. With in-app
  off the push carries a fresh id, as the mock does. `DeliverPush` reads the user's devices in
  batches through `Devices::eachOf()` and sends with `ApnsSender` (HTTP/2, ES256 provider token
  cached 40 minutes, sandbox or production host) or `FcmSender` (HTTP v1, service-account OAuth
  token cached 50 minutes, Android channel `updates`). A token the provider reports unregistered
  or malformed is dropped by `Devices::dropInvalidToken()`, unless the install registered a new one
  meanwhile. Until `PUSH_ENABLED` and a provider's credentials are set, `LogPushSender` logs ids
  only (HT-006). Payload `zPushPayload`; FCM data values are strings, so a null `target_id` is left
  out and the payload is repeated as JSON under `body` (FE-071). Uses `firebase/php-jwt`, already a
  dependency. `SilentNotifier` stays for contexts that must notify nobody.
- 7.3: `NotifyMissedDose` listens for `App\Events\Medications\DoseMissed`, which M6's auto-miss job
  dispatches once per dose (agreed with M6; the event file is identical on both branches). History
  only, never pushed, keyed `missed_dose:<medication>:<scheduled_for>`, wording in
  `lang/en/notifications.php` as the mock's. Push copy is English only (ISS-070).
- Next: Obi's review. Callers (M6, M8, M10, M11, M12) send through `Notifier` unchanged.

## 2026-11-19: M5.2 Entitlements, M5 done

- `App\Services\Subscriptions\Entitlements` holds every free-tier gate, reading premium from the
  record: `caregiverLimit()` (1 or 5), `medicationLimit()` (3 or null), `ensureCanAddMedication()`
  (422 `MEDICATION_LIMIT_REACHED`, `meta.limit` and `meta.active` as the mock sends them; a
  prescribed medication is exempt), `historyDays()` and `readingsDays()` (7 or null),
  `historyFrom()` and `readingsFrom()` (the first Lagos day a free account may read), and
  `requirePremium()` (403 `PREMIUM_REQUIRED`) for the report and insights. M6 calls these; no
  route of its own. The free numbers are `config('elevenmed.free_tier')`.
- M5 is done: 39 of 154 contract routes built, 115 pending.

## 2026-11-19: M5.1 Subscriptions

- `GET subscriptions/{userId}` for patients and doctors, their own only (404 otherwise): `none`
  without a current record; `active`, `plan_id: 'premium'`, `current_period_end` the start of the
  day after `premium_until` in Lagos, and `cancels_at_period_end: true`, since a recorded premium
  does not renew (FE-050).
- `User::isPremium()` reads the `subscription` relation: premium while `premium_until` is today or
  later in Lagos. It lapses on its own with nothing written, so no job runs.
- `GET portal/admin/subscriptions` is paged, newest change first, with `q` over the patient's name,
  phone (either spelling) and the reference, in SQL. `POST` records `{ phone, premium_until,
  reference }` (200): 400 `VALIDATION_FAILED` on `premium_until` unless after today in Lagos, 404
  `PATIENT_NOT_FOUND` on `phone` (a verified patient account only), 409 `REFERENCE_USED` on
  `reference` for any reference ever recorded. Recording again replaces the end date (FE-051).
- Two tables: `subscriptions` (one per patient, the current state) and `subscription_payments`
  (every recording, by reference, with the admin who recorded it). Erasure removes the first and
  keeps the second without the person, so a reference can never be recorded twice.
- `Subscriptions::activeCount()` is the premium count M13's admin summary will read.
## 2026-11-19: The seams between M5 to M13

So the remaining milestones can be built in parallel, each interface one module owns and others call
exists now with a stand-in bound by `#[Bind]`; the owning milestone swaps the binding.

- `Entitlements` (M5): `medicationLimit`, `historyDays`, `readingsDays` (null is unlimited) and
  `requirePremium` (403 `PREMIUM_REQUIRED`), from `elevenmed.free_tier` (3, 7, 7). `isPremium()`
  is still false until M5 reads the subscription record.
- `Notifier` (M7): `send(User, NotificationCategory, title, body, data): ?string` (the history
  row's id); stand-in `SilentNotifier`. `NotificationCategory` mirrors `zNotificationCategory`.
- Payments (M9): `Payable` (on each paid record, with `paymentReference` and
  `paymentPartnerName` for the admin desk), `PayableTargets` (one per purpose, named in
  `elevenmed.payments.targets`: `OrderPayables` M11, `BookingPayables` M12,
  `ConsultationPayables` M10), `Payments` (`initialise(targets, payer, channel)`, `summaryFor`,
  `summariesFor(purpose, ids)` for lists, `openAtCounter`, `collectAtCounter`,
  `refund(target, kobo = all)`; stand-in `NoPayments`), events `PaymentSucceeded` and
  `PaymentRefunded`, and the `PaymentPurpose`, `PaymentStatus` and `PaymentChannel` enums.
  Summaries are `zPaymentSummary` arrays, `authorization_url` included while pending.
- `PrescribedSchedules` (M6): `apply(patient, doctor, prescriptionId, diagnosis, drugs)` returning
  the `Medication` collection, idempotent on patient and prescription, for M10.3; stand-in
  `NoPrescribedSchedules`.
- `tests/Feature/Foundation/SeamsTest.php`. Next: the milestones, each in its own thread.
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
