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

- [ ] Add CI checks for Sass build, test schema creation, lint commands, PHPStan, code style, and PHPUnit.
  - Deferred on 2026-11-19 until the hosting platform is chosen. The checks to run: `symfony console sass:build`, `php bin/console doctrine:schema:create --env=test`, the lint commands, PHPStan, and the code style dry run from `docs/AGENT_WORKFLOW.md`, and `php bin/phpunit`.

## Documentation follow-up

- [ ] Update the docs when authentication, setup commands, or configuration values change.
- [ ] Link this file from a future README if the project gets one.

## Baseline features

The remaining target features from `docs/PURPOSE.md`, in build order. Each item names the packages that wait for it.

- [x] Reusable interface components: Twig components and icons as the interface baseline for every page (`symfony/ux-twig-component`, `symfony/ux-icons`).
  - `templates/components/Navbar.html.twig` and `templates/components/FlashMessages.html.twig` are anonymous Twig components called from `base.html.twig`. The flash component renders every flash type as a Bootstrap alert with an icon, so the per-page flash loops in the register and reset request templates are gone. The 11 icons render inline and are locked into `assets/icons/` through `ux:icons:lock`.
- [x] Announcement blog: short announcements in a public list and on a detail page.
  - `Announcement` holds title, content, and `publishedAt`; the list orders by publication date and the detail page renders the content through `nl2br`. The write path and the author field land with the admin functions item. The migration was hand-written while the Docker services were down, then ran against the development PostgreSQL database once they started. The test schema comes from the entity metadata. `tests/ResetDatabase.php` deletes announcement rows first.
  - Verified on 2026-11-21: `php bin/phpunit` and `vendor/bin/pest -v` pass with 40 tests and 314 assertions, PHPStan and the lint commands pass, `doctrine:schema:validate --env=test` reports sync, and the suite passes with outbound HTTPS blackholed, which proves the icons render from the locked files. `doctrine:migrations:migrate` applied the migration to the dev database and `symfony console doctrine:schema:validate` reports the dev schema in sync.
- [x] Simple documentation pages: edited pages with stable links. Needs the content decision recorded in `docs/DECISIONS.md`.
  - Proof of concept: `DocumentationController` renders `/docs/{page}` from `templates/documentation/{page}.{locale}.html.twig`, trying the current locale then the default locale. A new page or locale variant is a dropped template file; the URL stays stable. `welcome.en.html.twig` is the first page.
- [x] Private sections and admin functions: roles, access rules, separated admin pages, and pagination for large record sets (`symfony/ux-pagination`). The announcement write path and an author field land here.
  - Admins hold `ROLE_ADMIN` in the roles field; `access_control` guards `^/admin` and the controllers repeat the check with `#[IsGranted]`. The admin pages extend `templates/layouts/admin.html.twig`, and both lists paginate through ux-pagination with its Bootstrap theme, 10 per page.
  - User management: paginated list and a per-row modal from the UX Toolkit with a form that edits the email, the verified state, and the roles through a multi select. `ROLE_ADMIN` is the only assignable role today; an admin cannot remove the own admin role, and rejected or invalid submissions reload the user before the list renders again. Basic by design; other improvements are expected later.
  - Announcement management: paginated list, a create form, and an edit form with a textarea for the content. The author of a new announcement is the signed-in admin; the migration adding `author_id` was generated with `symfony console make:migration` against the dev database and migrated there. The public detail page shows the author.
  - The password fields on the registration, reset change, and profile forms got the reveal or hide button from the `password-visibility` recipe, installed from the installed `symfony/ux-toolkit` package.
  - Verified on 2026-11-21: `php bin/phpunit` and `vendor/bin/pest -v` pass with 56 tests and 385 assertions, PHPStan, `php-cs-fixer`, and the lint commands pass, and the dev and test schemas validate in sync. Later the same day, both admin lists gained an autocomplete search: the user search endpoint is guarded by `ROLE_ADMIN` on the field itself and filters the list to the selected record, and the announcement search reuses the public autocompleter.
- [x] Search in large data sets: autocomplete on the first searchable data (`symfony/ux-autocomplete`, `tom-select`, `serializer`).
  - The announcements page carries a TomSelect search over title and content, served by the bundle route `/autocomplete/announcement_autocomplete_field` through `AnnouncementAutocompleteField`. The GET search form skips CSRF, and selecting an announcement redirects to its detail page. The `serializer` package stays for the map annotations, the next consumer of JSON data.
  - Verified on 2026-11-21: `php bin/phpunit` and `vendor/bin/pest -v` pass with 59 tests and 403 assertions, PHPStan and the lint commands pass, and the live dev server rendered the search form, the generated controllers module, the Bootstrap 5 TomSelect stylesheet, and the endpoint result.
- [ ] User-provided data: upload validation, storage, and display (`symfony/ux-dropzone`). Storage and image processing are open decisions.
  - Three categories: the account avatar, shared images, and shared plain-text files. Archives, word processor documents, and PDFs stay out of scope. Recorded in `docs/DECISIONS.md`.
  - [x] Avatar upload as the proof of concept: the profile page offers a dropzone that stores a content-validated raster image under `public/uploads/avatars/` with a random name, shows it on the page, and deletes the previous file on replacement. SVG stays excluded, because a script-bearing SVG served from the app origin would be stored cross-site scripting.
  - [ ] Shared images and shared plain-text files wait for their features and architecture choices.
- [ ] Annotated 2D map: a map page with annotations as JSON (`symfony/ux-map`, `symfony/ux-leaflet-map`, `serializer`, `ux-router`).
- [ ] Scheduled maintenance: recurring tasks with recorded results and a manual trigger (`symfony/scheduler`, `messenger`). Forces the messenger transport decision recorded in `docs/DECISIONS.md`.
- [ ] Generated documentation pages: build pages from metadata on business services. The format must stay domain independent.
- [ ] Flexible dataset import: load structured datasets in development and production.

## Lower-priority improvements

- [ ] Announcement content: a richer rendering than `nl2br` plain text. The form can gain a better editor together with it.

## Closed on 2026-11-21

- [x] Email verification: missing `id`, unknown user, invalid signature, expired signature, and repeated click.
  - Missing `id`, unknown user, invalid signature, and the repeated click were closed on 2026-11-20; `docs/TESTING.md` describes them.
  - [x] Expired signature: the bundle compares `expires` against `time()`, so clock mocking does not reach it. The test re-signs an expired URL through the `symfonycasts.verify_email.uri_signer` service: `testVerifyWithExpiredSignatureReportsExpiredOrVerifiedState()` rebuilds the link with a past `expires` and a fresh signature, then checks the expired-link error on the register page for an unverified visitor and the verified state through the catch branch for a verified visitor.
- [x] Password reset: unknown email, invalid token, expired token, reused token, mismatched passwords, weak password, and compromised password.
  - Unknown email, invalid token, reused token, mismatched passwords, and weak password were closed on 2026-11-20; `docs/TESTING.md` describes them.
  - [x] Expired token: the bundle builds expiry dates from `Clock::get()`, so `ClockSensitiveTrait::mockTime()` moves the token past its lifetime. `testExpiredResetTokenIsRejected()` mocks the clock 2 hours ahead before the click, then asserts the expired-link flash and the unchanged password hash. The bundle garbage collector only removes requests expired more than a week ago, so the token survives to report its own expiry.
  - [x] Compromised password: `NotCompromisedPassword` did not run in the test environment, because the framework disables `validation.not_compromised_password` when `framework.test` is on. That contradicted the strict rules recorded for test and prod, so `config/packages/validator.yaml` opts the test environment back in. `testResetRejectsCompromisedPassword()` submits a known leaked password (`Password123456!`, present in breach corpora thousands of times) through the reset form and asserts the violation and the unchanged hash. The exchange with `api.pwnedpasswords.com` is recorded in `tests/recordings/pwnedpasswords-ranges.har`; the five test classes that submit password forms replay it through `#[UseRecord]`, so the suite needs no network. See `docs/TESTING.md` for the re-recording workflow.
  - Verified on 2026-11-21: `php bin/phpunit` and `vendor/bin/pest -v` pass with 36 tests and 298 assertions, and PHPStan, `lint:container`, `lint:yaml`, `lint:twig`, and `lint:translations` pass.
