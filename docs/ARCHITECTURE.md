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
| `app_logout` | `/logout` | Intercepted by the firewall to end the session. |
| `app_profile` | `/profile` | Shows account details and a password change form. |

Profiler and UX routes exist in development. They are not part of the product surface.

## Controllers

- `IndexController` renders the neutral start page.
- `RegistrationController` creates users, sends verification email, and verifies email links.
- `ResetPasswordController` handles reset requests, reset confirmation, token validation, and password changes.
- `SecurityController` displays the login form and declares the logout route.
- `ProfileController` shows account details and changes the signed-in user's password.

## Entities

- `App\Entity\User` stores email, roles, password hash, and verification state. It implements `UserInterface` and `PasswordAuthenticatedUserInterface`.
- `App\Entity\ResetPasswordRequest` stores the selector, hashed token, expiration, and the related user.

## Forms

- `RegistrationFormType` collects email, password, and terms acceptance. It maps the password to a non-mapped `plainPassword` field.
- `ResetPasswordRequestFormType` collects the email address.
- `ChangePasswordFormType` collects a repeated new password. The profile page and password reset flow both use it.
- `PasswordConstraints` supplies the shared password rules for both forms.

Registration and password reset use the same password rules. In the `dev` environment, the minimum is 6 characters and the strength and compromise checks are skipped. The `test` and `prod` environments keep the strict rules.

## Repositories and services

- `UserRepository` extends Doctrine's `ServiceEntityRepository` and implements password upgrading.
- `ResetPasswordRequestRepository` uses the SymfonyCasts reset password repository trait.
- `EmailVerifier` generates signed verification URLs and marks users as verified after validation.
- `VerifiedUserChecker` rejects unverified users during authentication and session refresh.

## Templates

- `templates/base.html.twig` loads the importmap, the JavaScript entrypoint, and success flash messages.
- `templates/index/`, `templates/profile/`, `templates/registration/`, `templates/reset_password/`, and `templates/security/` contain the page and email templates.
- `translations/messages.en.yaml` holds general messages for shared pages. Templates still use literal English text until the translation pass.

## Data storage

Development uses PostgreSQL through `DATABASE_URL`. Tests use SQLite through `.env.test`. The committed migration uses PostgreSQL syntax, so it cannot build the SQLite test schema. See `SETUP.md`.

## Authentication and redirects

`config/packages/security.yaml` uses `form_login` on the `main` firewall. The login form posts to `app_login` with CSRF protection. `VerifiedUserChecker` requires `User::isVerified()` to be true, so unverified accounts cannot log in.

Successful registration, email verification, login, and password change redirect to `app_index`. Logout also redirects to `app_index`. Password changes on the profile page redirect to `app_profile`.

`ProfileController` uses `#[IsGranted('ROLE_USER')]`, so anonymous visitors are sent to the login page. The `access_control` list is still empty.
