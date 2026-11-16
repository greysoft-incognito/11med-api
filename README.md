# 11med API

The API behind the 11med mobile app (`11med`) and web portals (`11med-web`). Laravel 13 on
PostgreSQL, from the `toneflix/laravel-op` starter kit. Start with [CLAUDE.md](CLAUDE.md).

```
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
php artisan serve                  # http://localhost:8000/api/v1/health
php artisan test                   # PostgreSQL database elevenmed_test
vendor/bin/pint --test
php artisan contract:routes        # refresh the route inventory from ../11med and ../11med-web
```

Deploys: `.github/workflows/deploy.yml`. A push to `staging` or `production`, or a manual run,
deploys to that environment's server once CI passes. See `human_todo.md` HT-001 for the secrets.
