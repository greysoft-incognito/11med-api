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
- [ ] **FE-060 · Schedules are paged.** `GET /medications/:userId` answers `{ items, next_cursor, total }`
  (FE-003), oldest first; the reminder scheduler should page through with `limit=100`. (M6.1)
- [ ] **FE-061 · When a schedule's doses begin.** A new schedule owes doses from an hour before it was
  added (or its `start_date`, if later), never further back, so backdating `start_date` adds no
  missed doses. A change to `specific_times`, `start_date` or `end_date` applies from an hour before
  the change; doses already answered or missed keep their times. Resuming a pause owes nothing for
  the paused days. The free-tier count (`3 of 3`) excludes prescribed schedules, which never take a
  place. `weekly` and `every_x_hours` are accepted and expanded daily from `specific_times`, as the
  handover's §5.1 says. (M6.1)
- [ ] **FE-062 · Dose answers the mock did not refuse.** `POST doses/log` answers 404
  `DOSE_NOT_FOUND` on `dose_id` for an instant that is not one of the schedule's times, or falls
  before the schedule's doses begin or inside a pause; 400 on `logged_at` more than five minutes
  ahead. `log-batch` takes at most 200 entries, applies them in `logged_at` order, clamps a third
  snooze and silently drops answers to deleted schedules. `DELETE doses/log/:id` on a dose past its
  hour leaves it `missed` rather than unanswered, and a dose the server marked missed has no answer
  to retract (404 `DOSE_NOT_ANSWERED`). (M6.2)
- [ ] **FE-063 · History is settled doses, paged.** `GET /medications/:userId/history` is paged
  newest first (FE-003) and lists answered and missed doses; a dose inside its hour and unanswered
  is on `/doses` until it is answered or missed. `window`, `days` and `date` must be integers and
  `YYYY-MM-DD` (400 otherwise); lengths outside 1 to 365 are clamped. (M6.3)
- [ ] **FE-064 · The report is a signed API link.** `url` is
  `https://<api>/api/v1/medications/reports/<id>/file?expires=…&signature=…`, a PDF for 15 minutes,
  then 403 `LINK_EXPIRED`; ten reports an hour per account (429). (M6.3)
- [ ] **FE-065 · Readings are paged.** `GET /readings` answers `{ window_days, items, next_cursor,
  total }`. `pulse` must be sent on a blood pressure, as `zLogReadingRequest` already requires,
  `null` when the cuff gives none. (M6.4)

## Both

- [ ] **FE-005 · Routes no contract has yet.** Consent records (registration: FE-006 adds it to the body; profile sharing),
  the QR access log, and the Paystack webhook (server-only, no client change). Contracts to be added
  when their milestones land. (M2, M4, M9)
