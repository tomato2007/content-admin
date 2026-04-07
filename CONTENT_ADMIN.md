# Content Admin (Laravel 10) — план реализации

Цель: отдельный локальный веб‑сервис (Laravel 10) для модерации и управления пулом контента (сейчас `telegram_jokes`) и публикациями в разные таргеты (Telegram сейчас, X/VK позже).

Сервис должен слушать **только 127.0.0.1** на сервере и открываться через существующий SSH tunnel, аналогично OpenClaw gateway.

---

## 0) Контекст текущей БД (уже есть)

### Основные таблицы
- `telegram_jokes` — общий пул контента.
  - `raw_text` — сырой текст.
  - `source_chat`, `source_message_id` — ссылка на источник.
  - флаги: `used`, `excluded_from_generation`.
- `telegram_post_queue` — что запланировано/публиковалось для конкретного канала.
  - важно: `post_text`, `queue_status`, `scheduled_at`, `posted_at`.
- `telegram_post_log` — факт отправки в Telegram.
  - `provider_message_id`, timestamp, результат/ошибка.
- `telegram_media` — медиа, привязанные к исходным сообщениям.

### Важная оговорка про мультиканальность
Флаг `used` в `telegram_jokes` — legacy‑глобальный. Для будущих X/VK лучше хранить “опубликовано/исключено” **по таргету**, не только глобально.

---

## 1) Архитектура сервиса

### Deployment / доступ
- Запуск только на loopback:
  - dev: `php artisan serve --host=127.0.0.1 --port=18880`
  - prod: nginx/caddy → php-fpm, bind только на 127.0.0.1.
- Доступ с локального ПК: SSH port forward (по аналогии с gateway).

### Auth
MVP: обязательно включить авторизацию.
- Быстрый вариант: стандартный Laravel auth (Breeze/Jetstream) + один админ‑пользователь.
- Альтернатива: basic auth на уровне reverse proxy (nginx/caddy) + всё равно Laravel middleware для защиты.

---

## 2) Рекомендуемый стек внутри Laravel

### Админка
Самый быстрый путь: **Filament v3** (Laravel 10) — ресурсы CRUD, фильтры, actions, relation managers.

Почему Filament:
- быстро сделать таблицы/фильтры/детальные страницы;
- удобно добавить кастомные actions (Exclude, Schedule, Publish now);
- минимум фронтенда.

---

## 3) MVP функциональность (итерация 1)

### 3.1. Экран: Content Pool (таблица `telegram_jokes`)
Показываем:
- id
- `raw_text` (обрезка + просмотр полностью)
- `source_chat`, `source_message_id`
- флаги `excluded_from_generation`, `used`
- признак media (есть ли связанное `telegram_media`)

Фильтры:
- не исключённые (`excluded_from_generation=false`)
- не used (`used=false`) — опционально
- with_media / without_media
- поиск по подстроке
- фильтр по источнику (`source_chat`)

Actions:
- **Exclude globally** → `telegram_jokes.excluded_from_generation=true`
- **Unexclude** → false
- **Mark used** / **Unmark used** (если надо)
- **Schedule to Telegram target** → создаём запись в `telegram_post_queue`
- **Publish now to Telegram** → триггерим публикацию (см. §6)

Важно: "Schedule" и "Publish now" должны (по возможности) сохранять ссылку на исходный joke, чтобы потом не матчить по `post_text`.

### 3.2. Экран: Telegram Queue (`telegram_post_queue`)
- список будущих scheduled
- список последних опубликованных

Действия:
- отменить/удалить (мягко, если в таблице есть статусы)
- перенести `scheduled_at`

### 3.3. Экран: Telegram Deliveries (`telegram_post_log`)
- последние N отправок
- фильтр по статусу (ok/error)
- просмотр ошибки

---

## 4) Задел под мультиканальность (итерация 2+)

Добавляем универсальные сущности “таргеты” и per-target state.

### 4.1. Новые таблицы (миграции Laravel)

#### `targets`
- `id`
- `platform` ENUM: `telegram`, `x`, `vk`
- `identifier` (например `@anecdots_mems`, `vk:club123`, `x:@handle`)
- `account` (алиас учётки, например `publisher`)
- `enabled` boolean
- timestamps

#### `content_target_state`
Хранит состояние конкретного контента относительно конкретного таргета.
- `id`
- `target_id` FK
- `content_type` (строка; пока `telegram_joke`)
- `content_id` (int; id из `telegram_jokes`)
- `status` ENUM: `eligible`, `excluded`, `scheduled`, `published`, `failed`
- `reason` text nullable
- timestamps

Индекс уникальности:
- (`target_id`, `content_type`, `content_id`)

#### (опционально) `deliveries_unified`
Если захочется унифицировать логи для TG/X/VK.

### 4.2. Семантика
- `telegram_jokes.excluded_from_generation` — глобальный бан (не использовать нигде).
- `content_target_state.status=excluded` — исключить только для конкретного таргета.

---

## 5) Связка очереди с `telegram_jokes` (важно для качества админки)

Рекомендуется добавить явную ссылку:
- вариант A (проще): добавить `telegram_post_queue.joke_id` (nullable FK → `telegram_jokes.id`).
- вариант B: таблица-связка `telegram_queue_items(queue_id, joke_id)`.

Для MVP допустимо начать без этого, но тогда:
- история публикаций будет опираться на `post_text` (ненадёжно);
- сложнее показывать медиа/источник.

---

## 6) Публикация (как дергать текущий код)

Сейчас публикация живёт в python-скриптах (`scripts/publish_due_post.py`) и в правилах канала (text-card, fallback to text, чистка хвостов и т.д.).

### MVP подход (быстрый, минимальные риски)
1) Добавить в python слой режим "publish конкретный joke_id" (или "publish конкретный текст").
2) В Laravel сделать Artisan Command, который вызывает python через `Symfony\Component\Process\Process`.
3) Логировать результат в `telegram_post_log` (если скрипт уже это делает — просто показывать).

Нужно предусмотреть:
- таймаут;
- вывод stdout/stderr в отдельный лог;
- блокировку от двойного клика (idempotency key) — хотя бы на уровне БД/lock.

### Dry-run
Отдельная команда: показать, что будет опубликовано (card vs text fallback) и почему.

---

## 7) План работ по шагам

### Шаг 1 — каркас
- [ ] Создать каталог `content-admin/`.
- [ ] Инициализировать Laravel 10 проект (composer create-project).
- [ ] Подключить Postgres env.
- [ ] Поднять локально на 127.0.0.1.

### Шаг 2 — админка
- [ ] Поставить Filament.
- [ ] Сгенерировать админ-пользователя.

### Шаг 3 — ресурсы по существующим таблицам
- [ ] `TelegramJokeResource` с фильтрами и actions.
- [ ] `TelegramPostQueueResource`.
- [ ] `TelegramPostLogResource`.

### Шаг 4 — действия Schedule/Publish
- [ ] Action “Schedule” создаёт запись в `telegram_post_queue`.
- [ ] Action “Publish now” вызывает Artisan Command → python publish.

### Шаг 5 — мультиканальный задел
- [ ] миграции `targets`, `content_target_state`.
- [ ] создать target: Telegram @anecdots_mems (enabled=true), X/VK (enabled=false).

---

## 8) Принципы, чтобы проект не развалился
- Всё “платформенное” (Telegram/X/VK) держать за интерфейсом Publisher/Adapter.
- Состояние “published/excluded” — по таргету.
- Логи доставок — обязательны: без них операторка бессмысленна.
- Безопасность: loopback bind + auth.

---

## 9) Вопросы, которые нужно закрыть до кодинга действий
1) Есть ли в `telegram_jokes` стабильный первичный ключ `id` (int/uuid)?
2) `used` — это “опубликовано в @anecdots_mems” или “использовано где-то вообще”?
3) Как лучше привязать очередь к joke: добавляем `telegram_post_queue.joke_id` (предпочтительно) или делаем отдельную связку?

---

## 10) Полная «карта зависимостей» (что нужно сервису)

### 10.1. Postgres
Laravel будет читать/писать в существующий Postgres.

Минимально нужны доступы (через `.env` сервиса):
- `DB_CONNECTION=pgsql`
- `DB_HOST=127.0.0.1`
- `DB_PORT=5432`
- `DB_DATABASE=...`
- `DB_USERNAME=...`
- `DB_PASSWORD=...`

Важно: текущие python-скрипты автопостинга используют env `PGHOST/PGPORT/PGDATABASE/PGUSER/PGPASSWORD` (см. `scripts/publish_due_post.py`).
Чтобы не плодить разные источники правды, лучше:
- в Laravel хранить DB креды стандартно (DB_*),
- а при вызове python-процессов пробрасывать также `PG*` переменные из конфигурации.

### 10.2. Telegram publisher token
Автопостинг сейчас читает токен из файла:
- `/home/serg/.openclaw/secrets/telegram-bot-token-publisher`

Laravel **не должен** читать этот файл напрямую из UI-кода.
Лучше: публикацию делать через существующий python слой (который уже знает, где токен), либо через отдельный internal service/command с ограниченными правами.

### 10.3. MinIO / медиа
Автопостинг для медиа вызывает `mc cp atlas/<bucket>/<object_key> ...`.
Значит на сервере должно быть:
- установлен `mc` (MinIO client)
- настроен alias `atlas`

Админка на MVP может лишь показывать факт наличия `telegram_media`, не скачивая файлы. Скачивание/превью медиа можно добавить позже.

### 10.4. Fonts / рендер text-card
Рендер карточек использует PIL + системные шрифты:
- `/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf`
- `/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf`

Админке на Laravel можно сделать “Preview” как:
- (MVP) текстовое объяснение: какой режим будет выбран (card vs text) + причина
- (позже) фактический preview-рендер (через отдельный internal endpoint/команду, лучше на Python где уже есть PIL-логика)

---

## 11) Контракты (API/CLI) между Laravel и текущими скриптами

### 11.1. Что есть сейчас
`scripts/publish_due_post.py [channel_key]` делает:
- определяет due-слот
- выбирает случайный joke из `telegram_jokes`
- публикует (media или text-card или plain text)
- пишет в `telegram_post_queue` (если не было строки) и `telegram_post_log`

### 11.2. Что нужно для админки (ручные действия)
Нужно добавить **детерминированные операции**, чтобы админка могла:

1) **Schedule конкретного joke** в `telegram_post_queue`
- Laravel напрямую вставляет строку в `telegram_post_queue` (простая операция)

2) **Publish now конкретного joke** (в обход random choice)
- Рекомендуется добавить новый скрипт или режим:
  - `scripts/publish_specific_joke.py <channel_key> <joke_id> [--force]`
  - или расширить `publish_due_post.py` аргументами:
    - `publish_due_post.py <channel_key> --joke-id <id> [--force]`

Требования к publish-now:
- уважать `quiet_hours` по умолчанию, но иметь флаг `--force` для ручной публикации
- делать те же cleanup/eligibility проверки (или хотя бы cleanup)
- писать `telegram_post_log` и (при необходимости) ставить/обновлять `telegram_post_queue`
- обеспечивать идемпотентность (см. 11.3)

3) **Dry-run** для joke (что будет: media/card/text)
- режим `--dry-run` возвращает JSON с:
  - cleaned_text
  - eligible=true/false + reason
  - planned_send_mode: media_type|text-card|text
  - required_font_size (если считали)

### 11.3. Идемпотентность и защита от двойного клика
Рекомендуемая схема:
- Laravel при нажатии “Publish now” создаёт запись `deliveries_unified`/локальный `admin_actions_log` со статусом `running` и уникальным ключом
- python при записи в `telegram_post_log` должен проверять, что этот joke не был опубликован в этот `channel_key` ранее (в `publish_due_post.py` уже есть анти‑дубль через `NOT EXISTS` на `telegram_post_log`)
- UI показывает пользователю результат (posted / failed + error)

---

## 12) Laravel структура проекта (рекомендовано)

Внутри `content-admin/`:
- `app/Models/TelegramJoke.php` (таблица `telegram_jokes`)
- `app/Models/TelegramPostQueue.php` (`telegram_post_queue`)
- `app/Models/TelegramPostLog.php` (`telegram_post_log`)
- `app/Models/TelegramMedia.php` (`telegram_media`)

Плюс новые модели для мультиканальности:
- `Target`
- `ContentTargetState`

Для Filament:
- `app/Filament/Resources/TelegramJokeResource`
- `app/Filament/Resources/TelegramPostQueueResource`
- `app/Filament/Resources/TelegramPostLogResource`
- `app/Filament/Resources/TargetResource`

Команды:
- `app/Console/Commands/TelegramPublishSpecificJoke.php`
- `app/Console/Commands/TelegramDryRunJoke.php`

---

## 13) Безопасность и эксплуатация

### 13.1. Сетевой доступ
- bind на 127.0.0.1
- доступ через SSH tunnel

### 13.2. Роли
Минимум: один админ‑пользователь.
Позже: роли “оператор” (без настроек) и “админ”.

### 13.3. Аудит действий
Добавить таблицу `admin_audit_log`:
- кто
- что сделал (exclude/schedule/publish)
- над чем (joke_id, target)
- когда
- результат

---

## 14) План реализации после подтверждения

После твоего подтверждения начнём реализацию в репозитории:
1) Создать Laravel 10 проект в `content-admin/`.
2) Настроить Filament и доступ к Postgres.
3) Поднять 3 ресурса (jokes/queue/log) в read-only режиме.
4) Добавить actions Exclude/Schedule.
5) Добавить publish-now через новый python режим (или отдельный скрипт) + Artisan Command.
6) Добавить `targets` + `content_target_state` (задел под X/VK).

