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

`tests/Controller/` holds the HTTP-facing tests: each one submits a request and asserts the routing, the response, and the database state. Functional and unit tests become useful when logic moves out of the controllers into services, and integration tests when components interact across layers. The empty directory `tests/Functional/` stays reserved for that stage. `tests/Integration/` opened with the cleanup task test, which crosses the scheduler task, the message bus, and the database in one call, and `tests/Unit/` opened with the reference compiler pass test, which drives the pass against a container builder in isolation.

`tests/Controller/AuthRateLimitTest.php` covers the auth throttling. The test kernel clears the `cache.rate_limiter` pool between tests through the framework's `kernel.test_state` tag, so every test starts from an empty limiter state; a test blocks an address by consuming the limit within one test, not by waiting for a window to pass. The login test loops over bounded attempts because its one minute window can reset while the loop runs.

`tests/Unit/TranslationCoverageTest.php` guards the catalogs: templates translate their literal keys through the messages domain, form labels and help through messages, constraint messages through validators, and the security exception through security, and the test fails naming the key and the file whenever a referenced key misses from its catalog. A missing entry renders the raw key on the page, which is how the map section headers once broke.

`tests/Controller/TranslatedPagesTest.php` checks the rendered pages with the profiler's translation collector. It walks the public, member, admin, and reference pages and fails with the page URL and the undefined keys. The collector sees strings the static scan cannot, such as the four `ux-dropzone` labels that the form theme translates and a file name the choice theme translated as a label. The reference pages are the one exception: their metadata is literal text that renders as itself, as `docs/DECISIONS.md` records, so the test collects those literals from the registry and allows only them on those pages.

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

The schema commands and the suite run with plain PHP on purpose. With Docker running, the Symfony CLI injects the container `DATABASE_URL`, which wins over the SQLite URL in `.env.test`. The `dbname_suffix` applies to the injected URL, because the injected URL names a server database: the run then targets the `app_test` database of the container, which no setup step creates, and every database test fails on connect with `database "app_test" does not exist`. Creating `app_test` would not fix the run: the test connection registers the `EnableForeignKeys` middleware of `config/services.yaml`, which sends `PRAGMA foreign_keys=ON` on connect, and PostgreSQL rejects that statement. The `dbname_suffix` also keeps the mistake away from the `app` database, so the development data is never at risk. Without the running services the CLI injects nothing and the run would pass, but keep plain PHP in every case so the command never depends on the container state.

## Recorded HTTP exchanges

The compromised password check runs in the test environment: `config/packages/validator.yaml` opts the test environment into `validation.not_compromised_password`, because the framework disables that validator by default when `framework.test` is on. The strict password rules recorded in `docs/DECISIONS.md` would otherwise not apply in tests.

Every password submitted in a test triggers one request to `api.pwnedpasswords.com`. The tests do not need a network connection: the five test classes that submit password forms carry `#[UseRecord('pwnedpasswords-ranges.har')]`, and the recorded exchanges replay from `tests/recordings/pwnedpasswords-ranges.har`.

When a test submits a new password, the replay reports a miss and names the fix. Run this once with network access to append the exchange, then commit the file:

```bash
SYMFONY_HTTP_RECORDER=missing php bin/phpunit
```

`SYMFONY_HTTP_RECORDER=passthrough php bin/phpunit` makes real requests and ignores the recordings. The recording directory comes from the `http-recorder-directory` parameter of the `SymfonyExtension` entry in `phpunit.dist.xml`.

## Dataset load benchmark

`bin/dataset-benchmark.php` measures what a load costs at the dimensioning scale and leaves no bench row behind. It generates a throwaway corpus of one map plus 30,000 points, loads it through the console command with debug off, reports the wall time and the process peak, then removes the bench rows and verifies the restored table counts. It is not part of the suite, because a run writes to the database it points at and takes seconds to a minute:

```bash
php bin/dataset-benchmark.php --env=test
php bin/dataset-benchmark.php --env=dev
php bin/dataset-benchmark.php --env=prod --count=40000
```

The script is a development tool: its prod mode is the fake prod, the local compose PostgreSQL under the prod kernel, never a real production server. For dev and prod it resolves the container connection itself: a `DATABASE_URL` already set in the environment wins (the Symfony CLI injects one through `symfony php`), and otherwise the script asks Docker for the port the `database` service publishes and rewrites the `.env` URL. The output names the connection it used, and it warns when it fell back to the `.env` default. The test environment needs no container: it reads the SQLite URL from `.env.test`. The run exits non-zero when the load fails or the counts do not come back, so it is safe in a scheduled check. The recorded baseline lives in `docs/DECISIONS.md`; run it before and after any change to the loader and keep the numbers.

## Quality checks

```bash
symfony console lint:container
symfony console lint:yaml config/ datasets/
symfony console lint:twig templates/
vendor/bin/phpstan analyse --no-progress
vendor/bin/php-cs-fixer fix --dry-run
vendor/bin/twig-cs-fixer lint templates/
symfony console doctrine:schema:validate
```

Lint commands and PHPStan pass with the full configuration. The `lint_php` alias uses all paths from `phpstan.dist.neon`. Do not add PHPStan suppressions or a baseline to hide future errors.

`php-cs-fixer` enforces the `@Symfony` rules from `.php-cs-fixer.dist.php`. The dry run fails when a file does not comply; `format_php` rewrites the files. The finder excludes `config/bundles.php` and `config/reference.php`, which are generated, and the `.php-cs-fixer.cache` file is ignored by git.

`twig-cs-fixer` lints the template style the same way: `vendor/bin/twig-cs-fixer lint templates/`, and `format_twig` rewrites the files. `run_all_checks` in `aliases.sh` chains the lint commands, PHPStan, both style dry runs, the schema validation, and the test suite; the aliases were written with a CI pipeline in mind.

## Current tests

- `tests/Controller/IndexControllerTest.php`: checks that the start page returns a successful response and shows the expected heading.
- `tests/Controller/AnnouncementControllerTest.php`: checks the announcement list order, the empty state, the detail page with the markdown content rendered (bold, list item) and the raw HTML escaped plus unsafe links without href, the 404 for an unknown id, the autocomplete endpoint result shape, and the search form redirect to the selected announcement.
- `tests/Controller/RegistrationControllerTest.php`: registers a user, checks the verification email including its subject, clicks the verification link, and checks the flash message and user state. Rejections cover the weak, short, and empty password, the duplicate and invalid email, and the unchecked terms.
- `tests/Controller/EmailVerificationControllerTest.php`: checks that a verification link without `id`, with an unknown user id, or with an invalid signature ends on the register page, and that the invalid signature shows the bundle error there. The repeated-click test verifies once and then opens the same link twice more, and both clicks report the verified state without re-running the verification. The expired-signature test rebuilds the link with a past `expires` and a fresh signature from the `symfonycasts.verify_email.uri_signer` service: an unverified visitor gets the expired-link error on the register page, and a verified visitor gets the verified state through the catch branch.
- `tests/Controller/ResetPasswordControllerTest.php`: requests a reset email, checks it including its subject, follows the link, changes the password, and checks the redirect and stored hash. Rejections cover the unknown email, the invalid token, the mismatched passwords, the weak password, the expired token, and the compromised password. The enumeration test compares the check-email page for a known and an unknown address. The reused token tests open the consumed link again, check the rejection and the unchanged password, and check that the rejected token is removed from the session: one test checks that the bare reset route then reports a missing token, and the other reads the persisted session through the test client's mock file storage and checks that a rejected link clears every reset entry, including the token object stored by the request step. The expired token mocks the clock past the 1 hour lifetime, and the compromised password replays the recorded HIBP exchange that reports the leak.
- `tests/Controller/SecurityControllerTest.php`: checks the login page, rejects unverified users, logs in a verified user, submits the rendered logout form, and rejects a GET logout with a 405.
- `tests/Controller/ProfileControllerTest.php`: checks that anonymous visitors are redirected to login, that the page shows account details, and that the password change form updates the hash. The avatar tests check the upload of a real PNG, the stored file on disk, the rendered image, the dropzone control, and the rejection of a plain-text file.
- `tests/Controller/SharedControllerTest.php`: checks the login redirect for the whole section, the empty state, the text upload flow with the stored file and the rendered content, the image upload with the rendered URL, the sniffed content deciding the category (PNG bytes under a .txt name upload as an image), the rejection of unsupported binary content, the newest-first order through the mocked clock, the pagination at 10 rows, the 404 for an unknown id, the owner deletion behind the page's own token, the 403 for another member, and the bad token that changes nothing.
- `tests/Controller/ReferenceControllerTest.php`: checks the grouped index, the shared files page rendering the `MAX_SIZE` constant value, the retention page rendering the `app.login_audit_retention_days` parameter value, and the 404s for an unknown slug and a slug outside the rule. A booting kernel already proves the real pages pass the build validation, and the pages read no database.
- `tests/Unit/ReferenceCompilerPassTest.php`: drives the pass against a container builder in isolation: the collection of declared pages with a dotted slug, the resolution of both value channels (a `%parameter%` citation and a concatenated class constant), and one error case per validation rule (duplicate slug, slug outside the rule, unknown parameter, parameter holding a placeholder, related slug no page carries, a second Reference attribute, a detail that is not a ReferenceDetail, an empty title).
- `tests/Unit/MarkdownRendererTest.php`: checks the rendering of headings, emphasis, code, lists, links, and tables, and locks the two policies: raw HTML escapes into visible literal text, and a `javascript:` or `data:` target disappears from the output.
- `tests/Controller/CsrfProtectionControllerTest.php`: submits a bad CSRF token to the registration, reset request, reset change, profile change, and login forms, and checks that no data changes on failure. A bad logout token returns a 403 and keeps the session.
- `tests/Controller/PasswordVisibilityTest.php`: checks that the login, registration, profile, and reset change forms render the reveal or hide button.
- `tests/Controller/FlashMessagesTest.php`: checks the flash alerts through the cleanup page: a success flash carries the closeable controller, the automatic close value, the dismiss button, and the countdown bar, and an error flash stays without them.
- `tests/Controller/Admin/AdminUserControllerTest.php`: checks the login redirect, the 403 for users without the admin role, the paginated list, the modal form updating the email, the verified state, and the roles, the self-demotion rejection, and the user search: the endpoint answers only to admins and the form filters the list.
- `tests/Controller/Admin/AdminAnnouncementControllerTest.php`: checks the login redirect, the 403, the creation flow with the author, the edit flow, the paginated list, and the announcement search filtering the list.
- `tests/Controller/Admin/AdminMaintenanceControllerTest.php`: checks the login redirect, the 403, the uploads list with the owner, the removal that deletes the file and clears the avatar field, and the shared text kind: the list shows the owner of a shared file, and a removal takes the row with the file.
- `tests/Controller/LoginAuditTest.php`: checks that a form login writes one audit row with the user metadata, that further requests from the same session write nothing, that the profile page shows the metadata, that the `_remember_me` checkbox sets the cookie only when checked, and that a returning visitor with the remember-me cookie alone gets a "resume" row.
- `tests/Controller/Admin/AdminLoginAuditControllerTest.php`: checks the login redirect, the 403, the list newest first, and the IP search matching as a substring or as a typed LIKE pattern.
- `tests/Controller/Admin/AdminMaintenanceCleanupControllerTest.php`: checks the login redirect, the 403, the expiring-row count on the page, the manual run that removes only the expired rows and records the run, and the bad CSRF token that changes nothing.
- `tests/Controller/MapControllerTest.php`: checks the map list order, the empty state, the 404 for an unknown id, the rendered display data of a map (marker positions after the space scaling, the symbol icons in the pin markup, the route colors and major flags, the image URL, the zoom caps in the Stimulus values), the filter panel rules (pins always, symbols only when a point carries some, the whole panel only when the map has annotations), the popup text escaping, and the display of a map without an image over its stored space.
- `tests/Controller/Admin/AdminMapControllerTest.php`: checks the login redirect, the 403, the map list, the creation with a pool image and its pixel size, the creation without an image, the zoom pair rejection, the edit that renames and unsets the image while keeping the space and the stored file, the edit that keeps the zoom caps when a crafted post submits them empty, the map deletion, the image pool page, the upload through the dropzone, the rejection of a plain file, and the maintenance removal rules: a removal refused with a flash while a map uses the image, and a removal that deletes an unused file.
- `tests/Controller/Admin/AdminMapEntryControllerTest.php`: checks the login redirect, the 403, the point creation with symbols, the rejections for an unknown icon name and a position outside the map space, the point edit and delete with the help naming the Cartesian space bounds, the point edit that keeps the position when a crafted post submits it empty, the route type create, edit, and delete, the route type edit that keeps the color when it is submitted empty, the route type deletion that takes its routes with it, the route creation requiring a route type first, the route creation with the major flag and the stored vertices, the rejections for a single vertex, a malformed line, and a vertex outside the space, the route edit with its pre-filled points textarea and its help naming the space bounds, the route edit that keeps the type when the select is submitted empty, the route edit that falls back to the type name when the name is cleared, the route deletion, and the map deletion that cascades over every entry.
- `tests/Integration/LoginAuditCleanupTaskTest.php`: calls the scheduled task service the way the scheduler worker would and checks that the dispatch removes the expired rows and records a run with the "schedule" trigger.
- `tests/Integration/DatasetLoaderTest.php`: runs the dataset loader against the test database with the files in `tests/fixtures/datasets/`: insertion with resolved references (the announcement section precedes the users it references, on purpose), the update-mode rerun that updates instead of duplicating, the insert mode that refuses slugs that already exist before any write, the removal entry that the other modes skip and report, the partial record that updates only the named fields, the partial load that resolves its references against rows of an earlier load, the clear mode that empties every table before loading, the refusal of a clear load that references earlier rows, the removal tag with its cascade, the removal guard refusing both demonstrated damage shapes (a hand-made pin that a cascade would sweep, and an announcement that the database would refuse the delete over), the batch size of 1 that resolves every reference across a batch boundary and leaves the unit of work empty, the dry run that writes nothing, the database rejection that rolls back the whole load, and one file per error under `errors/`: unknown reference, unknown section, duplicate slug, missing required field, unknown field, unrenderable symbol, single-vertex route, position outside the map space, a removal entry with extra fields, a reference to a removed record, a circular reference through two throwaway types, and a missing path. Every error test also checks that the database stayed untouched, because a load is one transaction.
- `tests/Integration/DatasetLoadCommandTest.php`: runs the console command: the explicit path load, the default-path note for the test environment, the `--update` rerun, the default mode refusing a second load with the colliding slugs, the `--update` and `--clear` exclusion, the clear confirmation answered yes and no, the `--force` requirement when non-interactive, the clear refusal in the prod environment through a command built with the prod environment value, the dry run, and the `--batch-size` option accepting 1 and rejecting 0.
- `tests/Form/PasswordConstraintsTest.php`: checks the password rules per environment.
- `tests/SetupSmokeTest.php`: fails with the setup command in the message when the Sass output or the test schema is missing.

All database tests use the `ResetDatabase` trait from `tests/ResetDatabase.php`. Its `resetDatabase()` method runs in `setUp()`. It deletes every `announcement`, `reset_password_request`, `login_audit`, and `shared_upload` row before every `user` row, every `maintenance_run` row, and the `map_point`, `map_route`, `map_route_type`, and `map` rows too, so dependent rows cannot block the user cleanup on databases that enforce the foreign key and each test starts with clean tables.

Two behaviors of the test stack show up in the admin tests. An invalid submitted form renders with HTTP 422: since Symfony 8.2, `render()` sets that status when a parameter holds a submitted invalid form. And the test client reboots the kernel for every request, so the entity manager of the test class holds an identity map from before the request: re-reading an entity after a form submit calls `$this->em->clear()` first, or `find()` returns the stale managed instance instead of the stored row. The same applies to inverse collections: a map built in the test carries in-memory collections, and a page request that renders `$map->getPoints()` reads the managed instance, so the map tests clear the entity manager before their rendering requests and assert the hydrated state, which is what production reads. Since the constructors stopped adding children into the inverse collections, recorded in `docs/DECISIONS.md`, clearing is what makes those rows appear at all.

A crafted post can also submit required fields empty. The map forms carry `App\Form\KeepCurrentData::emptyData()` on their mapped non-nullable fields, so such a submission keeps the current value and the tests above assert that outcome; the other forms of the app still crash on that input, which is why only the map tests cover it.

## Continuous integration

GitHub Actions runs the quality chain on every push and pull request through `.github/workflows/ci.yml`, on `ubuntu-latest` over PHP 8.4 and 8.5. Every command runs on plain `php bin/console`, because the runner has no Docker service and no Symfony CLI: the SQLite test database is self-contained, and the suite replays its HTTP exchanges from `tests/recordings/`, so nothing beyond the composer install touches the network. The chain mirrors `run_all_checks` in `aliases.sh`, and a change to the chain updates both. See `docs/DECISIONS.md` for the platform choice.

## Coverage gaps

The P2 test list from `TODO.md` is complete. The remaining test work is tied to the deferred items there: the rate limiting tests with the production readiness pass.

When fixing a bug, write a test that reproduces it first.
