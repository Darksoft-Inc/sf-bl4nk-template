```text
########    ##          ##    ##    ##      ##  ##      ##  
########    ##          ##    ##    ##      ##  ##      ##  
##      ##  ##          ##    ##    ####    ##  ##    ##    
##      ##  ##          ##    ##    ####    ##  ##    ##    
########    ##          ##########  ##  ##  ##  ######      
########    ##          ##########  ##  ##  ##  ######      
##      ##  ##                ##    ##    ####  ##    ##    
##      ##  ##                ##    ##    ####  ##    ##    
########    ##########        ##    ##      ##  ##      ##  
########    ##########        ##    ##      ##  ##      ##  
---------------------------------------------------------------
```

A Symfony application baseline for public-facing services: MMO games, collaborative tools, and sandboxes. It holds the features that several applications share, so each new application starts from the same base and adds its own domain rules. It is a working base, not a finished product.

The baseline is Symfony 8.2 (currently installed as a beta; a stable release is required before production use) on PHP 8.4 or newer, with Bootstrap 5.3, Symfony UX, Asset Mapper, and Stimulus. `docs/PURPOSE.md` records the scope and the rules for package decisions.

## Features

- Account management: registration with email verification, login, logout, password reset, and a profile page with password change and avatar upload. Every login and every restore of a persistent session lands in a login audit with the IP.
- Announcement blog: a public list and a detail page with an autocomplete search, the content renders through markdown (`app_markdown`), and the admin form is a plain textarea with syntax help.
- Documentation pages: stable links rendered from per-locale templates.
- Annotated 2D maps: a public display with pins, symbols, and filtered routes, and an admin section for maps, points, route types, and routes.
- Admin section behind `ROLE_ADMIN`: users, announcements, maps, the login audit, uploads, and maintenance, with paginated lists and autocomplete searches.
- Scheduled maintenance: recurring tasks through the Symfony scheduler, recorded runs, and manual triggers from the admin pages.
- Flexible dataset import: `app:dataset:load` reads YAML datasets keyed by a stored slug, in development and production.
- Shared files: a members-only section where every member shares an image or a plain-text file, the list paginates newest first, and only the uploader removes a file.
- Generated reference pages: `/reference` renders pages declared on services through the `Reference` attribute, validated at build time, with values that come from class constants and container parameters.
- Interface: Bootstrap 5.3 compiled from Sass, Twig components, locked icons, and translations.

Every target feature from `docs/PURPOSE.md` is built. `TODO.md` at the repository root tracks the remaining work, such as the production values and the stable Symfony release.

## Requirements

- PHP 8.4 or newer
- Composer
- Symfony CLI
- Docker and Docker Compose for PostgreSQL and Mailpit

## Quick start

```bash
composer install
symfony console importmap:install
symfony console sass:build
docker compose up -d
symfony serve -d
symfony console doctrine:database:create
symfony console doctrine:migrations:migrate
symfony console app:dataset:load
```

The Sass build is required: pages return HTTP 500 until it runs. The dataset load creates an admin and a member account; the passwords are documented in `datasets/dev/users.yaml`.

`docs/SETUP.md` details every step, including the database and mail tools, the production dataset workflow, and the scheduled task worker.

## Tests

```bash
php bin/console doctrine:schema:create --env=test
php bin/phpunit
```

Both commands run on plain PHP, never through the Symfony CLI: with the Docker services up, the CLI injects the container `DATABASE_URL`, which breaks the SQLite test connection. `docs/TESTING.md` explains the runners, the recorded HTTP exchanges, and the quality checks. GitHub Actions runs the same chain on every push and pull request through `.github/workflows/ci.yml`.

## Documentation

`docs/` holds the project documentation, indexed in `docs/README.md`:

- `docs/PURPOSE.md`: scope and package decisions.
- `docs/SETUP.md`: installation and the development tools.
- `docs/TESTING.md`: tests and quality checks.
- `docs/CONFIGURATION.md`: environment variables and deployment values.
- `docs/ARCHITECTURE.md`: routes, controllers, entities, and services.
- `docs/DECISIONS.md`: current choices and open questions.

## Status

[![CI](https://github.com/Darksoft-Inc/sf-bl4nk-template/actions/workflows/ci.yml/badge.svg)](https://github.com/Darksoft-Inc/sf-bl4nk-template/actions/workflows/ci.yml) [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4.svg)](https://www.php.net/)

## License

MIT. See `LICENSE`.
