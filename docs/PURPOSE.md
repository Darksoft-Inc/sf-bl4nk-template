# Purpose

This project is a starter application for public-facing services. It holds the features that several applications share, so each new application can start from the same base and add its own domain rules. It is a working base, not a finished product.

The current code provides the base and part of account management. The features listed below are the target. Use this document to decide whether to keep, remove, or change a package. Update the document when the target changes.

## Application baseline

- Symfony 8.2. The installed version is 8.2.0-BETA2. RC1 is planned for the end of November 2026. A beta is enough for development. Use a stable release before production.
- PHP 8.4 or newer. PHP 8.5 is recommended for advanced Symfony features.
- Bootstrap 5.3 for the layout on desktop, tablet, and mobile screens. Sass builds the styles from the Bootstrap source so colors can be customized.
- Symfony UX with Asset Mapper and Stimulus. Turbo is not part of the baseline and is not installed. The target applications use multi-page navigation rather than a single-page application.

## Target features

| Feature | What the starter app must provide |
|---|---|
| Account management | Registration, email verification, login, logout, password reset, and a profile page with personal details and password change. |
| Login audit | Record every login and every restore of a persistent session with the IP, for later review; keep the records bounded by a scheduled cleanup with a manual trigger. The last connection metadata shows on the profile page and in the admin user list. |
| Announcement blog | Publish short announcements and show them in a list and on a detail page. |
| Simple documentation pages | Serve edited documentation pages with stable links. |
| Generated documentation pages | Build pages from metadata declared on business services. The metadata format must not depend on one application's domain. |
| Annotated 2D map | Show a two-dimensional map with annotations supplied by the application. |
| Private sections and admin functions | Restrict sections by user role, keep administrative functions separate from public pages, and page through large sets of records such as logs. |
| Scheduled maintenance | Run recurring maintenance tasks and record their results. These actions may also be started manually from admin pages. |
| Translations | Translate interface texts, messages, and emails. |
| Flexible dataset import | Load structured datasets in development and production. Use the same import code where practical. |
| User-provided data | Accept, validate, store, and display user uploads in three categories: the account avatar, shared images, and shared plain-text files. Archives, word processor documents, and PDF files are out of scope. |
| Responsive interface | Work on desktop, tablet, and mobile screens. |
| Search in large data sets | Provide autocomplete where a full list is too large to show at once. |
| Reusable interface components | Build pages from modular Twig components and icons. |

## Example applications

The base is intended for several public-facing services. Examples include:

- An MMORPG in a medieval fantasy setting.
- An action MMO about investigation.
- A sandbox application.
- A collaborative tool for sharing intelligence about other games.

These examples set the context for design choices. An application should not add its own domain content to this starter app.

## Package decisions

Keep a package when it supports the baseline or a target feature. Remove a package when no item in this document needs it. Add a package only for a feature that the starter app must provide.

| Area | Current support | Decision |
|---|---|---|
| Pages and layout | `symfony/twig-bundle`, `symfony/asset-mapper`, `symfonycasts/sass-bundle`, `twbs/bootstrap` | Keep. Bootstrap 5.3 and Stimulus are the baseline. Do not add Turbo without changing the baseline. |
| Routes in JavaScript | `symfony/ux-router`, the `@symfony/ux-router` entry in `importmap.php` | Keep. JavaScript reads routes from the router instead of data attributes rendered by controllers. |
| Entity identifiers | The `dataset_slug` column on the six importable entities | The dataset import keys its rows by slug and resolves `@type.slug` references at load time, so stored rows depend on no client-generated identifier. `symfony/uid` stays removed for the reason recorded on 2026-11-21. |
| Account management | `symfony/security-bundle`, `symfony/form`, `symfony/validator`, `symfony/mailer`, `symfony/http-client`, `symfonycasts/reset-password-bundle`, `symfonycasts/verify-email-bundle` | Keep. `symfony/http-client` runs the compromised password check. |
| Translations | `symfony/translation`, `symfony/ux-translator`, `symfony/intl`, `twig/intl-extra` | Keep. The catalogs live in `translations/`. |
| Annotated map | `symfony/ux-map`, `symfony/ux-leaflet-map`, Leaflet entries in `importmap.php` | Keep. |
| JSON for the frontend | `symfony/serializer` | Keep. Dynamic frontend data, such as map annotations and autocomplete results, arrives as JSON. |
| Scheduled maintenance | `symfony/scheduler`, `symfony/messenger`, `symfony/doctrine-messenger` | Keep. Choose and document a transport before using background jobs. |
| User uploads | `symfony/ux-dropzone`, `symfony/validator` | Keep the upload control. Decide storage and image processing when the feature is built. |
| Blog and documentation pages | Doctrine ORM and Twig | No extra package is needed at this stage. |
| Private sections and admin | `symfony/security-bundle` | Keep. Add roles and access rules with the features. |
| Large admin lists | `symfony/ux-pagination` | Keep for lists such as logs. |
| Search | `symfony/ux-autocomplete`, `tom-select` in `importmap.php` | Keep. Add the search endpoint with the feature that needs it. |
| Reusable interface components | `symfony/ux-twig-component`, `symfony/ux-icons` | Keep. Reusable components and icons are part of the interface baseline. |

The following packages were removed because no target feature needs them:

- `symfony/notifier`: It sends notifications through SMS, chat, or push channels. Flash messages use the session, and confirmation emails use `symfony/mailer`.
- `symfony/tui`: It builds interactive command line interfaces. The starter app has no command line tools.
- `symfony/ux-inspector`: It is an experimental development tool for Stimulus, LiveComponent, and Turbo. The baseline uses Stimulus without Turbo.
- `symfony/ux-chartjs`: Statistics display is too specific for the shared base. A derived application can add chart support with its own data.
- `symfony/web-link`: The package sends no header by itself; a feature must call `preload()` or `link()`. Add it back when a preload or performance feature is designed.
- `symfony/expression-language`: No route condition, security expression, or configuration expression exists. Complex access rules fit a security voter better. Add the package back if an expression declared in configuration appears.
- `symfony/uid` (removed on 2026-11-21): Deferred references can use slug columns instead, and a dataset loader can resolve relations between its own rows when it inserts them. No code used the package.

The same rule applies to JavaScript entries in `importmap.php`. An entry without a feature is a removal candidate.
