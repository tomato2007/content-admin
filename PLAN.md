# Content Admin — план реализации админ‑панели

Дата: 2026-04-08

## Цель
Сделать админ‑панель (Filament) для управления постингом, где:
- пользователь видит **только доступное ему**;
- один пользователь может управлять **несколькими платформами**;
- у одной платформы может быть **несколько админов**;
- для **каждой платформы** ведётся свой **план постинга** и своя **история постинга**.

> Под «платформой» понимаем любую поддерживаемую соцсеть/канал (Telegram/…); важно отделять *тип платформы* от *конкретного аккаунта/объекта публикации*.

---

## Термины (предлагаемая модель данных)
1) **Platform** — *тип* платформы/сети (например: `telegram`, `vk`, `tiktok`).
2) **PlatformAccount** — конкретный управляемый объект в этой платформе (канал/группа/аккаунт). Именно для него задаются план и история.
3) **PlatformAdmin (pivot)** — связь many-to-many: какие пользователи администрируют какие PlatformAccount.
4) **PostingPlan** — настройки/правила постинга для конкретного PlatformAccount.
5) **PostingHistory** — журнал попыток/фактов публикаций для конкретного PlatformAccount.

---

## Данные и миграции (Laravel)

### 1) platforms
Справочник типов:
- `id`
- `key` (string уникальный: `telegram`, `vk` …)
- `name` (читаемое имя)
- `is_enabled` (bool)

### 2) platform_accounts
Конкретные аккаунты/каналы, которыми управляем:
- `id`
- `platform_id` (FK)
- `external_id` (string, уникальный в рамках платформы; например channel_id)
- `title` (string)
- `is_enabled` (bool)
- `settings` (json, опционально: специфичные параметры платформы)
- timestamps

Индексы:
- unique(`platform_id`, `external_id`)

### 3) platform_account_user (pivot)
Связь админов и платформенных аккаунтов:
- `platform_account_id`
- `user_id`
- `role` (string, опционально: `owner|admin|viewer`)
- timestamps (опционально)

Индексы:
- unique(`platform_account_id`, `user_id`)

### 4) posting_plans
План постинга для PlatformAccount:
- `id`
- `platform_account_id` (FK)
- `timezone` (string, default `UTC`)
- `quiet_hours_from` / `quiet_hours_to` (time, nullable)
- `rules` (json) — расширяемое поле правил
  - пример: `{ "posts_per_day": 3, "min_interval_minutes": 120, "days": [1,2,3,4,5] }`
- `is_active` (bool)
- timestamps

Индексы:
- unique(`platform_account_id`) если план строго один на аккаунт

### 5) posting_history
История постинга по PlatformAccount:
- `id`
- `platform_account_id` (FK)
- `status` (enum/string: `queued|sent|failed|skipped`)
- `scheduled_at` (datetime, nullable)
- `sent_at` (datetime, nullable)
- `payload` (json, nullable) — метаданные/краткое содержимое
- `error` (text, nullable)
- timestamps

Индексы:
- (`platform_account_id`, `created_at`)
- (`status`, `created_at`)

---

## Авторизация и доступ (критично)

### Политика доступа (высокоуровнево)
- Пользователь может видеть/редактировать **только** те PlatformAccount, где он присутствует в pivot `platform_account_user`.
- План и история доступны **только через** доступный PlatformAccount.

### Реализация
1) **Policies**
- `PlatformAccountPolicy`: view/update => `auth()->user()->platformAccounts()->whereKey($id)->exists()`
- `PostingPlanPolicy`: проверка через `platform_account_id`.
- `PostingHistoryPolicy`: проверка через `platform_account_id`.

2) **Filament**
- В каждом Resource переопределить `getEloquentQuery()` и ограничивать выборку по текущему пользователю.
- Для действий (view/edit/delete) опираться на Policies.

3) URL и безопасность
- Не использовать маршруты вида `/admin/users/{id}/...` для обычных пользователей.
- Основные страницы строить вокруг PlatformAccount (которыми он админит).
- Даже если ID сущности виден в URL (например `/admin/platform-accounts/15/edit`) — это нормально, пока Policies режут чужие записи (403).

---

## Filament UI (структура)

### Навигация (минимум)

#### Группа меню «Проекты» (динамическая)
Хотим UX как в SaaS: в левом меню раскрывающаяся группа **«Проекты»**, внутри:
- список **конкретных платформенных аккаунтов** (PlatformAccount), доступных текущему пользователю;
- в конце — пункт **«+ Добавить»**.

Реализация в Filament:
- генерировать navigation items динамически в `AdminPanelProvider` (или через `Filament::registerNavigationItems()`), делая запрос только к тем PlatformAccount, которые связаны с `auth()->user()`;
- каждый item ведёт на страницу просмотра конкретного PlatformAccount (например `PlatformAccountResource::getUrl('view', ['record' => $id])` или кастомная page `PlatformAccountViewPage`).

Важно:
- этот список **не должен** показывать чужие проекты; фильтр — через pivot `platform_account_user`;
- даже если кто-то подставит ID в URL вручную — Policies должны вернуть 403.

1) **Platform Accounts** (Resource)
- список доступных пользователю аккаунтов
- поля: Platform, Title, Enabled
- действия: открыть «План», открыть «История»

2) **Posting Plan**
Вариант A (проще): отдельный Resource `PostingPlanResource` с жестким фильтром по доступным PlatformAccount.
Вариант B (лучше UX): страница/страницы внутри PlatformAccount:
- `PlatformAccountViewPage` с табами:
  - Tab: Plan (форма)
  - Tab: History (таблица)
  - Tab: Admins (управление pivot, доступно только `owner`)

3) **Posting History**
- таблица
- фильтры: период, статус
- сортировка по `created_at desc`

### Управление администраторами платформы
- если нужно UI для добавления админов к платформе:
  - делать это через Relation Manager в Filament (belongsToMany)
  - права: только `owner` или отдельная роль.

---

## Технические шаги реализации (по порядку)

1) Миграции + модели:
- `Platform`, `PlatformAccount`, `PostingPlan`, `PostingHistory`
- связи:
  - User <-> PlatformAccount many-to-many
  - PlatformAccount -> Platform (belongsTo)
  - PlatformAccount -> PostingPlan (hasOne)
  - PlatformAccount -> PostingHistory (hasMany)

2) Seed/initial:
- заполнить `platforms` базовыми ключами.

3) Policies:
- написать и зарегистрировать policies.

4) Filament ресурсы/страницы:
- `PlatformAccountResource` (всегда фильтровать по текущему пользователю)
- страницы/RelationManagers для плана/истории/админов

5) Тесты (минимум):
- feature tests на 403 при попытке открыть чужой PlatformAccount/Plan/History.

---

## Примечания по текущему окружению
- Приложение запущено в Docker через `php artisan serve` (dev-only).
- Доступ с локального ПК — через SSH port-forward.

---

## Открытые вопросы (нужно уточнить позже)
1) Нужны ли роли `owner/admin/viewer` или достаточно просто «есть доступ / нет доступа»?
2) План — какие именно параметры нужны (частота, окна, медиа-слоты, рандомизация, источники контента)?
3) История — что фиксируем: только успехи или все попытки (включая ошибки/ретраи/скрины ответа API)?
