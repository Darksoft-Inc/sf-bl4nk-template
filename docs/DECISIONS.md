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

## Admin section

Decision from 2026-11-21: admins are users with `ROLE_ADMIN` stored in the roles field, the `/admin` paths are guarded twice, and both admin lists paginate with `symfony/ux-pagination` in offset mode.

Evidence: `User::getRoles()` guarantees `ROLE_USER` for everyone, so no role hierarchy is needed. `config/packages/security.yaml` guards `^/admin` and the controllers repeat the check with `#[IsGranted('ROLE_ADMIN')]`. `config/packages/ux_pagination.yaml` selects the Bootstrap theme shipped with the bundle, and the lists order on stable keys with a fixed page size of 10. The user edit form lives in a modal per row, built from the UX Toolkit bootstrap kit; the forms are named `user_edit_{id}` so ten rows on a page do not produce duplicate field ids. The password fields on the public forms got the toggle from the `password-visibility` recipe of the common kit.

Consequence: a new admin page follows the same rules: an `/admin` path, the `IsGranted` attribute, and the shared layout in `templates/layouts/admin.html.twig`. The announcement gained its author; the migration was generated with `symfony console make:migration` against the development database. The user edit replaces the roles wholesale but an admin cannot remove the own admin role: the controller rejects the change with a flash and reloads the user, and rejected or invalid submissions reload the user as well so the list never shows unflushed in-memory values.

Why the reload is needed: a mapped form writes the submitted values into the model during submit, before validation, so an invalid submission leaves them in the managed entity. Under the test client the kernel and its entity manager survive the redirect, and the list query then returns the managed instance instead of the database row. Production processes rebuild the entity manager per request, and the reload also removes the risk of a later flush in the same request writing rejected values.

## User uploads

Decision from 2026-11-21: uploads fall into three categories, the account avatar, shared images, and shared plain-text files, and only the avatar is built so far.

Evidence: the avatar lands on the profile page as the proof of concept. `AvatarFormType` uses the `ux-dropzone` control and validates with the `File` constraint: 2 MB at most, and PNG, JPEG, WebP, or GIF only, checked by content sniffing rather than the file name. SVG stays excluded because a script-bearing SVG served from the app origin would be stored cross-site scripting. The controller stores the file under `%kernel.project_dir%/public/uploads/avatars` through the `app.avatar_dir` parameter, under a random name with the extension the sniffed type suggests, and deletes the previous avatar after a successful upload. `.gitignore` excludes `public/uploads/`.

Consequence: the shared-image and shared-text categories wait for their features and architecture choices, such as where the file records live and whether storage moves behind an abstraction. Image processing stays undecided. The plain-text category validates the content as text, since archives, word processor documents, and PDFs are out of scope. The profile shows the avatar capped by the `app.avatar_display_max` parameter, 96 pixels per side by default: the browser scales the image down, the upload itself is not rejected for its pixel size.

## Upload management

Decision from 2026-11-21: each upload category is managed separately through one contract, and the admin maintenance section lists and removes files of every kind.

Evidence: `App\Uploads\UploadKindInterface` defines the contract: a kind owns its storage location and its own file-name rules, lists its files with an owner and a public URL when one exists, and removes a file together with the field that references it. Kinds register through the `app.upload_kind` tag; `AdminMaintenanceController` collects them into a map by kind name. The remove action posts through the `post-link` recipe with the `remove-upload` CSRF token id, and `AvatarUploadKind` validates its hex names before any file operation, so path traversal through the kind name or file name cannot reach the disk.

Consequence: a new category is one class implementing the interface plus its own validation and clearing rules; the maintenance pages pick it up without changes. Map images arrive as such a kind, and pre-existing images can serve as backgrounds; both landed on 2026-11-21, see the Annotated 2D map section.

## Annotated 2D map

Decision from 2026-11-21: the map display runs Leaflet through the ux-map bridge over a flat image plane, and a separate Stimulus controller decorates it through the bundle events.

Evidence: `UxMapBuilder` turns one `Map` entity into a `Symfony\UX\Map\Map` with `LeafletOptions(tileLayer: false)`, no attribution control, the per-map `minZoom` and `maxZoom` caps, markers with `InfoWindow` popups, and polylines whose `extra` data carries the route type color and the major flag. `assets/controllers/image_map_controller.js` does not extend the bridge controller: it listens to the `ux:map:*` events on the canvas element, sets `bridgeOptions.crs = L.CRS.Simple` at `pre-connect`, draws `L.imageOverlay` from the map `extra` and fits the view at `connect`, turns the marker `extra` into a `divIcon` with the pin and its symbols, and styles the lines from the polyline `extra`. These are the documented extension hooks: since ux-map 2.27 the CHANGELOG describes the `pre-connect` bridgeOptions override and the `extra` channel, and the Leaflet bridge README shows the same `before-create` bridgeOptions mutation for markers. The bundle controller stays the only map creator, and the eager app controller always connects before the lazily imported bridge controller, so the listeners are in place before the first event. The controller sits on the element wrapping the canvas instead of on the canvas itself, because the filter panel next to the canvas shares its actions.

Consequence: switching the map renderer through `UX_MAP_DSN` keeps the image-plane adaptation broken until the controller learns the new event payloads. The initial view fits the image bounds in JavaScript because the PHP side cannot know the viewport size.

The coordinate space: `MapPoint` and `MapRoute` store positions in image pixels, x growing right and y growing down from the top-left corner. The admin side is fully Cartesian: the point fields and the route textarea take pixel values bounded by the map space, and the field help names the bounds ("Pixels from the left edge of the map, 0 to %space%"), so no admin ever sees a latitude, a longitude, or a scaled value. The geo encoding exists only inside `UxMapBuilder`: the builder converts them to Leaflet latitudes and longitudes under `L.CRS.Simple`, where lat 0 sits at the bottom, so `lat = (height - y) * scale` and `lng = x * scale`. The scale fits the whole space into the widest range `Symfony\UX\Map\Point` accepts: it validates latitudes against [-90, 90] and longitudes against [-180, 180] even for image maps, which the builder works around with `min(180 / width, 90 / height)`. The forced geographic ranges on an image plane are not desirable, but the result is acceptable: the whole map stays visible at low zoom levels and every level doubles the magnification. Cope with the workaround for now, and revisit it when a future ux-map release lifts the range guard for non-geographic maps. The space comes from the image pixel size recorded on the map and survives an image being unset, so annotations keep their positions; a map that never carried an image uses a nominal 512x512 space.

The event slots: the bundle CHANGELOG distinguishes the two objects a `ux:map:pre-connect` listener can shape: `event.detail.options` holds the normalized PHP map options, `event.detail.bridgeOptions` holds the renderer-specific options that the Leaflet controller spreads last into `L.map()`. Community examples mutate `options.crs` directly, and it works because the controller spreads both objects, but `bridgeOptions` is the documented slot for renderer options and wins the spread anyway, so the crs switch goes there. The Leaflet object comes from the event payload, `event.detail.L`, which the bridge attaches to every event; the README example destructures it the same way. A listener that references a bare `L` without importing leaflet throws before the map initializes.

Negative zoom levels: Leaflet accepts them under `L.CRS.Simple`, and a `pre-connect` listener could inject a negative `minZoom` past the PHP-side non-negative validation. The display does not need them: the Point range workaround caps the space at 90x180 units, so zoom 0 already shows the whole map on every screen size, and `fitBounds` picks the initial level; levels below 0 would zoom out into empty space. The magnification ceiling stays the per-map `maxZoom` parameter.

The filters: the pins and symbols checkboxes toggle CSS classes on the canvas element, because the symbols are part of the pin `divIcon` and stay glued to the pin at every zoom level; hidden markers have no hit area, so their popups close with them. The lines radio group offers all lines, major routes only, or none: `image_map_controller.js` records every polyline with its major flag at `polyline:after-create` and switches the SVG element display. The symbols checkbox is rendered only when at least one point of the map carries a symbol, because a map may have none; the whole filter panel is rendered only when the map carries at least one point or route.

The symbols: a point carries a list of ux-icons names in a JSON column, rendered server-side into the pin icon through `IconRendererInterface`. The point form validates each name by rendering it at submission, so the public page never meets an unknown icon, and the dev auto-lock of ux-icons persists the first render into `assets/icons/`. A symbol icon that only exists on the iconify CDN therefore needs either one online save or one `php bin/console ux:icons:import` run before the page renders offline; the icons used by the shipped code are already imported.

The image pool: `MapImageUploadKind` manages `public/uploads/maps/` through the upload-kind contract. Files arrive through the admin dropzone under random hex names, or as pre-existing files an operator drops into the directory; both are listed the same way and both are removable from the maintenance uploads page. Unlike the avatar kind, a removal is refused while any map still references the file: `UploadInUseException` extends the invalid-argument path of the contract, and the maintenance controller reports it with a flash instead of a 404, because an image is public content and clearing it silently would publish a map without its background. The map form picks from the pool or "no image"; changing or unsetting the image keeps the stored file, the way an avatar replacement keeps the old file until the kind removes it. Uploads are validated at 2 MB with PNG, JPEG, WebP, or GIF content sniffing, matching the php.ini `upload_max_filesize` of the platform and the SVG exclusion recorded for the avatar, and larger images take the pre-existing path.

The popups: the bridge inserts popup content as HTML, so the builder escapes point titles, point descriptions, and route texts with `htmlspecialchars` before they reach the `InfoWindow`. An admin cannot store a script through the annotation fields.

The admin write path: `AdminMapController` manages maps, parameters, and the image pool; `AdminMapPointController`, `AdminMapRouteTypeController`, and `AdminMapRouteController` manage the three entry kinds from the map edit page. Route vertices come from a textarea holding one "x, y" pair per line: `RoutePoints::parse` is the single rule for both the form constraint and the controller write, so the stored line always matches the accepted text. The zoom pair is validated by a callback constraint on the `maxZoom` field that reads the model value of `minZoom`, which the form has already written during submit. Deleting a map cascades over its points, routes, and route types; deleting a route type cascades over its routes, and the confirm text names the route count.

The empty required fields: the form mapper writes the submitted data into the model during submit, before validation runs, so an empty submission of a mapped field with a non-nullable setter (the zoom caps, the position, the color, the route type select) reaches the setter as null and crashes the request with a TypeError. The browser cannot produce that request, because these inputs carry the native `required` and `type=number` or `type=color` validation; only a hand-crafted post reaches it, and it needs an admin session and a valid CSRF token, writes nothing, and renders the generic error page, so it is not a security issue. The map forms close it with the framework's own hook, `App\Form\KeepCurrentData::emptyData()`: the `empty_data` option returns the current view data, so an empty submission keeps the value unchanged instead of crashing, and normal submissions still map. The other forms of the app keep the pre-existing behavior for now; the same one-line option extends to them the same way when wanted. The optional route name is a browser-reachable case of its own: the browser sends an empty string when the field is cleared, the controller stores that as null, and the popup falls back to the type name, as the form help text promises.

## Announcement search

Decision from 2026-11-21: the announcements page carries a TomSelect autocomplete field that searches the title and the content and jumps to the selected announcement.

Evidence: `AnnouncementAutocompleteField` is an entity autocomplete field over `Announcement` with `searchable_fields` set to title and content, ten results at most, served by the bundle route `/autocomplete/{alias}`. The results are public data, so the endpoint needs no role. `AnnouncementSearchType` submits as GET without CSRF, and the index controller redirects to the detail page of the selected announcement. `assets/controllers.json` autoimports the Bootstrap 5 TomSelect stylesheet, matching the app layout.

Consequence: another searchable entity repeats the pattern: one autocompleter field class, one small GET form, one redirect on submit. The endpoint alias derives from the field class name, `announcement_autocomplete_field`.

The admin searches from 2026-11-21 follow the same pattern with two differences. The lists stay in place: selecting a user or an announcement filters the list to that record instead of navigating away. The endpoints live under the bundle's one flat namespace, `/autocomplete/{alias}`, not under `/admin` or `/announcements`: the alias carries the scope by name and the field carries the access rule. `AdminUserAutocompleteField` sets `security: 'ROLE_ADMIN'`, so anonymous callers are sent to the login page by the firewall and non-admins get 403. The admin announcement search reuses the public field, because announcement titles are public data.

## Login audit and user metadata

Decision from 2026-11-21: one audit row per authentication, recorded from `LoginSuccessEvent`, and user metadata updated in the same event.

Evidence: the `AuthenticatorManager` dispatches `LoginSuccessEvent` when an authenticator ran, which means the form login, or the remember-me authenticator when a visitor returns and their session is gone. Requests served from a live session restore the token in the `ContextListener` without dispatching any login event, so they write nothing: the table gains a row on a new login or on the restore of a persistent session, never per request. The security component points at this hook itself: the docblock of `RememberMeAuthenticator::isInteractive()` names "listeners reacting to a user coming back, such as one updating a 'last login' date" as the users of the interactive-login events, and `LoginSuccessEvent` is the event that covers both the form login and the remember-me restore. `App\Security\LoginAuditSubscriber` therefore reads `LoginSuccessEvent`, tells a resume from a login through `getAuthenticator() instanceof RememberMeAuthenticator`, writes the `login_audit` row, and sets `lastConnectedAt` and `lastIp` on the user.

The resume path needs persistent sessions, so the firewall enables `remember_me` with a 7 day lifetime, shorter than the 1 year default to bound the replay window of a stolen cookie. The login form gained the `_remember_me` checkbox, unchecked by default. The cookie secret defaults to `%kernel.secret%`, and `APP_SECRET` is empty in the committed dev `.env`; setting it is already the production task in `TODO.md`, and the remember-me cookie then signs with the production secret.

The `login_audit` table stores the user relation with `ON DELETE SET NULL` plus an `identifier` snapshot: an account deletion keeps its audit rows readable for their retention period. The admin list at `/admin/login-audit` searches by IP with a SQL LIKE pattern: an input without `%` or `_` matches as a substring, an input with wildcards is used as typed, so both `192.168` and `192.168.%` work.

Consequence: "last connected" is the time of the last sign-in or persistent-session restore, not the last page view. The profile page and the admin user list display it with the recorded IP.

## Maintenance cleanup task

Decision from 2026-11-21: the login audit cleanup is the proof of concept for recurring maintenance: a scheduled task that can also be triggered by hand, with every run recorded.

Evidence: `App\Task\LoginAuditCleanupTask` carries `#[AsPeriodicTask(frequency: '1 day')]`. The scheduler turns each due task into a `ServiceCallMessage` and exposes the schedule as the `scheduler_default` messenger transport; a worker running `symfony console messenger:consume scheduler_default` calls the task, which dispatches the `App\Message\LoginAuditCleanup` message. With `scheduler.use_messenger_routing: true` and no transport routed for that class, the message runs inline in the worker. The handler removes the rows older than `app.login_audit_retention_days` (90) and records a `maintenance_run` row with the task, the trigger, the result, and the time. `#[AsCronTask]` would need the `dragonmantank/cron-expression` package, which is not installed; `AsPeriodicTask` ships with the scheduler and covers the daily cadence, and cron syntax can be added with the package when a schedule needs one.

The manual trigger dispatches the same message from the maintenance page at `/admin/maintenance/cleanup` with the trigger set to "manual", behind the `cleanup-login-audit` CSRF token id. Because the bus is synchronous, the controller reads the removed count from the `HandledStamp` and reports it in the flash; the code already handles an asynchronous transport, which would answer "queued" instead.

Consequence: the messenger transport decision stays deferred as before. Routing `App\Message\LoginAuditCleanup` to a real transport later changes where the work runs and nothing else, and the scheduled trigger then runs outside the web request that pressed the button. A next recurring task repeats the pattern: an invokable task service with the attribute, a message, a handler, a recorded run.

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

Approach A is implemented by the documentation route since 2026-11-21: `templates/documentation/{page}.{locale}.html.twig`, with the default locale as fallback, and no code change for a new page or variant.

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
