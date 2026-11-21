# Configuration

## Environment files

`.env` contains committed defaults only. Real values belong in `.env.local`, `.env.test.local`, or the secrets vault. The ignore rules already exclude local env files.

## Environment variables

| Variable | Current default | Notes |
|---|---|---|
| `APP_ENV` | `dev` | Set to `test`, `dev`, or `prod` as needed. |
| `APP_SECRET` | empty in `.env` | Set a real value for non-development environments. |
| `APP_SHARE_DIR` | `var/share` | Shared storage path. |
| `DEFAULT_URI` | `http://localhost` | Used to generate absolute URLs outside HTTP requests, including email links. |
| `MAILER_DSN` | `null://null` | No email is delivered with this value. Set a real DSN for deployed environments. |
| `ADMIN_EMAIL` | `admin@example.com` | Sender address for account emails. |
| `ADMIN_NAME` | `Website Admin` | Sender name for account emails. |
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` | `config/packages/messenger.yaml` currently defines only the `sync` transport. |
| `DATABASE_URL` | PostgreSQL in `.env`, SQLite in `.env.test` | Change credentials before deployment. |
| `UX_MAP_DSN` | `leaflet://default` | Used by the installed map package. |

## Mail sender

Sender values come from `ADMIN_EMAIL` and `ADMIN_NAME`. `config/services.yaml` exposes them as `app.admin_email` and `app.admin_name`. `RegistrationController` and `ResetPasswordController` receive the values through `#[Autowire(param: ...)]`.

Replace both defaults before deployment. The current values are placeholders.

## Login audit retention

`app.login_audit_retention_days` in `config/services.yaml` (90 by default) is the age at which a login audit row becomes removable. The scheduled cleanup and the manual trigger on `/admin/maintenance/cleanup` both remove the rows past that age. Lowering the value removes rows sooner; raising it keeps more history.

## Map image pool

`app.map_image_dir` in `config/services.yaml` points at `public/uploads/maps/`, the pool of map background images. The admin upload at `/admin/maps/images` stores files there under random names, and an operator can place pre-existing images there directly; the map form and the maintenance uploads page list both the same way. The upload accepts 2 MB PNG, JPEG, WebP, or GIF images, matching the php.ini `upload_max_filesize` of the platform; larger images take the pre-existing path. An image used by a map cannot be removed from the maintenance pages.

`UX_MAP_DSN` selects the map renderer; `leaflet://default` renders the public map display through `ux_map` and the `image_map_controller.js` decoration. See `docs/DECISIONS.md` for the image-plane adaptation.

## Translations

`framework.enabled_locales` is set to `['en']` in `config/packages/framework.yaml`, and `en` is the default locale. Three catalogs live in `translations/`:

- `messages.en.yaml` covers page titles, navigation, form labels, flash messages, and email texts. Templates render these keys through the `trans` filter, and the form theme translates label keys through the `messages` domain.
- `validators.en.yaml` covers the entity and form constraint messages. The validator translates them through the `validators` domain.
- `security.en.yaml` covers `app.verified_email_required`, the key `VerifiedUserChecker` throws. The login template translates it through the `security` domain.

Email subjects are set inside the email templates through `email.setSubject()`. Adding a locale means copying the three catalogs and enabling the locale in `framework.enabled_locales`. For pages with long prose, follow the content-heavy pages policy in `docs/DECISIONS.md`: prose comes from per-locale templates or from a dataset, not from the catalogs.

## Dataset values

`datasets/` holds the YAML datasets that `app:dataset:load` imports. `datasets/dev/` is committed development data: an admin and a member account, one announcement, and one demo map. `datasets/prod/` is git-ignored, because production records hold real values, starting with the admin account and its password, which the loader hashes at load time. `datasets/shared/` loads in every environment and ships empty. See `docs/DECISIONS.md` for the slug-keyed rules, `docs/SETUP.md` for the commands, and `datasets/prod/README.md` for the production workflow.

## Security and signed URLs

`config/packages/security.yaml` defines a user provider and a `form_login` authenticator on the `main` firewall. The routes are `app_login` and `app_logout`. `App\Security\VerifiedUserChecker` requires a verified email address before login. Successful login and logout redirect to `app_index`. The profile route uses `#[IsGranted('ROLE_USER')]`. The `access_control` list guards `/admin` with `ROLE_ADMIN`.

The firewall enables persistent sessions with `remember_me`: the cookie lives 7 days, is signed with `%kernel.secret%` (`APP_SECRET`, see the production item in `TODO.md`), and is only sent when the login form's `_remember_me` checkbox is checked. `LoginAuditSubscriber` records every login and every persistent-session restore; see `docs/DECISIONS.md`.

`framework.uri_signer.expiration` is unset. Symfony 8.2 deprecates signed URIs without an expiration, but that option configures the framework's own `uri_signer` service and does not reach the private signer the verify-email bundle builds for its links. `config/services.yaml` instead overrides the bundle's `symfonycasts.verify_email.uri_signer` service with the arguments the bundle uses, `'%kernel.secret%'` and `signature`, plus a default expiration of 1 day. The value stays above the bundle link lifetime of 1 hour (`symfonycasts_verify_email.lifetime`, default 3600), because the bundle then reports an expired link while the signer reports an invalid signature. No bundle release passes an expiration, including the 2.x branch under development, so the override stays until the bundle fixes its `sign()` call. The choice is recorded in `docs/DECISIONS.md`.

## Symfony version

`composer.json` requires `8.2.*` and allows dev packages. The installed runtime is Symfony 8.2.0-BETA2. Move to a stable release before production use.

## Configuration reference

Generated configuration descriptions live in:

- `config/reference.php`
- `config/schema.json`

Read these files before adding or changing bundle options. They are regenerated when the debug container is rebuilt.
