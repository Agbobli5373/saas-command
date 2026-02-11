# SaaS Command

SaaS Command is a Laravel 12 + Inertia React starter focused on multi-tenant SaaS delivery:
workspace onboarding, billing, usage controls, notifications, and operations readiness.

## Tech Stack

- PHP 8.2+
- Laravel 12
- Inertia.js v2 + React 19 + TypeScript
- Tailwind CSS v4
- Laravel Fortify (auth)
- Laravel Cashier (Stripe billing)
- Laravel Wayfinder (typed route helpers)
- Pest (testing)

## Key Features

- Multi-tenant workspaces and member roles
- Onboarding flow with plan selection
- Billing and subscription management
- Usage metering and limits
- Notification center and operational checks
- Localization support (English and German)

## Localization

Supported locales:

- `en` (English)
- `de` (German)

Implementation details:

- User preference persisted in `users.locale`
- Guest preference persisted with session + locale cookie
- Locale update endpoint: `POST /locale` (`locale.update`)
- Frontend dictionaries shared through Inertia props

Translation files:

- `/lang/en.json`
- `/lang/de.json`
- `/lang/de/auth.php`
- `/lang/de/passwords.php`
- `/lang/de/validation.php`

## Local Setup

1. Install dependencies:

```bash
composer install
npm install
```

2. Create environment file and app key:

```bash
cp .env.example .env
php artisan key:generate
```

3. Configure database in `.env`, then migrate:

```bash
php artisan migrate
```

4. Generate Wayfinder routes:

```bash
php artisan wayfinder:generate --with-form --no-interaction
```

5. Start the app:

```bash
composer run dev
```

Alternative first-time shortcut:

```bash
composer run setup
```

## Quality Checks

Frontend:

```bash
npm run lint
npm run format:check
pnpm exec tsc --noEmit
```

Backend:

```bash
vendor/bin/pint --dirty
php artisan test --compact
```

Targeted example tests:

```bash
php artisan test --compact tests/Feature/Localization/LocaleSwitchingTest.php
php artisan test --compact tests/Feature/Localization/GermanTranslationTest.php
```

## Troubleshooting

If frontend assets are missing (`Unable to locate file in Vite manifest`), run:

```bash
npm run dev
```

or:

```bash
npm run build
```
