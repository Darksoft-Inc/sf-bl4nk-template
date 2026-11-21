# Project documentation

These files hold project-specific notes for developers and agents. `AGENTS.md` remains the general rulebook for this repository. Do not duplicate its rules here.

## Start here

- `PURPOSE.md`: project scope and the reference for package decisions.
- `SETUP.md`: install the app from a fresh checkout.
- `TESTING.md`: run tests and understand the current test setup.
- `CONFIGURATION.md`: environment variables, secrets, and deployment values.
- `ARCHITECTURE.md`: routes, controllers, entities, forms, and current flows.
- `AGENT_WORKFLOW.md`: checks and limits for automated work.
- `DECISIONS.md`: current choices and open questions.

## Current status

The app has registration, email verification, login, logout, password reset, and profile pages. The profile page shows account details and changes the password. The announcement blog shows announcements in a list and on a detail page, and the base template renders through Twig components with locked icons. The documentation route serves pages from per-locale templates, with a welcome page in English. An admin section behind `ROLE_ADMIN` manages users and announcements with paginated lists. The other target features from `PURPOSE.md` are not built yet. The landing page is a neutral start page.

`TODO.md` at the repository root lists the work found during the state review. Pick items from that file.
