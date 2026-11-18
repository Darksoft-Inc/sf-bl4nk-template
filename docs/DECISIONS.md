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

Current choice: registration and password reset exist, but login, logout, and access control do not.

Evidence: `config/packages/security.yaml` has no authenticator and no access control. `debug:router` has no login or logout route.

Consequence: registered users cannot sign in through the application. Tests use `loginUser()` and do not cover this gap.

Open question: use a login form, JSON login, or another authentication method. Decide whether email verification is required before login.

## Installed UX and infrastructure packages

Current choice: the project installs several UX packages, notifier, scheduler, and messenger packages.

Evidence: `composer.json`, `symfony.lock`, `importmap.php`, and the generated package configuration files.

Consequence: setup includes more assets and configuration than the current pages use.

Open question: which packages have a planned use, and which should be removed.
