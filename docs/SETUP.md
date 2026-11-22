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
- **Queries**: `symfony console doctrine:query:dql "SELECT e.id FROM App\Entity\Name e"` runs DQL against the dev database, and `php bin/console doctrine:query:dql --env=test ...` against the test database. The app registers no `doctrine:query:sql` command: the console offers DQL instead.
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

## Load the datasets

```bash
symfony console app:dataset:load
```

The command loads `datasets/shared/` plus `datasets/dev/` in one transaction: an admin and a member account with the passwords documented in `datasets/dev/users.yaml`, one announcement, and one demo map with a point, a route type, and a route. Records are keyed by dataset slug: the command inserts new slugs and stops before any write when a slug already exists, so after an edit to the files apply the change with `--update`, and rebuild a disposable database from empty tables with `--clear` (interactive confirmation, or `--force`; refused in prod). Rows created outside the import are never touched by either mode. A subset of a dataset loads on its own when its references name rows of an earlier load, which is the practical way to update a few records of a large set. Large loads write in batches of 100 records between two flushes; `--batch-size=N` bounds memory further or speeds the load up. The rules and the format are in `docs/DECISIONS.md`.

For production, create the records in `datasets/prod/` on the server, which git ignores, and load them with `symfony console app:dataset:load --env=prod`, then with `--env=prod --update` after every edit. The workflow, including the first admin account and removal entries, is in `datasets/prod/README.md`.

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

Also keep the test commands on plain `php`. Running them through the Symfony CLI while the Docker services are up injects the container `DATABASE_URL`, which wins over the SQLite URL in `.env.test`: the `dbname_suffix` `_test` then targets the `app_test` database of the container, which no setup step creates, and the run fails on connect. The development database is never reached that way. `TESTING.md` names the failure messages.

## Verify the setup

```bash
symfony console about
symfony console debug:router
php bin/phpunit
```

A fresh checkout without `sass:build` or the test schema fails during the browser tests. See `TESTING.md` for the exact errors.

## Local configuration

Put local overrides in `.env.local` or `.env.test.local`. These files are ignored by git. Do not add real secrets to `.env`.
