# Backend

**Стек:** PHP 8.5 · Symfony 8 · EasyAdmin 5 · MySQL 8 · Twig · Docker.

**Принцип:** тонкие контроллеры → сервисы (`OrderService`, `PaymentService`, …).

---

## Структура `src/`

```
Entity/          Dish, MenuDay, MenuDayDish, Customer, Order, OrderItem, PickupPoint, AdminUser
Enum/            OrderStatus, OrderChannel
Repository/
Service/
  OrderStatusService.php    # смена статусов, batch на кухне
  MenuDayService.php        # неделя меню
  KitchenSummaryService.php # сводка порций
  PaymentService.php        # подтверждение оплаты по API
  OrderService.php          # создание заказа, cutoff, snapshot
  OrderCutoffService.php    # правила cutoff и горизонта меню
  CustomerService.php       # нормализация телефона, find-or-create
  MenuCatalogService.php    # публичное меню для API
  NotificationService.php   # TG/VK push, admin alert
  Bot/BotOrderFlowService.php
Controller/
  Admin/           EasyAdmin + кастомные экраны
  Api/             orders, menu, payment, bot webhooks
  Web/             (этап 3) публичный сайт
Dto/               CreateOrderDto, RepeatOrderDto, …
Entity/BotSession  # состояние диалога TG/VK
Form/Admin/        DishFormType, MenuDayFormType, …
Command/           app:seed:*
```

---

## Модель данных

```
PickupPoint
Dish ──► MenuDayDish ◄── MenuDay
  │
  └── OrderItem ◄── Order ──► Customer
                      │
                      └── PickupPoint
```

### Order

- `uuid`, `humanNumber`, `customer`, `pickupDate`, `pickupPoint`
- `channel`: `web` | `telegram` | `vk`
- `status`, `totalAmount` (копейки), `comment`, `repeatToken`
- `paidAt`, `createdAt`

### OrderItem

- `dishSnapshot` (JSON): `{ "dish_id", "name", "unit_price" }`
- `lineTotal` **не хранится** — `quantity * unit_price`

### Dish.composition (JSON)

```json
{
  "weight_g": 350,
  "ingredients": ["чечевица", "морковь"],
  "allergens": [],
  "note": "лёгкий, без острого"
}
```

### Customer

- `personalDataConsentAt` — согласие на обработку ПДн

---

## Согласие на обработку ПДн

| Канал | Как |
|---|---|
| API / сайт | `personal_data_consent: true` |
| Telegram / VK | не требуется |

---

## Повтор заказа

`GET/POST /api/orders/repeat/{repeatToken}` · ссылка `/order/repeat/{token}`

**GET** — превью: телефон, имя, точка выдачи из исходного заказа.

**POST** — новый заказ: обязательны `pickup_date` и `items`; состав и комментарий не копируются из старого заказа.

---

## Боты

`POST /api/bot/telegram/webhook` · `POST /api/bot/vk/callback`

---

## Публичное API заказов

### Меню

```
GET /api/menu
```

Возвращает опубликованные дни меню на горизонт `ORDER_MENU_HORIZON_DAYS` (по умолчанию 7).

### Создание заказа

```
POST /api/orders
Content-Type: application/json
```

```json
{
  "phone": "+79123456789",
  "name": "Анна",
  "pickup_date": "2026-06-14",
  "pickup_point_id": 1,
  "channel": "web",
  "comment": "без лука",
  "items": [
    { "menu_day_dish_id": 12, "quantity": 2 }
  ]
}
```

Ответ **201** — заказ в статусе `pending_payment` + блок `payment` (QR/реквизиты).

### Статус заказа

```
GET /api/orders/{uuid}
```

---

## Оплата

Подтверждение **только автоматическое** через webhook. Реализация: `PaymentService` + `PaymentWebhookController`.

### Endpoint

```
POST /api/payment/webhook
Header: X-Payment-Token: {PAYMENT_WEBHOOK_SECRET}
Content-Type: application/json
```

Провайдеры с адаптерами:

| Провайдер | URL | Авторизация | Назначение |
|---|---|---|---|
| **generic** | `/api/payment/webhook` | `X-Payment-Token` | **Наш** нормализованный формат JSON — не СБП. Удобен для тестов, cron-скриптов и прокси «провайдер → наш API». |
| **sber** | `/api/payment/sber/webhook` | `X-Sber-Webhook-Secret` | Callback Сбербанка (эквайринг / СБП через API Сбера) |
| **yookassa** | `/api/payment/yookassa/webhook` | `X-Yookassa-Secret` | Webhook ЮKassa (если понадобится) |

**СБП** — способ оплаты (перевод по QR), а не формат webhook. Клиент платит через СБП, а **Сбер** (или другой банк) при успехе шлёт callback на `/api/payment/sber/webhook`; адаптер переводит его в общую логику `PaymentService`.

### Тело запроса

```json
{
  "order_uuid": "018f3a2e-…",
  "amount": 35000
}
```

| Поле | Обязательно | Описание |
|---|---|---|
| `order_uuid` | да | UUID заказа из нашей системы |
| `amount` | нет | Сумма в копейках; если передана — сверяется с `Order.totalAmount` |
| `external_id` | нет | ID платежа у провайдера (зарезервировано для логов) |

### Ответ 200

```json
{
  "status": "paid",
  "order_uuid": "…",
  "human_number": 123,
  "paid_at": "2026-06-13T12:00:00+05:00"
}
```

### Ошибки

| HTTP | `error` | Когда |
|---|---|---|
| 401 | `unauthorized` | неверный `X-Payment-Token` |
| 404 | `order_not_found` | заказ не найден |
| 409 | `order_cancelled` | заказ отменён |
| 422 | `amount_mismatch` | сумма не совпала |

Повторный webhook для уже оплаченного заказа → **200**, статус `paid` (идемпотентно).

### Интеграция провайдера

На этапе 5 добавляется адаптер (cron, middleware или отдельный micro-endpoint), который:

1. Получает событие от банка / СБП / ЮKassa.
2. Находит `order_uuid` (из metadata платежа или комментария перевода).
3. Вызывает `PaymentService::confirmPayment()` или проксирует на `/api/payment/webhook`.

### Переменные окружения

| Переменная | Описание |
|---|---|
| `PAYMENT_WEBHOOK_SECRET` | секрет для заголовка `X-Payment-Token` |
| `PAYMENT_QR_URL` | URL QR для страницы оплаты |
| `PAYMENT_CARD` | реквизиты карты (fallback) |
| `ORDER_CUTOFF_HOUR` | час cutoff (по умолчанию 18) |
| `ORDER_MENU_HORIZON_DAYS` | горизонт меню в днях (по умолчанию 7) |
| `SBER_WEBHOOK_SECRET` | секрет для webhook Сбербанка |
| `YOOKASSA_WEBHOOK_SECRET` | секрет для webhook YooKassa |

---

## Seed-команды

| Команда | Назначение |
|---|---|
| `app:seed:admin` | Admin из `ADMIN_EMAIL` / `ADMIN_PASSWORD` |
| `app:seed:pickup-point` | Точка «Хануман» |
| `app:seed` | Обе команды |

---

## Полезные console-команды

```bash
php bin/console cache:clear
php bin/console doctrine:migrations:migrate
php bin/console debug:router | grep api
```
