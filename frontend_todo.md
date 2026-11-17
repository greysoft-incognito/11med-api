# Client changes

Where the real API needs a client to change. Grouped by client; each names the route and the
milestone that introduced it. The clients tick them; never delete.

## Portals (`11med-web`)

- [ ] **FE-001 · Validation code.** Every 400 validation refusal is `VALIDATION_FAILED`, the app's
  spelling; the portals' mock answers `VALIDATION`. No screen branches on it, so only the mock and
  the handover's §8 table change. (M1)

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

## Both

- [ ] **FE-005 · Routes no contract has yet.** Consent records (registration: FE-006 adds it to the body; profile sharing),
  the QR access log, and the Paystack webhook (server-only, no client change). Contracts to be added
  when their milestones land. (M2, M4, M9)
