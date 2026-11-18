# Agent workflow

This page holds project-specific notes for agents. `AGENTS.md` holds the general rules for the repository.

## Before starting

1. Pick one item from `TODO.md`.
2. Read the related code and configuration.
3. Read `config/reference.php` before changing bundle options.
4. Check `symfony.lock` before assuming a recipe or package is installed.
5. Keep the change limited to the picked item.

## Local setup checks

A fresh checkout needs these commands before browser tests work:

```bash
symfony console sass:build
php bin/console doctrine:schema:create --env=test
```

See `SETUP.md` and `TESTING.md` for details.

## Verification commands

Run the checks that apply to the change:

```bash
php bin/phpunit
symfony console lint:container
symfony console lint:yaml config/
symfony console lint:twig templates/
vendor/bin/phpstan analyse --no-progress
symfony console doctrine:schema:validate
```

Run `symfony console sass:build` after changing SCSS files.

## Limits

- Do not modify `AGENTS.md` as part of project tasks unless the user asks.
- Do not commit generated files from `var/`, `public/assets/`, or `assets/vendor/`.
- Do not add PHPStan suppressions or a baseline for the current errors.
- Do not use `json_decode()` for request data. Use `MapRequestPayload` or `MapQueryString`.
- Do not assume package configuration from older Symfony versions. Check `config/reference.php` and `config/schema.json`.
- Do not use real secrets in committed files.

## When changing behavior

- Write a failing test first for bug fixes.
- Update `docs/` when commands, configuration, or flows change.
- Mark the matching item in `TODO.md` as done after verification.
- State what changed and what command verified it.
