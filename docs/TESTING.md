# Testing

## Authoritative commands

PHPUnit is the source of truth:

```bash
php bin/phpunit
```

Pest is a presentation layer over the same suite:

```bash
vendor/bin/pest -v
```

Tests stay in the PHPUnit class style, so both commands run the whole suite. Recorded in `docs/DECISIONS.md`. Run the two serially: both use `var/test.db`, and parallel runs corrupt each other's data.

## Deprecations

Both commands report no deprecations and exit 0.

The `UriSigner::sign()` deprecation from the verify-email bundle was resolved on 2026-11-19. The bundle builds its own signer and signs without an expiration, so `config/services.yaml` overrides the bundle's `symfonycasts.verify_email.uri_signer` service with a default expiration. See `docs/CONFIGURATION.md` and `docs/DECISIONS.md` for the details and the removal condition.

PHPUnit 13 does not use the bridge deprecation handler; PHPUnit itself reports deprecations, and `trigger_deprecation` is registered in the `<deprecationTrigger>` list of `phpunit.dist.xml`. The file fails the run on deprecations triggered by app code through `failOnSelfDeprecation` and `failOnDirectDeprecation`, and reports vendor triggered indirect deprecations without failing. With only indirect deprecations, the summary says `OK, but there were issues!` and the exit code is 0.

## Before running browser tests

Run these commands once after a fresh checkout:

```bash
symfony console sass:build
php bin/console doctrine:schema:create --env=test
```

If the Sass output is missing, `IndexControllerTest` fails with HTTP 500 and this message: "run php bin/console sass:build".

If the SQLite test schema is missing, `RegistrationControllerTest` fails with `TableNotFoundException: no such table: user`.

The test database uses `sqlite:///%kernel.project_dir%/var/test.db` from `.env.test`. The `dbname_suffix` in `config/packages/doctrine.yaml` applies to server databases only. DoctrineBundle ignores it for SQLite, so the test file is always `var/test.db`.

## Quality checks

```bash
symfony console lint:container
symfony console lint:yaml config/
symfony console lint:twig templates/
vendor/bin/phpstan analyse --no-progress
symfony console doctrine:schema:validate
```

Lint commands and PHPStan pass with the full configuration. The `lint_php` alias uses all paths from `phpstan.dist.neon`. Do not add PHPStan suppressions or a baseline to hide future errors.

## Current tests

- `tests/Controller/IndexControllerTest.php`: checks that the start page returns a successful response and shows the expected heading.
- `tests/Controller/RegistrationControllerTest.php`: registers a user, checks the verification email including its subject, clicks the verification link, and checks the redirect and user state.
- `tests/Controller/ResetPasswordControllerTest.php`: requests a reset email, checks it including its subject, follows the link, changes the password, and checks the redirect and stored hash.
- `tests/Controller/SecurityControllerTest.php`: checks the login page, rejects unverified users, logs in a verified user, and logs out.
- `tests/Controller/ProfileControllerTest.php`: checks that anonymous visitors are redirected to login, that the page shows account details, and that the password change form updates the hash.

All database tests use the `ResetDatabase` trait from `tests/ResetDatabase.php`. Its `resetDatabase()` method runs in `setUp()`. It deletes every `reset_password_request` row before every `user` row, so abandoned reset requests cannot block the user cleanup on databases that enforce the foreign key.

## Coverage gaps

Add tests for validation failures, CSRF failures, email verification errors, reset token errors, account enumeration behavior, protected pages when they exist, and flash messages. See `TODO.md` for the full list.

When fixing a bug, write a test that reproduces it first.
