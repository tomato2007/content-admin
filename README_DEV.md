# content-admin (dev)

## Run (Docker dev container)

This repo is intended to be developed against a local PostgreSQL database.
Docker is optional, but the committed Docker setup already assumes PHP 8.3 and PostgreSQL.

## Local prerequisites

- PHP `8.3+`
- PostgreSQL `16+` recommended
- Composer
- Optional: Docker / Docker Compose if you want to run the app inside the provided container

## 1) Prepare local environment

Create `.env` from `.env.example` and set a local PostgreSQL database:

```bash
cp .env.example .env
php artisan key:generate
```

Expected DB settings for local development:

```dotenv
DATABASE_URL=
PGHOST=127.0.0.1
PGPORT=5432
PGDATABASE=content_admin
PGUSER=content_admin
PGPASSWORD=
PGSSLMODE=disable
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=content_admin
DB_USERNAME=content_admin
DB_PASSWORD=
DB_SCHEMA=public
DB_SSLMODE=disable
TELEGRAM_PUBLISHER_DRIVER=null
TELEGRAM_RUNTIME_CONNECTION=telegram_runtime
TELEGRAM_CHANNEL_CONFIG_TABLE=telegram_channel_configs
TELEGRAM_PUBLISH_SCRIPT_PATH=
```

The project now accepts PostgreSQL settings in two compatible styles:

- Laravel-native `DB_*`
- production-style `DATABASE_URL` or `PG*`

`TELEGRAM_PUBLISHER_DRIVER=null` is the safe default for local development.

If you want to exercise the real Telegram adapter locally, switch the driver and point it to the Python script explicitly:

```dotenv
TELEGRAM_PUBLISHER_DRIVER=real
TELEGRAM_PUBLISH_SCRIPT_PATH=/absolute/path/to/publish_planned_telegram_post.py
```

The app now resolves both the Telegram runtime connection and the channel config table from config instead of hardcoded machine-specific values.

If you also need to mirror the production content-source topology from [docs/prod-db.md](docs/prod-db.md), configure the external `posts_source` connection:

```dotenv
POSTS_SOURCE_CONNECTION=posts_source
POSTS_SOURCE_DATABASE_URL=
POSTS_SOURCE_DB_HOST=127.0.0.1
POSTS_SOURCE_DB_PORT=5432
POSTS_SOURCE_DB_DATABASE=anecdots
POSTS_SOURCE_DB_USERNAME=content_admin
POSTS_SOURCE_DB_PASSWORD=
POSTS_SOURCE_DB_SCHEMA=public
POSTS_SOURCE_DB_SSLMODE=disable
POSTS_SOURCE_QUALIFIED_TABLE=public.posts
```

Important: the project now exposes these settings in config, but automatic ingestion from `public.posts` is still a separate implementation task. The current UI still creates `PlannedPost` records manually.

## 2) Install dependencies

Host machine:

```bash
composer install
npm install
```

Optional Docker workflow:

```bash
docker compose up -d
docker compose exec app composer install
```

## 3) Prepare database

```bash
php artisan migrate
php artisan db:seed
```

## 4) Run the app

Host machine:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Optional Docker workflow:

```bash
docker compose up -d
```

Admin panel URL:

```text
http://127.0.0.1:8000/admin
```

## Local admin bootstrap

When `APP_ENV=local`, `php artisan db:seed` also creates a local Filament user.

Default credentials:

```text
Email: admin@local.test
Password: admin12345
```

You can override them through `.env`:

```dotenv
LOCAL_DEV_ADMIN_NAME="Local Admin"
LOCAL_DEV_ADMIN_EMAIL=admin@local.test
LOCAL_DEV_ADMIN_PASSWORD=admin12345
```

Current local bootstrap now provides:

- Base platforms: `telegram`, `vk`, `x`
- A reproducible local admin login for Filament
- A demo Telegram platform account owned by the local admin
- An active posting plan with three seeded posting slots
- Seeded queue examples in `pending_review`, `approved`, `failed`, and `published` states
- Seeded posting history entries so the delivery/history screens are not empty

## Database

- The application is developed against PostgreSQL, not MySQL
- `config/database.php` now defaults to `pgsql`, not `mysql`
- The app DB accepts `DATABASE_URL` and `PG*` fallback variables, in addition to `DB_*`
- `docker-compose.yml` uses `network_mode: host`, so `DB_HOST=127.0.0.1` points to the host PostgreSQL service from inside the container
- `telegram_runtime` is the legacy Telegram runtime/config connection
- `posts_source` is the production-style content-source connection intended for `anecdots.public.posts`
- Keep dev and test databases isolated from any production-like environment
