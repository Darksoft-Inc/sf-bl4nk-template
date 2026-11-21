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

`compose.override.yaml` publishes the PostgreSQL and Mailpit ports without fixed host values. Use `docker compose ps` to see the assigned ports, or `symfony open:local:webmail` to open the mail interface directly.

## Development tools

- **Database**: Adminer runs at `http://localhost:8080` when the containers are up. The login form already proposes the `database` host. Sign in with the `app` user, the password from `POSTGRES_PASSWORD` (`!ChangeMe!` by default), and the `app` database.
- **Emails**: Mailpit catches the mail sent through `symfony serve`, because the CLI injects `MAILER_DSN` for the running containers. Open the interface with `symfony open:local:webmail`.
- **Styles**: `symfony serve` starts the Sass watch worker from `.symfony.local.yaml`. Save an `.scss` file and the worker rebuilds `var/sass`; reload the page to see the change. Development serves assets without a build cache.
- **Scripts**: JavaScript has no build step. Save the file and reload the page.
- **Automatic page updates**: `templates/base.html.twig` links the FrankenPHP hot reload client, which activates with the `FRANKENPHP_HOT_RELOAD` environment variable. That path needs the app served by FrankenPHP plus the watcher of the frankenphp-hot-reload package. The plain `symfony serve` runtime does not provide it.

## Create the development database

```bash
symfony console doctrine:database:create
symfony console doctrine:migrations:migrate
```

The development database uses PostgreSQL through `DATABASE_URL` in `.env`.

## Run the scheduled tasks

The scheduler ships its tasks as the `scheduler_default` messenger transport. One worker runs every scheduled task, including the daily login audit cleanup:

```bash
symfony console messenger:consume scheduler_default
```

The worker stays in the foreground and checks the schedule every few seconds. In production it belongs in a supervised process list, like any messenger worker. The maintenance page `/admin/maintenance/cleanup` runs the cleanup by hand and lists the recorded runs, so the schedule stays verifiable without a worker.

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

Also keep the test commands on plain `php`. Running them through `symfony console` while the Docker services are up injects the dev `DATABASE_URL`, which wins over the SQLite URL in `.env.test` and connects the test environment to the development database.

## Verify the setup

```bash
symfony console about
symfony console debug:router
php bin/phpunit
```

A fresh checkout without `sass:build` or the test schema fails during the browser tests. See `TESTING.md` for the exact errors.

## Local configuration

Put local overrides in `.env.local` or `.env.test.local`. These files are ignored by git. Do not add real secrets to `.env`.
