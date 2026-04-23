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
- Generated moderation queue from `posts_source` is implemented: operators can create the next 10 candidate posts from the source pool and review them before approval.
- Generated source posts enter the queue as `draft + pending_review`, then move to `approved + scheduled` after moderation approval when a schedule is present.
- The scheduler only auto-publishes `approved + scheduled` posts, which keeps generated drafts out of automatic publishing until moderation is complete.

## Generated queue workflow

User-facing queue states are:

- `Ожидание модерации` → `moderation_status=pending_review`
- `Подтвержден, ожидает публикации` → `moderation_status=approved` + `status=scheduled`
- `Опубликован` → `status=published`
- `Ошибка публикации` → `status=failed`

Operator flow:

1. Open a platform account queue.
2. Use `Generate next 10 posts` to create candidate queue items from `posts_source`.
3. Review generated items in the moderation queue.
4. Approve items that should be published. If `scheduled_at` is set, approval moves them into `approved + scheduled`.
5. Scheduled publishing picks only approved scheduled posts, so pending-review generated items are never auto-published prematurely.

If new candidates stop appearing, check:

- `posts_source` connection settings
- whether source records already exist in queue states `pending_review` or `approved + scheduled`
- whether source content remains eligible after cleaning/validation
- whether generation is being attempted during source-pool quiet hours

## Run locally

Use [README_DEV.md](README_DEV.md).
