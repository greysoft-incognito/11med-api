# For the owner

Things only the owner can do or decide. Each says whether it blocks and what.

- [ ] **HT-001 · GitHub environments for deploys.** Create `staging` and `production` environments on
  the repository. Secrets: `SSH_HOST`, `SSH_USERNAME`, `DEPLOY_PATH`, and `SSH_KEY` or
  `SSH_PASSWORD` (`SSH_PORT` defaults to 22; `SSH_KNOWN_HOSTS` optional). Shared values may be
  repository secrets; an environment secret overrides one of the same name. Variables: `APP_URL`
  (enables the health check), optionally `PHP_BIN` and `COMPOSER_CMD`. On each server: a git clone at
  `DEPLOY_PATH` with its `.env`, PostgreSQL, a queue worker and the scheduler's cron. Step by step in
  `docs/DEPLOY.md`. **Blocks:** deploys only.
- [ ] **HT-002 · SMS and email.** KudiSMS API key and sender id; SMTP credentials and the from address.
  Then `SMS_ENABLED=true`, `KUDISMS_API_KEY` and `KUDISMS_SENDER_ID` in each server's `.env`, and
  `php artisan dbconfig:set prefered_sms_channel KUDISMS` (the kit defaults to Twilio; `docs/SETUP.md`). Until then codes go to the log; staging can set
  `OTP_FIXED_CODE=123456` so testers can sign up. **Blocks:** M2 going live.
- [ ] **HT-003 · Social sign-in ids.** `APPLE_BUNDLE_ID` defaults to the app's bundle id,
  `ng.elevenmed.app`. `GOOGLE_WEB_CLIENT_ID` is the app's `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID`, which
  lives in the EAS `development` environment on expo.dev, not in the repo (only `563169425212-…` is
  written down). Until it is set, Google sign-in answers 422 `SOCIAL_UNAVAILABLE`. Also set
  `PORTALS_URL` to wherever the portals are hosted. **Blocks:** Google sign-in going live.
- [ ] **HT-004 · What survives account deletion, and for how long.** A legal answer (app spec item 19).
  The placeholder is `elevenmed.erasure` in `config/elevenmed.php`: consent records and the erasure
  record itself, severed from the person, for six years. **Blocks:** nothing; erasure ships with it.
- [ ] **HT-005 · Object storage.** An S3-compatible bucket and keys, then `MEDIA_PUBLIC_DISK` and
  `MEDIA_PRIVATE_DISK` pointed at an `s3` disk (a public-read bucket or prefix for pictures, a
  private one for attachments, results and licences). Until then files go to the server's
  `storage/` (`php artisan storage:link` for the public ones). **Blocks:** M3 going live.
- [ ] **HT-006 · Push credentials.** The APNs key (`.p8`), key id and team id; an FCM service account JSON for the Firebase project the Android app is built with. Place both files on each server outside `public/` and set the `PUSH_ENABLED`, `APNS_*` and `FCM_CREDENTIALS_PATH` variables (`docs/SETUP.md` §3). **Blocks:** M7 going live; until then pushes are logged.
- [ ] **HT-007 · Paystack.** Test and live secret keys, and the webhook URL set on the dashboard. Set
  `PAYSTACK_SECRET_KEY` in each server's `.env` (test key on staging, live on production) and the
  webhook URL `https://<API>/api/v1/payments/webhook/paystack` on that Paystack account; leave its
  callback URL empty (`docs/SETUP.md` §3). Until then `POST payments` answers 422
  `PAYMENTS_UNAVAILABLE`. **Blocks:** M9 going live.
- [ ] **HT-008 · Call provider.** A Daily.co (recommended) or Agora account and API key. M10 built
  the Daily.co adapter: set `CALL_PROVIDER=daily` and `DAILY_API_KEY` (docs/SETUP.md §3). Agora
  would need an adapter of its own. **Blocks:** voice and video consultations going live.
- [ ] **HT-009 · Allergy classes.** A clinician to review the drug class seed
  (`database/seeders/DrugClassSeeder.php`: 11 classes, the generic and common Nigerian brand names
  in each). Each class's `reviewed_at` stays empty until then. **Blocks:** nothing; M10.3 ships the
  seed unreviewed, and a doctor can prescribe past a warning.
- [x] **HT-010 · Test server database.** Answered 2026-11-17: PHP 8.4.27, PostgreSQL 14.23 client; CI now matches. **Blocks:** the first staging deploy.
- [ ] **HT-011 · A malware scanner for PDFs.** ClamAV on the server, or a scanning service, for
  attachments, results and licences (app handover §12). Until then a PDF with scripts, launch
  actions or embedded files is refused and the rest is stored as sent. **Blocks:** nothing.
- [ ] **HT-012 · The app's download link and the emergency page address.** `APP_DOWNLOAD_URL` is the
  link in the caregiver invitation SMS (default `https://11med.ng/app`; it should open the right
  store). `EMERGENCY_URL` is where a QR code points (default `https://11med.ng/profile/view`, the
  portals' public page). **Blocks:** caregiver invitations and QR codes going live.
