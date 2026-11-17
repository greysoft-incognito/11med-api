# Deploying the 11med API

Two servers, `staging` and `production`, set up the same way. GitHub Actions deploys to them over
SSH with `.github/workflows/deploy.yml`, after the test suite passes.

## When a deploy runs

| Trigger | Deploys |
| --- | --- |
| A push to the `staging` branch | That commit to staging |
| A push to the `production` branch | That commit to production |
| **Actions → Deploy → Run workflow**, choosing a branch and a server | The chosen branch's head to the chosen server |

Every run first runs CI (Pint, a fresh migration and PHPUnit on PostgreSQL). If CI fails, nothing
deploys. Two deploys to the same server never overlap: a second one waits for the first.

## Setting up a server (once per server)

1. **Software**: PHP 8.4 (CI tests on 8.4) with `pdo_pgsql`, `pgsql`, `intl`, `zip`, `gd`, `exif`, `bcmath`
   and `mbstring`; Composer 2; Git; PostgreSQL 14 or later; a web server (nginx or Apache) with PHP-FPM;
   Redis for the queue and cache in production.
2. **Database**: create the database and a user that owns it.
3. **Clone the repository** to the path you will use as `DEPLOY_PATH`, e.g.
   `/var/www/11med-api`. Use a deploy key or token that can read the repository, so that the
   server's `git fetch` works without a prompt.
4. **`.env`**: copy `.env.example` to `.env` in that folder and fill it in: `APP_ENV`
   (`staging` or `production`), `APP_KEY` (run `php artisan key:generate` once), `APP_DEBUG=false`,
   `APP_URL`, the `DB_*` values, `QUEUE_CONNECTION=redis` and `CACHE_STORE=redis` with the `REDIS_*`
   values, and mail. For sign-in also set `SMS_ENABLED` (with the kit's SMS settings) or, on staging
   only, `OTP_FIXED_CODE`; `GOOGLE_WEB_CLIENT_ID` and `APPLE_BUNDLE_ID` for social sign-in; and
   `PORTALS_URL`, where portal reset emails link to. The deploy never writes `.env`, and stops if it
   is missing.
5. **Web server**: point the document root at `DEPLOY_PATH/public`. Give the PHP-FPM user write
   access to `storage/` and `bootstrap/cache/`.
6. **Scheduler**: the consultation deadlines, missed doses and abandoned payments run on Laravel's
   scheduler, so add this cron entry for the PHP-FPM user:

   ```
   * * * * * cd /var/www/11med-api && php artisan schedule:run >> /dev/null 2>&1
   ```

7. **Queue worker**: keep `php artisan queue:work` running under Supervisor or systemd.
   `scripts/generate-supervisor-config.sh <user> [processes] [driver]` writes a Supervisor
   program for it. Each deploy runs `queue:restart`, so workers pick up the new code.
8. **First run**: `composer install --no-dev`, `php artisan migrate --force` and
   `php artisan app:sync-roles`, or just run the workflow, which does all of it.
9. **First team account**: `php artisan app:create-admin you@example.com "Your Name"` opens an admin
   account and emails a first-password link, which it also prints.

## GitHub settings

Under **Settings → Environments**, create `staging` and `production`. Each environment holds its
own server's secrets. A repository secret (**Settings → Secrets and variables → Actions**) with
the same name is used for every environment that does not set its own, so put shared values there
and only the differences on each environment.

### Secrets

| Name | Required | What |
| --- | --- | --- |
| `SSH_HOST` | yes | The server's hostname or IP |
| `SSH_USERNAME` | yes | The user to sign in as; it must own `DEPLOY_PATH` |
| `SSH_KEY` | one of these two | A private key, the whole file including the `BEGIN` and `END` lines. Used whenever it is set |
| `SSH_PASSWORD` | one of these two | The user's password. Used only when `SSH_KEY` is not set |
| `SSH_PORT` | no | Defaults to 22 |
| `SSH_KNOWN_HOSTS` | no | The server's line from `known_hosts`. Without it the workflow trusts whatever key the server presents on each run (`ssh-keyscan`), so set it for production |
| `DEPLOY_PATH` | yes | The clone on the server, e.g. `/var/www/11med-api` |

### Variables

| Name | Required | What |
| --- | --- | --- |
| `APP_URL` | no | The API's base URL. When set, the job checks `APP_URL/api/v1/health` after deploying and fails if it does not answer |
| `PHP_BIN` | no | The PHP binary when it is not `php` on the path, e.g. `/usr/bin/php8.3` |
| `COMPOSER_CMD` | no | How to run Composer when `composer` is not on the path, e.g. `php8.3 /usr/local/bin/composer` |

To require a person's approval before production deploys, add a required reviewer to the
`production` environment.

## What a deploy does

`scripts/deploy.sh` runs on the GitHub runner. It checks that the required secrets are set, signs
in with the key or the password, and streams `scripts/deploy-remote.sh` to the server. On the
server, inside `DEPLOY_PATH`, the remote script:

1. Puts the app into maintenance mode (and always takes it out again, even when a step fails).
2. Fetches the branch and checks out the exact commit that passed CI.
3. Clears cached manifests, then runs `composer install --no-dev --optimize-autoloader`.
4. Runs migrations (`migrate --force`) and syncs roles and permissions (`app:sync-roles`).
5. Links storage, caches config, routes and events, and restarts the queue workers.

## Rolling back

Create a branch at the commit you want back (`git branch rollback <sha>` and push it), then run
**Deploy** by hand from that branch. The deploy checks out exactly that commit. Migrations do not roll back on their
own. If a release added one that has to be undone, run `php artisan migrate:rollback --step=1` on the
server first, then redeploy the earlier commit.

## When it fails

| Message | Fix |
| --- | --- |
| `Set SSH_HOST for the 'staging' environment` (or `SSH_USERNAME`, `DEPLOY_PATH`) | Add the secret to the environment or the repository |
| `Set SSH_KEY or SSH_PASSWORD` | Add one of them |
| `Permission denied (publickey)` | The key's public half is not in `~/.ssh/authorized_keys` for `SSH_USERNAME` |
| `Permission denied (password)` | The password is wrong, or the server does not allow password sign-in (`PasswordAuthentication` in `sshd_config`) |
| `… is not a git clone` | `DEPLOY_PATH` is wrong, or the repository was never cloned there |
| `No .env in …` | Create the server's `.env` (step 4 above) |
| The health check fails | Look at `storage/logs/laravel.log` on the server; check `APP_URL` and the web server's document root |
