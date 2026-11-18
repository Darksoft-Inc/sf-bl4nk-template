# Current choices and open questions

This page records current project choices that are not obvious from the code. It also records questions that need an answer during the work in `TODO.md`.

## Test database

Current choice: development uses PostgreSQL and tests use SQLite.

Evidence: `.env` sets a PostgreSQL `DATABASE_URL`. `.env.test` sets a SQLite URL. `src/Migrations/Version20261118001505.php` contains PostgreSQL identity syntax.

Consequence: `doctrine:migrations:migrate --env=test` fails on SQLite. Tests need `doctrine:schema:create --env=test`.

Open question: keep SQLite tests with a platform independent migration, or use PostgreSQL for tests too.

## Generated CSS

Current choice: Sass output is generated into `var/sass/` and is not committed.

Evidence: `var/` is ignored by git. `assets/app.js` imports `styles/app.scss`. The runtime requires a generated output file.

Consequence: a fresh checkout needs `symfony console sass:build` before pages and browser tests work.

## Symfony version

Current choice: Symfony `8.2.*`, currently installed as 8.2.0-BETA2.

Evidence: `composer.json` requires `8.2.*` and allows dev packages. `symfony console about` reports the beta version.

Open question: when to move to a stable Symfony release.

## Authentication

Current choice: form login at `/login`, logout at `/logout`, and a verified email address required for login.

Evidence: `config/packages/security.yaml` configures `form_login` with CSRF protection and `App\Security\VerifiedUserChecker`. `SecurityController` declares both routes. Login, logout, registration, verification, and password change redirect to `app_index`.

Consequence: unverified users cannot sign in. `access_control` is still empty because the app has no protected pages.

Open question: which routes need access rules when the app grows.

## Installed UX and infrastructure packages

Current choice: the project installs several UX packages, scheduler, and messenger packages. Notifier, TUI, Inspector, and Chart.js were removed after the purpose review.

Evidence: `composer.json`, `symfony.lock`, `importmap.php`, and the generated package configuration files.

Consequence: setup includes more assets and configuration than the current pages use.

Open question: which packages have a planned use, and which should be removed.
