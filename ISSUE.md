# Issues

Logged as found, never deleted; a resolved issue is marked resolved.

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
