# Notification Service

Микросервис массовой рассылки SMS и Email уведомлений с приоритизацией.

## Технологический стек

- **PHP 8.4** / **Laravel 12**
- **PostgreSQL 16** — основная база данных
- **RabbitMQ 3** — брокер сообщений
- **Redis 7** — кэш и дедубликация
- **Nginx** — веб-сервер
- **Docker Compose** — оркестрация

## Быстрый старт

### Требования

- Docker >= 20.10
- Docker Compose V2

### Установка одной командой

```bash
make install
```

Эта команда выполнит:
1. Скопирует `.env.example` в `.env`
2. Соберёт и поднимет все контейнеры (app, nginx, worker, postgres, RabbitMQ, redis)
3. Установит composer-зависимости
4. Сгенерирует APP_KEY
5. Выполнит миграции
6. Сгенерирует OpenAPI-документацию

### Без Make

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan l5-swagger:generate
```

### Доступные URL

| Сервис | URL |
|--------|-----|
| API | http://localhost:8080/api/v1 |
| Swagger UI | http://localhost:8080/api/documentation |
| RabbitMQ Management | http://localhost:15672 (guest / guest) |

## API

### POST /api/v1/notifications/send

Массовая отправка уведомлений. Возвращает `202 Accepted`.

**Тело запроса:**

```json
{
  "channel": "sms",
  "message": "Your access code is 4829",
  "recipient_ids": ["sub_001", "sub_002", "sub_003"],
  "priority": "transactional",
  "idempotency_key": "batch-2026-05-15-abc123"
}
```

| Поле | Тип | Обязательно | Описание |
|------|-----|-------------|----------|
| `channel` | string | да | `sms` или `email` |
| `message` | string | да | Текст сообщения (до 4000 символов) |
| `recipient_ids` | string[] | да | Массив ID получателей (1-10000) |
| `priority` | string | да | `transactional` (высокий приоритет) или `marketing` (низкий) |
| `idempotency_key` | string | да | Ключ идемпотентности (до 128 символов) |

**Пример ответа (202):**

```json
{
  "idempotency_key": "batch-2026-05-15-abc123",
  "total_recipients": 3,
  "priority": "transactional",
  "channel": "sms"
}
```

### GET /api/v1/subscribers/{subscriberId}/notifications

История уведомлений подписчика с пагинацией.

**Query-параметры:**

| Параметр | Тип | Описание |
|----------|-----|----------|
| `status` | string | Фильтр: `queued`, `sent`, `delivered`, `rejected` |
| `channel` | string | Фильтр: `sms`, `email` |
| `per_page` | int | Кол-во на страницу (1-100, по умолчанию 20) |

**Пример:**

```
GET /api/v1/subscribers/sub_001/notifications?status=delivered&per_page=10
```

## Архитектура

### Слои приложения

```
app/
├── Contracts/          — Интерфейсы (Gateway, Repository, Service)
├── DTOs/               — Data Transfer Objects
├── Enums/              — Channel, Priority, NotificationStatus
├── Events/             — Доменные события
├── Exceptions/         — Кастомные исключения
├── Gateways/Stub/      — Заглушки SMS/Email провайдеров
├── Gateways/Decorators/ — CircuitBreaker и RateLimiter декораторы
├── Gateways/            — GatewayResolver
├── Http/Controllers/   — API контроллеры с OpenAPI аннотациями
├── Http/Requests/      — Form Request с валидацией
├── Http/Resources/     — API Resource для JSON ответов
├── Jobs/               — Queue jobs
├── Listeners/          — Обработчики событий
├── Models/             — Eloquent модели (агрегаты)
├── Repositories/       — Реализация паттерна Repository
├── Services/           — Бизнес-логика
└── Providers/          — Привязка интерфейсов к реализациям
```

### Приоритизация очередей

Очереди разделены по каналу и приоритету:

- `sms_high` — транзакционные SMS (коды доступа, срочные)
- `email_high` — транзакционные Email
- `sms_low` — маркетинговые SMS-рассылки
- `email_low` — маркетинговые Email-рассылки

### Retry-механизм

- 3 попытки отправки
- Экспоненциальный backoff: 10 сек, 60 сек, 5 мин
- После исчерпания попыток статус меняется на `rejected`

### Статусы уведомлений

| Статус | Описание |
|--------|----------|
| `queued` | Принято, ожидает отправки |
| `sent` | Передано шлюзу/провайдеру |
| `delivered` | Доставка подтверждена провайдером |
| `rejected` | Ошибка доставки (несуществующий номер, таймаут и т.д.) |

### Аудит-лог

Таблица `notification_logs` хранит историю всех переходов статусов с таймстампами и контекстом (причина отказа, номер попытки).

### Модель как агрегат

`Notification` — агрегат с защитой инвариантов:
- Нет mass assignment (`$guarded = ['*']`)
- Создание только через `Notification::createNew()`
- Переходы состояний через методы: `markAsSent()`, `markAsDelivered()`, `markAsRejected()`
- Невалидные переходы бросают `InvalidArgumentException`

### Gateway-заглушки

`StubSmsGateway` и `StubEmailGateway` имитируют работу реальных провайдеров. Для подключения реального провайдера достаточно:
1. Создать класс, реализующий `SmsGatewayInterface` или `EmailGatewayInterface`
2. Заменить привязку в `NotificationServiceProvider`

### Устойчивость к сбоям

Шлюзы оборачиваются двумя декораторами (кроме тестового окружения):

- **RateLimiter** (`RateLimitedGatewayDecorator`) — ограничение запросов к провайдеру (по умолчанию 100/сек)
- **Circuit Breaker** (`CircuitBreakerGatewayDecorator`) — автоматическое отключение при серии ошибок (порог: 5 ошибок, cooldown: 30 сек)
