# Домен Content Admin

Дата актуализации: 2026-04-20

## Назначение системы

Content Admin — это кабинет операторов контента, в котором администраторы управляют:

- аккаунтами публикации на разных платформах;
- правилами и расписанием публикации;
- очередью конкретных постов;
- ручной модерацией;
- ручной и автоматической публикацией;
- историей доставки и audit trail.

## Основные термины

### Platform

Тип платформы публикации.

Примеры:

- `telegram`
- `vk`
- `x`

Platform определяет:

- `driver`
- доступность платформы;
- набор platform-specific настроек на уровне аккаунта.

### PlatformAccount

Конкретный аккаунт, канал, группа или профиль, куда публикуется контент.

Примеры:

- Telegram channel
- VK community
- X account

Это центральная доменная сущность системы.

У `PlatformAccount` есть:

- платформа;
- операторы;
- настройки публикации;
- опциональная привязка Telegram-бота для прямой публикации через Bot API;
- один `PostingPlan`;
- много `PlannedPost`;
- много `PostingHistory`;
- много `AdminAuditLog`.

### PostingPlan

Правила публикации для конкретного `PlatformAccount`.

Содержит:

- `timezone`
- `quiet_hours_from`, `quiet_hours_to`
- `is_active`
- `rules`

`PostingPlan` отвечает за policy-level настройки, а не за конкретный контент.

### PostingSlot

Календарный слот публикации внутри `PostingPlan`.

Содержит:

- день недели;
- локальное время;
- флаг активности.

Используется для preview ближайших публикаций и как основа будущей автоматической генерации/планирования очереди.

### PlannedPost

Конкретный пост в плане/очереди.

Это операционная сущность модерации.

Содержит:

- контент;
- источник контента;
- время публикации;
- статус жизненного цикла;
- moderation status;
- связь замены;
- поля подтверждения/удаления;
- служебные заметки.

### PostingHistory

Append-only журнал попыток публикации.

Каждая запись описывает факт:

- попытки публикации;
- результата;
- provider ID;
- полезной нагрузки;
- ошибки;
- источника запуска;
- idempotency key.

Это не очередь и не план. Это журнал доставки.

### AdminAuditLog

Append-only журнал действий оператора.

Фиксирует:

- кто сделал действие;
- над каким аккаунтом;
- над какой сущностью;
- какое было действие;
- состояние до и после.

## Роли доступа

### Owner

Может:

- управлять аккаунтом;
- менять settings;
- менять план;
- управлять администраторами;
- модерировать и публиковать.

### Admin

Может:

- смотреть аккаунт;
- менять план;
- модерировать очередь;
- публиковать;
- смотреть историю и аудит.

Не может:

- управлять администраторами аккаунта;
- управлять owner-only настройками аккаунта.

### Viewer

Может:

- только смотреть доступные данные.

Не может:

- менять план;
- модерировать;
- публиковать;
- менять администраторов.

## Доменные отношения

```text
Platform
  -> hasMany PlatformAccount

PlatformAccount
  -> belongsTo Platform
  -> belongsToMany User (with role)
  -> hasOne PostingPlan
  -> hasMany PlannedPost
  -> hasMany PostingHistory
  -> hasMany AdminAuditLog

PostingPlan
  -> belongsTo PlatformAccount
  -> hasMany PostingSlot

PlannedPost
  -> belongsTo PlatformAccount
  -> belongsTo replacementOf
  -> hasMany replacements
  -> belongsTo approver / creator / updater / deleteConfirmer

PostingHistory
  -> belongsTo PlatformAccount
  -> belongsTo PlannedPost

AdminAuditLog
  -> belongsTo User
  -> belongsTo PlatformAccount
```

## Статусы домена

### PlannedPostStatus

- `draft`
- `scheduled`
- `publishing`
- `published`
- `failed`
- `cancelled`
- `replaced`

### ModerationStatus

- `pending_review`
- `approved`
- `rejected`
- `needs_replacement`
- `delete_requested`
- `delete_confirmed`

### PostingHistoryStatus

- `queued`
- `sent`
- `failed`
- `skipped`
- `cancelled`

## Ключевые доменные правила

### 1. План и очередь — не одно и то же

- `PostingPlan` отвечает за правила;
- `PlannedPost` отвечает за конкретный экземпляр публикации.

### 2. История публикаций append-only

`PostingHistory` нельзя использовать как mutable-состояние поста. Это журнал событий доставки.

### 3. Аудит действий append-only

`AdminAuditLog` нужен для восстановления цепочки действий оператора. Изменения не должны уничтожать след действий.

### 4. Замена не должна перетирать исходный пост

Замена реализуется как:

- исходный `PlannedPost` получает статус `replaced`;
- создаётся новый `PlannedPost` со ссылкой `replace_of_id`.

### 5. Удаление должно быть подтверждаемым

Удаление операционного поста не должно быть “мгновенным без следа”.

Нормальный поток:

- `requestDelete`
- `confirmDelete`

### 6. Публиковать можно только допустимый пост

Для публикации должны выполняться guard conditions:

- аккаунт платформы активен;
- пост связан с аккаунтом;
- moderation status позволяет публикацию;
- статус поста не финальный (`published`, `cancelled`, `replaced`).

### 7. Telegram допускает direct и legacy publish path

- legacy path использует `settings.channel_key`, runtime-конфиг и внешний Python adapter;
- direct path использует подключённый bot token и `settings.target_chat_id`.

## Текущие workflow сценарии

### Moderation workflow

- создать `PlannedPost`
- `approve`
- `reject`
- `requestDelete`
- `confirmDelete`
- `replace`
- `reschedule`

### Publishing workflow

- dry-run
- publish now
- retry failed
- запись результата в `PostingHistory`

## Точки будущего расширения

### Автоматическая публикация

Сейчас ручная публикация уже существует, но scheduler/job контур ещё не завершён.

### Новые платформы

Сейчас Telegram интеграция частично реализована, `VK` и `X` пока только заявлены доменно.

### Универсальный content domain

Сейчас `PlannedPost` уже умеет хранить `source_type` и `source_id`, что даёт задел для нескольких источников контента.

## Что считать инвариантами

- каждый `PlatformAccount` имеет один `PostingPlan`;
- доступ к аккаунту определяется связью пользователя с аккаунтом;
- `owner/admin/viewer` — это роль в контексте аккаунта, а не глобальная роль системы;
- публикация и аудит должны быть трассируемыми;
- platform-specific настройки не должны разрушать общий домен аккаунта публикации.
