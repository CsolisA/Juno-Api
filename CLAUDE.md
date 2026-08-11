# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project state

This is a stock Laravel 13 application skeleton (PHP ^8.3). No custom domain code has been added yet beyond the framework defaults — `app/Models/User.php`, `app/Http/Controllers/Controller.php`, `app/Providers/AppServiceProvider.php`, and a single `/` welcome route in `routes/web.php`. There is no `routes/api.php` registered in `bootstrap/app.php` yet (only `web`, `console`, and the `/up` health route are wired up).

Local env (`.env`): SQLite for the database, database-backed sessions/queue/cache. `bootstrap/app.php` renders JSON error responses for any request matching `api/*`, anticipating an API to be added under that prefix.

## Commands

Run all commands from the repo root; no `cd` into subdirectories needed.

```bash
# Install PHP deps
composer install

# Run the full dev stack (server + queue listener + log tailer + vite), concurrently
composer run dev

# Run tests (clears config cache first)
composer run test
# equivalent to:
php artisan test

# Run a single test file / filter by name
php artisan test tests/Feature/ExampleTest.php
php artisan test --filter=test_the_application_returns_a_successful_response

# Lint / format PHP (Laravel Pint)
vendor/bin/pint            # fix
vendor/bin/pint --test     # check only, no changes

# Frontend (Vite + Tailwind v4)
npm run dev
npm run build

# Artisan
php artisan migrate
php artisan tinker
```

Tests run against a separate in-memory-style config: `CACHE_STORE=array`, `SESSION_DRIVER=array`, `QUEUE_CONNECTION=sync`, `DB_DATABASE=testing`, `BROADCAST_CONNECTION=null`, `MAIL_MAILER=array` (see `phpunit.xml`), so tests don't touch the local `database/database.sqlite` file or dispatch real jobs/mail.

## Architecture notes

- Standard Laravel structure: `app/Http/Controllers`, `app/Models`, `app/Providers`, `routes/{web,console}.php`, `database/{migrations,factories,seeders}`.
- Bootstrapping/middleware/exception handling is centralized in `bootstrap/app.php` (Laravel 11+ style — there is no `app/Http/Kernel.php`). Add new route files (e.g. `routes/api.php`) and middleware groups there via `Application::configure()->withRouting()` / `->withMiddleware()`.
- `bootstrap/app.php` already forces JSON rendering for exceptions on any `api/*` route, so new API endpoints should live under an `api/` prefix to get consistent JSON error responses for free.
- Autoloading (PSR-4, `composer.json`): `App\` → `app/`, `Database\Factories\` → `database/factories/`, `Database\Seeders\` → `database/seeders/`, `Tests\` → `tests/`.
- `tests/Unit` and `tests/Feature` are split per `phpunit.xml` testsuites; `TestCase.php` is the shared base (Feature tests typically extend it for HTTP/DB helpers, Unit tests extend PHPUnit's base directly).
- Sail/Docker (`compose.yaml`) is configured for MySQL 8.4, but local `.env` currently uses SQLite — Sail is optional, not the primary dev workflow.
