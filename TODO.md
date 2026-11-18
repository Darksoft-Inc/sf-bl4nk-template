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

## Project context

- The app is a starter application with as many features integrated as practical. It will be the common base for several public-facing services, such as an MMORPG and collaborative tools. Record this purpose in a new document, `docs/PURPOSE.md`, and use it as the reference when deciding which packages to keep, remove or change.
- Symfony 8.2 RC1 is planned for the end of November 2026. Symfony 8.2.0-BETA1 stability is enough to start development, so the current beta does not block feature work. A stable release is still required before production use.
- Translation support is required.
- A user profile page with personal details and a password change option is required.
- Some useful services are missing from the development container. Discuss them at a later stage; no container changes are planned yet.

## Done in this pass

- [x] Create `TODO.md` from the state review.
- [x] Create the first version of `docs/`.

## P0: Define the project scope

- [x] Write `docs/PURPOSE.md` and use it as the reference for package decisions.
  - State that the app is a feature-rich starter app and the common base for public-facing services such as an MMORPG and collaborative tools.
  - List the features a starter app must provide, then use that list to review `composer.json` and `importmap.php`.
  - Link the document from `docs/README.md`.
  - First version completed from the baseline, feature list, and example applications supplied on 2026-11-18.

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

- [ ] Add access control for pages that need a user.
  - [x] Add login and logout routes.
  - [x] Require a verified email address for login.
  - [x] Redirect login, registration, verification, and password change to `app_index`.
  - [x] Add access rules when protected pages exist, starting with the profile page. `ProfileController` uses `#[IsGranted('ROLE_USER')]`.

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
  - [x] Remove the notifier package and its placeholder `admin@example.com` recipient.

- [x] Remove hardcoded mail values from controllers.
  - Sender values now come from `ADMIN_EMAIL` and `ADMIN_NAME` through `app.admin_email` and `app.admin_name` in `config/services.yaml`.

- [ ] Handle the Symfony 8.2 signed URL deprecation.
  - `vendor/bin/pest -v` reports that `UriSigner::sign()` needs an explicit expiration.
  - Check the verify-email bundle behavior first.
  - The available option is `framework.uri_signer.expiration` in `config/reference.php:637-640`.

- [x] Align password rules.
  - Both forms now use `PasswordConstraints::forNewPassword()`: 12 characters, password strength, and compromised password check.
  - The `dev` environment uses 6 characters and skips the strength and compromise checks. `test` and `prod` keep the strict rules.

- [x] Replace the placeholder landing page.
  - The page now has a neutral heading and links to login, registration, and password reset.
  - `templates/base.html.twig` now renders success flash messages.

- [ ] Clean generated leftovers.
  - [ ] Remove commented code from `src/Controller/ResetPasswordController.php:148-156` and `src/Repository/UserRepository.php:36-59`.
  - [x] Remove the TODO from `src/Controller/RegistrationController.php`.
  - [x] Remove the unused scaffold in `tests/Pest.php:25-37`.
  - [x] Replace the commented redirect assertion in `tests/Controller/RegistrationControllerTest.php:47`.

- [ ] Move off Symfony 8.2 beta before production use.
  - Symfony 8.2 RC1 is planned for the end of November 2026. A beta is acceptable for development, so this item does not block feature work.
  - `composer.json:4-5` allows dev packages.
  - The runtime reports Symfony 8.2.0-BETA2.

## P1: Add a user profile page

- [x] Add a profile page for signed-in users.
  - Show personal details from `App\Entity\User`, such as the email address and verification state.
  - Offer a password change option. Reuse `ChangePasswordFormType` and align its rules with registration (see the password rules item above).
  - Add an access rule for the route, and tests for anonymous access and a successful password change.

## P1: Add translation support

- [ ] Add translation support across the app.
  - Decide the default locale and the supported locales before coding.
  - Cover templates, form labels and errors, validation messages, flash messages, and emails.
  - Check the installed `symfony/translation`, `symfony/ux-translator`, and `twig/intl-extra` packages before adding dependencies.
  - Add catalogs under `translations/` and verify them with `symfony console lint:translations`.
  - A placeholder `translations/messages.en.yaml` now holds general messages for shared pages. Templates are not switched to translation keys yet.
  - `framework.enabled_locales` is set to `['en']`, so `symfony console lint:translations` now passes.

## P2: Add tests

- [ ] Registration validation: duplicate email, invalid email, empty password, short password, and unchecked terms.
- [ ] CSRF failure on all forms.
- [ ] Email verification: missing `id`, unknown user, invalid signature, expired signature, and repeated click.
- [ ] Password reset: unknown email, invalid token, expired token, reused token, mismatched passwords, weak password, and compromised password.
- [ ] Account enumeration check for the reset request route.
- [x] Login, logout, and verified email requirement.
- [x] Protected pages when routes that need a user exist, starting with the profile page.
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

- [ ] Decide whether scheduler, messenger transports, and the remaining installed UX packages are intentional. Use `docs/PURPOSE.md` as the reference.
  - Notifier, TUI, Inspector, and Chart.js were removed on 2026-11-18.
- [ ] Remove unused packages from `composer.json` and `importmap.php` if they have no planned use.
- [ ] Add a real admin or fixture user if role-based access is planned.
- [ ] Review rate limiting for registration, login, verification, and password reset routes.

## Later: development container services

- [ ] Discuss and add useful services missing from the development container.
  - `compose.yaml` currently provides PostgreSQL. `compose.override.yaml` publishes Mailpit ports without fixed host values.
  - Collect the list of missing services before changing the container setup.

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
