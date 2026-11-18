# Architecture

This page describes the current application, not a target design. `TODO.md` lists planned changes.

## Application routes

| Name | Path | Purpose |
|---|---|---|
| `app_index` | `/index` | Landing page. Still the maker placeholder. |
| `app_register` | `/register` | Registration form and email verification entry point. |
| `app_verify_email` | `/verify/email` | Validates the signed verification link. |
| `app_forgot_password_request` | `/reset-password` | Password reset request form. |
| `app_check_email` | `/reset-password/check-email` | Confirmation page after a reset request. |
| `app_reset_password` | `/reset-password/reset/{token}` | Validates the token and changes the password. |

Profiler and UX routes exist in development. They are not part of the product surface.

## Controllers

- `IndexController` renders the landing page.
- `RegistrationController` creates users, sends verification email, and verifies email links.
- `ResetPasswordController` handles reset requests, reset confirmation, token validation, and password changes.

There is no login controller and no logout route yet.

## Entities

- `App\Entity\User` stores email, roles, password hash, and verification state. It implements `UserInterface` and `PasswordAuthenticatedUserInterface`.
- `App\Entity\ResetPasswordRequest` stores the selector, hashed token, expiration, and the related user.

## Forms

- `RegistrationFormType` collects email, password, and terms acceptance. It maps the password to a non-mapped `plainPassword` field.
- `ResetPasswordRequestFormType` collects the email address.
- `ChangePasswordFormType` collects a repeated new password.

Password rules differ between registration and password reset. See `TODO.md`.

## Repositories and services

- `UserRepository` extends Doctrine's `ServiceEntityRepository` and implements password upgrading.
- `ResetPasswordRequestRepository` uses the SymfonyCasts reset password repository trait.
- `EmailVerifier` generates signed verification URLs and marks users as verified after validation.

## Templates

- `templates/base.html.twig` loads the importmap and the JavaScript entrypoint.
- `templates/index/`, `templates/registration/`, and `templates/reset_password/` contain the page and email templates.

There is no shared flash message block yet.

## Data storage

Development uses PostgreSQL through `DATABASE_URL`. Tests use SQLite through `.env.test`. The committed migration uses PostgreSQL syntax, so it cannot build the SQLite test schema. See `SETUP.md`.

## Authentication state

`config/packages/security.yaml` has an entity user provider and a `main` firewall. The firewall has no authenticator. All application routes are public. Tests call `loginUser()` to inject a token, so they do not prove that a user can sign in through the app.
