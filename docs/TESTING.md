# Testing

## Authoritative commands

Use PHPUnit as the source of truth:

```bash
php bin/phpunit
```

Pest is installed and can show more readable output:

```bash
vendor/bin/pest -v
```

Pest reports a signed URL deprecation that PHPUnit hides because `phpunit.dist.xml` ignores indirect deprecations. Treat the deprecation as a known issue until it is fixed.

## Before running browser tests

Run these commands once after a fresh checkout:

```bash
symfony console sass:build
php bin/console doctrine:schema:create --env=test
```

If the Sass output is missing, `IndexControllerTest` fails with HTTP 500 and this message: "run php bin/console sass:build".

If the SQLite test schema is missing, `RegistrationControllerTest` fails with `TableNotFoundException: no such table: user`.

The test database uses `sqlite:///%kernel.project_dir%/var/test.db` from `.env.test`. `config/packages/doctrine.yaml` adds a database name suffix for ParaTest.

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

- `tests/Controller/IndexControllerTest.php`: checks that `/index` returns a successful response.
- `tests/Controller/RegistrationControllerTest.php`: registers a user, checks the verification email, clicks the verification link, and checks the user state.
- `tests/Controller/ResetPasswordControllerTest.php`: requests a reset email, follows the link, changes the password, and checks the stored hash.

The tests delete all users in `setUp()`. This is local cleanup, not a fixture system. Dependent `reset_password_request` rows are not cleaned there.

## Coverage gaps

Add tests for validation failures, CSRF failures, email verification errors, reset token errors, account enumeration behavior, protected pages after login is added, and flash messages. See `TODO.md` for the full list.

When fixing a bug, write a test that reproduces it first.
