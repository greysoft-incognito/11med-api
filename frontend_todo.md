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

## Both

- [ ] **FE-005 · Routes no contract has yet.** Consent records (registration and profile sharing),
  the QR access log, and the Paystack webhook (server-only, no client change). Contracts to be added
  when their milestones land. (M2, M4, M9)
