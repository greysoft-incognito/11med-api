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
| SMTP account and a from address | Email codes, portal password links | M2 | Mail is written to the log | HT-002 |
| Google OAuth web client id | Google sign-in | M2 | 422 `SOCIAL_UNAVAILABLE` for Google | HT-003 |
| Apple bundle id | Apple sign-in | M2 | Defaults to `ng.elevenmed.app`, already right | HT-003 |
| S3-compatible bucket and keys | Uploaded files | M3 | Files stay on the server's own disk | HT-005 |
| A malware scanner (ClamAV or a service) | PDFs | M3 | PDFs with scripts or embedded files are refused; others stored as sent | HT-011, optional |
| The real store link and emergency page address | Caregiver SMS, QR codes | M4 | The defaults below are used | HT-012 |
| Paystack account: secret key (test on staging, live on production) and the webhook URL on its dashboard | Paying for orders, lab bookings and consultations | M9 | `POST payments` answers 422 `PAYMENTS_UNAVAILABLE`; paying at the counter still works | HT-007 |

Not needed yet, listed so they are not a surprise: APNs key and FCM service account (M7, HT-006),
a Daily.co or Agora key (M10, HT-008).

## 2. Server

- **PHP 8.4** with `pdo_pgsql`, `pgsql`, `intl`, `zip`, `gd` (built with JPEG, PNG and WebP),
  `exif`, `bcmath`, `mbstring`. Composer 2, Git, a web server with PHP-FPM.
- **Upload limits** (M3): `upload_max_filesize = 6M` and `post_max_size = 8M` in `php.ini`;
  `client_max_body_size 8m;` in nginx. The API refuses anything over 5 MB itself.
- **Document root** `DEPLOY_PATH/public`; the PHP-FPM user can write `storage/` and `bootstrap/cache/`.
- **Scheduler** (M2 onwards): a cron line for the PHP-FPM user. It prunes spent tokens and codes,
  every hour removes uploads no record claimed (M3), and every five minutes runs `payments:sweep`
  (M9), which settles or abandons gateway payments pending over an hour and re-sends refunds the
  gateway never received.

  ```
  * * * * * cd /var/www/11med-api && php artisan schedule:run >> /dev/null 2>&1
  ```

- **Queue worker** (M2 onwards): every SMS and email is queued, and so is every refund sent to
  Paystack (M9), so without a worker nothing is sent.
  Run `php artisan queue:work` under Supervisor or systemd;
  `scripts/generate-supervisor-config.sh <user> [processes] [driver]` writes the Supervisor program.

## 3. Environment variables

Set in the server's `.env` (copied from `.env.example`). The deploy never writes it.

### Laravel

| Variable | Value |
| --- | --- |
| `APP_ENV` | `staging` or `production` |
| `APP_KEY` | `php artisan key:generate`, once per server. It also encrypts QR tokens (M4): changing it makes every saved code unreadable, so patients would have to revoke and reissue |
| `APP_DEBUG` | `false` |
| `APP_URL` | The API's public `https://` address. Upload URLs and signed file links are built from it (M3) |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL. `DB_CONNECTION=pgsql` |
| `QUEUE_CONNECTION`, `CACHE_STORE` | `redis` in production (with `REDIS_HOST`, `REDIS_PASSWORD`, `REDIS_PORT`), or `database` |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | SMTP (HT-002). `MAIL_MAILER=log` until then |

### 11med

| Variable | Default | What | Since |
| --- | --- | --- | --- |
| `SMS_ENABLED` | `false` | `true` sends texts through the gateway; `false` writes them to the log | M2 |
| `KUDISMS_API_KEY`, `KUDISMS_SENDER_ID` | none | The KudiSMS account (HT-002). Also set the channel, section 4 | M2 |
| `OTP_FIXED_CODE` | none | Staging only: every phone and email code is this value, so testers can sign up before SMS works. Ignored in production | M2 |
| `GOOGLE_WEB_CLIENT_ID` | none | The app's `EXPO_PUBLIC_GOOGLE_WEB_CLIENT_ID`, from the EAS `development` environment on expo.dev (HT-003) | M2 |
| `APPLE_BUNDLE_ID` | `ng.elevenmed.app` | The audience Apple sign-in tokens are checked against | M2 |
| `PORTALS_URL` | `http://localhost:3000` | Where the portals live; reset and welcome emails link to `PORTALS_URL/set-password?token=…` | M2 |
| `CONSENT_POLICY_VERSION` | `2026-07-01` | The policy version recorded when the app sends no consent record | M2 |
| `MEDIA_PUBLIC_DISK` | `public` | Disk for photos and store pictures. Point at an `s3` disk with public read once the bucket exists (HT-005) | M3 |
| `MEDIA_PRIVATE_DISK` | `local` | Disk for attachments, lab results and licences. A private `s3` disk once the bucket exists | M3 |
| `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_URL`, `AWS_ENDPOINT`, `AWS_USE_PATH_STYLE_ENDPOINT` | none | The bucket, for the `s3` disk (HT-005) | M3 |
| `EMERGENCY_URL` | `https://11med.ng/profile/view` | The public page a QR code opens: the portals' `/profile/view` (HT-012) | M4 |
| `APP_DOWNLOAD_URL` | `https://11med.ng/app` | The store link in a caregiver invitation SMS (HT-012) | M4 |
| `PAYSTACK_SECRET_KEY` | none | Paystack's secret key (`sk_test_…` on staging, `sk_live_…` on production). It also verifies the webhook's signature. Never give it to a client (HT-007) | M9 |
| `PAYSTACK_CALLBACK_URL` | `elevenmed://pay` | Where Paystack's page sends the patient back; `?reference=` is added per payment. The app's pay screen (app CLAUDE.md, "Deep links"). See ISS-090 | M9 |
| `PAYMENTS_EMAIL_DOMAIN` | `customers.11med.ng` | Paystack needs an email; an account without one is sent as `<account id>@<this domain>`. No mail is ever sent there | M9 |
| `PAYMENTS_ABANDON_AFTER_MINUTES` | `60` | How long a gateway payment may stay pending before the sweep asks Paystack once more and abandons it | M9 |
| `PAYSTACK_BASE_URL` | `https://api.paystack.co` | Only for pointing tests at a stand-in | M9 |

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

## 5. Commands, once per server

Run in `DEPLOY_PATH` after the first deploy (the deploy itself runs migrations, role sync and
`storage:link` every time):

```
php artisan key:generate                       # only if APP_KEY is empty
php artisan dbconfig:sync                      # seeds the runtime settings
php artisan dbconfig:set prefered_sms_channel KUDISMS
php artisan app:create-admin you@example.com "Your Name"   # first team account; prints its link
```

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
