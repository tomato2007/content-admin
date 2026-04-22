# Content Admin

Laravel 10 + Filament v3 admin panel for managing platform accounts, posting plans, moderation, and Telegram publishing.

## Key docs

- Dev bootstrap: [README_DEV.md](README_DEV.md)
- Architecture: [docs/architecture.md](docs/architecture.md)
- Domain model: [docs/domain.md](docs/domain.md)
- Production DB requirements: [docs/prod-db.md](docs/prod-db.md)

## Database topology

The project now exposes three PostgreSQL connection roles:

- `pgsql`: main application database for `content_admin`
- `telegram_runtime`: legacy Telegram runtime/config connection
- `posts_source`: external content-source connection intended for `anecdots.public.posts`

PostgreSQL configuration is supported in two compatible forms:

- Laravel-style `DB_*`
- production-style `DATABASE_URL` or `PG*`

See [.env.example](.env.example) for the full variable list.

## Current status

- Manual `PlannedPost` creation, moderation, posting history, and Telegram Bot API manual publish are implemented.
- `posts_source` configuration for `anecdots.public.posts` is now present in project config.
- Automatic ingestion/pick-from-pool from `public.posts` is not implemented yet; [docs/prod-db.md](docs/prod-db.md) remains the source-of-truth spec for that work.

## Run locally

Use [README_DEV.md](README_DEV.md).
