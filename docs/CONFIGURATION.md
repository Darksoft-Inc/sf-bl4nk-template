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
| `MESSENGER_TRANSPORT_DSN` | `doctrine://default?auto_setup=0` | `config/packages/messenger.yaml` currently defines only the `sync` transport. |
| `DATABASE_URL` | PostgreSQL in `.env`, SQLite in `.env.test` | Change credentials before deployment. |
| `UX_MAP_DSN` | `leaflet://default` | Used by the installed map package. |

## Mail sender

Sender values are hardcoded in controllers:

- `src/Controller/RegistrationController.php:22-23`
- `src/Controller/ResetPasswordController.php:28-29`

Move these values to parameters or environment variables before deployment. The current value `admin@website.com` is a placeholder.

`config/packages/notifier.yaml` also uses `admin@example.com`. Replace it or remove the notifier package if it is not used.

## Security and signed URLs

`config/packages/security.yaml` defines a user provider and a `form_login` authenticator on the `main` firewall. The routes are `app_login` and `app_logout`. `App\Security\VerifiedUserChecker` requires a verified email address before login. Successful login and logout redirect to `app_index`. There is no `access_control` rule yet.

`framework.uri_signer.expiration` is currently unset. Symfony 8.2 deprecates signed URIs without an expiration. Check the verify-email bundle behavior before setting a default. The option is documented in `config/reference.php` near line 637.

## Symfony version

`composer.json` requires `8.2.*` and allows dev packages. The installed runtime is Symfony 8.2.0-BETA2. Move to a stable release before production use.

## Configuration reference

Generated configuration descriptions live in:

- `config/reference.php`
- `config/schema.json`

Read these files before adding or changing bundle options. They are regenerated when the debug container is rebuilt.
