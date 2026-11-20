# TODO

This file records the project state review from 2026-11-18 and the work done since. Pick one item at a time and mark it done only after the change is verified.

Baseline at review time:

- The git tree was clean on `master`.
- The app used Symfony 8.2.0-BETA2 on PHP 8.5.4.
- `php bin/phpunit` passed with 3 tests and 33 assertions, but only because generated Sass output and a local test database were present.
- Lint commands passed for the container, YAML, and Twig.
- `vendor/bin/phpstan analyse` failed with 5 errors.
- `composer audit --locked` found no advisories.
- Project-specific notes live in `docs/`. `AGENTS.md` is not part of this work.

Progress on 2026-11-19: a fresh checkout works with the two documented commands, the generated leftovers are gone, translation support covers templates, forms, validation, flash messages, and emails, the test suite has a reset helper and 96 passing assertions with the deprecation visible in both runners, the dependency review ended with only `symfony/web-link` and `symfony/expression-language` removed, and the dev container gained Adminer. The signed URL deprecation was resolved later the same day through the signer service override in `config/services.yaml`. The remaining open items are deferred work: production values and rate limiting, the move off the 8.2 beta, the P2 test list, and CI.

Progress on 2026-11-20: the simple P2 tests are in, and the reused reset token and repeated verification click fixes followed the same day. The suite reports 33 tests and 252 assertions in both runners. Four problems surfaced and were fixed: the registration form accepted syntactically invalid email addresses because `EmailType` sets no server-side constraint, the repeated password mismatch rendered its translation key because the key sat in the `messages` domain while form `invalid_message` values translate through the `validators` domain, a rejected reset token stayed in the session and replayed its validation error on every later visit to the reset route, and a repeated click on a verification link re-ran the verification instead of reporting the verified state.

Progress on 2026-11-21: the last three P2 tests are in, and the suite reports 36 tests and 298 assertions in both runners. The upstream report for the UriSigner deprecation was submitted to SymfonyCasts/verify-email-bundle and is being processed; the local draft file is removed. The compromised password case found a gap: the framework disables `validation.not_compromised_password` when `framework.test` is on, so the strict rules recorded for the test environment did not apply, and a leaked password passed the suite. `config/packages/validator.yaml` opts the test environment back in, the five test classes that submit password forms replay the HIBP exchanges from `tests/recordings/pwnedpasswords-ranges.har` through `#[UseRecord]`, and the recording workflow is documented in `docs/TESTING.md` and `docs/DECISIONS.md`.

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

- [x] Make the Sass build step part of setup and CI.
  - Evidence: removing `var/sass` makes `IndexControllerTest` fail with HTTP 500 and "run php bin/console sass:build".
  - Command: `symfony console sass:build`.
  - Files: `assets/app.js:8`, `assets/styles/app.scss:4`, `templates/base.html.twig:12`, `config/packages/symfonycasts_sass.yaml`.
  - The setup side is done: `docs/SETUP.md` lists the build in the install steps, `.symfony.local.yaml` runs `sass:build --watch` with the dev server, and the fresh-checkout verification on 2026-11-19 ran the build and passed the suite. The CI side is covered by the deferred CI item below.

- [x] Make test database setup repeatable.
  - Evidence: a fresh SQLite database gives `TableNotFoundException: no such table: user`.
  - Command: `php bin/console doctrine:schema:create --env=test`.
  - The committed migration is PostgreSQL specific. `doctrine:migrations:migrate --env=test` fails on SQLite with `near "AS": syntax error`.
  - Decision: keep SQLite for tests. The test schema comes from the entity metadata through `doctrine:schema:create`, which is platform independent. The migration stays PostgreSQL specific and targets development and production only. Recorded in `docs/DECISIONS.md`.
  - `.env.test` was missing from the repository, so the test environment fell back to the PostgreSQL URL from `.env` and tests failed with a connection error. Restored it with the recipe defaults and the SQLite URL the docs describe.
  - Verified on a fresh checkout on 2026-11-19: `doctrine:schema:create --env=test`, then `php bin/phpunit` passes with 11 tests and 94 assertions. The drop and create cycle from `docs/SETUP.md` passes, a missing schema still fails with `no such table: user`, and `doctrine:schema:validate --env=test` reports the schema in sync.

- [x] Add access control for pages that need a user.
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
  - [ ] Rate limiting for the public auth routes. Planned in `docs/DECISIONS.md`.
  - [x] Remove the notifier package and its placeholder `admin@example.com` recipient.

- [x] Remove hardcoded mail values from controllers.
  - Sender values now come from `ADMIN_EMAIL` and `ADMIN_NAME` through `app.admin_email` and `app.admin_name` in `config/services.yaml`.

- [x] Handle the Symfony 8.2 signed URL deprecation.
  - `vendor/bin/pest -v` reports that `UriSigner::sign()` needs an explicit expiration.
  - Since 2026-11-19, `php bin/phpunit` reports the same deprecation and exits 0, so both runners show it. See the Deprecations section in `docs/TESTING.md`.
  - Check the verify-email bundle behavior first.
  - The available option is `framework.uri_signer.expiration` in `config/reference.php:637-640`.
  - Finding on 2026-11-19: the option does not fix this deprecation. The bundle builds its own signer through `UriSignerFactory` as `symfonycasts.verify_email.uri_signer`, and `framework.uri_signer.expiration` configures the framework's separate `uri_signer` service. No released or unreleased bundle version passes an expiration, including the 2.x branch.
  - Fix: `config/services.yaml` overrides the bundle service with the bundle's arguments, `'%kernel.secret%'` and `signature`, plus a default expiration of 1 day. The value stays above the bundle's 1 hour link lifetime, so the bundle's own expiry check still decides the error message. Recorded in `docs/DECISIONS.md` and `docs/CONFIGURATION.md`.
  - Verified on 2026-11-19: `php bin/phpunit` and `vendor/bin/pest -v` report 11 tests and 96 assertions with no deprecations, and PHPStan, `lint:yaml`, `lint:container`, and `lint:translations` pass.
  - No upstream issue existed on 2026-11-19. The report was submitted to `SymfonyCasts/verify-email-bundle` on 2026-11-21 and is being processed upstream. The draft held at `docs/upstream-verify-email-signer-deprecation.md` is removed.

- [x] Align password rules.
  - Both forms now use `PasswordConstraints::forNewPassword()`: 12 characters, password strength, and compromised password check.
  - The `dev` environment uses 6 characters and skips the strength and compromise checks. `test` and `prod` keep the strict rules.

- [x] Replace the placeholder landing page.
  - The page now has a neutral heading and links to login, registration, and password reset.
  - `templates/base.html.twig` now renders success flash messages.

- [x] Clean generated leftovers.
  - [x] Remove commented code from `src/Controller/ResetPasswordController.php:148-156` and `src/Repository/UserRepository.php:36-59`.
    - The `$translator` parameter of `request()` and `processSendingPasswordResetEmail()` served only the removed flash block, so both signatures lost it. The catch now captures no variable, and the stray `)` on the old line 51 is gone.
    - Verified on 2026-11-19: `vendor/bin/phpstan analyse --no-progress` reports no errors, and `php bin/phpunit` passes with 11 tests and 94 assertions.
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

- [x] Add translation support across the app.
  - Decision: the default locale is `en` and it is the only enabled locale, matching `framework.enabled_locales` in `config/packages/framework.yaml`. Recorded in `docs/DECISIONS.md`.
  - `translations/messages.en.yaml` now covers page titles, navigation, account labels, form labels, the repeated password mismatch, flash messages, and email texts. All templates render these keys.
  - `translations/validators.en.yaml` holds the entity and form constraint messages: the unique email check, the terms checkbox, the email blank check, and the password blank and length checks. The validator translates them through the `validators` domain, so the weak password test still shows the full sentence.
  - `translations/security.en.yaml` holds `app.verified_email_required`. `VerifiedUserChecker` throws the key and the login template translates it through the `security` domain.
  - Email subjects moved from the controllers into the email templates through `email.setSubject()`. Both email tests now assert the subject.
  - No new packages were needed. `symfony/ux-translator` and `twig/intl-extra` stayed unused.
  - Verified on 2026-11-19: `symfony console lint:translations` reports every domain valid, `vendor/bin/phpstan analyse --no-progress` reports no errors, and `php bin/phpunit` passes with 11 tests and 96 assertions, including the exact English strings the tests check.

## P2: Add tests

- [x] Registration validation: duplicate email, invalid email, empty password, short password, and unchecked terms.
  - The invalid email case found a bug: `EmailType` sets the HTML5 input type only, so an invalid address passed validation and the verification email then crashed with an RFC 5322 exception in `Mime\Address`. `RegistrationFormType` now adds the `Email` constraint, found by the test on 2026-11-20.
  - Verified on 2026-11-20: `php bin/phpunit` passes with 30 tests and 200 assertions, PHPStan reports no errors, and the lint commands pass.
- [x] CSRF failure on all forms.
  - `tests/Controller/CsrfProtectionControllerTest.php` submits a bad token to the registration, reset request, reset change, profile change, and login forms. The form failures assert 422 and that no data changed, because the CSRF error renders on a hidden field and is not visible in the response. The login failure asserts the security "Invalid CSRF token." message.
- [x] Email verification: missing `id`, unknown user, invalid signature, expired signature, and repeated click.
  - [x] Missing `id`, unknown user, and invalid signature: `tests/Controller/EmailVerificationControllerTest.php`.
  - [x] Expired signature: the bundle compares `expires` against `time()`, so clock mocking does not reach it. The test re-signs an expired URL through the `symfonycasts.verify_email.uri_signer` service: `testVerifyWithExpiredSignatureReportsExpiredOrVerifiedState()` rebuilds the link with a past `expires` and a fresh signature, then checks the expired-link error on the register page for an unverified visitor and the verified state through the catch branch for a verified visitor.
  - [x] Repeated click.
    - Repeated clicks on a still-valid link now flash "Your email address has already been verified." and redirect to the index, without re-running the verification. The first click keeps its own flash.
    - `EmailVerifier::handleEmailConfirmation()` was split into `validateEmailConfirmation()` and `markEmailVerified()`, because the controller needs the verified state between validating the link and marking the user. The controller validates the signature first, so requests without a valid signature answer the same way whether the account exists, is verified, or is not. An expired link clicked by a verified user also reports the verified state through the catch branch.
    - Verified on 2026-11-20: `php bin/phpunit` and `vendor/bin/pest` pass with 33 tests and 252 assertions, and PHPStan and the lint commands pass.
- [x] Password reset: unknown email, invalid token, expired token, reused token, mismatched passwords, weak password, and compromised password.
  - [x] Unknown email, invalid token, mismatched passwords, and weak password: `tests/Controller/ResetPasswordControllerTest.php`. The mismatch case found the key `app.form.password_must_match` in the wrong translation domain: form `invalid_message` values translate through the `validators` domain, so the key moved from `messages.en.yaml` to `validators.en.yaml`.
  - [x] Expired token: the bundle builds expiry dates from `Clock::get()`, so `ClockSensitiveTrait::mockTime()` moves the token past its lifetime. `testExpiredResetTokenIsRejected()` mocks the clock 2 hours ahead before the click, then asserts the expired-link flash and the unchanged password hash. The bundle garbage collector only removes requests expired more than a week ago, so the token survives to report its own expiry.
  - [x] Reused token.
    - The tests open the reset link again after a completed reset: the reuse is rejected with the invalid link flash, and the password stays the first new password.
    - The test found a gap in `ResetPasswordController::reset()`: a rejected token stayed in the session under `ResetPasswordPublicToken`, so every later visit to `/reset-password/reset` replayed the validation of a token already known to be invalid. The catch path now removes the token, and a later visit gets the missing token 404 instead of the stale flash.
    - Verified on 2026-11-20: `php bin/phpunit` passes with 32 tests and 235 assertions, and PHPStan and the lint commands pass.
  - [x] Compromised password: `NotCompromisedPassword` did not run in the test environment, because the framework disables `validation.not_compromised_password` when `framework.test` is on. That contradicted the strict rules recorded for test and prod, so `config/packages/validator.yaml` opts the test environment back in. `testResetRejectsCompromisedPassword()` submits a known leaked password (`Password123456!`, present in breach corpora thousands of times) through the reset form and asserts the violation and the unchanged hash. The exchange with `api.pwnedpasswords.com` is recorded in `tests/recordings/pwnedpasswords-ranges.har`; the five test classes that submit password forms replay it through `#[UseRecord]`, so the suite needs no network. See `docs/TESTING.md` for the re-recording workflow.
  - Verified on 2026-11-21: `php bin/phpunit` and `vendor/bin/pest -v` pass with 36 tests and 298 assertions, and PHPStan, `lint:container`, `lint:yaml`, `lint:twig`, and `lint:translations` pass.
- [x] Account enumeration check for the reset request route.
  - The test compares the full page text of the check-email page for a known and an unknown address, and only the email count differs.
- [x] Login, logout, and verified email requirement.
- [x] Protected pages when routes that need a user exist, starting with the profile page.
- [x] Flash messages on the pages that display them.
  - The base template success flash is asserted after email verification, the register page error flash by the invalid signature test, and the request page error flash by the invalid token test. The profile success flash was already asserted by `ProfileControllerTest`.
- [x] Email content and signed URLs with the configured `DEFAULT_URI`.
  - The verification link extraction reads `router.request_context.base_url`, which resolves the `DEFAULT_URI` from `config/packages/routing.yaml`, instead of a hardcoded `http://localhost`. The reset link extraction matches the path only and stays host independent.
- [x] Form and entity validation tests.
  - The forms are validated through HTTP in the controller tests, `PasswordConstraints` has its own test, and the entity's only constraint is the unique email check, which the duplicate registration test covers. A validator level test would repeat that coverage without adding behavior.
- [x] A setup smoke test that fails clearly when Sass output or the test schema is missing.
  - `tests/SetupSmokeTest.php` checks for generated CSS under `var/sass/` and for the `user` and `reset_password_request` tables in the test database. The failure messages name the command to run, and both failures were checked by removing each artifact once.
- [x] Add assertions after `preg_match()` in both controller tests.
  - `tests/Controller/RegistrationControllerTest.php`, `extractVerificationLink()`
  - `tests/Controller/ResetPasswordControllerTest.php`, `extractResetLink()`

## P2: Test infrastructure and tooling

- [x] Add fixtures or a database reset helper instead of deleting all users in `setUp()`.
  - Decision: a reset helper, not fixtures, to avoid a new dependency while the app has one entity pair. `tests/ResetDatabase.php` provides the `ResetDatabase` trait, and all four database tests call `resetDatabase()` in `setUp()`.
  - The reset removed the only reader of `SecurityControllerTest::$userRepository`, so the property, its assignment, and the import are gone. PHPStan caught the write-only property.
  - Verified on 2026-11-19: `php bin/phpunit` passes with 11 tests and 96 assertions, and `vendor/bin/phpstan analyse --no-progress` reports no errors.
- [x] Clear dependent `reset_password_request` rows when cleaning user data.
  - The helper deletes `reset_password_request` rows before `user` rows. Verified by inserting an abandoned reset request with `dbal:run-sql` and running another test: both tables count zero rows afterwards.
- [x] Align `aliases.sh` with the project commands.
  - [x] PHPStan now uses the full configured path list.
  - [x] `aliases.sh:45` used Pest. `AGENTS.md` names `php bin/phpunit` as the test command. `run_tests` now runs `php bin/phpunit`.
- [ ] Add CI checks for Sass build, test schema creation, lint commands, PHPStan, and PHPUnit.
  - Deferred on 2026-11-19 until the hosting platform is chosen. The checks to run: `symfony console sass:build`, `php bin/console doctrine:schema:create --env=test`, the lint commands and PHPStan from `docs/AGENT_WORKFLOW.md`, and `php bin/phpunit`.

## P2: Dependency and architecture review

- [x] Decide whether scheduler, messenger transports, and the remaining installed UX packages are intentional. Use `docs/PURPOSE.md` as the reference.
  - Notifier, TUI, Inspector, and Chart.js were removed on 2026-11-18.
  - Review outcome on 2026-11-19: every remaining package maps to a `docs/PURPOSE.md` target feature. Messenger keeps only the `sync` transport; the `doctrine://` lines and `MESSENGER_TRANSPORT_DSN` stay as placeholders until background jobs need a transport. Recorded in `docs/DECISIONS.md`.
- [x] Remove unused packages from `composer.json` and `importmap.php` if they have no planned use.
  - Removed `symfony/web-link` and `symfony/expression-language`: no installed package depends on them, and no application code references them. `lint_autowiring` now runs `lint:container` alone, because `lint:expressions` left with expression-language.
  - `symfony/serializer`, `symfony/uid`, and `symfony/ux-router` came back the same day, with their planned uses recorded in `docs/PURPOSE.md`: JSON for dynamic frontend data, entity identifiers that exist before the database key, and routes in JavaScript.
  - `symfony/http-client` and `symfony/process` stay: the compromised password check runs through the HTTP client and the Sass build through Process.
  - Verified on 2026-11-19: `composer remove`, `composer require`, and their auto-scripts pass, `php bin/phpunit` reports 11 tests and 96 assertions with the known deprecation, and PHPStan, `lint:container`, `lint:yaml`, `lint:twig`, and `lint:translations` pass.
- [x] Add a real admin or fixture user if role-based access is planned.
  - Decision: defer until the first admin feature. No admin page or route exists, and tests create their own users, so an admin or fixture user would be dead data today. Recorded in `docs/DECISIONS.md`.
- [x] Review rate limiting for registration, login, verification, and password reset routes.
  - Decision: deferred to the production readiness pass, because no target feature in `docs/PURPOSE.md` names it. The plan: `symfony/rate-limiter`, `login_throttling` for the login form, and per-IP limiters in the registration, verification, and reset request controllers. Recorded in `docs/DECISIONS.md`.

## Later: development container services

- [x] Discuss and add useful services missing from the development container.
  - `compose.yaml` currently provides PostgreSQL. `compose.override.yaml` publishes Mailpit ports without fixed host values.
  - Outcome on 2026-11-19: Adminer joined `compose.override.yaml` on fixed port 8080, localhost only, with the `database` host preset. The CLI in this environment has no `open:local:db`, so the fixed port is the predictable URL.
  - Emails needed no new service: Mailpit already runs and `symfony open:local:webmail` opens the interface.
  - SCSS already rebuilds in realtime: `.symfony.local.yaml` starts `sass:build --watch` with the server. JavaScript changes need a browser reload, and updates without a reload require the FrankenPHP hot reload already linked in `templates/base.html.twig`. Recorded in `docs/DECISIONS.md` and `docs/SETUP.md`.
  - Verified: `symfony console lint:yaml` accepts both compose files, and the Sass watch flag is confirmed in the command help. The containers cannot run in this sandbox, so the Adminer and Mailpit flows still need one check on a machine with Docker.



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
