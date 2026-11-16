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
  **Blocks:** nothing until M2 goes live (OTP and links are logged until then).
- [ ] **HT-003 · Social sign-in ids.** Apple bundle id; the Google web client id. **Blocks:** M2.5 going live.
- [ ] **HT-004 · What survives account deletion, and for how long.** A legal answer (app spec item 19).
  **Blocks:** nothing; erasure ships with a placeholder the confirmation screen names.
- [ ] **HT-005 · Object storage.** An S3-compatible bucket and keys. **Blocks:** M3 going live (local disk until then).
- [ ] **HT-006 · Push credentials.** The APNs key (`.p8`), key id and team id; an FCM service account. **Blocks:** M7 going live.
- [ ] **HT-007 · Paystack.** Test and live secret keys, and the webhook URL set on the dashboard. **Blocks:** M9 going live.
- [ ] **HT-008 · Call provider.** A Daily.co (recommended) or Agora account and API key. **Blocks:** M10.4 going live.
- [ ] **HT-009 · Allergy classes.** A clinician to review the drug class seed. **Blocks:** nothing; M10.3 ships the seed marked unreviewed.
- [x] **HT-010 · Test server database.** Answered 2026-11-17: PHP 8.4.27, PostgreSQL 14.23 client; CI now matches. **Blocks:** the first staging deploy.
