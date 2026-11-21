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
| `app_documentation_show` | `/docs/{page}` | Renders a documentation page. Unknown pages return 404. |
| `app_admin_user_index` | `/admin/users` | Paginated user list, only for admins. |
| `app_admin_user_edit` | `/admin/users/{id}/edit` | Saves the user modal form. |
| `app_admin_announcement_index` | `/admin/announcements` | Paginated announcement list, only for admins. |
| `app_admin_announcement_new` | `/admin/announcements/new` | Creates an announcement. |
| `app_admin_announcement_edit` | `/admin/announcements/{id}/edit` | Edits an announcement. |

Profiler and UX routes exist in development. They are not part of the product surface.

## Controllers

- `IndexController` renders the neutral start page.
- `AnnouncementController` lists announcements and shows one announcement.
- `DocumentationController` renders a documentation page from a per-locale template, falling back to the default locale.
- `App\Controller\Admin\AdminUserController` lists the users page by page and saves the modal edit form that changes the email, the verified state, and the admin role.
- `App\Controller\Admin\AdminAnnouncementController` lists, creates, and edits announcements. The author of a new announcement is the signed-in admin.
- `RegistrationController` creates users, sends verification email, and verifies email links.
- `ResetPasswordController` handles reset requests, reset confirmation, token validation, and password changes.
- `SecurityController` displays the login form and declares the logout route.
- `ProfileController` shows account details and changes the signed-in user's password.

## Entities

- `App\Entity\User` stores email, roles, password hash, and verification state. It implements `UserInterface` and `PasswordAuthenticatedUserInterface`.
- `App\Entity\ResetPasswordRequest` stores the selector, hashed token, expiration, and the related user.
- `App\Entity\Announcement` stores the title, content, publication date, and the author. The constructor takes the four fields, and the admin form can change the publication date through `setPublishedAt()`.

## Forms

- `RegistrationFormType` collects email, password, and terms acceptance. It maps the password to a non-mapped `plainPassword` field.
- `ResetPasswordRequestFormType` collects the email address.
- `ChangePasswordFormType` collects a repeated new password. The profile page and password reset flow both use it.
- `PasswordConstraints` supplies the shared password rules for both forms.
- `AdminUserFormType` edits the email and the verified state and holds an unmapped `isAdmin` checkbox for the admin role.
- `AnnouncementFormType` edits the title, the publication date, and the content.

`templates/form/fields.html.twig` is a form theme registered after the Bootstrap layout. It wraps every Symfony-rendered password field with the visibility toggle from the `password-visibility` recipe. The login form is raw HTML and keeps its plain field.

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
- `templates/announcement/` holds the announcement list and detail pages. `templates/documentation/` holds the documentation pages, named `{page}.{locale}.html.twig`: the route `/docs/{page}` tries the current locale first, then the default locale, and a page or a locale variant is added by dropping the template file. `templates/index/`, `templates/profile/`, `templates/registration/`, `templates/reset_password/`, and `templates/security/` contain the page and email templates.
- `templates/layouts/admin.html.twig` extends the base template with the admin section navigation. The pages under `templates/admin/` extend it and fill the `admin_body` block.
- `templates/form/fields.html.twig` holds the password field form theme.
- `config/packages/ux_pagination.yaml` selects the Bootstrap theme of `symfony/ux-pagination`; both admin lists paginate through it.
- `translations/` holds the app catalogs: `messages.en.yaml` for pages, form labels, flash messages, and emails, `validators.en.yaml` for constraint messages, and `security.en.yaml` for the login error from `VerifiedUserChecker`. Templates and controllers render translation keys, not literal text.

## Icons

`ux-icons` renders the icons inline through the `ux_icon()` Twig function. The icons used by the templates are locked into `assets/icons/` with `php bin/console ux:icons:lock`, which scans the templates and imports each icon from iconify. The locked files are committed, so pages and tests render without a network connection. A new icon in a template needs the same command run once.

## Data storage

Development uses PostgreSQL through `DATABASE_URL`. Tests use SQLite through `.env.test`. The committed migration uses PostgreSQL syntax, so it cannot build the SQLite test schema. See `SETUP.md`.

## Authentication and redirects

`config/packages/security.yaml` uses `form_login` on the `main` firewall. The login form posts to `app_login` with CSRF protection. `VerifiedUserChecker` requires `User::isVerified()` to be true, so unverified accounts cannot log in.

Successful registration, email verification, login, and password change redirect to `app_index`. Logout also redirects to `app_index`. Password changes on the profile page redirect to `app_profile`.

`ProfileController` uses `#[IsGranted('ROLE_USER')]`, so anonymous visitors are sent to the login page. The `access_control` list guards `/admin` with `ROLE_ADMIN`, and the admin controllers repeat the check with `#[IsGranted('ROLE_ADMIN')]`. Admins hold `['ROLE_ADMIN']` in the roles field; `ROLE_USER` comes from the entity for everyone, so no role hierarchy is configured. The navbar shows the admin link only to admins.
