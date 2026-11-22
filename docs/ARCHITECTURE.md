# Architecture

This page describes the current application, not a target design. `TODO.md` lists planned changes.

## Application routes

| Name | Path | Purpose |
|---|---|---|
| `app_index` | `/index` | Neutral start page. |
| `app_register` | `/register` | Registration form and email verification entry point. |
| `app_verify_email` | `/verify/email` | Validates the signed verification link. |
| `app_forgot_password_request` | `/reset-password` | Password reset request form. |
| `app_check_email` | `/reset-password/check-email` | Confirmation page after a reset request. |
| `app_reset_password` | `/reset-password/reset/{token}` | Validates the token and changes the password. |
| `app_login` | `/login` | Displays the login form. The firewall handles POST requests. |
| `app_logout` | `/logout` | POST only, behind the `logout` CSRF token. Intercepted by the firewall to end the session. |
| `app_profile` | `/profile` | Shows account details and a password change form. |
| `app_announcement_index` | `/announcements` | Lists announcements, newest first. |
| `app_announcement_show` | `/announcements/{id}` | Shows one announcement. Unknown ids return 404. |
| `app_map_index` | `/maps` | Lists the published maps, newest first. |
| `app_map_show` | `/maps/{id}` | Shows one annotated map with its filters. Unknown ids return 404. |
| `app_documentation_show` | `/docs/{page}` | Renders a documentation page. Unknown pages return 404. |
| `app_admin_user_index` | `/admin/users` | Paginated user list, only for admins. |
| `app_admin_user_edit` | `/admin/users/{id}/edit` | Saves the user modal form. |
| `app_admin_announcement_index` | `/admin/announcements` | Paginated announcement list, only for admins. |
| `app_admin_announcement_new` | `/admin/announcements/new` | Creates an announcement. |
| `app_admin_announcement_edit` | `/admin/announcements/{id}/edit` | Edits an announcement. |
| `app_admin_map_index` | `/admin/maps` | Paginated map list, only for admins. |
| `app_admin_map_new` | `/admin/maps/new` | Creates a map with its parameters. |
| `app_admin_map_edit` | `/admin/maps/{id}/edit` | Edits the map parameters and lists its entries. |
| `app_admin_map_delete` | `/admin/maps/{id}/delete` | Removes a map and its entries. |
| `app_admin_map_images` | `/admin/maps/images` | Lists the map image pool with the upload form. |
| `app_admin_map_image_upload` | `/admin/maps/images/upload` | Stores an uploaded image in the pool. |
| `app_admin_map_point_new` | `/admin/maps/{mapId}/points/new` | Creates a point on a map. |
| `app_admin_map_point_edit` | `/admin/maps/points/{id}/edit` | Edits a point. |
| `app_admin_map_point_delete` | `/admin/maps/points/{id}/delete` | Removes a point. |
| `app_admin_map_route_type_new` | `/admin/maps/{mapId}/route-types/new` | Creates a route type on a map. |
| `app_admin_map_route_type_edit` | `/admin/maps/route-types/{id}/edit` | Edits a route type. |
| `app_admin_map_route_type_delete` | `/admin/maps/route-types/{id}/delete` | Removes a route type with its routes. |
| `app_admin_map_route_new` | `/admin/maps/{mapId}/routes/new` | Creates a route on a map. |
| `app_admin_map_route_edit` | `/admin/maps/routes/{id}/edit` | Edits a route. |
| `app_admin_map_route_delete` | `/admin/maps/routes/{id}/delete` | Removes a route. |
| `app_admin_maintenance_uploads` | `/admin/maintenance/uploads` | Lists the uploads of every kind, only for admins. |
| `app_admin_maintenance_upload_remove` | `/admin/maintenance/uploads/{kind}/{filename}/remove` | Removes an upload and clears the field that references it. |
| `app_admin_login_audit_index` | `/admin/login-audit` | Paginated login audit list with an IP pattern search, only for admins. |
| `app_admin_maintenance_cleanup` | `/admin/maintenance/cleanup` | Shows the login audit retention state and the recorded runs, only for admins. |
| `app_admin_maintenance_cleanup_run` | `/admin/maintenance/cleanup/run` | Runs the login audit cleanup by hand. |

Profiler and UX routes exist in development. They are not part of the product surface.

## Controllers

- `IndexController` renders the neutral start page.
- `AnnouncementController` lists announcements and shows one announcement.
- `MapController` lists the maps and builds the public display of one map through `App\Map\UxMapBuilder`.
- `DocumentationController` renders a documentation page from a per-locale template, falling back to the default locale.
- `App\Controller\Admin\AdminUserController` lists the users page by page and saves the modal edit form that changes the email, the verified state, and the admin role.
- `App\Controller\Admin\AdminAnnouncementController` lists, creates, and edits announcements. The author of a new announcement is the signed-in admin.
- `App\Controller\Admin\AdminMapController` lists the maps, saves the map parameters, manages the map image pool with its upload form, and removes maps.
- `App\Controller\Admin\AdminMapPointController` creates, edits, and removes the points of a map.
- `App\Controller\Admin\AdminMapRouteTypeController` creates, edits, and removes the route types of a map; a removal takes the routes of the type with it.
- `App\Controller\Admin\AdminMapRouteController` creates, edits, and removes the routes of a map.
- `App\Controller\Admin\AdminMaintenanceController` lists the uploads of every registered kind and removes them on request; a removal refused because a map uses the file reports a flash error.
- `App\Controller\Admin\AdminLoginAuditController` lists the login audit rows page by page and filters them by an IP pattern.
- `App\Controller\Admin\AdminMaintenanceCleanupController` shows the login audit cleanup state and dispatches the cleanup message by hand.
- `RegistrationController` creates users, sends verification email, and verifies email links.
- `ResetPasswordController` handles reset requests, reset confirmation, token validation, and password changes.
- `SecurityController` displays the login form and declares the logout route.
- `ProfileController` shows account details, changes the signed-in user's password, and replaces the avatar through `App\Uploads\AvatarStorage`: a validated upload saved under a random name, after deleting the previous file.

## Entities

- `App\Entity\User` stores email, roles, password hash, verification state, the avatar filename, and the last connection metadata: `lastConnectedAt` and `lastIp`, set by `LoginAuditSubscriber`. It implements `UserInterface` and `PasswordAuthenticatedUserInterface`.
- `App\Entity\ResetPasswordRequest` stores the selector, hashed token, expiration, and the related user.
- `App\Entity\Announcement` stores the title, content, publication date, and the author. The constructor takes the four fields, and the admin form can change the publication date through `setPublishedAt()`.
- `App\Entity\LoginAudit` stores one authentication event: the user (nullable, cleared when the account is deleted), the identifier snapshot, the client IP, the start of the User-Agent header, the type (`LoginAuditType::Login` for a form login, `LoginAuditType::Resume` for the restore of a persistent session), and the time. The constructor takes the five values; the rows are never updated.
- `App\Entity\MaintenanceRun` stores one run of a maintenance task: the task name, the trigger ("schedule" or "manual"), a result summary, and the time.
- `App\Entity\Map` stores the display name, the optional image file name and its pixel size, the zoom caps, and the creation date. The image size defines the annotation space and survives an image being unset; `getSpaceWidth()` and `getSpaceHeight()` expose it with a nominal fallback.
- `App\Entity\MapPoint` stores one pin: the title, the optional description, the position in image pixels, and the list of symbol icon names in a JSON column. The inverse collections of `Map`, `MapRouteType`, and `MapRoute` hydrate from the database and no constructor writes into them; see the dataset import section for the measured reason.
- `App\Entity\MapRouteType` stores one route type of a map: the name and the "#rrggbb" line color.
- `App\Entity\MapRoute` stores one route: the owning map, the type, the optional name shown in the popup (falling back to the type name through `getDisplayName()`), the optional description, the major flag, and the line vertices in a JSON column.

## Forms

- `RegistrationFormType` collects email, password, and terms acceptance. It maps the password to a non-mapped `plainPassword` field.
- `ResetPasswordRequestFormType` collects the email address.
- `ChangePasswordFormType` collects a repeated new password. The profile page and password reset flow both use it.
- `PasswordConstraints` supplies the shared password rules for both forms.
- `AdminUserFormType` edits the email and the verified state, and offers the assignable roles in a multi select. `ROLE_ADMIN` is the only assignable role today; new roles extend `AdminUserFormType::assignableRoles()`. An admin cannot remove the own admin role: the controller rejects the change, reloads the user, and reports it with a flash.
- `AnnouncementFormType` edits the title, the publication date, and the content.
- `AnnouncementAutocompleteField` marks an entity autocomplete over `Announcement`, searching the title and the content, served by the bundle at `/autocomplete/announcement_autocomplete_field`. `AnnouncementSearchType` wraps it in a GET form without CSRF, because a search changes nothing.
- `AdminUserAutocompleteField` is the admin counterpart over `User`, searching the email, with the `ROLE_ADMIN` guard set on the field itself. `AdminUserSearchType` and `AdminAnnouncementSearchType` wrap the fields in GET forms; the announcement form reuses the public autocompleter, whose results are public data.
- `AvatarFormType` collects the avatar upload through the `ux-dropzone` control, with content-sniffed image validation.
- `LoginAuditSearchType` wraps one IP text field in a GET form without CSRF, because a search changes nothing.
- `MapFormType` edits the map name, the zoom caps, and the background image chosen from the pool; the image field is unmapped because saving it also records the pixel size read from the file. A callback constraint on `maxZoom` rejects a maximum below the minimum.
- `MapImageFormType` collects the pool upload through the `ux-dropzone` control, with the same content-sniffed validation as the avatar.
- `MapPointFormType` edits a pin: title, description, x and y bounded to the map space, and the symbols as icon names parsed through `Symbols`, whose parse rule is shared with the controller write, and validated by rendering them, so an unknown icon cannot reach the public display.
- `MapRouteTypeFormType` edits the type name and the color through the native color input.
- `MapRouteFormType` edits a route: the type among the types of the map, the optional name, the description, the major flag, and the vertices as one "x, y" pair per line through `RoutePoints`, whose parse rule is shared with the controller write.
- `KeepCurrentData` supplies the `empty_data` closure for the mapped fields with non-nullable setters: a submission that a browser cannot produce (an empty required field) keeps the current value instead of crashing the request. See `docs/DECISIONS.md`.

`templates/form/fields.html.twig` is a form theme registered after the Bootstrap layout. It wraps every Symfony-rendered password field with the visibility toggle from the `password-visibility` recipe, including the one on the login form, which `LoginFormType` renders with the field names the firewall expects.

The autocomplete uses `assets/controllers.json`: the recipe entry registers the bundle Stimulus controller eagerly and autoimports the Bootstrap 5 TomSelect stylesheet. `importmap.php` maps `@symfony/ux-autocomplete` to the local vendor controller, and `symfony/stimulus-bundle` generates the controllers module from both sources at serve time.

Registration and password reset use the same password rules. In the `dev` environment, the minimum is 6 characters and the strength and compromise checks are skipped. The `test` and `prod` environments keep the strict rules.

## Repositories and services

- `UserRepository` extends Doctrine's `ServiceEntityRepository` and implements password upgrading.
- `AnnouncementRepository` extends Doctrine's `ServiceEntityRepository` and returns the announcements newest first.
- `ResetPasswordRequestRepository` uses the SymfonyCasts reset password repository trait.
- `LoginAuditRepository` lists the audit rows newest first with the optional IP pattern filter, and counts or removes the rows older than a threshold.
- `MaintenanceRunRepository` returns the latest recorded runs of a task.
- `MapRepository` returns the maps newest first and finds the maps that use one of a list of image file names; the kind uses the second method to report pool owners and refuse in-use removals.
- `EmailVerifier` generates signed verification URLs and marks users as verified after validation.
- `PasswordChanger` sets a new password on an account and writes it; registration, the password reset, and the profile page share the one write path.
- `VerifiedUserChecker` rejects unverified users during authentication and session refresh.
- `App\Security\LoginAuditSubscriber` listens to `LoginSuccessEvent`, records the audit row, and updates the last connection metadata. See `docs/DECISIONS.md` for why this event keeps the table from filling up per request.
- `App\Message\LoginAuditCleanup` is the cleanup message; `App\MessageHandler\LoginAuditCleanupHandler` removes the expired rows and records the run; `App\Task\LoginAuditCleanupTask` is the scheduler entry that dispatches the message once a day. `App\Maintenance\LoginAuditRetention` holds the retention window (`app.login_audit_retention_days`): the handler and the maintenance page both ask it for the threshold, so the parameter and the arithmetic live in one place.
- `App\Uploads` holds the upload kinds: `UploadKindInterface` defines the contract and `AbstractDirectoryUploadKind` implements the directory listing and the name checks shared by the kinds, so each kind states its location, its file-name rule, and who references its files. `AvatarUploadKind` manages the avatars, `MapImageUploadKind` the map image pool, refusing a removal while a map uses the file through `UploadInUseException`, and `AvatarStorage` owns the avatar write path: the profile page replaces an avatar through it and the avatar kind clears a removed file with its referencing account through it. Kinds register through the `app.upload_kind` tag; the map form takes its image choices from the kind's listing, not from its own directory scan.
- `App\Map\UxMapBuilder` turns one `Map` entity into the ux-map definition of the public display: it scales the pixel space into the range `Point` accepts, renders the pin and symbol icons, escapes the popup texts, and carries the image URL and the space in the map `extra`. The design choices are in `docs/DECISIONS.md`.
- `assets/controllers/image_map_controller.js` decorates the ux-map Leaflet controller through its `ux:map:*` events: it switches the map to `L.CRS.Simple`, draws the background image, styles the pins and lines, and applies the pins, symbols, and lines filters.

## Dataset import

- `app:dataset:load` (`App\Command\DatasetLoadCommand`) loads YAML datasets into the database. Without arguments it loads `datasets/shared/` plus `datasets/<environment>/`; arguments name files or directories instead, relative to the project directory. `--purge` first deletes every row that carries a dataset slug, `--dry-run` reports without writing, `--batch-size` sets how many records are written between two flushes (default 100), and the purge asks for confirmation interactively, requires `--force` otherwise, and is refused in the prod environment.
- `App\Dataset\DatasetLoader` parses every file once inside one transaction. The parse validates the structure, the duplicate slugs, the shape of removal entries, and the field names; the reference validation returns the write order from the same pass that rejects the cycles, so a record is written after every record it references, and every structural failure happens before any write. The writes run in batches: each full batch is flushed and the entity manager cleared, so memory holds the parsed records and one batch. A slug new to the database inserts a row, a known slug updates the fields the record names, and rows without a slug are never touched.
- A reference `@type.slug` resolves against the current load first and against the row of an earlier load second, found by its dataset slug, so a subset of a dataset loads on its own whenever its references exist. A purge load must name every record it references, because the purge deletes the earlier rows first. `App\Dataset\DatasetRecord` holds one record with typed accessors and the failure messages, `App\Dataset\DatasetResolver` maps references to entities of the current batch and to reference proxies built from the row ids of earlier batches, and `App\Dataset\DatasetException` reports a failure with the file, the section, and the slug. A record written as `slug: {remove: true}` deletes the row carrying that slug; the loader derives the delete order from the entity metadata, so children go before the rows they reference.
- Each importable entity is a dataset type in `App\Dataset\Type\`, registered through the `app.dataset_type` tag the way the upload kinds register: `UserDatasetType`, `AnnouncementDatasetType`, `MapDatasetType`, `MapPointDatasetType`, `MapRouteTypeDatasetType`, and `MapRouteDatasetType`. A type declares its section name, its fields, and the fields a new record must carry; the loader checks the record shape, and the type validates the values it knows: the user type hashes the plain `password` field, the point type validates symbol names by rendering them the way the point form does, the map type requires `imageWidth` and `imageHeight` together and `maxZoom` at or above `minZoom`, and the route type requires at least two vertices inside the map space.
- The six entities store their dataset key in the unique, nullable `dataset_slug` column. `datasets/dev/` ships the development seed, an admin and a member account, one announcement, and one demo map with a point, a route type, and a route; `datasets/prod/` is git-ignored and holds its own workflow in `datasets/prod/README.md`. The design choices are in `docs/DECISIONS.md`.

## Templates

- `templates/base.html.twig` loads the importmap, the JavaScript entrypoint, the `Navbar` component, the `FlashMessages` component, and the content container. The root element carries `lang="{{ app.request.locale }}"` and `data-bs-theme="dark"`.
- `templates/components/` holds the anonymous Twig components: `Navbar` renders the navigation with the account links, `FlashMessages` renders every flash type as a Bootstrap alert with an icon, a dismiss button, and, for the successes, an automatic close after 5 seconds with a countdown bar, `PostLink` submits a link as a POST form with a CSRF token, `LogoutLink` wraps `PostLink` for the logout route, `Modal` with its `Header`, `Title`, `Body`, and `Footer` sub-components renders the admin dialogs, and `MaintenanceNav` renders the maintenance sub-navigation. `PostLink`, `LogoutLink`, and `Modal` come from the UX Toolkit recipes, and the flash alerts use its `closeable` Stimulus controller.
- `templates/announcement/` holds the announcement list and detail pages. `templates/documentation/` holds the documentation pages, named `{page}.{locale}.html.twig`: the route `/docs/{page}` tries the current locale first, then the default locale, and a page or a locale variant is added by dropping the template file. `templates/map/` holds the public map list and the map display page with its filter panel. `templates/index/`, `templates/profile/`, `templates/registration/`, `templates/reset_password/`, and `templates/security/` contain the page and email templates.
- `templates/layouts/admin.html.twig` extends the base template with the admin section navigation. The pages under `templates/admin/` extend it and fill the `admin_body` block. `templates/admin/map/` holds the map pages and the point, route type, and route form pages.
- `templates/admin/login_audit/` holds the audit list. `templates/admin/maintenance/` holds the maintenance pages, which share the `MaintenanceNav` sub-navigation from `templates/components/`.
- `templates/form/fields.html.twig` holds the password field form theme.
- `config/packages/ux_pagination.yaml` selects the Bootstrap theme of `symfony/ux-pagination`; both admin lists paginate through it.
- `translations/` holds the app catalogs: `messages.en.yaml` for pages, form labels, flash messages, and emails, `validators.en.yaml` for constraint messages, and `security.en.yaml` for the login error from `VerifiedUserChecker`. Templates and controllers render translation keys, not literal text.

## Icons

`ux-icons` renders the icons inline through the `ux_icon()` Twig function. The icons used by the templates are locked into `assets/icons/` with `php bin/console ux:icons:lock`, which scans the templates and imports each icon from iconify. The locked files are committed, so pages and tests render without a network connection. A new icon in a template needs the same command run once.

## Data storage

Development uses PostgreSQL through `DATABASE_URL`. Tests use SQLite through `.env.test`. The committed migration uses PostgreSQL syntax, so it cannot build the SQLite test schema. See `SETUP.md`.

## Authentication and redirects

`config/packages/security.yaml` uses `form_login` on the `main` firewall. The login form posts to `app_login` with CSRF protection. `VerifiedUserChecker` requires `User::isVerified()` to be true, so unverified accounts cannot log in. The firewall offers persistent sessions: `remember_me` with a 7 day cookie lifetime, signed with `%kernel.secret%`, activated by the `_remember_me` checkbox on the login form. When a visitor returns with the cookie and no session, the firewall re-authenticates them from it.

`LoginAuditSubscriber` runs on every successful authentication and records what happened: see the Entities section and `docs/DECISIONS.md`.

Successful registration, email verification, login, and password change redirect to `app_index`. Logout also redirects to `app_index`. Password changes on the profile page redirect to `app_profile`.

`ProfileController` uses `#[IsGranted('ROLE_USER')]`, so anonymous visitors are sent to the login page. The `access_control` list guards `/admin` with `ROLE_ADMIN`, and the admin controllers repeat the check with `#[IsGranted('ROLE_ADMIN')]`. Admins hold `['ROLE_ADMIN']` in the roles field; `ROLE_USER` comes from the entity for everyone, so no role hierarchy is configured. The navbar shows the admin link only to admins.
