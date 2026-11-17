# Plan

Milestones in build order. Each ends green (Pint, the suite on PostgreSQL, the route ratchet) and as
one pull request from `feat/<milestone>`. Status: `[ ]` not started, `[~]` in progress, `[x]` done,
`[!]` blocked (see the id).

## M1 Foundation `[x]`

- [x] 1.1 The kit, PostgreSQL, Pint, the kit's own suite green on PostgreSQL
- [x] 1.2 The envelope, `ApiException` and the renderer, 400 `VALIDATION_FAILED`, cursor paging, public ids, soft-deleting base model
- [x] 1.3 The route ratchet over both clients' mock routes
- [x] 1.4 `CLAUDE.md` and the coordination files
- [x] 1.5 CI, and `deploy.yml` with staging and production

## M2 Identity `[x]`

- [x] 2.1 Roles, the user model's app and portal fields, `Device`, `ConsentRecord`
- [x] 2.2 Access tokens (15 minutes) and rotating refresh tokens, `TOKEN_EXPIRED`
- [x] 2.3 App registration, OTP verify and resend budget, login with lockouts, logout, session
- [x] 2.4 Forgot and reset password, change password, email change and verification
- [x] 2.5 Apple and Google sign-in, linking a phone
- [x] 2.6 Devices (`PUT`/`DELETE devices/:installId`)
- [x] 2.7 Portal sign-in, refresh, sign-out, forgot, set-password
- [x] 2.8 Account erasure

## M3 Media `[x]`

- [x] 3.1 `POST media` for every purpose, size, type and resize checks, re-encode without EXIF
- [x] 3.2 The ownership rule, signed URLs for medical files, orphan collection

## M4 Profile and caregiving `[x]`

- [x] 4.1 Medical profile (create, read, update), LGA validation
- [x] 4.2 QR tokens, revocation, the public emergency page with its access log and rate limit
- [x] 4.3 Caregiver invites (SMS, deferred link), accept, remove, read-only access

## M5 Subscriptions and entitlements `[ ]`

- [ ] 5.1 `subscriptions/:userId`, `is_premium`, the admin record and list
- [ ] 5.2 The `Entitlements` service with every free-tier gate

## M6 Medications and readings `[ ]`

- [ ] 6.1 Schedules, the cap with the prescription exemption, pause and resume
- [ ] 6.2 Dose expansion, logging, the offline batch, snoozes, the missed-dose rule
- [ ] 6.3 History, compliance, the PDF report
- [ ] 6.4 Readings and insights

## M7 Notifications and push `[ ]`

- [ ] 7.1 History, read, read-all, preferences
- [ ] 7.2 The `Notifier`, APNs and FCM senders, invalid-token cleanup
- [ ] 7.3 The missed-dose job with dedupe

## M8 Doctors `[ ]`

- [ ] 8.1 Doctor profile, documents, submit, verification states
- [ ] 8.2 Admin review of documents and applications
- [ ] 8.3 The directory, availability, slots

## M9 Payments `[ ]`

- [ ] 9.1 `Payable`, Paystack initialise, `payments/:reference`
- [ ] 9.2 The webhook, abandonment, refunds by share, the admin desk

## M10 Consultations `[ ]`

- [ ] 10.1 Booking now and scheduled, cancel, the two deadlines on the scheduler
- [ ] 10.2 The doctor's inbox, accept, decline, start, end
- [ ] 10.3 Chat with attachments, prescriptions, idempotent apply, allergy classes, ratings, follow-ups
- [ ] 10.4 Lab requests, call rooms through a provider adapter
- [ ] 10.5 Earnings and 48-hourly payouts, the admin payout routes

## M11 Partners and pharmacy `[x]`

- [x] 11.1 Applications, approval with the welcome email, suspension
- [x] 11.2 The pharmacy portal: settings, summary, drugs
- [x] 11.3 The app's store, cart, checkout into one order per pharmacy
- [x] 11.4 Fulfilment, rider, cancellations, refunds

## M12 Laboratories `[ ]`

- [ ] 12.1 The catalogue (admin) and price lists (portal)
- [ ] 12.2 The app's directory, requests and bookings
- [ ] 12.3 The laboratory portal's bookings, results, cancellations

## M13 Guide and the servers `[ ]`

- [ ] 13.1 The guide's conversation with the emergency check first
- [ ] 13.2 Staging and production running, scheduler and queue workers, both clients pointed at staging
