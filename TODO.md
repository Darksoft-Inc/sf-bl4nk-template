# TODO

This file records the project state review from 2026-11-18. Pick one item at a time and mark it done only after the change is verified.

Baseline at review time:

- The git tree was clean on `master`.
- The app used Symfony 8.2.0-BETA2 on PHP 8.5.4.
- `php bin/phpunit` passed with 3 tests and 33 assertions, but only because generated Sass output and a local test database were present.
- Lint commands passed for the container, YAML, and Twig.
- `vendor/bin/phpstan analyse` failed with 5 errors.
- `composer audit --locked` found no advisories.
- Project-specific notes live in `docs/`. `AGENTS.md` is not part of this work.

## Done in this pass

- [x] Create `TODO.md` from the state review.
- [x] Create the first version of `docs/`.

## P0: Make a fresh checkout work

- [ ] Make the Sass build step part of setup and CI.
  - Evidence: removing `var/sass` makes `IndexControllerTest` fail with HTTP 500 and "run php bin/console sass:build".
  - Command: `symfony console sass:build`.
  - Files: `assets/app.js:8`, `assets/styles/app.scss:4`, `templates/base.html.twig:12`, `config/packages/symfonycasts_sass.yaml`.

- [ ] Make test database setup repeatable.
  - Evidence: a fresh SQLite database gives `TableNotFoundException: no such table: user`.
  - Command: `php bin/console doctrine:schema:create --env=test`.
  - The committed migration is PostgreSQL specific. `doctrine:migrations:migrate --env=test` fails on SQLite with `near "AS": syntax error`.
  - Decide between a platform independent migration and a PostgreSQL test database.
  - Files: `.env.test`, `config/packages/doctrine.yaml:22-26`, `src/Migrations/Version20261118001505.php`, `aliases.sh:35-36`.

- [ ] Add authentication and access control.
  - Evidence: `debug:router` has no login or logout route. `config/packages/security.yaml:15-23` has a firewall but no authenticator. `access_control` is empty.
  - Add a login route, a logout route, and access rules for pages that need a user.
  - Decide whether email verification is required before login.
  - Update redirects after login, registration, verification, and password reset.
  - Tests use `loginUser()`, which hides the missing login flow.

## P1: Fix functional and configuration gaps

- [x] Fix the 5 PHPStan errors without suppressions or baselines.
  - `public/index.php:8`
  - `tests/Controller/RegistrationControllerTest.php:74`
  - `tests/Controller/ResetPasswordControllerTest.php:84`
  - `tests/Pest.php:35`
  - `tests/bootstrap.php:7`

- [ ] Configure production values.
  - Set `APP_SECRET` in `.env.local` or the vault. `.env:19` is empty.
  - Configure a real `MAILER_DSN`. `.env:30` uses `null://null`.
  - Replace the default database password in `.env:47`.
  - Configure trusted hosts and proxies if the app runs behind a reverse proxy.
  - Replace `admin@example.com` in `config/packages/notifier.yaml:12` or remove the notifier package.

- [ ] Remove hardcoded mail values from controllers.
  - Files: `src/Controller/RegistrationController.php:22-23,49` and `src/Controller/ResetPasswordController.php:28-29,162`.
  - Use parameters or environment variables for sender address and sender name.

- [ ] Handle the Symfony 8.2 signed URL deprecation.
  - `vendor/bin/pest -v` reports that `UriSigner::sign()` needs an explicit expiration.
  - Check the verify-email bundle behavior first.
  - The available option is `framework.uri_signer.expiration` in `config/reference.php:637-640`.

- [ ] Align password rules.
  - `src/Form/RegistrationFormType.php:41-46` requires 6 characters.
  - `src/Form/ChangePasswordFormType.php:35-43` requires 12 characters plus strength and compromised checks.

- [ ] Replace the placeholder landing page.
  - File: `templates/index/index.html.twig:3-18`.
  - Add shared navigation and flash rendering in `templates/base.html.twig`.

- [ ] Clean generated leftovers.
  - [ ] Remove TODOs and commented code from `src/Controller/RegistrationController.php:55,89`, `src/Controller/ResetPasswordController.php:148-156`, and `src/Repository/UserRepository.php:36-59`.
  - [x] Remove the unused scaffold in `tests/Pest.php:25-37`.
  - [ ] Replace the commented redirect assertion in `tests/Controller/RegistrationControllerTest.php:47`.

- [ ] Move off Symfony 8.2 beta before production use.
  - `composer.json:4-5` allows dev packages.
  - The runtime reports Symfony 8.2.0-BETA2.

## P2: Add tests

- [ ] Registration validation: duplicate email, invalid email, empty password, short password, and unchecked terms.
- [ ] CSRF failure on all forms.
- [ ] Email verification: missing `id`, unknown user, invalid signature, expired signature, and repeated click.
- [ ] Password reset: unknown email, invalid token, expired token, reused token, mismatched passwords, weak password, and compromised password.
- [ ] Account enumeration check for the reset request route.
- [ ] Authentication routes and protected pages after login is implemented.
- [ ] Flash messages on the pages that display them.
- [ ] Email content and signed URLs with the configured `DEFAULT_URI`.
- [ ] Form and entity validation tests.
- [ ] A setup smoke test that fails clearly when Sass output or the test schema is missing.
- [x] Add assertions after `preg_match()` in both controller tests.
  - `tests/Controller/RegistrationControllerTest.php:71-74`
  - `tests/Controller/ResetPasswordControllerTest.php:82-84`

## P2: Test infrastructure and tooling

- [ ] Add fixtures or a database reset helper instead of deleting all users in `setUp()`.
- [ ] Clear dependent `reset_password_request` rows when cleaning user data.
- [ ] Align `aliases.sh` with the project commands.
  - [x] PHPStan now uses the full configured path list.
  - [ ] `aliases.sh:45` uses Pest. `AGENTS.md` names `php bin/phpunit` as the test command.
- [ ] Add CI checks for Sass build, test schema creation, lint commands, PHPStan, and PHPUnit.

## P2: Dependency and architecture review

- [ ] Decide whether notifier, scheduler, messenger transports, and the installed UX packages are intentional.
- [ ] Remove unused packages from `composer.json` and `importmap.php` if they have no planned use.
- [ ] Add a real admin or fixture user if role-based access is planned.
- [ ] Review rate limiting for registration, login, verification, and password reset routes.

## Documentation follow-up

- [x] `docs/README.md`
- [x] `docs/SETUP.md`
- [x] `docs/TESTING.md`
- [x] `docs/CONFIGURATION.md`
- [x] `docs/ARCHITECTURE.md`
- [x] `docs/AGENT_WORKFLOW.md`
- [x] `docs/DECISIONS.md`
- [ ] Update the docs when authentication, setup commands, or configuration values change.
- [ ] Link this file from a future README if the project gets one.
