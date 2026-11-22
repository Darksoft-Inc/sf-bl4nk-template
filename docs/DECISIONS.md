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

Decision from 2026-11-21: the interface carries a "roman noir 1930s" palette as a proof of concept, compiled from the Bootstrap source.

Evidence: `assets/styles/app.scss` overrides the eight theme colors before the vendor import (brass `#c9a13b` as `$primary`, aged paper as `$light`, smoke as `$secondary`, dried-blood red as `$danger`) and sets warm dark-mode surfaces after it, because the base template sets `data-bs-theme="dark"`. TomSelect hard-codes a light dropdown border, so the stylesheet points it at the theme border color.

Consequence: the prebuilt `bootstrap.min.css` is never loaded. Color customization needs the Sass path, and the SCSS source arrives through composer as `twbs/bootstrap`, so the unused `bootstrap/dist/css/bootstrap.min.css` entry was removed from `importmap.php` on 2026-11-21. The `bootstrap` JavaScript entry stays: `assets/app.js` imports it for the modal and navbar scripts. The palette is a proof of concept: replace the variables in `assets/styles/app.scss` when the project chooses its final look.

## Symfony version

Current choice: Symfony `8.2.*`, currently installed as 8.2.0-BETA2.

Evidence: `composer.json` requires `8.2.*` and allows dev packages. `symfony console about` reports the beta version.

Open question: when to move to a stable Symfony release.

## Authentication

Current choice: form login at `/login`, logout at `/logout`, and a verified email address required for login.

Evidence: `config/packages/security.yaml` configures `form_login` with CSRF protection and `App\Security\VerifiedUserChecker`. `SecurityController` declares both routes. Login, logout, registration, verification, and password change redirect to `app_index`.

Consequence: unverified users cannot sign in. The profile route requires `ROLE_USER` through `#[IsGranted('ROLE_USER')]`. `access_control` is still empty.

Open question: which other routes need access rules when the app grows.

Decision from 2026-11-21: logout submits as POST behind the `logout` CSRF token, through the `logout-link` recipe of the UX Toolkit.

Evidence: `config/packages/security.yaml` sets `enable_csrf: true` on the firewall logout, the `app_logout` route accepts POST only, and the navbar, the start page, and the login page render `<twig:LogoutLink>`, which wraps the existing `PostLink` component. `config/packages/csrf_protection.yaml` already listed `logout` among the stateless token ids, so the double-submit cookie flow covers the token without a session, and the `csrf-protection` Stimulus controller already runs on the hidden field the recipe renders. A GET request gets a 405, and a bad token gets a 403 and keeps the session.

Consequence: `SecurityControllerTest` submits the rendered logout form, and its new case covers the 405 on GET. `CsrfProtectionControllerTest` covers the bad token. The two maintenance tests pick the token of their own form instead of the first `_csrf_token` input on the page, because the navbar logout form carries one too.

Decision from 2026-11-21: the login page renders through `App\Form\LoginFormType` like every other form.

Evidence: the type has no block prefix, so its unmapped children submit as `_username`, `_password`, and `_remember_me`, the parameter names the `form_login` firewall reads, and the firewall still validates the `_csrf_token` field itself with the `authenticate` token id, so the form disables its own CSRF layer. The Bootstrap 5 form theme styles the fields, and the shared password form theme adds the visibility toggle.

Consequence: the login page matches the styling and the toggle of the other password pages, and the login tests submit the same field names as before, so no test data changed.

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

Decision from 2026-11-21: the root element declares its language and the dark theme, and the account logout renders through the `logout-link` recipe.

Evidence: `templates/base.html.twig` opens with `<html lang="{{ app.request.locale }}" data-bs-theme="dark">`. The language attribute lets screen readers pick the pronunciation and gives the dormant `symfony/ux-translator` runtime its page locale if it is ever wired; the theme attribute belongs to the palette record in the Generated CSS section. `ux:install logout-link --kit=common` wrote `templates/components/LogoutLink.html.twig`, which reuses `PostLink` and `logout_path()`.

Decision from 2026-11-21: the flash alerts dismiss through the `closeable` recipe, and the successes close on their own.

Evidence: `ux:install closeable --kit=common` wrote `assets/controllers/closeable_controller.js`. `templates/components/FlashMessages.html.twig` adds the close button as Bootstrap's `btn-close` inside an `alert-dismissible` alert and drives it with `closeable#close`; success and info alerts carry `data-closeable-auto-close-value="5000"` and a countdown bar styled in `assets/styles/app.scss`, while every other type stays until dismissed. Successes use `role="status"` and the rest keep `role="alert"`, so an auto-removing message is not announced as urgent.

Consequence: a dismissed or auto-closed flash is gone from the DOM, and a page reload brings it back only through a new redirect, which the session never sends: the flash bag is consumed on render.

## Admin section

Decision from 2026-11-21: admins are users with `ROLE_ADMIN` stored in the roles field, the `/admin` paths are guarded twice, and both admin lists paginate with `symfony/ux-pagination` in offset mode.

Evidence: `User::getRoles()` guarantees `ROLE_USER` for everyone, so no role hierarchy is needed. `config/packages/security.yaml` guards `^/admin` and the controllers repeat the check with `#[IsGranted('ROLE_ADMIN')]`. `config/packages/ux_pagination.yaml` selects the Bootstrap theme shipped with the bundle, and the lists order on stable keys with a fixed page size of 10. The user edit form lives in a modal per row, built from the UX Toolkit bootstrap kit; the forms are named `user_edit_{id}` so ten rows on a page do not produce duplicate field ids. The password fields on the public forms got the toggle from the `password-visibility` recipe of the common kit.

Consequence: a new admin page follows the same rules: an `/admin` path, the `IsGranted` attribute, and the shared layout in `templates/layouts/admin.html.twig`. The announcement gained its author; the migration was generated with `symfony console make:migration` against the development database. The user edit replaces the roles wholesale but an admin cannot remove the own admin role: the controller rejects the change with a flash and reloads the user, and rejected or invalid submissions reload the user as well so the list never shows unflushed in-memory values.

Why the reload is needed: a mapped form writes the submitted values into the model during submit, before validation, so an invalid submission leaves them in the managed entity. Under the test client the kernel and its entity manager survive the redirect, and the list query then returns the managed instance instead of the database row. Production processes rebuild the entity manager per request, and the reload also removes the risk of a later flush in the same request writing rejected values.

## User uploads

Decision from 2026-11-21: uploads fall into three categories, the account avatar, shared images, and shared plain-text files, and only the avatar is built so far.

Evidence: the avatar lands on the profile page as the proof of concept. `AvatarFormType` uses the `ux-dropzone` control and validates with the `File` constraint: 2 MB at most, and PNG, JPEG, WebP, or GIF only, checked by content sniffing rather than the file name. SVG stays excluded because a script-bearing SVG served from the app origin would be stored cross-site scripting. The profile page delegates the storage to `App\Uploads\AvatarStorage`, which stores the file under `%kernel.project_dir%/public/uploads/avatars` through the `app.avatar_dir` parameter, under a random name with the extension the sniffed type suggests, and deletes the previous avatar after a successful upload; the maintenance page clears a removed avatar through the same service, so every write to the avatar directory follows the one naming rule. `.gitignore` excludes `public/uploads/`.

Consequence: the shared-image and shared-text categories wait for their features and architecture choices, such as where the file records live and whether storage moves behind an abstraction. Image processing stays undecided. The plain-text category validates the content as text, since archives, word processor documents, and PDFs are out of scope. The profile shows the avatar capped by the `app.avatar_display_max` parameter, 96 pixels per side by default: the browser scales the image down, the upload itself is not rejected for its pixel size.

Decision from 2026-11-22: the shared categories are built: a members-only section at `/shared`, one typed record per file, served from `public/uploads/shared/`.

Evidence: `SharedController` guards the section with `#[IsGranted('ROLE_USER')]` at the class level, the first non-admin section restricted to members; the three answers recorded as open on 2026-11-21 are: one entity, `SharedUpload`, holds both categories through the `SharedUploadType` enum (image, text), so one list, one pagination, and one upload flow exist, and the type drives the validation and the display; the files are stored in the public tree, `public/uploads/shared/images/` and `public/uploads/shared/text/`, served directly the way avatars and map images are; and the write path follows the avatar pattern, `SharedUploadStorage::store()`, rather than a new abstraction. `SharedUploadFormType` accepts a 2 MB PNG, JPEG, WebP, GIF, or text/plain upload, checked by content sniffing; `store()` decides the category from the sniffed extension, not from the original name, so a PNG named "notes.txt" is an image, and plain text always lands under a `.txt` name, so a served file is always `text/plain`. The owner deletes a file from the detail page behind the `delete-shared` token, and the ownership check runs before the token check: another member never reaches it. The two kinds, `SharedImageUploadKind` and `SharedTextUploadKind`, list and remove the same files from the maintenance pages, taking the row with the file; the kind contract also covers a stored file whose row is gone.

Consequence: an upload whose owner account was deleted keeps its file and row with a null owner: the relation is `ON DELETE SET NULL`, and no identifier snapshot is kept, unlike the login audit, because a shared file is member content, not a security record, and the email of a deleted account has no reason to outlive the account. Such a file is removed from the maintenance pages only. Image processing stays out: the detail page lets the browser cap the display size, the choice the avatar made. Shared files are user content, not authored base data, so `SharedUpload` carries no dataset slug and the importable types stay six.

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

Update from 2026-11-22: the content renders through markdown, see the Markdown rendering section; the `nl2br` rendering is gone.

## Markdown rendering

Decision from 2026-11-22: member-authored text renders through `league/commonmark`, and the announcement detail is the first consumer.

Evidence: `App\Text\MarkdownRenderer` wraps the Github-flavored converter, whose defaults are unsafe for member content, verified on the installed 2.10: `html_input` defaults to `HtmlFilter::ALLOW`, which passes raw HTML through, and `allow_unsafe_links` defaults to `true`, which passes a `javascript:` target through. The renderer sets the two policies itself: `HtmlFilter::ESCAPE` and `allow_unsafe_links => false`. ESCAPE beats STRIP on measured behavior: STRIP removes an inline tag but leaks its inner text into visible output, and a line that opens an HTML block, such as a pasted `<script>` at the start of a line, swallows the whole line silently; ESCAPE shows every character as visible literal text and never loses content. With `allow_unsafe_links` false, a `javascript:` link renders as `<a>link</a>` without an href, and a `data:` image loses its src.

`App\Text\MarkdownExtension` exposes the renderer as the Twig filter `app_markdown`, declared `is_safe(['html'])`: the filter result is the only raw HTML the templates emit, and the renderer's output is what makes it safe. `twig/markdown-extra` stays uninstalled: its adapters run a converter the application does not configure, and the two policies must be the application's own. The package footprint is closed: composer added `league/commonmark` with `league/config` and its `nette`/`dflydev` dependencies and changed no installed version (no upgrade, no downgrade), and the package ships no JavaScript, so `importmap.php` gains no entry.

The admin side keeps a plain textarea with monospace styling and a help line naming the syntax: no editor, no WYSIWYG, the writer sees the markdown and the public page sees the rendering. The development demo announcement carries a markdown sample, applied through `app:dataset:load --update`.

Consequence: the rendering is a service any future page can call, and the filter works in any template: a member-restricted journal or any other use a derived application invents needs no new wiring. Nothing is stored rendered: the markdown stays the source of truth in the row, so re-rendering follows a converter upgrade with no migration. Raw HTML appears as literal text, so a pasted `<script>` shows as characters on the page and executes nowhere.

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

Removal from 2026-11-21: `symfony/uid` is uninstalled.

Evidence: no code used it since the restore, and the recorded planned uses do not need it. Deferred references can use slug columns, such as a scenario dataset naming records before they exist, and a dataset loader can resolve relations between its own rows at insert time, so the stored rows never depend on a client-generated UUID or ULID.

Consequence: `composer.json` and `composer.lock` no longer contain the package, and the entity identifiers row in `docs/PURPOSE.md` records the slug approach instead. `symfony/ux-router` and `symfony/ux-translator` remain the only restored packages without a consumer; the routes dump stays empty until a JavaScript feature names its first route, and the same review rule applies at the next pass.

The remaining UX packages match the target features: autocomplete for search, dropzone for uploads, icons and twig components for the interface, leaflet maps for the annotated map, pagination for admin lists, and translator for translations.

## Dataset import

Decision from 2026-11-21: datasets are YAML files keyed by a stored slug, and one load inserts new slugs, updates known ones, and never touches rows whose slug column is null.

Evidence: the format follows the repository: `symfony/yaml` is already a hard dependency, every other human-written data file (configuration, translations) is YAML, and `lint:yaml` covers the new directory. The user's earlier system keyed imported rows by a UID and kept updates safe that way; this project replaces the generated identifier with the dataset slug, the approach recorded when `symfony/uid` was removed on 2026-11-21. The `dataset_slug` column (unique, nullable) on the six importable entities, added by `Version20261121212030`, is that key: a load finds existing rows by it, so re-runs update instead of duplicating, and rows created by hand or through the application, whose column is null, are never matched, updated, or deleted. Relations are `@type.slug` references resolved at load time in an order computed from the references themselves, so records can be written in any order and reference each other across files, and no stored row depends on a client-generated identifier.

Consequence: the same import code serves development and production, as `docs/PURPOSE.md` requires. Files load from `datasets/shared/` in every environment plus `datasets/<environment>/`; the prod directory is git-ignored, because production records hold real values such as the admin password, hashed at load time. A record may name any subset of its type's fields: on an existing row only the named fields change, and a new slug must carry every required field. Removal is opt-in and bounded, answering the known drawback of a keyed import: a record written as `slug: {remove: true}`, which names only the slug, deletes the row carrying that slug and nothing else, cascading the way the schema defines, and the entry can be dropped once the row is gone. `--purge` deletes every row that carries a slug and is refused in the prod environment; it asks for confirmation interactively and requires `--force` otherwise. `--dry-run` runs the whole load and rolls it back. One load is one transaction, and invalid structure, unknown or circular references fail before any write. Tests keep creating their own rows, so test data stays in the fixture style; only the loader's own tests read dataset files, from `tests/fixtures/datasets/`.

Decision from 2026-11-21: a reference resolves against the current load first and against an earlier load second, so a dataset can be loaded in parts.

Evidence: a reference names `@type.slug`; the first pass treats it as internal when a record of that slug is in the load and as external otherwise, checking the external ones by a lookup on the dataset slug before any write. A partial file whose references point at rows imported earlier therefore loads on its own, which is the practical way to update a few records of a large dataset. A record referenced this way resolves to a Doctrine reference proxy built from the row id, so no relation needs the entity of an earlier row in memory.

Consequence: a purge load must be self-contained: the purge deletes every slugged row first, so a reference to an earlier row would dangle, and the load fails with a message that names the reference and the purge as the reason. The same load code stays in use for full and partial runs.

Decision from 2026-11-21: the loader runs two passes over the files and writes in batches, and the map entity constructors no longer write into the inverse collections.

Evidence: the memory measurement of the first version, which parsed every record into memory and flushed once, loaded 20,003 records into 545 MB under a debug kernel, dominated by the Doctrine data collector storing a backtrace per query, an artifact of `kernel.debug` that any import pays. Measured again with debug off, the state a production import runs in, on the test SQLite database: the kernel boots into 49 MB; 20,003 records in batches of 100 peak at 106 MB and 3.3 s; 100,003 records peak at 220 MB and 20 s; the same 100,003 records written in one flush, the pattern of the first version, peak at 487 MB. The first pass therefore keeps only a light index, record identity, file, and references, and releases each file after reading it; the second pass streams the files again and flushes and clears every 100 records through `--batch-size`, so the entity manager holds one batch at a time. The constructors of `MapPoint`, `MapRouteType`, and `MapRoute` previously added each new entity to the parent's inverse collection: the parent arrives in the second pass as a reference proxy, and initializing its collection reloads every earlier child, which turns batched writes back into one growing collection. The inverse collections are read models that hydrate from the database, and no reader of a freshly built parent existed, so the constructors stopped writing into them. The map tests that relied on the shared in-memory collection now clear the entity manager before their rendering requests, which makes them read the same hydrated state production reads.

Dimensioning from 2026-11-22: a load targets 2,000 to 40,000 rows, the scale of the previous hand-built importer of the earlier architecture, which reached 30,000, so the loader is judged against that ceiling rather than against the shipped development seed. Measured against the test database with debug off, one file holding one map and N points: 30,000 rows in batches of 100 peak at 140 MB in 5.1 s, 40,000 rows at 173 MB in 7.2 s, and the same 40,000 written in one flush (`--batch-size 40000`) peak at 275 MB in 7.6 s. These numbers are process resident memory, so they sit about 50 MB above the zend-level peaks recorded above. The batching and the resolver id harvesting stay sized right: one flush costs 60 percent more memory at the same duration. What the ceiling puts in question is the second parse with the pending-list ordering, which exists only to keep the record data out of memory: the write phase already holds one file's complete parsed records, and the single-file 40,000-row run above holds the whole corpus at once within 173 MB, so a one-parse load that keeps its records and writes them in the order the cycle check computes cannot exceed that measured peak. The simplification is the open item in `TODO.md`; the batching, the transaction, and the reference resolution stay as they are.

Measured against production shape on 2026-11-22: the prod environment against the compose PostgreSQL, the same one-file corpus, a 256M cap matching the smallest prod host. With the default batches of 100, 40,000 rows peak at 167 MB resident in 48 s and complete under `php -d memory_limit=256M`, and 30,000 rows peak at 140 MB in 36 s under the same cap. With `--batch-size 40000`, one flush, the same 40,000 rows peak at 278 MB resident and also complete under the cap, but with almost no headroom: the zend peak that the limit polices came close to 256 MB, and a record heavier than the bench shape, which carries a title and two numbers, exhausts it. The default batching stays the rule on a 256M host, and the ceiling is not the place to raise `--batch-size`. PostgreSQL is where the time goes, about 1.2 ms per row across the slug lookup and the insert, so a 40,000-row load takes under a minute and the 2,000-row floor takes seconds.

Decision from 2026-11-22: the load runs in one of three modes, and `--purge` is gone.

Evidence: the hybrid purge deleted every slugged row and then re-loaded, a shape no established tool keeps, because the tools that reload wholesale own their whole tables (dbt seeds) or run on disposable databases (Doctrine fixtures, whose default purge-everything is the mistake the ecosystem writes warnings about). The starter's tables mix the dataset world, rows with a slug, and the user world, rows without one, so the mode set follows the rule that the safe path is the default and the destructive paths are explicit: insert by default, refusing before any write with the colliding slugs named (about 13 ms per type on the ceiling database, measured); `--update` to apply the fields a record names on a known slug and to accept removal entries, the production mode; `--clear` to empty every table with one statement each and then insert, dev and test only, exclusive with `--update`, confirmation unless `--force`, refused in prod. A removal entry applies in `--update` only; the insert and the clear mode skip the entry and the load reports the skipped count, because they start from a database with nothing to remove and a file that still carries a historical removal must load on a fresh install. A clear load must be self-contained like the purge was. The insert mode skips the per-record slug lookup the update mode pays, so a first load is also faster than the previous upsert default.

Consequence: the workflows read: a fresh install runs the default, an edit to the YAML runs `--update`, a non-regression check or a massive change after a pull runs `--clear` on a disposable database. Production never sees a wholesale delete: rows without a slug are never touched, and a retired row is a removal entry under `--update`, which the next decision guards.

Decision from 2026-11-22: a removal entry is refused when rows outside the dataset reference the record.

Evidence: the ORM cascades (`cascade: ['remove']` on the inverse collections) delete every child of a removed row in the unit of work, imported or hand-made, in every environment, and the tests demonstrated both damage shapes: a hand-made pin on an imported map was swept silently by `maps: {old-town: {remove: true}}`, with the result table reporting only the map, and a removal of an imported author left the load to fail against the foreign key on PostgreSQL or to leave a dangling reference on the test SQLite, whose `PRAGMA foreign_keys` reads 0 through the application connection. The guard walks the entity metadata for the to-one associations that reference the removed class, keeps CASCADE edges and the ORM-cascaded collections as wipes of rows outside the dataset, keeps edges without an `onDelete` as load failures, and skips SET NULL edges, which the login audit uses on purpose. The counting is batched: one query per incoming association covers the whole removal list of a target class, about 3.6 ms each measured on the 40,000-row ceiling database, and only a violated association runs the per-record counts that name the records. Both demonstrated cases are covered by reproducing tests that fail before the guard and pass after it.

Consequence: rows that carry a dataset slug still cascade along with their parent, the recorded design; the removal entry in the file stays the single place a non-dev author reads and writes. A derived application's user tables need no registration: the guard reads every mapped class, so the inventory or location tables of a consumer are covered the moment the entity exists.

Update from 2026-11-22: the test connection registers DBAL's `EnableForeignKeys` middleware under `when@test`, so `PRAGMA foreign_keys` reads 1 through the application connection and the test database refuses what PostgreSQL refuses. The recorded benchmark numbers are repeatable through `bin/dataset-benchmark.php`, which loads a throwaway one-map corpus of 30,000 points through the console command, reports the wall time and the process peak, removes the bench rows, and verifies the restored counts; it measured 3.8 s at 78 MB on the test SQLite and 35 s at 78 MB on the fake prod PostgreSQL, the compose container under the prod kernel, on 2026-11-22. The script is a development tool: it resolves the container connection itself, through a set `DATABASE_URL` or through Docker introspection, and never points at a real production server.

Decision from 2026-11-22: the loader parses once and writes in the order the cycle check produces.

Evidence: the dimensioning target of 2,000 to 40,000 rows made the second parse and the pending-list ordering cost more complexity than the memory they saved. The write phase already held each file's complete parsed records, and the measured single-file peak proved that holding the whole corpus at once was the worst case, so the second pass bought no headroom at the ceiling. The parse now builds the records themselves, the reference validation returns the write order from the same Kahn pass that rejects the cycles, and `DatasetIndexEntry` with the pending-list machinery is gone. Measured after the change, the same one-file corpus: the test database peaks at 136 MB in 5.5 s against 173 MB in 7.2 s before, and the prod PostgreSQL load under the 256M cap peaks at 132 MB in 50 s against 167 MB in 48 s before. The batching, the one-transaction rule, the external reference resolution, the removals, and the purge keep their behavior; the loader and command integration suites pass unchanged, including the batch size of 1, the dry run, and every error file.

Consequence: memory holds the parsed records of the whole load for the duration of the write phase. The dimensioning ceiling bounds that at the measured 132 to 136 MB, and the floor does not feel it.

## Generated reference pages

Decision from 2026-11-22: the generated pages are declared on the services they document, collected into a registry at build time, and served from `/reference`.

Evidence: a service declares a page with the `#[Reference]` attribute: a slug, a title, a summary, an optional group, a list of `ReferenceDetail` entries (a label, a text) rendered as a `<dl>`, and a list of related slugs rendered as links. `ReferenceCompilerPass`, registered in `Kernel::build()`, walks the container definitions, reads the attribute of every loadable class, and hands the pages to `ReferenceRegistry` as a plain array shape, because a compiled container cannot carry objects as arguments; the registry rebuilds the page objects from it. The metadata lives on services only: a class nothing instantiates has no page.

The validation fails the build naming the page: a slug outside `Reference::SLUG_PATTERN`, a slug another page already carries, an empty title or summary, a detail that is not a `ReferenceDetail` or has an empty side, a related slug no page carries, and a `%parameter%` the container does not define. The slug rule takes lowercase letters and digits in segments separated by hyphens or dots. A class that fails to load is skipped: it carries no readable metadata, and this project ships without optional packages that some vendor classes reference, as the security bridge class that names a removed expression-language interface shows.

Two channels keep the cited values true. A text may concatenate a class constant inside the attribute argument itself, a native constant expression PHP resolves at instantiation, and a text may cite a container parameter wrapped in `%...%`, which the pass resolves and refuses to leak when the value still holds a placeholder, such as an env() indirection. Constants change with the code and parameters change with services.yaml, and the container rebuilds exactly when either changes.

The slug dot convention: "files.upload" and "files.storage" are valid names for pages of one theme. The registry keeps a slug opaque, no hierarchy is deduced and no link follows from a prefix, grouping on the index is the job of the `group` field.

The routes stay separate from `/docs`: `/docs/{page}` would capture "reference" as a page name, and the two families differ in source (edited templates against service metadata) and in controller. The show route requirement is `Reference::SLUG_REQUIREMENT`, the pattern minus its anchors, kept beside `SLUG_PATTERN` so the validation and the route cannot drift.

The text of a page is metadata, not a catalog key: every string goes through `trans`, and a literal renders as itself when no catalog entry exists, so a derived app writes plain text and translates later by adding catalog entries under the same literal. The chrome around the pages (the index title, the related heading, the back link) uses catalog keys as usual. The `Reference` attribute is repeatable on purpose: a duplicated declaration fails the build with a message naming the class, instead of an engine crash at `newInstance()` or a silent first-declaration win.

Consequence: adding a page is one attribute on a service, and the rebuild validates it. Nothing lands in the database, so the feature ships no migration and no dataset type: the code is the source. The first pages document the starter's own services: `login-audit-retention` on `LoginAuditRetention`, whose retention window cites `%app.login_audit_retention_days%`, and `shared-files` on `SharedUploadStorage`, whose size limit cites `MAX_SIZE`, the constant the upload form enforces too.

## Rate limiting

Decision from 2026-11-19: deferred to the production readiness pass.

Evidence: no target feature in `docs/PURPOSE.md` mentions rate limiting, and no route has a limiter today. The review covered registration, login, email verification, and password reset.

Plan: `symfony/rate-limiter` on the four public auth routes. `login_throttling` in `config/packages/security.yaml` covers the login form. The registration, verification, and reset request controllers consume a limiter keyed on the client IP. Tests control the limiter windows with the mock clock.

## Admin and fixture users

Decision from 2026-11-19: no admin or fixture user is added yet.

Evidence: `docs/PURPOSE.md` plans private sections and admin functions, but no admin page or route exists. The only role in use is the implicit `ROLE_USER` that `User::getRoles()` guarantees.

Consequence: an admin role, a real admin user, and fixtures land with the first admin feature. Tests create their own users, so a fixture user would only be dead data today.

Update from 2026-11-21: the admin section carries `ROLE_ADMIN`, and the dataset import ships the accounts as dataset records instead of hand-written fixtures: `datasets/dev/users.yaml` holds the admin and the member with documented development passwords, and the accounts of a deployed instance come from the git-ignored `datasets/prod/` directory through the same load command. Tests still create their own users, so the suite stays independent of the datasets.
