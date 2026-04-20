# Content Admin — план реализации админ‑панели

Дата: 2026-04-10

## Цель
Сделать кабинет управления публикацией контента, где:
- пользователь видит только доступные ему проекты;
- один пользователь может администрировать несколько платформенных аккаунтов;
- у одного аккаунта платформы может быть несколько админов;
- для каждого аккаунта есть свои настройки, свой план публикации, своя очередь/модерация и своя история;
- новые платформы и соцсети можно добавлять без переделки всей админки.

> Ключевая идея: строим систему вокруг **конкретного аккаунта публикации** (например Telegram-канал, VK-сообщество, X-аккаунт), а не вокруг абстрактной «соцсети вообще».

---

## Что считаем правильной архитектурой

Нужно разделить 4 разных уровня:

1) **Platform** — тип платформы (`telegram`, `vk`, `x`, `instagram` ...)
2) **PlatformAccount** — конкретный объект публикации внутри платформы (канал/группа/аккаунт)
3) **PostingPlan** — правила и расписание публикаций для этого аккаунта
4) **PlannedPost** — конкретный элемент плана/очереди, который можно модерировать, подтверждать, удалять, заменять и публиковать

Это важное разделение. Если смешать `plan` и `queue/history`, потом будет неудобно реализовывать модерацию, замену постов и ручное управление.

---

## Термины и сущности

### 1) Platform
Справочник типов платформ.

Поля:
- `id`
- `key` (unique: `telegram`, `vk`, `x` ...)
- `name`
- `is_enabled`
- `driver` (строка, например `telegram`, `vk`, `x`; пригодится для publisher adapter)
- timestamps

### 2) PlatformAccount
Конкретный аккаунт/канал/группа, куда публикуется контент.

Поля:
- `id`
- `platform_id` (FK)
- `title`
- `external_id` (id/username канала или аккаунта внутри платформы)
- `handle` (nullable, например `@anecdots_mems`)
- `is_enabled`
- `settings` (json, platform-specific)
- `credentials_ref` (nullable, ссылка на секрет/алиас, а не сам токен)
- timestamps

Индексы:
- unique(`platform_id`, `external_id`)

### 3) platform_account_user
Связь many-to-many между пользователями и аккаунтами платформ.

Поля:
- `platform_account_id`
- `user_id`
- `role` (`owner|admin|viewer`)
- timestamps

Индексы:
- unique(`platform_account_id`, `user_id`)

### 4) PostingPlan
Правила публикации для конкретного `PlatformAccount`.

Поля:
- `id`
- `platform_account_id` (FK)
- `timezone` (default `UTC`)
- `is_active`
- `quiet_hours_from` / `quiet_hours_to`
- `rules` (json)
- timestamps

Примеры `rules`:
```json
{
  "posts_per_day": 3,
  "min_interval_minutes": 120,
  "allowed_weekdays": [1,2,3,4,5,6,7],
  "prefer_media_every_n_posts": 4,
  "source_pool": "telegram_jokes"
}
```

### 5) PostingSlots или PostingScheduleRules
Если понадобится точное календарное расписание, лучше вынести в отдельную таблицу, а не пихать всё в JSON.

Вариант таблицы:
- `id`
- `posting_plan_id`
- `weekday`
- `time_local`
- `is_enabled`
- timestamps

Это даст удобный UI вроде: «понедельник 09:00, 14:00, 19:00».

### 6) PlannedPost
Главная сущность для модерации и управления планом публикаций.

Это **конкретный пост в плане/очереди** для конкретного аккаунта.

Поля:
- `id`
- `platform_account_id` (FK)
- `source_type` (например `telegram_joke`, `manual`, `generated`)
- `source_id` (nullable)
- `content_snapshot` (json/text, зафиксированный текст/медиа на момент планирования)
- `scheduled_at`
- `status`
- `moderation_status`
- `replace_of_id` (nullable FK на другой `planned_posts.id`)
- `deleted_at` (soft delete)
- `approved_by` (nullable user_id)
- `approved_at` (nullable)
- `created_by`
- `updated_by` (nullable)
- `delete_confirmed_by` (nullable user_id)
- `delete_confirmed_at` (nullable)
- `notes` (nullable)
- timestamps

Рекомендуемые `status`:
- `draft`
- `scheduled`
- `publishing`
- `published`
- `failed`
- `cancelled`
- `replaced`

Рекомендуемые `moderation_status`:
- `pending_review`
- `approved`
- `rejected`
- `needs_replacement`
- `delete_requested`
- `delete_confirmed`

### 7) PostingHistory
Append-only история фактов публикации и попыток доставки.

Поля:
- `id`
- `platform_account_id` (FK)
- `planned_post_id` (nullable FK)
- `status` (`queued|sent|failed|skipped|cancelled`)
- `scheduled_at` (nullable)
- `sent_at` (nullable)
- `provider_message_id` (nullable)
- `payload` (json)
- `response` (json)
- `error` (text, nullable)
- timestamps

Индексы:
- (`platform_account_id`, `created_at`)
- (`planned_post_id`, `created_at`)
- (`status`, `created_at`)

### 8) AdminAuditLog
Журнал действий админов.

Поля:
- `id`
- `user_id`
- `platform_account_id` (nullable)
- `entity_type`
- `entity_id`
- `action` (`create_account`, `approve_post`, `request_delete`, `confirm_delete`, `replace_post`, `publish_now` ...)
- `before` (json nullable)
- `after` (json nullable)
- timestamps

Это особенно полезно для удаления, подтверждений и замены.

---

## Как трактовать «план публикации»

У нас должно быть **2 уровня плана**:

### Уровень A. Правила публикации
Это `PostingPlan`:
- частота;
- quiet hours;
- дни недели;
- интервалы;
- правила по медиа;
- источник контента.

### Уровень B. Конкретные запланированные публикации
Это `PlannedPost`:
- какой контент;
- на когда;
- кто подтвердил;
- удалён / заменён / опубликован;
- нужна ли модерация.

Такой дизайн лучше всего покрывает твой сценарий: **план + модерация + подтверждение удаления + замена**.

---

## Авторизация и доступ

### Базовое правило
Пользователь видит и меняет только те `PlatformAccount`, где он есть в `platform_account_user`.

### Роли
Рекомендую сразу закладывать 3 роли:
- `owner` — управляет аккаунтом, планом, админами, настройками
- `admin` — модерирует контент, меняет план, может публиковать
- `viewer` — только просмотр

Если хочешь быстрее MVP, можно сначала реализовать только `owner` и `admin`, а `viewer` оставить как задел.

### Policies
Нужны policy для:
- `PlatformAccount`
- `PostingPlan`
- `PlannedPost`
- `PostingHistory`
- `AdminAuditLog`

Отдельно:
- управление списком админов, credentials/settings аккаунта — только `owner`
- approve/replace/delete/publish — `owner` или `admin`

---

## Filament UI, как лучше организовать кабинет

### Навигация
Группа меню: **Проекты**
- внутри список доступных `PlatformAccount`
- внизу `+ Добавить аккаунт`

### Внутри каждого PlatformAccount
Лучший UX, на мой взгляд, это **одна страница кабинета аккаунта** с вкладками:

1. **Overview**
- основная информация об аккаунте
- платформа
- статус
- кто админы
- краткая сводка: ближайшие публикации, последние ошибки, последние успешные посты

2. **Plan**
- правила публикации (`PostingPlan`)
- расписание/слоты
- переключатели активности

3. **Queue / Moderation**
- список `PlannedPost`
- действия:
  - approve
  - reject
  - request delete
  - confirm delete
  - replace content
  - reschedule
  - publish now

4. **History**
- `PostingHistory`
- фильтры по статусу, периоду, ручным/авто публикациям

5. **Admins**
- управление `platform_account_user`
- доступно только `owner`

6. **Settings / Integration**
- platform-specific settings
- ссылка на credentials alias / publisher account
- тест соединения позже

### Почему именно так
Потому что пользователь мыслит не сущностями БД, а «моим Telegram-каналом», «моим VK-пабликом». Значит основной экран должен быть вокруг `PlatformAccount`, а не вокруг разрозненных таблиц.

---

## Как добавлять новые платформы

Чтобы потом нормально добавлять VK/X/другое, нужен publisher layer:

### Интерфейс публикации
Например:
- `PublisherDriverInterface`
- `publish(PlannedPost $post, PlatformAccount $account): PublishResult`
- `dryRun(...)`
- `validateAccountSettings(...)`

### Драйверы
- `TelegramPublisherDriver`
- `VkPublisherDriver`
- `XPublisherDriver`

Для MVP Telegram можно оставить текущий Python pipeline и вызывать его через Laravel command/service adapter.

То есть:
- UI и orchestration в Laravel
- фактическая публикация Telegram пока через Python
- новые платформы потом можно подключать либо тоже через внешние workers, либо через native Laravel service

---

## Контент и модерация

С учётом текущего контекста, сначала можно брать контент из существующего пула (`telegram_jokes`), но архитектуру лучше делать с заделом:

### Вариант MVP
- использовать текущий контентный пул как source
- `PlannedPost.source_type = telegram_joke`
- `source_id = telegram_jokes.id`
- `content_snapshot` хранит зафиксированный текст после очистки/подготовки

### Позже
сделать универсальный `content_items` и `content_assets` для мультиплатформенности.

### Что обязательно нужно в модерации PlannedPost
- просмотр исходного текста
- просмотр очищенного/подготовленного текста
- пометка «нужна замена»
- замена поста другим источником или ручным текстом
- подтверждение удаления через отдельное действие, а не мгновенное удаление
- журнал, кто и что сделал

---

## Поэтапный план реализации

## Этап 1. Основа кабинета
Цель: получить нормальную модель доступа и сущности аккаунтов.

Сделать:
- миграции:
  - `platforms`
  - `platform_accounts`
  - `platform_account_user`
  - `posting_plans`
  - `posting_history`
  - `admin_audit_logs`
- модели и связи
- роли `owner/admin` минимум
- policies
- seed базовых платформ (`telegram`, `vk`, `x`)
- базовый `PlatformAccount` кабинет в Filament

Результат:
- можно создавать платформы/аккаунты
- можно назначать нескольких админов
- у каждого аккаунта уже есть свой кабинет, план и история

## Этап 2. План публикации и календарная логика
Цель: сделать управляемый план публикаций.

Сделать:
- `PostingPlan` UI
- при необходимости `PostingSlots`
- страницу Plan с формой правил и расписания
- генерацию/отображение ближайших слотов публикации

Результат:
- у каждого аккаунта есть отдельный управляемый план публикаций

## Этап 3. Очередь и модерация
Цель: реализовать операционную работу редактора/админа.

Сделать:
- миграцию и модель `planned_posts`
- вкладку `Queue / Moderation`
- статусы и moderation workflow
- действия:
  - approve
  - reject
  - replace
  - request delete
  - confirm delete
  - reschedule
- audit log по действиям

Результат:
- план публикации становится реально управляемым, а не просто набором правил

## Этап 4. Интеграция публикации
Цель: публиковать и писать историю доставки.

Сделать:
- application service / action для `PublishPlannedPost`
- Telegram adapter через текущий Python pipeline
- dry-run
- обработку ошибок
- запись в `posting_history`
- защиту от двойного запуска

Результат:
- можно публиковать вручную и автоматически
- вся история видна в кабинете

## Этап 5. Унификация под новые платформы
Цель: расширение без архитектурного долга.

Сделать:
- driver-based publisher layer
- account settings UI для разных платформ
- валидацию settings/credentials
- подключение VK/X
- постепенный переход от telegram-specific content source к универсальному content domain

Результат:
- новые соцсети добавляются как новый driver и настройки аккаунта, а не переписыванием админки

---

## Что делать первым

Моя рекомендация:

### Сначала делать:
1. `Platform / PlatformAccount / platform_account_user`
2. кабинет аккаунта
3. `PostingPlan`
4. `PostingHistory` как read-model

### Потом:
5. `PlannedPost` + модерация
6. publish integration

Причина простая:
- сначала надо построить каркас управления аккаунтами и правами доступа;
- потом поверх него уже добавлять очередь, модерацию и реальную публикацию;
- иначе быстро получится мешанина из Telegram-логики, очереди и прав.

---

## Минимальный backlog по файлам

### Миграции
- `create_platforms_table`
- `create_platform_accounts_table`
- `create_platform_account_user_table`
- `create_posting_plans_table`
- `create_posting_history_table`
- `create_admin_audit_logs_table`
- позже: `create_posting_slots_table`
- позже: `create_planned_posts_table`

### Модели
- `Platform`
- `PlatformAccount`
- `PostingPlan`
- `PostingHistory`
- `AdminAuditLog`
- позже: `PostingSlot`
- позже: `PlannedPost`

### Filament
- `PlatformAccountResource` или кастомная page кабинета
- relation manager / tabs для:
  - Plan
  - History
  - Admins
  - позже Queue/Moderation

### Policies
- `PlatformAccountPolicy`
- `PostingPlanPolicy`
- `PostingHistoryPolicy`
- позже `PlannedPostPolicy`

### Services / Actions
- позже `PublishPlannedPostAction`
- позже `ReplacePlannedPostAction`
- позже `ConfirmDeletePlannedPostAction`

### Tests
- доступ только к своим аккаунтам
- owner/admin permissions
- 403 на чужие аккаунты
- moderation transitions
- publish idempotency

---

## Открытые вопросы
1) На первом этапе делаем только `owner/admin`, или сразу добавлять `viewer`?
2) План должен быть rule-based, slot-based, или сразу оба механизма?
3) Замена поста должна сохранять историю старого planned post как `replaced`, или можно просто перезаписывать? Я бы делал только через историю, без тихого overwrite.
4) Для platform account credentials: храним только reference/alias на внешний секрет или допустимо encrypted field в БД? Я бы предпочёл alias/reference.
5) Нужен ли глобальный super-admin, который видит все аккаунты?

---

## Рекомендуемое решение по умолчанию

Если нужно выбрать путь без лишнего риска, я рекомендую:
- строить UI вокруг `PlatformAccount`;
- разделять `PostingPlan` и `PlannedPost`;
- модерацию делать на уровне `PlannedPost`;
- удаление делать только через `request delete -> confirm delete`;
- замену делать созданием нового `PlannedPost` со связью `replace_of_id`, а не редактированием без следа;
- Telegram пока публиковать через существующий Python слой.

Это даст нормальный кабинет управления и не загонит нас в тупик, когда начнут добавляться новые платформы.

---

## Конкретный backlog на первую реализацию

Ниже не просто идеи, а рабочая последовательность задач, в которой можно реально идти по коду.

### Итерация 1 — foundation кабинета
Цель: получить работающий кабинет с платформами, аккаунтами, доступами, планом и историей без сложной модерации.

#### Task 1. Базовые enum/константы и роли
Сделать:
- определить роли `owner`, `admin`, `viewer`
- определить базовые статусы для `PostingHistory`
- подготовить единое место для констант/enum

Файлы:
- `app/Enums/PlatformAccountRole.php`
- `app/Enums/PostingHistoryStatus.php`
- при необходимости `app/Support/...`

Acceptance criteria:
- роли и статусы не размазаны строками по коду
- policies и Filament используют единые enum/константы

#### Task 2. Миграции foundation-слоя
Сделать миграции:
- `platforms`
- `platform_accounts`
- `platform_account_user`
- `posting_plans`
- `posting_history`
- `admin_audit_logs`

Важно:
- `platform_account_user.role`
- `platform_accounts.settings` как json
- `platform_accounts.credentials_ref` как nullable string
- `posting_plans.rules` как json
- индексы на частые выборки

Acceptance criteria:
- `php artisan migrate` проходит чисто
- структура покрывает multi-platform + multi-admin

#### Task 3. Eloquent модели и связи
Сделать модели:
- `Platform`
- `PlatformAccount`
- `PostingPlan`
- `PostingHistory`
- `AdminAuditLog`
- доработать `User`

Связи:
- `Platform hasMany PlatformAccount`
- `PlatformAccount belongsTo Platform`
- `PlatformAccount belongsToMany User`
- `PlatformAccount hasOne PostingPlan`
- `PlatformAccount hasMany PostingHistory`
- `User belongsToMany PlatformAccount`

Acceptance criteria:
- связи работают через tinker/tests
- pivot role читается и используется

#### Task 4. Seed базовых платформ и первого owner
Сделать:
- seeder платформ: `telegram`, `vk`, `x`
- опционально seeder demo account для локальной разработки
- назначение текущего админа owner для demo account

Файлы:
- `database/seeders/PlatformSeeder.php`
- `database/seeders/DatabaseSeeder.php`

Acceptance criteria:
- после seeding в системе есть базовые типы платформ
- локально можно зайти и увидеть хотя бы один аккаунт

#### Task 5. Policies и доступы
Сделать policies:
- `PlatformAccountPolicy`
- `PostingPlanPolicy`
- `PostingHistoryPolicy`
- `AdminAuditLogPolicy`

Правила:
- `owner/admin/viewer` видят свой аккаунт
- только `owner` управляет админами и settings аккаунта
- `owner/admin` могут менять план
- история доступна только участникам аккаунта

Acceptance criteria:
- чужой аккаунт открывается с 403
- owner/admin/viewer получают ожидаемые права

#### Task 6. Filament: кабинет аккаунта платформы
Сделать основной UX вокруг `PlatformAccount`.

Вариант реализации:
- `PlatformAccountResource`
- кастомная view page или edit page с вкладками:
  - Overview
  - Plan
  - History
  - Admins
  - Settings

На первом шаге можно начать даже без красивых tabs, но структура должна вести к кабинету аккаунта.

Acceptance criteria:
- пользователь после логина видит свои аккаунты
- может открыть страницу конкретного аккаунта
- чужие аккаунты не видит и не открывает

#### Task 7. Filament: управление администраторами аккаунта
Сделать:
- relation manager для `platform_account_user`
- добавление/удаление админов
- выбор роли
- ограничения: только owner

Acceptance criteria:
- owner может добавить второго админа
- admin не может менять список админов

#### Task 8. Filament: план публикации
Сделать UI для `PostingPlan`:
- timezone
- quiet hours
- active/inactive
- rules JSON пока можно редактировать через простую форму

Лучше сразу не делать слишком “магическую” форму на все случаи. Достаточно базовых полей + небольшой structured rules block.

Acceptance criteria:
- у каждого аккаунта есть отдельный редактируемый план
- owner/admin могут менять план
- viewer не может

#### Task 9. Filament: история публикаций
Сделать read-only таблицу `PostingHistory`:
- статус
- planned/sent timestamps
- provider_message_id
- ошибка
- фильтры по статусу и периоду

Acceptance criteria:
- история видна только по своему аккаунту
- удобна для базовой диагностики

#### Task 10. Аудит действий
Сделать базовую запись в `admin_audit_logs` для действий:
- создание аккаунта
- обновление плана
- изменение списка админов
- изменение настроек аккаунта

Acceptance criteria:
- ключевые административные изменения логируются
- в БД можно понять кто и что менял

#### Task 11. Feature tests на foundation
Минимальный набор тестов:
- owner видит свой аккаунт
- admin видит свой аккаунт
- viewer видит свой аккаунт, но не редактирует план
- чужой аккаунт -> 403
- только owner управляет администраторами

Файлы:
- `tests/Feature/PlatformAccountAccessTest.php`
- `tests/Feature/PostingPlanAccessTest.php`
- `tests/Feature/PlatformAccountAdminManagementTest.php`

Acceptance criteria:
- права доступа защищены тестами

---

### Итерация 2 — планирование слотов и операционный контур
Цель: сделать план публикации не только набором правил, но и рабочим расписанием.

#### Task 12. Posting slots
Если нужен календарный план, добавить таблицу `posting_slots`:
- `posting_plan_id`
- `weekday`
- `time_local`
- `is_enabled`

Acceptance criteria:
- можно задать несколько слотов в неделю для одного аккаунта

#### Task 13. UI слотов
Сделать удобное редактирование слотов внутри Plan.

Acceptance criteria:
- расписание читается без просмотра raw JSON

#### Task 14. Upcoming schedule preview
Сделать вычисление ближайших N слотов публикации.

Acceptance criteria:
- в кабинете видно, когда ожидаются ближайшие публикации

---

### Итерация 3 — очередь, модерация, подтверждения, замены
Цель: закрыть твой ключевой сценарий ручного управления контентом.

#### Task 15. Миграция и модель `planned_posts`
Сделать:
- таблицу `planned_posts`
- статусы и moderation statuses
- soft deletes
- поля `approved_by`, `approved_at`, `replace_of_id`, `delete_confirmed_by`

Acceptance criteria:
- planned post поддерживает жизненный цикл модерации и замены

#### Task 16. Filament: Queue / Moderation
Сделать вкладку/ресурс очереди:
- список planned posts
- фильтры по статусам
- карточка поста
- preview snapshot

Acceptance criteria:
- админ видит рабочую очередь конкретного аккаунта

#### Task 17. Модерационные actions
Сделать actions:
- `approve`
- `reject`
- `requestDelete`
- `confirmDelete`
- `replace`
- `reschedule`

Важно:
- удаление не мгновенное, а через явное подтверждение
- замена не перетирает старую запись без следа

Acceptance criteria:
- есть прозрачный audit-friendly workflow

#### Task 18. Audit log для модерации
Логировать:
- approve/reject
- request delete / confirm delete
- replace
- reschedule

Acceptance criteria:
- можно восстановить цепочку действий по каждому посту

#### Task 19. Tests модерации
Покрыть:
- переходы статусов
- права owner/admin/viewer
- replace flow
- delete confirmation flow

---

### Итерация 4 — публикация и интеграция с Telegram pipeline
Цель: связать кабинет с фактической публикацией.

#### Task 20. Publisher abstraction
Добавить контракт:
- `PublisherDriverInterface`
- `PublishResult`
- `DryRunResult`

Acceptance criteria:
- orchestration не зависит жёстко от Telegram

#### Task 21. Telegram driver/adaptor
Сделать адаптер к текущему Python pipeline.

Вариант:
- Laravel action/command вызывает Python script
- собирает stdout/stderr
- нормализует результат в `PublishResult`

Acceptance criteria:
- planned post можно отправить через существующий Telegram pipeline без переписывания всей логики

#### Task 22. Publish now / retry / dry-run
Добавить actions:
- publish now
- retry failed
- dry-run preview

Acceptance criteria:
- админ может инициировать публикацию вручную
- история попыток записывается в `posting_history`

#### Task 23. Idempotency / locking
Нужны:
- защита от двойного клика
- защита от параллельной публикации одного planned post

Acceptance criteria:
- один planned post не публикуется дважды из-за гонки

---

### Итерация 5 — новые платформы
Цель: подготовить масштабирование beyond Telegram.

#### Task 24. Platform-specific settings forms
Сделать настройки аккаунта с ветвлением по driver/platform.

Acceptance criteria:
- Telegram/VK/X могут иметь разные поля без хака в одной форме

#### Task 25. Account validation
Сделать validate/test connection для platform settings.

Acceptance criteria:
- при сохранении можно проверить, что настройки аккаунта вообще рабочие

#### Task 26. New drivers
Подключать следующие платформы как новые драйверы:
- `VkPublisherDriver`
- `XPublisherDriver`

Acceptance criteria:
- расширение делается через новый driver, а не переписыванием core логики

---

## Что делать прямо сейчас, без распыления

### Sprint 1 (рекомендую начать с этого)
Взять в работу задачи:
- Task 1. Enum/константы
- Task 2. Миграции foundation
- Task 3. Модели и связи
- Task 4. Seed
- Task 5. Policies
- Task 6. Кабинет PlatformAccount
- Task 8. PostingPlan UI
- Task 9. PostingHistory UI
- Task 11. Feature tests

### Sprint 2
- Task 7. Управление администраторами
- Task 10. Audit log
- Task 12-14. Слоты и preview schedule

### Sprint 3
- Task 15-19. Queue / Moderation / replace / confirm delete

### Sprint 4
- Task 20-23. Publish integration

---

## Что я считаю правильным компромиссом

Чтобы не закопаться в архитектуру раньше времени, я бы делал так:
- сразу строим правильную сущность `PlatformAccount`
- сразу делаем multi-admin
- сразу делаем отдельные `PostingPlan` и `PostingHistory`
- `PlannedPost` и модерацию делаем следующей волной
- Telegram publishing пока оставляем через Python adapter

Это даст быстрый прогресс без архитектурного тупика.
