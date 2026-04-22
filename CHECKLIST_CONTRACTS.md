# Checklist DB Contracts

## Что сейчас настроено для БД

- `[x]` Основное приложение по умолчанию работает через `PostgreSQL`, connection `pgsql`.
- `[x]` Для основного подключения поддержаны оба стиля env:
  `DATABASE_URL`
  и fallback через `DB_*` / `PG*`.
- `[x]` Для основного подключения вынесены `search_path` и `sslmode`:
  `DB_SCHEMA`, `DB_SSLMODE`, `PGSSLMODE`.
- `[x]` Legacy Telegram runtime вынесен в отдельный connection `telegram_runtime` с отдельным URL и env-параметрами.
- `[x]` Добавлен отдельный внешний source connection `posts_source` для продового источника постов.
- `[x]` Для `posts_source` зафиксирован дефолтный контракт:
  БД `anecdots`, schema `public`.
- `[x]` Для source DB вынесен контракт таблицы:
  `public.posts`.
- `[x]` Для source DB зафиксированы имена рабочих колонок:
  `content`, `media_url`, `published_at`.
- `[x]` Для будущего source scheduler уже зафиксированы операционные параметры:
  `max_posts_per_run=1`
  `quiet_hours 00:00-06:00 UTC`.
- `[x]` Все эти переменные уже описаны в `.env.example`.
- `[x]` README dev уже синхронизирован с этим контрактом.
- `[x]` Очереди Laravel тоже переведены на Postgres-default, а не MySQL-default.
- `[x]` Есть тест, который проверяет наличие этих конфиг-контрактов.

## Что пока не настроено на уровне реализации

- `[ ]` Нет `PostsRepository`, который реально читает из `posts_source -> public.posts`.
- `[ ]` Нет транзакционного pick/mark published для source DB.
- `[ ]` Нет миграций/SQL-check для индексов внешней таблицы `public.posts`.
- `[ ]` Нет кода, который уже строит очередь из `public.posts`.

## Что уже есть на уровне внутренних таблиц приложения

- `[x]` Внутренние таблицы `content_admin` уже мигрированы и работают:
  `platform_accounts`, `posting_plans`, `posting_slots`, `planned_posts`, `posting_history`, `admin_audit_logs`.
