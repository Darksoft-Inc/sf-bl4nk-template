# Setup

This page describes the local setup. Run Symfony commands through `symfony console` when the Docker services are used. The CLI exposes the container environment variables to the commands.

## Requirements

- PHP 8.4 or newer
- Composer
- Symfony CLI
- Docker and Docker Compose for PostgreSQL and Mailpit

## Install the application

```bash
composer install
symfony console importmap:install
symfony console sass:build
```

The Sass build is required. Generated CSS lives in `var/sass/` and is not committed. Without the build, pages that import `templates/base.html.twig` return HTTP 500 with a message to run `sass:build`.

Run the Sass build again after changing `assets/styles/app.scss` or imported styles.

## Start the services and web server

```bash
docker compose up -d
symfony serve -d
```

`compose.override.yaml` publishes Mailpit ports without fixed host values. Use `docker compose ps` to see the assigned ports.

## Create the development database

```bash
symfony console doctrine:database:create
symfony console doctrine:migrations:migrate
```

The development database uses PostgreSQL through `DATABASE_URL` in `.env`.

## Create the test database

```bash
php bin/console doctrine:schema:create --env=test
```

If the local test schema is stale, recreate it:

```bash
php bin/console doctrine:schema:drop --force --env=test
php bin/console doctrine:schema:create --env=test
```

Do not use `doctrine:migrations:migrate --env=test` with the current test database. The test database uses SQLite, and the committed migration contains PostgreSQL specific SQL.

## Verify the setup

```bash
symfony console about
symfony console debug:router
php bin/phpunit
```

A fresh checkout without `sass:build` or the test schema fails during the browser tests. See `TESTING.md` for the exact errors.

## Local configuration

Put local overrides in `.env.local` or `.env.test.local`. These files are ignored by git. Do not add real secrets to `.env`.
