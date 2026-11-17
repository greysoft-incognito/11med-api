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
- [ ] **FE-110 · A partner's contact email must be free.** `POST /partners/apply` is 409
  `EMAIL_TAKEN` on `contact_email` when that address already signs in to an account, and the
  team's approval is refused the same way if it became one meanwhile. The mock guards neither.
  `dev_set_password_link` is always null: the link goes by email only. (M11.1)

## Both

- [ ] **FE-005 · Routes no contract has yet.** Consent records (registration: FE-006 adds it to the body; profile sharing),
  the QR access log, and the Paystack webhook (server-only, no client change). Contracts to be added
  when their milestones land. (M2, M4, M9)
