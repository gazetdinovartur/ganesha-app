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
  OrderService.php          # (этап 4) создание заказа
  NotificationService.php   # (этап 7) TG/VK
Controller/
  Admin/           EasyAdmin + кастомные экраны
  Api/             PaymentWebhookController
  Web/             (этап 3) публичный сайт
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

---

## Оплата

Подтверждение **только автоматическое** через webhook. Реализация: `PaymentService` + `PaymentWebhookController`.

### Endpoint

```
POST /api/payment/webhook
Header: X-Payment-Token: {PAYMENT_WEBHOOK_SECRET}
Content-Type: application/json
```

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
