# Архитектура Content Admin

Дата актуализации: 2026-04-21

## Цель документа

Этот документ фиксирует:

- текущую рабочую архитектуру проекта;
- целевое направление к feature-first структуре;
- правила размещения кода, чтобы новые изменения не усиливали технический долг.

Документ описывает реальность проекта на сегодня, а не только желаемое состояние.

## Текущее состояние

Сейчас проект реализован как Laravel 10 + Filament v3 приложение со следующими слоями:

- `app/Models` — Eloquent модели домена;
- `app/Policies` — правила доступа;
- `app/Services` — application/service layer для workflow и publishing;
- `app/Observers` — побочные эффекты модели и audit logging;
- `app/Filament/Resources` — административный UI;
- `database/migrations`, `database/seeders` — схема и bootstrap данных.

Это рабочая, но не feature-first структура. Она уже поддерживает:

- `Platform`, `PlatformAccount`, `PostingPlan`, `PostingSlot`;
- `PlannedPost`, `PostingHistory`, `AdminAuditLog`;
- multi-admin доступ через `platform_account_user`;
- moderation workflow;
- dry-run и ручную публикацию;
- Filament-кабинет вокруг `PlatformAccount`.

С `2026-04-21` проект начал использовать первые feature-first application entry points в `app/Features/*/Application/Actions` для:

- `PlatformAccounts` — создание аккаунта и управление администраторами;
- `PlannedPosts` — moderation workflow transitions;
- `Publishing` — dry-run и manual publish entry points для presentation слоя.

## Целевое направление

Проект должен развиваться в сторону feature-first модульной структуры с разделением:

- `Domain` — термины, сущности, value objects, enum, доменные правила;
- `Application` — use cases, actions, orchestration, workflow;
- `Infrastructure` — Eloquent, внешние адаптеры, publishing drivers, DB integration;
- `Presentation` — Filament resources/pages/actions, HTTP слой.

На данном этапе мы не делаем жёсткий большой рефакторинг всего `app/`, но все новые значимые изменения должны уже укладываться в это направление.

## Архитектурные принципы

### 1. Центр системы — `PlatformAccount`

Система строится не вокруг “соцсети вообще”, а вокруг конкретного аккаунта публикации.

Вокруг него группируются:

- права доступа;
- настройки платформы;
- план публикации;
- очередь модерации;
- история публикаций;
- аудит действий.

### 2. Разделение правил и фактов

Нельзя смешивать:

- `PostingPlan` — правила и расписание;
- `PlannedPost` — конкретный операционный пост в очереди;
- `PostingHistory` — факт попытки публикации;
- `AdminAuditLog` — факт действия оператора.

Это ключевое разделение домена.

### 3. Application logic не должна расползаться по UI

Filament-ресурсы и relation managers не должны становиться местом, где живёт бизнес-логика.

Допустимо:

- простое открытие форм;
- вызов action/service/use case;
- форматирование данных для UI.

Недопустимо:

- описывать полный workflow переходов статусов прямо в UI;
- строить сложную логику публикации и модерации внутри страниц/ресурсов;
- размазывать правила авторизации по видимости кнопок без policy/use case guard.

### 4. Внешняя интеграция должна быть изолирована

Любая платформенная интеграция должна идти через driver/adaptor слой.

Сейчас в проекте уже есть начало этого подхода:

- `PublisherDriverInterface`
- `PublishingService`
- `TelegramPublisherDriver`
- `NullPublisherDriver`

Следующий шаг — уйти от глобального выбора драйвера на всё приложение к resolver по `platform.driver`.

## Рекомендуемая структура для новых модулей

Новые крупные изменения добавлять по feature-first схеме:

```text
app/
  Features/
    PlatformAccounts/
      Domain/
      Application/
      Infrastructure/
      Presentation/
    PostingPlans/
      Domain/
      Application/
      Infrastructure/
      Presentation/
    PlannedPosts/
      Domain/
      Application/
      Infrastructure/
      Presentation/
    Publishing/
      Domain/
      Application/
      Infrastructure/
      Presentation/
```

Пока существующий код ещё живёт в `app/Models`, `app/Services`, `app/Filament`, разрешено:

- не переносить старый код массово без необходимости;
- но новые use cases и новые интеграционные блоки уже добавлять в feature-first директории;
- старые сервисы постепенно выносить в модули при естественных изменениях.
- legacy-сервисы допустимо оставлять как compatibility layer, если presentation уже переключён на feature-first actions.

## Правила по слоям

### Domain

Содержит:

- доменные enum;
- доменные сущности и правила;
- ограничения жизненного цикла;
- словарь терминов.

Не должен зависеть от Filament и UI.

### Application

Содержит:

- use case / action классы;
- orchestration;
- workflow переходов;
- сценарии публикации, замены, удаления, планирования.

Application слой может зависеть от domain и контрактов, но не должен зависеть от конкретного Filament UI.

### Infrastructure

Содержит:

- Eloquent persistence;
- DB integration;
- publisher drivers;
- внешние process/CLI/DB adapters;
- runtime-specific код.

Сюда относится и текущая интеграция с Telegram runtime / Python script, а также прямой Telegram Bot API publish для аккаунтов с подключённым bot token.

### Presentation

Содержит:

- Filament resources;
- relation managers;
- page actions;
- form/table schemas;
- HTTP controllers, если появятся.

Presentation слой вызывает application слой и policies.

## Политика развития текущего кода

### Что можно оставлять как есть

- существующие Eloquent модели;
- существующие policies;
- рабочие Filament ресурсы;
- текущие feature tests;
- существующий publishing service до шага отдельного рефакторинга.

### Что нужно менять в первую очередь

- новые workflow transitions оформлять как action/use case классы;
- новые publishing-интеграции не добавлять прямо в `Filament` или в общий service без контракта;
- новые platform-specific формы и валидаторы не смешивать в одну общую “универсальную” форму;
- machine-specific зависимости выносить в config/env/adapter.

## Границы ответственности по ключевым частям

### Access control

- `Policy` отвечает за право действия над сущностью;
- UI может скрывать действие, но не заменяет policy;
- сложные guard conditions должны дублироваться в application/use case слое.

### Moderation workflow

- переходы статусов принадлежат application/domain слою;
- audit log должен писаться в рамках одного use case;
- UI только инициирует действие.

### Publishing

- `PublishingService` координирует публикацию;
- driver публикует в конкретную платформу;
- история попыток доставки должна быть append-only;
- idempotency и locking должны жить в orchestration, а не быть только UI-защитой.

## Стратегия постепенной миграции

### Краткосрочно

- поддерживаем текущую рабочую структуру;
- документируем правила;
- новые документы и use case классы добавляем в feature-first стиле.

### Среднесрочно

- выносим moderation actions из общего `PlannedPostWorkflowService` в feature module;
- выносим publishing resolver/drivers в модуль `Publishing`;
- разносить platform-specific configuration forms по платформам.

### Долгосрочно

- минимизировать общие `app/Services`;
- перейти к модульной структуре как основной;
- оставить старые директории только как legacy boundary, пока код не будет естественно перенесён.
