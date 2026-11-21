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

## Test organization

`tests/Controller/` holds the HTTP-facing tests: each one submits a request and asserts the routing, the response, and the database state. They stay the only kind of test while the logic lives in the controllers. Functional and unit tests become useful when logic moves out of the controllers into services, and integration tests when components interact across layers. The empty directories `tests/Functional/`, `tests/Integration/`, and `tests/Unit/` are reserved for that stage.

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

If the Sass output is missing, `tests/SetupSmokeTest.php` fails first and names `symfony console sass:build` in its message. Without the smoke test, `IndexControllerTest` fails with HTTP 500 and this message: "run php bin/console sass:build".

If the SQLite test schema is missing, `tests/SetupSmokeTest.php` fails first and names `php bin/console doctrine:schema:create --env=test` in its message. Without the smoke test, `RegistrationControllerTest` fails with `TableNotFoundException: no such table: user`.

The test database uses `sqlite:///%kernel.project_dir%/var/test.db` from `.env.test`. The `dbname_suffix` in `config/packages/doctrine.yaml` applies to server databases only. DoctrineBundle ignores it for SQLite, so the test file is always `var/test.db`.

The schema commands run with plain PHP on purpose. With Docker running, `symfony console` injects the dev `DATABASE_URL`, which wins over the SQLite URL in `.env.test`, and the test environment would connect to the development database instead.

## Recorded HTTP exchanges

The compromised password check runs in the test environment: `config/packages/validator.yaml` opts the test environment into `validation.not_compromised_password`, because the framework disables that validator by default when `framework.test` is on. The strict password rules recorded in `docs/DECISIONS.md` would otherwise not apply in tests.

Every password submitted in a test triggers one request to `api.pwnedpasswords.com`. The tests do not need a network connection: the five test classes that submit password forms carry `#[UseRecord('pwnedpasswords-ranges.har')]`, and the recorded exchanges replay from `tests/recordings/pwnedpasswords-ranges.har`.

When a test submits a new password, the replay reports a miss and names the fix. Run this once with network access to append the exchange, then commit the file:

```bash
SYMFONY_HTTP_RECORDER=missing php bin/phpunit
```

`SYMFONY_HTTP_RECORDER=passthrough php bin/phpunit` makes real requests and ignores the recordings. The recording directory comes from the `http-recorder-directory` parameter of the `SymfonyExtension` entry in `phpunit.dist.xml`.

## Quality checks

```bash
symfony console lint:container
symfony console lint:yaml config/
symfony console lint:twig templates/
vendor/bin/phpstan analyse --no-progress
vendor/bin/php-cs-fixer fix --dry-run
symfony console doctrine:schema:validate
```

Lint commands and PHPStan pass with the full configuration. The `lint_php` alias uses all paths from `phpstan.dist.neon`. Do not add PHPStan suppressions or a baseline to hide future errors.

`php-cs-fixer` enforces the `@Symfony` rules from `.php-cs-fixer.dist.php`. The dry run fails when a file does not comply; `format_php` rewrites the files. The finder excludes `config/bundles.php` and `config/reference.php`, which are generated, and the `.php-cs-fixer.cache` file is ignored by git.

## Current tests

- `tests/Controller/IndexControllerTest.php`: checks that the start page returns a successful response and shows the expected heading.
- `tests/Controller/AnnouncementControllerTest.php`: checks the announcement list order, the empty state, the detail page, the 404 for an unknown id, the autocomplete endpoint result shape, and the search form redirect to the selected announcement.
- `tests/Controller/RegistrationControllerTest.php`: registers a user, checks the verification email including its subject, clicks the verification link, and checks the flash message and user state. Rejections cover the weak, short, and empty password, the duplicate and invalid email, and the unchecked terms.
- `tests/Controller/EmailVerificationControllerTest.php`: checks that a verification link without `id`, with an unknown user id, or with an invalid signature ends on the register page, and that the invalid signature shows the bundle error there. The repeated-click test verifies once and then opens the same link twice more, and both clicks report the verified state without re-running the verification. The expired-signature test rebuilds the link with a past `expires` and a fresh signature from the `symfonycasts.verify_email.uri_signer` service: an unverified visitor gets the expired-link error on the register page, and a verified visitor gets the verified state through the catch branch.
- `tests/Controller/ResetPasswordControllerTest.php`: requests a reset email, checks it including its subject, follows the link, changes the password, and checks the redirect and stored hash. Rejections cover the unknown email, the invalid token, the mismatched passwords, the weak password, the expired token, and the compromised password. The enumeration test compares the check-email page for a known and an unknown address. The reused token tests open the consumed link again, check the rejection and the unchanged password, and check that the rejected token is removed from the session. The expired token mocks the clock past the 1 hour lifetime, and the compromised password replays the recorded HIBP exchange that reports the leak.
- `tests/Controller/SecurityControllerTest.php`: checks the login page, rejects unverified users, logs in a verified user, and logs out.
- `tests/Controller/ProfileControllerTest.php`: checks that anonymous visitors are redirected to login, that the page shows account details, and that the password change form updates the hash. The avatar tests check the upload of a real PNG, the stored file on disk, the rendered image, the dropzone control, and the rejection of a plain-text file.
- `tests/Controller/CsrfProtectionControllerTest.php`: submits a bad CSRF token to the registration, reset request, reset change, profile change, and login forms, and checks that no data changes on failure.
- `tests/Controller/PasswordVisibilityTest.php`: checks that the registration, profile, and reset change forms render the reveal or hide button.
- `tests/Controller/Admin/AdminUserControllerTest.php`: checks the login redirect, the 403 for users without the admin role, the paginated list, the modal form updating the email, the verified state, and the roles, the self-demotion rejection, and the user search: the endpoint answers only to admins and the form filters the list.
- `tests/Controller/Admin/AdminAnnouncementControllerTest.php`: checks the login redirect, the 403, the creation flow with the author, the edit flow, the paginated list, and the announcement search filtering the list.
- `tests/Form/PasswordConstraintsTest.php`: checks the password rules per environment.
- `tests/SetupSmokeTest.php`: fails with the setup command in the message when the Sass output or the test schema is missing.

All database tests use the `ResetDatabase` trait from `tests/ResetDatabase.php`. Its `resetDatabase()` method runs in `setUp()`. It deletes every `announcement` and `reset_password_request` row before every `user` row, so dependent rows cannot block the user cleanup on databases that enforce the foreign key.

## Coverage gaps

The P2 test list from `TODO.md` is complete. The remaining test work is tied to the deferred items there: the rate limiting tests with the production readiness pass, and the CI checks once the hosting platform is chosen.

When fixing a bug, write a test that reproduces it first.
