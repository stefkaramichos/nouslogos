# Copilot instructions for this repository

Purpose: Help AI coding agents become productive quickly in this Laravel application.

- Repo type: Laravel (PHP) web app. Key roots: `artisan`, `composer.json`, `package.json`, `vite.config.js`, `phpunit.xml`.

- Quick setup (typical developer flow):
  1. `composer install`
  2. Copy environment: `cp .env.example .env` (or copy manually on Windows). Fill DB credentials.
  3. `php artisan key:generate`
  4. `php artisan migrate --seed` (or run migrations selectively)
  5. `npm install`
  6. `npm run dev` (development) or `npm run build` (production). Vite is used for assets.
  7. `php artisan storage:link` if working with uploaded files.
 8. On this workspace the project lives under XAMPP (`c:\xampp\htdocs\booking-app`) — developers often use Apache from XAMPP or `php artisan serve` for quick testing.

- Running tests and linting:
  - Run tests: `php artisan test` or `vendor/bin/phpunit -c phpunit.xml`.
  - Frontend: follow `package.json` scripts; there is no enforced JS linting configured by default.

- Where to look for core components (examples):
  - Routes: `routes/web.php`
  - Controllers: `app/Http/Controllers/` (feature controllers grouped here)
  - Models: `app/Models/` (e.g. `app/Models/Appointment.php`, `Company.php`, `Customer.php`)
  - Views: `resources/views/` (Blade templates; many feature folders like `appointments`, `customers`, `expenses`)
  - Migrations: `database/migrations/` (note date-prefixed files; e.g. soft deletes added in `2025_12_12_110724_add_soft_deletes_to_appointments_table.php`)
  - Seeders & Factories: `database/seeders/`, `database/factories/` (e.g. `CompanySeeder.php`, `UserFactory.php`)

- Important project-specific patterns to follow (discoverable in code):
  - Blade-first UI: views are feature-organized and controllers return Blade views (look in `resources/views/*`).
  - Resource-style controllers and RESTful routes are commonly used; check `routes/web.php` for `Route::resource` or grouped routes.
  - Eloquent models often use standard Laravel conventions; some tables use soft deletes (see migrations).
  - Asset pipeline uses Vite; compiled assets appear under `public/build`.
  - Uploaded files are stored under `storage` and served via `public/storage` after `php artisan storage:link`.

- Integration and external dependencies:
  - Mail, queue, and other credentials are configured in `config/` and via environment variables (see `config/mail.php`, `config/queue.php`, `config/services.php`).
  - Check `composer.json` and `package.json` for third-party libraries.

- Safe change checklist for code changes that touch DB or assets:
  1. Run or update migrations under `database/migrations` and ensure seeds remain consistent.
 2. Update factories/seeders when adding required fields.
 3. Rebuild assets: `npm run build` (or `npm run dev` while iterating).
 4. Run tests: `php artisan test`.

- Examples to reference in PRs or generated code:
  - Model: `app/Models/Appointment.php`
  - Controller patterns: files in `app/Http/Controllers/`
  - Blade layout: `resources/views/layouts/` (use existing sections and stacks)

- What not to assume:
  - Do not assume a specific database is available; the repo does not store `.env` or connection credentials.
  - Do not assume a containerized workflow; the repo appears to be developed in a local XAMPP environment.

If anything in this guidance is unclear or you want more detail on a specific area (routes, a feature folder, or build scripts), request that section and I will expand or merge with existing instructions.
