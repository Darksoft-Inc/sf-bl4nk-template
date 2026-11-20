# TODO

Open work on the application. Pick one item at a time and mark it done only after the change is verified.

Completed items are removed from this file. Their record lives in the git history and in the docs: decisions and evidence in `docs/DECISIONS.md`, the test suite in `docs/TESTING.md`, setup in `docs/SETUP.md`, configuration values in `docs/CONFIGURATION.md`. The items closed on 2026-11-21 stay at the bottom for a transition period and can be removed when the next pass starts.

Progress on 2026-11-21: the last three P2 tests are in, and the suite reports 36 tests and 298 assertions in both runners. The upstream report for the UriSigner deprecation was submitted to SymfonyCasts/verify-email-bundle and is being processed; the local draft file is removed. The compromised password case found a gap: the framework disables `validation.not_compromised_password` when `framework.test` is on, so the strict rules recorded for the test environment did not apply, and a leaked password passed the suite. `config/packages/validator.yaml` opts the test environment back in, the five test classes that submit password forms replay the HIBP exchanges from `tests/recordings/pwnedpasswords-ranges.har` through `#[UseRecord]`, and the recording workflow is documented in `docs/TESTING.md` and `docs/DECISIONS.md`. The remaining open items stay deferred on their recorded blockers: the production environment, the Symfony 8.2 release, and the hosting platform.

## P1: Configure production values

- [ ] Configure production values.
  - Set `APP_SECRET` in `.env.local` or the vault. `.env:19` is empty.
  - Configure a real `MAILER_DSN`. `.env:30` uses `null://null`.
  - Replace the default database password in `.env:47`.
  - Configure trusted hosts and proxies if the app runs behind a reverse proxy.
  - [ ] Rate limiting for the public auth routes. Planned in `docs/DECISIONS.md`.
  - [x] Remove the notifier package and its placeholder `admin@example.com` recipient.

## P1: Move off Symfony 8.2 beta before production use

- [ ] Move off Symfony 8.2 beta before production use.
  - Symfony 8.2 RC1 is planned for the end of November 2026. A beta is acceptable for development, so this item does not block feature work.
  - `composer.json:4-5` allows dev packages.
  - The runtime reports Symfony 8.2.0-BETA2.

## P2: Test infrastructure and tooling

- [ ] Add CI checks for Sass build, test schema creation, lint commands, PHPStan, and PHPUnit.
  - Deferred on 2026-11-19 until the hosting platform is chosen. The checks to run: `symfony console sass:build`, `php bin/console doctrine:schema:create --env=test`, the lint commands and PHPStan from `docs/AGENT_WORKFLOW.md`, and `php bin/phpunit`.

## Documentation follow-up

- [ ] Update the docs when authentication, setup commands, or configuration values change.
- [ ] Link this file from a future README if the project gets one.

## Closed on 2026-11-21

- [x] Email verification: missing `id`, unknown user, invalid signature, expired signature, and repeated click.
  - Missing `id`, unknown user, invalid signature, and the repeated click were closed on 2026-11-20; `docs/TESTING.md` describes them.
  - [x] Expired signature: the bundle compares `expires` against `time()`, so clock mocking does not reach it. The test re-signs an expired URL through the `symfonycasts.verify_email.uri_signer` service: `testVerifyWithExpiredSignatureReportsExpiredOrVerifiedState()` rebuilds the link with a past `expires` and a fresh signature, then checks the expired-link error on the register page for an unverified visitor and the verified state through the catch branch for a verified visitor.
- [x] Password reset: unknown email, invalid token, expired token, reused token, mismatched passwords, weak password, and compromised password.
  - Unknown email, invalid token, reused token, mismatched passwords, and weak password were closed on 2026-11-20; `docs/TESTING.md` describes them.
  - [x] Expired token: the bundle builds expiry dates from `Clock::get()`, so `ClockSensitiveTrait::mockTime()` moves the token past its lifetime. `testExpiredResetTokenIsRejected()` mocks the clock 2 hours ahead before the click, then asserts the expired-link flash and the unchanged password hash. The bundle garbage collector only removes requests expired more than a week ago, so the token survives to report its own expiry.
  - [x] Compromised password: `NotCompromisedPassword` did not run in the test environment, because the framework disables `validation.not_compromised_password` when `framework.test` is on. That contradicted the strict rules recorded for test and prod, so `config/packages/validator.yaml` opts the test environment back in. `testResetRejectsCompromisedPassword()` submits a known leaked password (`Password123456!`, present in breach corpora thousands of times) through the reset form and asserts the violation and the unchanged hash. The exchange with `api.pwnedpasswords.com` is recorded in `tests/recordings/pwnedpasswords-ranges.har`; the five test classes that submit password forms replay it through `#[UseRecord]`, so the suite needs no network. See `docs/TESTING.md` for the re-recording workflow.
  - Verified on 2026-11-21: `php bin/phpunit` and `vendor/bin/pest -v` pass with 36 tests and 298 assertions, and PHPStan, `lint:container`, `lint:yaml`, `lint:twig`, and `lint:translations` pass.
