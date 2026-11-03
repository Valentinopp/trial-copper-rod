# Neon Postgres

Project: `dawn-shape-08057312`, organization `org-jolly-glade-65725170`, branch `production`.
`neon.ts` provisions Postgres. Authentication remains Laravel authentication with
the existing employee accounts; no additional Neon services are required.

## Environment and driver

Run Neon commands from the repository root:

```sh
neon login
neon deploy
```

Neon CLI writes `DATABASE_URL`, `DATABASE_URL_UNPOOLED`, and `NEON_BRANCH` into
the root `.env.local` (or root `.env` if present). Both are ignored by Git.
Laravel loads this root environment before `backend/.env`, which still holds
`APP_KEY`, session, mail, and other app settings. Existing process environment
variables take precedence. Test runs skip loading the root environment.

The app uses its existing Laravel Eloquent/PDO PostgreSQL driver, requiring
`pdo_pgsql`. Normal `pgsql` requests use pooled `DATABASE_URL` with TLS.
`pgsql_direct` uses `DATABASE_URL_UNPOOLED` for migrations and imports. Without
Neon variables, the existing `DB_HOST`/`DB_DATABASE`/`DB_URL` configuration still works.

For the pooled connection, PDO uses native parameter binding without named
prepared statements (`Pdo\Pgsql::ATTR_DISABLE_PREPARES`). This supports the local
Herd libpq 16 build and preserves PostgreSQL boolean parameter handling.
See the [PHP driver option](https://www.php.net/manual/en/class.pdo-pgsql.php#pdo-pgsql.constants.attr-disable-prepares)
and [PgBouncer compatibility guidance](https://www.pgbouncer.org/faq.html#how-to-use-prepared-statements-with-transaction-pooling).
Direct connections retain ordinary native prepared statements.

```sh
cd backend
php artisan migrate --database=pgsql_direct --force
```

## Build and start

```sh
npm ci
npm --prefix frontend ci
npm run build
npm start
```

The app runs at `http://127.0.0.1:8000`. PHP must be on PATH or in `PHP_BINARY`.
On Windows the start script also discovers an existing Herd PHP installation.
It uses Laravel's `--no-reload` option so Herd's DLL/environment settings are
preserved in the PHP server subprocess. To use another port:

```sh
npm start -- --port=8123
```

This starts the local development web server. Deploying Postgres to Neon does
not publish a PHP website; hosting Laravel publicly is a separate deployment.

## Existing master data

The source export is stored privately under `backend/storage/app/private/backups/`.
It includes categories, units, Copper Rod references, products, partners, BoMs,
personnel masters, access profiles, and employee accounts with their existing
password hashes. No operational SO, schedule, stock, or LOT records are imported.

The guarded first-time importer keeps IDs, relationships, timestamps, and sequence
counters, and verifies every imported row inside one transaction:

```sh
php scripts/import-neon-masters.php /absolute/path/to/private-master-snapshot.json
```

It only accepts a fresh Neon database containing migration defaults. It refuses
to overwrite an existing user/product/transaction database. Test migrations and
the import on a temporary Neon branch before applying them to production.

## HTTP verification

With `npm start` running, set `SMOKE_USERNAME` and `SMOKE_PASSWORD` to an existing
operator account and run:

```sh
node scripts/neon-smoke.mjs
```

The smoke check logs in over HTTP, creates and finishes a temporary LOT through
Livewire, checks all four Copper Rod pages, verifies the monthly actual export,
then deletes only the LOT it created. Run on an empty trial database: the report
assertion expects the test LOT to be the only production result in that month.
Never run `migrate:fresh` or the regular destructive test suite against Neon production.

Initial setup was verified with 28 schema migrations and 103 master/account rows
across 13 tables. The HTTP check confirmed operator login, LOT create/update,
four pages returning HTTP 200, and net production 120 kg appearing as 0.12 MT in
the monthly Excel payload. The smoke LOT and the temporary migration branch were
removed afterward; Copper Rod transaction tables remained empty.
