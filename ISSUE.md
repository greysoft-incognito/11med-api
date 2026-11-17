# Issues

Logged as found, never deleted; a resolved issue is marked resolved.

### ISS-100: A doctor in a session can still be booked for now

- **Logged:** 2026-11-17 · **Found during:** M10.1 · **Blocking:** no · **Status:** open
- **Detail:** Neither handover says whether a doctor with a session in progress stays available to
  new "now" requests. The API follows the mock: `is_available_now` is the doctor's own toggle, and
  a request waits five minutes for them to accept it. A busy doctor can let it lapse, and the
  patient is refunded.
- **Plan:** if product wants it, treat a doctor with a call or chat in progress as unavailable in
  `DoctorDirectory` and `Bookings::slot()`.

### ISS-101: No reminder before a scheduled session

- **Logged:** 2026-11-17 · **Found during:** M10.1 · **Blocking:** no · **Status:** open
- **Detail:** A scheduled consultation is accepted up to an hour before its slot and must be joined
  within ten minutes of it, but nothing reminds either side as it nears. No handover asks for one.
- **Plan:** a scheduler step sending a push to both sides fifteen minutes before, through the
  `Notifier`, once M7 lands.

### ISS-102: Only the Daily.co call adapter exists

- **Logged:** 2026-11-17 · **Found during:** M10.4 · **Blocking:** no · **Status:** open
- **Detail:** `CallProvider` has the mock and Daily.co. The contract also names `agora`; choosing it
  refuses calls with `CALL_UNAVAILABLE` (HT-008 recommends Daily.co).
- **Plan:** an `AgoraCallProvider` (a channel name and an RTC token) if Agora is chosen.

### ISS-090: Paystack may not accept the app's `elevenmed://` callback

- **Logged:** 2026-11-17 · **Found during:** M9.1 · **Blocking:** no · **Status:** open
- **Detail:** App handover §16 asks for `callback_url = elevenmed://pay?reference=…`. Paystack
  documents `callback_url` as a web address, and whether its page redirects to a custom scheme is
  unconfirmed. Payment itself does not depend on it: the webhook settles the payment and the app
  reads it on return to the foreground.
- **Plan:** try it with the test keys (HT-007). If Paystack refuses the scheme, point
  `PAYSTACK_CALLBACK_URL` at a small https page on the portals that opens `elevenmed://pay`, which
  needs no API change.

### ISS-080: Doctors are not told of review decisions yet

- **Logged:** 2026-11-17 · **Found during:** M8.2 · **Blocking:** no · **Status:** resolved
- **Detail:** Portal handover §6 makes each decision a push to the doctor, and a suspension a
  message to patients with a booking in progress. M8 dispatches `DoctorReviewed` (with the previous
  status) and `DoctorDocumentReviewed`, but nothing sends yet: the `Notifier` is M7's and the
  bookings are M10's.
- **Plan:** M7 adds listeners that send through the `Notifier`; M10 adds the suspension listener
  for open consultations.
- **Resolved (doctor side):** with M7's `Notifier` on main, `NotifyDoctorOfReview` sends each
  decision and each rejected document to the doctor (category `account`). The notice to patients
  with an open consultation when a doctor is suspended stays with M10.
- **Update 2026-11-17 (M10):** suspending a doctor, or rejecting one who was approved, cancels and
  refunds their bookings that have not begun and tells each patient.

### ISS-070: Push copy is English only

- **Logged:** 2026-11-21 · **Found during:** M7.2 · **Blocking:** no · **Status:** open
- **Detail:** App handover §8.2 asks for titles and bodies in the locale the device registered with,
  and devices carry `locale`, but `Notifier::send()` takes finished English strings and the API has
  only `lang/en`. A Yorùbá, Hausa or Igbo user gets English pushes and history rows.
- **Plan:** once the app's translations are reviewed, let callers pass a `lang/` key with
  parameters, and render it per device for the push and in the user's locale for the history row.

### ISS-005: No route verifies the emergency contact's number

- **Logged:** 2026-11-18 · **Found during:** M4.1 · **Blocking:** no · **Status:** open
- **Detail:** App spec §3.2.5 asks for an OTP to the emergency contact's number on first save, and
  `zEmergencyContact.is_verified` reports it, but no contract has a route to send or confirm that
  code. The API stores `contact_verified_at` and resets it when the number changes, so the field is
  always false today, as in the mock.
- **Plan:** when product wants it, a send and a confirm route under `profile/` using `OneTimeCodes`
  with a `contact:` target, added to the app's contracts first.

### ISS-004: One stored size for every picture

- **Logged:** 2026-11-17 · **Found during:** M3.1 · **Blocking:** no · **Status:** open
- **Detail:** The handover suggests a 400 px square for avatars beside the 1600 px original. Every
  contract carries one `url`, so the API stores the uploaded picture only, up to 1600 px. A doctor
  list showing photos then downloads full-size pictures, which costs data.
- **Plan:** when a list screen needs it, add a `thumbnail_url` to the contract (an FE item) and a
  rendition at upload.

### ISS-001: The kit's route bindings compare a string to the integer `id`

- **Logged:** 2026-11-16 · **Found during:** M1.1 · **Blocking:** yes · **Status:** resolved (M1.1)
- **Detail:** `User::resolveRouteBinding()` and `NotificationTemplate::resolveRouteBinding()` ran
  `where('id', $value)->orWhere(...)`; PostgreSQL refuses a non-numeric string against a bigint and
  the aborted transaction failed eleven kit tests. Same as agrolog-backend ISS-008.
- **Fix:** compare `id` only when the value is numeric. Worth fixing upstream in the kit.

### ISS-002: An unpinned PostgreSQL session zone shifts `timestamptz` writes

- **Logged:** 2026-11-16 · **Found during:** M1.1 · **Blocking:** no · **Status:** resolved (M1.1)
- **Detail:** Laravel writes wall clocks without an offset; a server in West Africa Time would read
  them an hour off. Same as agrolog-backend ISS-009.
- **Fix:** the `pgsql` connection sets `'timezone' => 'UTC'`.

### ISS-003: The kit's own routes are live under `/api`

- **Logged:** 2026-11-16 · **Found during:** M1.2 · **Blocking:** no · **Status:** resolved (M2)
- **Detail:** `routes/api/auth.php` exposes open self-registration and password login at
  `/api/auth/*`, and `routes/api/admin.php` the kit's admin routes, beside ours under `/api/v1`.
  Neither is in a client contract.
- **Plan:** M2 removes the kit's registration and login routes, as agrolog did, and limits the
  kit's admin routes to the `admin` role.
- **Resolved:** 2026-11-17. `routes/api/auth.php` and `routes/api/account.php` are removed (sign-up,
  sign-in, reset, verification and account deletion all bypassed `/api/v1` and erasure); the kit's
  admin routes need the `admin` role. The kit's tests for the removed routes went with them.

### ISS-130: The guide's test codes and the portals' catalogue seed disagree

- **Logged:** 2026-11-27 · **Found during:** M13.1 · **Blocking:** no · **Status:** open
- **Detail:** the app's guide rules point to `PT`, `GENOTYPE`, `BLOODGROUP`, `EUCR` and `STOOLMCS`;
  the portals' seed catalogue has none of them (it says `KFT` where the guide says `EUCR`). The
  real catalogue is the admin's (M12), so a `find_test` action naming a test it does not hold
  leads to an empty list.
- **Plan:** `ReplyGuard` already drops any test code `TestCatalogue::known()` does not return; once
  M12 binds it to the catalogue table those actions disappear on their own. The admin should add
  the missing tests, or the rules should name `KFT`, once the catalogue is seeded.

### ISS-120: `lab_requests.consultation_id` has no foreign key

- **Logged:** 2026-11-26 · **Found during:** M12.2 · **Blocking:** no · **Status:** resolved (M10)
- **Detail:** M10 owns `consultations` and builds in parallel, so M12 stores the consultation's
  public uuid without a reference. `LabRequests::create()` is called only by M10's doctor route,
  which checks the consultation first.
- **Plan:** once M10 is on `main`, a migration in M12's date range adds the foreign key to
  `consultations.uuid` (or swaps to its bigint id).
- **Resolved:** 2026-11-17 (M10). `2026_11_26_200000_add_consultation_foreign_key_to_lab_requests`
  references `consultations.uuid` and cascades when a consultation is erased.

### ISS-121: A request from an erased doctor has no `doctor_id`

- **Logged:** 2026-11-26 · **Found during:** M12.2 · **Blocking:** no · **Status:** open
- **Detail:** `zLabRequest.doctor_id` is a required uuid, but erasing the doctor nulls
  `lab_requests.doctor_id` (the name and MDCN number stay, as signed). The resource would answer
  null and the app's parse would refuse that request.
- **Plan:** decide with M8 whether a doctor's erasure keeps a tombstone id, or the contract makes
  `doctor_id` nullable (an FE item); the same question applies to prescriptions.

