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

The app has registration, email verification, login, logout, password reset, and profile pages. Every login and every restore of a persistent session lands in the login audit with the IP, and the profile page shows the last connection metadata. The profile page changes the password and uploads an avatar. The announcement blog shows announcements in a list and on a detail page with an autocomplete search, and the base template renders through Twig components with locked icons. The documentation route serves pages from per-locale templates, with a welcome page in English. The annotated maps display publicly at `/maps` with pins, symbols, and filtered routes. An admin section behind `ROLE_ADMIN` manages users, announcements, and maps with paginated lists and autocomplete searches, and its maintenance pages list every upload, search the login audit, and run the audit cleanup by hand. The daily cleanup runs through the scheduler and records every run. The dataset import loads YAML datasets in development and production through `app:dataset:load`, keyed by a stored slug, with a development seed and a git-ignored production directory. A members-only section at `/shared` lets every member upload an image or a plain-text file, lists the files newest first, and only the uploader removes one. The generated reference section at `/reference` builds its pages from metadata declared on services, collected and validated at build time: two starter pages document the login audit retention and the shared file rules. Every target feature from `PURPOSE.md` is built; the remaining items in `TODO.md` are the production values, the stable Symfony release, and the CI choice. The landing page is a neutral start page.

`TODO.md` at the repository root lists the work found during the state review. Pick items from that file.
