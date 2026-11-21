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
| `app_announcement_index` | `/announcements` | Lists announcements, newest first. |
| `app_announcement_show` | `/announcements/{id}` | Shows one announcement. Unknown ids return 404. |

Profiler and UX routes exist in development. They are not part of the product surface.

## Controllers

- `IndexController` renders the neutral start page.
- `AnnouncementController` lists announcements and shows one announcement.
- `RegistrationController` creates users, sends verification email, and verifies email links.
- `ResetPasswordController` handles reset requests, reset confirmation, token validation, and password changes.
- `SecurityController` displays the login form and declares the logout route.
- `ProfileController` shows account details and changes the signed-in user's password.

## Entities

- `App\Entity\User` stores email, roles, password hash, and verification state. It implements `UserInterface` and `PasswordAuthenticatedUserInterface`.
- `App\Entity\ResetPasswordRequest` stores the selector, hashed token, expiration, and the related user.
- `App\Entity\Announcement` stores the title, content, and publication date. The constructor takes all three fields, and `publishedAt` has no setter.

## Forms

- `RegistrationFormType` collects email, password, and terms acceptance. It maps the password to a non-mapped `plainPassword` field.
- `ResetPasswordRequestFormType` collects the email address.
- `ChangePasswordFormType` collects a repeated new password. The profile page and password reset flow both use it.
- `PasswordConstraints` supplies the shared password rules for both forms.

Registration and password reset use the same password rules. In the `dev` environment, the minimum is 6 characters and the strength and compromise checks are skipped. The `test` and `prod` environments keep the strict rules.

## Repositories and services

- `UserRepository` extends Doctrine's `ServiceEntityRepository` and implements password upgrading.
- `AnnouncementRepository` extends Doctrine's `ServiceEntityRepository` and returns the announcements newest first.
- `ResetPasswordRequestRepository` uses the SymfonyCasts reset password repository trait.
- `EmailVerifier` generates signed verification URLs and marks users as verified after validation.
- `VerifiedUserChecker` rejects unverified users during authentication and session refresh.

## Templates

- `templates/base.html.twig` loads the importmap, the JavaScript entrypoint, the `Navbar` component, the `FlashMessages` component, and the content container.
- `templates/components/` holds the anonymous Twig components: `Navbar` renders the navigation with the account links, and `FlashMessages` renders every flash type as a Bootstrap alert with an icon.
- `templates/announcement/` holds the announcement list and detail pages. `templates/index/`, `templates/profile/`, `templates/registration/`, `templates/reset_password/`, and `templates/security/` contain the page and email templates.
- `translations/` holds the app catalogs: `messages.en.yaml` for pages, form labels, flash messages, and emails, `validators.en.yaml` for constraint messages, and `security.en.yaml` for the login error from `VerifiedUserChecker`. Templates and controllers render translation keys, not literal text.

## Icons

`ux-icons` renders the icons inline through the `ux_icon()` Twig function. The icons used by the templates are locked into `assets/icons/` with `php bin/console ux:icons:lock`, which scans the templates and imports each icon from iconify. The locked files are committed, so pages and tests render without a network connection. A new icon in a template needs the same command run once.

## Data storage

Development uses PostgreSQL through `DATABASE_URL`. Tests use SQLite through `.env.test`. The committed migration uses PostgreSQL syntax, so it cannot build the SQLite test schema. See `SETUP.md`.

## Authentication and redirects

`config/packages/security.yaml` uses `form_login` on the `main` firewall. The login form posts to `app_login` with CSRF protection. `VerifiedUserChecker` requires `User::isVerified()` to be true, so unverified accounts cannot log in.

Successful registration, email verification, login, and password change redirect to `app_index`. Logout also redirects to `app_index`. Password changes on the profile page redirect to `app_profile`.

`ProfileController` uses `#[IsGranted('ROLE_USER')]`, so anonymous visitors are sent to the login page. The `access_control` list is still empty.
