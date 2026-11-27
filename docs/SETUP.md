# Setup requirements

Everything the API needs outside its code: accounts with other services, server settings, environment
variables, runtime settings and one-off commands. The deploy itself (GitHub environments, SSH, what a
deploy runs, rollback) is in [DEPLOY.md](DEPLOY.md). Owner actions that are still open are tracked
by id in [human_todo.md](../human_todo.md).

**Every pull request that adds a requirement updates this file in the same PR.**

## 1. Outside services and credentials

| Service | Needed for | Since | Without it | Status |
| --- | --- | --- | --- | --- |
| PostgreSQL 14+ | Everything | M1 | Nothing runs | Test server answered (HT-010) |
| Redis | Queue and cache in production | M1 | Use `database` for both (fine for staging) | |
| Two servers and GitHub environments `staging`, `production` | Deploys | M1 | No deploys | HT-001 |
| KudiSMS account: API key and sender id | Phone codes, caregiver invitations | M2, M4 | Texts are written to the log only | HT-002 |
| Resend account: an API key and a sending domain verified in Resend (or an SMTP account) | Email codes, portal password links, a partner's welcome and suspension emails | M2, M11 | Mail is written to the log | HT-002 |
| Google OAuth web client id | Google sign-in | M2 | 422 `SOCIAL_UNAVAILABLE` for Google | HT-003 |
| Apple bundle id | Apple sign-in | M2 | Defaults to `ng.elevenmed.app`, already right | HT-003 |
| S3-compatible bucket and keys | Uploaded files | M3 | Files stay on the server's own disk | HT-005 |
| A malware scanner (ClamAV or a service) | PDFs | M3 | PDFs with scripts or embedded files are refused; others stored as sent | HT-011, optional |
| The real store link and emergency page address | Caregiver SMS, QR codes | M4 | The defaults below are used | HT-012 |
| Apple Push Notifications key (`.p8`), its key id and the team id | Push to iPhones | M7 | Pushes are written to the log (ids only) | HT-006 |
| Firebase project service account JSON (role Firebase Cloud Messaging API Admin, with the FCM HTTP v1 API enabled) | Push to Android | M7 | Pushes are written to the log (ids only) | HT-006 |
| Paystack account: secret key (test on staging, live on production) and the webhook URL on its dashboard | Paying for orders, lab bookings and consultations | M9 | `POST payments` answers 422 `PAYMENTS_UNAVAILABLE`; paying at the counter still works | HT-007 |
| Daily.co account and API key | Voice and video consultations | M10 | `CALL_PROVIDER=mock` answers made-up rooms only the app's stand-in can open; chat consultations work | HT-008 |
| A clinician's review of the drug class seed | The allergy check at prescribing | M10 | The seed ships unreviewed; doctors can still prescribe past a warning | HT-009, optional |

## 2. Server

- **PHP 8.4** with `pdo_pgsql`, `pgsql`, `intl`, `zip`, `gd` (built with JPEG, PNG and WebP),
  `exif`, `bcmath`, `mbstring`, and `dom` (the compliance report's PDF renderer, M6). Composer 2, Git, a web server with PHP-FPM.
- **Upload limits** (M3): `upload_max_filesize = 6M` and `post_max_size = 8M` in `php.ini`;
  `client_max_body_size 8m;` in nginx. The API refuses anything over 5 MB itself.
- **Document root** `DEPLOY_PATH/public`; the PHP-FPM user can write `storage/` and `bootstrap/cache/`.
- **Scheduler** (M2 onwards): a cron line for the PHP-FPM user. It prunes spent tokens and codes,
  every hour removes uploads no record claimed (M3) and compliance report PDFs older than a day,
  and **every minute marks doses missed sixty minutes after they were due** (M6,
  `medications:settle-doses`). Without the cron, unanswered doses never count against
  compliance and missed-dose notifications never go out.

  every hour removes uploads no record claimed (M3), every five minutes runs `payments:sweep`
  (M9), which settles or abandons gateway payments pending over an hour and re-sends refunds the
  gateway never received, and every day deletes guide conversations idle past
  `GUIDE_RETENTION_DAYS` (M13). Since M10 it also runs `consultations:settle` every minute (a request not
  accepted in 5 minutes, or a session not joined in 10, expires and is refunded; a chat closes at
  the end of its window) and `consultations:payouts` at 02:00 every other day (doctors' payouts).
  Without the scheduler, consultations never expire and patients are never refunded for them.

  ```
  * * * * * cd /var/www/11med-api && php artisan schedule:run >> /dev/null 2>&1
  ```

- **Queue worker** (M2 onwards): every SMS, email, push (M7) and refund sent to Paystack (M9) is
  queued, so without a worker nothing is sent.
  Run `php artisan queue:work` under Supervisor or systemd;
  `scripts/generate-supervisor-config.sh <user> [processes] [driver]` writes the Supervisor program.

## 3. Environment variables

Set in the server's `.env` (copied from `.env.example`). The deploy never writes it.

The deploy caches config and keeps queue workers running, so an edit to `.env` takes effect only after
`php artisan config:cache && php artisan queue:restart` in the API folder. Queued work (every email, SMS
and push) runs in the workers, so skipping the restart leaves them on the old values.

### Laravel

| Variable | Value |
| --- | --- |
| `APP_ENV` | `staging` or `production` |
| `APP_KEY` | `php artisan key:generate`, once per server. It also encrypts QR tokens (M4): changing it makes every saved code unreadable, so patients would have to revoke and reissue. It also encrypts guide messages (M13), which become unreadable too: start over clears them. It also encrypts doctors' payout account numbers (M8), which a changed key would lose: doctors would have to enter them again before a payout |
| `APP_DEBUG` | `false` |
| `APP_URL` | The API's public `https://` address. Upload URLs and signed file links are built from it (M3) |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL. `DB_CONNECTION=pgsql` |
| `QUEUE_CONNECTION`, `CACHE_STORE` | `redis` in production (with `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`), or `database` |
| `MAIL_MAILER` | `resend` to send through Resend, `smtp` for an SMTP account, `log` (the default) to write mail to the log until one is set up (HT-002) |
| `RESEND_API_KEY` | With `MAIL_MAILER=resend`: the API key from the Resend dashboard (API Keys, sending access is enough) |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | The sender on every email. With Resend the address must be on a domain verified under Domains in its dashboard, or Resend refuses the email |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION` | With `MAIL_MAILER=smtp` only |

### 11med

| Variable | Default | What | Since |
| --- | --- | --- | --- |
| `SMS_ENABLED` | `false` | `true` sends texts through the gateway; `false` writes them to the log | M2 |
| `KUDISMS_API_KEY`, `KUDISMS_SENDER_ID` | none | The KudiSMS account (HT-002). Also set the channel, section 4 | M2 |
| `OTP_FIXED_CODE` | none | Staging only: every phone and email code is this value, so testers can sign up before SMS works. Ignored in production | M2 |
| `GOOGLE_WEB_CLIENT_ID` | none | The app's `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID`, from the EAS `development` environment on expo.dev (HT-003) | M2 |
| `APPLE_BUNDLE_ID` | `ng.elevenmed.app` | The audience Apple sign-in tokens are checked against | M2 |
| `PORTALS_URL` | `http://localhost:3000` | Where the portals live, e.g. the deployed portal's `https://…` origin with no trailing path. Reset and welcome emails link to `PORTALS_URL/set-password?token=…`, so on a server it must be set or the links point at localhost | M2 |
| `CORS_ALLOWED_ORIGINS` | `PORTALS_URL` | Browser origins allowed to call the API, comma separated, e.g. the staging and local portals. The app is native and needs none | M13 |
| `CONSENT_POLICY_VERSION` | `2026-07-01` | The policy version recorded when the app sends no consent record | M2 |
| `MEDIA_PUBLIC_DISK` | `public` | Disk for photos and store pictures. Point at an `s3` disk with public read once the bucket exists (HT-005) | M3 |
| `MEDIA_PRIVATE_DISK` | `local` | Disk for attachments, lab results and licences. A private `s3` disk once the bucket exists | M3 |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | none | The bucket, for the `s3` disk (HT-005) | M3 |
| `EMERGENCY_URL` | `https://11med.ng/profile/view` | The public page a QR code opens: the portals' `/profile/view` (HT-012) | M4 |
| `APP_DOWNLOAD_URL` | `https://11med.ng/app` | The store link in a caregiver invitation SMS (HT-012) | M4 |
| `REPORT_DISK` | `local` | Disk for compliance report PDFs, served by a 15-minute signed link and deleted after a day. A private `s3` disk once the bucket exists (HT-005); never a public one | M6 |

| `GUIDE_RETENTION_DAYS` | `30` | Guide conversations idle this many days are deleted by the scheduler, with their messages (HT-130) | M13 |
| `PAYSTACK_SECRET_KEY` | none | Paystack's secret key (`sk_test_…` on staging, `sk_live_…` on production). It also verifies the webhook's signature. Never give it to a client (HT-007) | M9 |
| `PAYSTACK_CALLBACK_URL` | `elevenmed://pay` | Where Paystack's page sends the patient back; `?reference=` is added per payment. The app's pay screen (app CLAUDE.md, "Deep links"). See ISS-090 | M9 |
| `PAYMENTS_EMAIL_DOMAIN` | `customers.11med.ng` | Paystack needs an email; an account without one is sent as `<account id>@<this domain>`. No mail is ever sent there | M9 |
| `PAYMENTS_ABANDON_AFTER_MINUTES` | `60` | How long a gateway payment may stay pending before the sweep asks Paystack once more and abandons it | M9 |
| `PAYSTACK_BASE_URL` | `https://api.paystack.co` | Only for pointing tests at a stand-in | M9 |
| `PUSH_ENABLED` | `false` | `true` sends pushes through APNs and FCM for each provider whose credentials are set; otherwise ids are logged | M7 |
| `APNS_KEY_PATH` | none | Absolute path to the `.p8` key file, outside `public/`, readable by the PHP-FPM and queue user (HT-006) | M7 |
| `APNS_KEY_ID`, `APNS_TEAM_ID` | none | The key's 10-character id and the Apple team id (HT-006) | M7 |
| `APNS_BUNDLE_ID` | `ng.elevenmed.app` | The app's bundle id, sent as `apns-topic` | M7 |
| `APNS_PRODUCTION` | `false` | `false` on staging, whose phones run development builds with sandbox tokens; `true` on production for App Store and TestFlight builds. The wrong value makes Apple refuse every token, and refused tokens are deleted | M7 |
| `FCM_CREDENTIALS_PATH` | none | Absolute path to the Firebase service account JSON, outside `public/` (HT-006). The project id is read from it | M7 |
| `CONSULTATION_COMMISSION_PERCENT` | `15` | The platform's share of each consultation fee, taken when a payout is made. A payout keeps the percentage it was made with | M10 |
| `CALL_PROVIDER` | `mock` | `daily` once the Daily.co key exists (HT-008). `mock` answers made-up rooms; anything else refuses calls with 422 `CALL_UNAVAILABLE` | M10 |
| `DAILY_API_KEY` | none | The Daily.co API key. Never give it to a client: the API mints a token per participant | M10 |
| `DAILY_API_URL` | `https://api.daily.co/v1` | Only for pointing tests at a stand-in | M10 |

Development only: `ELEVENMED_APP_PATH` and `ELEVENMED_PORTALS_PATH` point at the client checkouts
(default `../11med` and `../11med-web`) for `php artisan contract:routes`.

### Paystack dashboard (M9, HT-007)

On each Paystack account (test for staging, live for production), under Settings, API Keys &
Webhooks:

- **Webhook URL:** `https://<the API's address>/api/v1/payments/webhook/paystack`, for example
  `https://11med-api.greysoft.com.ng/api/v1/payments/webhook/paystack` on the test server. The
  webhook is what marks a payment paid and a refund done; without it payments settle only when the
  app reads them or the sweep runs.
- **Callback URL:** leave empty. The API sends one with every transaction.
- Refunds draw on the Paystack balance; keep it funded, or refunds fail and wait for the team
  (`payment_refunds.status = failed`).

## 4. Runtime settings

Kept in the database (`laravel-dbconfig`), changed with Artisan, no deploy needed.

| Setting | Value | Command |
| --- | --- | --- |
| `prefered_sms_channel` | `KUDISMS` (the kit's default is Twilio) | `php artisan dbconfig:set prefered_sms_channel KUDISMS` |

The free tier's limits (3 medications, 1 caregiver, 7 days of history and readings) are code
config in `config/elevenmed.php` (`free_tier`, `caregivers`), changed by a deploy, not here. Premium
needs no service or credential: the team records it in the admin portal (M5).

## 5. Commands, once per server

Run in `DEPLOY_PATH` after the first deploy (the deploy itself runs migrations, role sync and
`storage:link` every time):

```
php artisan key:generate                       # only if APP_KEY is empty
php artisan dbconfig:sync                      # seeds the runtime settings
php artisan dbconfig:set prefered_sms_channel KUDISMS
php artisan app:create-admin you@example.com "Your Name"   # first team account; prints its link
php artisan db:seed --class=DrugClassSeeder --force        # the allergy classes (M10); safe to run again
php artisan db:seed --class=LabCatalogueSeeder --force    # the starting test catalogue (M12)
```

Run the `DrugClassSeeder` line again after a release that changes the seed (it adds what is
missing and never removes a class).

The test catalogue (M12) starts empty, and a laboratory can only price tests that are in it. The
seeder adds the twenty tests the app's mock uses, only those missing, so running it again never
undoes the team's edits in the admin portal.

On a test server only, `php artisan db:seed --class=DemoSeeder --force` loads the app mock's
demo data so the app and portals open on real screens: the demo patient `08030000001` and doctor
`08030000003` (password `Password1`, the app's "Sign in as the demo" buttons), five more approved
doctors, and five pharmacies and five laboratories whose portal accounts are
`<name-slug>@example.com` with the same password (`harbourview-pharmacy@example.com`,
`clinix-diagnostics@example.com`, ...). It runs once (nothing happens when the demo patient
exists) and fetches its pictures from Unsplash and Pexels, so the server needs outbound HTTPS;
a picture it cannot fetch is left out. Never run it on production: the passwords are public.

The missed-dose job can be run by hand to check the scheduler is wired: `php artisan medications:settle-doses`
prints how many doses it marked missed.

## 6. Local development

```
composer install
cp .env.example .env && php artisan key:generate
createdb elevenmed && createdb elevenmed_test     # PostgreSQL; set DB_USERNAME and DB_PASSWORD
php artisan migrate --seed
php artisan test                                  # runs on elevenmed_test
vendor/bin/pint --test
```

Keep `../11med` and `../11med-web` checked out beside this repository: the route ratchet test reads
their mocks, and skips that comparison when they are missing.

## 7. Pointing the clients at a server

Both clients run against in-browser or in-app mocks until told otherwise. To use a server (staging
first), set these in the client, not here:

| Client | Variables | Value for staging |
| --- | --- | --- |
| App (`../11med`) | `EXPO_PUBLIC_USE_MOCKS=false`, `EXPO_PUBLIC_API_URL` | The server's `APP_URL`, without `/api/v1` (the app adds it). Set in the EAS `development` environment on expo.dev, since builds and `npm run ship` read from there |
| Portals (`../11med-web`) | `NEXT_PUBLIC_API_MODE=live`, `NEXT_PUBLIC_API_BASE_URL` | The same address, without `/api/v1` |

On the server, `CORS_ALLOWED_ORIGINS` (or `PORTALS_URL`) must name the portals' origin, or the
browser refuses every call. Check a server is ready with `curl https://<APP_URL>/api/v1/health`.

## 8. A server is running when

- The deploy workflow has run green for it (HT-001), and `/api/v1/health` answers.
- `php artisan schedule:list` shows the jobs in section 2 and the cron line is installed.
- A queue worker is up (`supervisorctl status`), so codes and invitations leave the queue.
- SMS and mail are real or deliberately logged (HT-002), and uploads go to the bucket (HT-005).
- The runtime settings and first admin from section 5 exist.

