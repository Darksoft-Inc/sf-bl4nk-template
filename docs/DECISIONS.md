# Current choices and open questions

This page records current project choices that are not obvious from the code. It also records questions that need an answer during the work in `TODO.md`.

## Test database

Current choice: development uses PostgreSQL and tests use SQLite.

Evidence: `.env` sets a PostgreSQL `DATABASE_URL`. `.env.test` sets a SQLite URL. `src/Migrations/Version20261118001505.php` contains PostgreSQL identity syntax.

Consequence: `doctrine:migrations:migrate --env=test` fails on SQLite. Tests need `doctrine:schema:create --env=test`.

Decision from 2026-11-19: keep SQLite for tests. The test schema comes from the entity metadata through `doctrine:schema:create`, which works on every platform. The committed migration stays PostgreSQL specific, because a migration holds raw SQL for one platform, and it targets the development and production databases only. A PostgreSQL test database would need the Docker service for every test run.

`config/packages/doctrine.yaml` keeps the ParaTest `dbname_suffix`. DoctrineBundle ignores that option for SQLite, so the test file is always `var/test.db`.

## Generated CSS

Current choice: Sass output is generated into `var/sass/` and is not committed.

Evidence: `var/` is ignored by git. `assets/app.js` imports `styles/app.scss`. The runtime requires a generated output file.

Consequence: a fresh checkout needs `symfony console sass:build` before pages and browser tests work.

## Symfony version

Current choice: Symfony `8.2.*`, currently installed as 8.2.0-BETA2.

Evidence: `composer.json` requires `8.2.*` and allows dev packages. `symfony console about` reports the beta version.

Open question: when to move to a stable Symfony release.

## Authentication

Current choice: form login at `/login`, logout at `/logout`, and a verified email address required for login.

Evidence: `config/packages/security.yaml` configures `form_login` with CSRF protection and `App\Security\VerifiedUserChecker`. `SecurityController` declares both routes. Login, logout, registration, verification, and password change redirect to `app_index`.

Consequence: unverified users cannot sign in. The profile route requires `ROLE_USER` through `#[IsGranted('ROLE_USER')]`. `access_control` is still empty.

Open question: which other routes need access rules when the app grows.

## Email verification signer

Decision from 2026-11-19: override the verify-email bundle's signer service to set a default expiration.

Evidence: Symfony 8.2 deprecates `UriSigner::sign()` without an expiration. The bundle builds its own signer through `UriSignerFactory` as `symfonycasts.verify_email.uri_signer`, so the `framework.uri_signer.expiration` option does not reach that instance. No released or unreleased bundle version passes an expiration, including the 2.x branch under development on 2026-11-19.

Consequence: `config/services.yaml` defines the service with the arguments the bundle uses, `'%kernel.secret%'` and `signature`, plus a default expiration of 1 day. The value stays above the bundle link lifetime of 1 hour, so the bundle's own expiry check reports an expired link and the signer expiration acts as a safety net. Both runners report no deprecations since the override. The override is removed when the bundle passes an expiration itself.

The deprecation was reported to `SymfonyCasts/verify-email-bundle` on 2026-11-21 and the issue is being processed upstream.

## Translations

Current choice: `en` is the default locale and the only enabled locale.

Evidence: `config/packages/framework.yaml` sets `enabled_locales: ['en']`. `translations/` holds `messages.en.yaml`, `validators.en.yaml`, and `security.en.yaml`. Controllers translate flash messages with `TranslatorInterface`, templates use the `trans` filter, and constraint messages use the `validators` domain.

Consequence: adding a locale requires copying the three catalogs and enabling the locale in `framework.enabled_locales`. Email subjects live in the email templates through `email.setSubject()`, so the subject follows the locale of the rendering request.

## Development container

Current choice: `compose.yaml` runs PostgreSQL. `compose.override.yaml` publishes dynamic ports for PostgreSQL and Mailpit, and runs Adminer on fixed port 8080 bound to localhost.

Evidence: the Symfony CLI of this environment has `open:local:webmail` but no `open:local:db` command, so the database interface needs a predictable URL. `.symfony.local.yaml` starts `symfony console sass:build --watch` together with the server.

Consequence: mail reaches Mailpit when commands run through the Symfony CLI, because the CLI injects `DATABASE_URL` and `MAILER_DSN` for the running containers. Adminer asks for the user and password from the compose environment. Styles rebuild while the server runs. JavaScript changes need a page reload. Updates without a reload require the FrankenPHP hot reload path already linked in `templates/base.html.twig`.

## Test runners

Decision from 2026-11-19: PHPUnit is the source of truth and Pest is a presentation layer.

Evidence: every test class extends `TestCase` or `WebTestCase` and uses no Pest-only syntax, so both `php bin/phpunit` and `vendor/bin/pest` run the same suite. `AGENTS.md` names `php bin/phpunit`. `AGENTS.md` is a recipe file tracked in `symfony.lock`, so the project does not edit it; `docs/AGENT_WORKFLOW.md` and `docs/TESTING.md` hold the project specific layer over it.

Consequence: tests stay in the PHPUnit class style, including future tests. Pest-only features such as `test()` closures and `arch()` would leave `php bin/phpunit` unable to run the whole suite, so they are not used. `run_tests` in `aliases.sh` runs `php bin/phpunit`.

Deprecation reporting: with PHPUnit 10 and newer, the bridge does not register its deprecation handler and `SYMFONY_DEPRECATIONS_HELPER` has no effect. PHPUnit reports deprecations itself, and `phpunit.dist.xml` fails the run on app code deprecations through `failOnSelfDeprecation` and `failOnDirectDeprecation` while reporting vendor triggered indirect deprecations without failing. The `UriSigner::sign()` deprecation that both runners showed was resolved on 2026-11-19 through the signer override; see the Email verification signer section.

## Compromised password check in tests

Decision from 2026-11-21: the check runs in the test environment, and its HIBP requests replay from a recording.

Evidence: `PasswordConstraints::forNewPassword()` keeps `NotCompromisedPassword` outside the dev environment, but the framework disables the `validator.not_compromised_password` service when `framework.test` is on, so the constraint had no effect in tests before 2026-11-21 and a leaked password passed the test suite. `config/packages/validator.yaml` opts the test environment back in, matching the strict rules this project records for test and prod.

Consequence: every password submitted in a test performs one request to `api.pwnedpasswords.com`. The five test classes that submit password forms are marked `#[UseRecord('pwnedpasswords-ranges.har')]` and replay the recorded exchanges from `tests/recordings/`. A new password in a test fails the replay until `SYMFONY_HTTP_RECORDER=missing php bin/phpunit` runs once with network access and appends the exchange. See `docs/TESTING.md`.

## Interface components

Decision from 2026-11-21: the base template renders anonymous Twig components, and the icons are locked into the repository.

Evidence: `templates/components/Navbar.html.twig` holds the navigation and `templates/components/FlashMessages.html.twig` renders every flash type as a Bootstrap alert with an icon. `base.html.twig` calls both, so the per-page flash loops in the register and reset request templates are gone. `ux:icons:lock` imported the 11 icons used by the templates into `assets/icons/`.

Consequence: pages render without a network connection, and a new icon in a template needs one `php bin/console ux:icons:lock` run before it renders offline. See `docs/ARCHITECTURE.md`.

## Announcement blog

Decision from 2026-11-21: the blog ships the read side only, without an author field.

Evidence: `docs/PURPOSE.md` requires a public list and a detail page. The write path belongs to the private sections and admin functions item, so no controller creates announcements and no user relation exists yet. The content displays through `nl2br` because `docs/PURPOSE.md` adds no markdown package for this feature.

Consequence: `Announcement` holds title, content, and `publishedAt`, which is set once in the constructor. The author field and the publish form land with the admin functions item. The migration was hand-written while the development database was unreachable and ran against the development PostgreSQL database on 2026-11-21; the test schema comes from the entity metadata.

## Content-heavy pages

Decision from 2026-11-19: how a page with long prose gets its text.

- A: One template per locale, for example `templates/about/index.fr.html.twig`. The variant holds the prose directly and does not depend on translated blocks or variables. Symfony selects a template by name only, so the controller or a parent template must choose the variant for the current locale. Use this for mostly static prose.
- B: The prose comes from a dataset and the page renders it. The surrounding blocks and labels come from the catalogs or from per-locale variants. This approach is not implemented and needs a dataset design first. Use this for managed content.
- C: All text comes from the catalogs. Use this only when the page is merely a form. The current pages, login, registration, password reset, and profile, are forms and use this approach.

The approaches are not exclusive. A feature chooses per page: a documentation feature can keep static prose in per-locale templates while managed content comes from a dataset, and forms keep using the catalogs.

Adding a locale means copying the catalogs and, when prose pages exist, adding their per-locale templates or dataset content too.

## Installed UX and infrastructure packages

Decision from 2026-11-19: the review against `docs/PURPOSE.md` kept every package with a planned feature and removed the rest.

Removed: `symfony/web-link` and `symfony/expression-language`. No installed package depends on them, and no application code references them. `symfony/web-link` sends no header by itself; a feature must call `preload()` or `link()`. Complex access rules belong in a security voter, and expressions belong in declarations in configuration; neither exists today. The `lint:expressions` part of the `lint_autowiring` alias left with the package.

Restored on 2026-11-19 after the planned uses were recorded in `docs/PURPOSE.md`: `symfony/serializer` for the JSON that carries dynamic data to the frontend, `symfony/uid` for entity values that identify related rows before the database assigns the auto-generated key, and `symfony/ux-router` so JavaScript reads routes from the router instead of data attributes rendered by controllers.

Kept: `symfony/http-client` runs the compromised password check, `symfony/process` runs the Sass build, and scheduler, messenger, and doctrine-messenger support the scheduled maintenance feature. Messenger defines only the `sync` transport. The `doctrine://` lines in `config/packages/messenger.yaml` and `MESSENGER_TRANSPORT_DSN` in `.env` stay as placeholders until background jobs need a transport.

The remaining UX packages match the target features: autocomplete for search, dropzone for uploads, icons and twig components for the interface, leaflet maps for the annotated map, pagination for admin lists, and translator for translations.

## Rate limiting

Decision from 2026-11-19: deferred to the production readiness pass.

Evidence: no target feature in `docs/PURPOSE.md` mentions rate limiting, and no route has a limiter today. The review covered registration, login, email verification, and password reset.

Plan: `symfony/rate-limiter` on the four public auth routes. `login_throttling` in `config/packages/security.yaml` covers the login form. The registration, verification, and reset request controllers consume a limiter keyed on the client IP. Tests control the limiter windows with the mock clock.

## Admin and fixture users

Decision from 2026-11-19: no admin or fixture user is added yet.

Evidence: `docs/PURPOSE.md` plans private sections and admin functions, but no admin page or route exists. The only role in use is the implicit `ROLE_USER` that `User::getRoles()` guarantees.

Consequence: an admin role, a real admin user, and fixtures land with the first admin feature. Tests create their own users, so a fixture user would only be dead data today.
