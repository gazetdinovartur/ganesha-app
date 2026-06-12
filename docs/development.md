# Руководство разработчика

Онбординг для нового человека в проекте: как поднять окружение, проверить API, ботов и оплату.

Связанные документы: [deploy.md](deploy.md) · [backend.md](backend.md) · [admin.md](admin.md)

---

## 1. Что это за проект

Symfony 8 monolith: публичное API заказов, админка (EasyAdmin), webhook оплаты, Telegram/VK-боты.

| Слой | Где смотреть |
|---|---|
| Бизнес-логика заказов | `src/Service/OrderService.php` |
| Повтор заказа | `src/Service/OrderRepeatService.php` |
| Статусы + push | `src/Service/OrderStatusService.php`, `NotificationService.php` |
| Боты | `src/Service/Bot/BotOrderFlowService.php` |
| API | `src/Controller/Api/` |
| Админка | `src/Controller/Admin/` |

---

## 2. Первый запуск (локально)

### Требования

- Docker + Docker Compose
- Git
- (Опционально) Composer на хосте

### Шаги

```bash
git clone <repo-url> ganesha-app
cd ganesha-app

cp .env.example .env.local
```

Отредактируй `.env.local` — минимум:

```dotenv
APP_SECRET=случайная_длинная_строка
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=надёжный_пароль
PAYMENT_WEBHOOK_SECRET=dev_webhook_secret
DEFAULT_URI=http://localhost:8080
```

Поднять контейнеры:

```bash
docker compose -f docker-compose.yml up -d --build
docker compose -f docker-compose.yml exec php composer install
docker compose -f docker-compose.yml exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f docker-compose.yml exec php bin/console app:seed
```

| URL | Назначение |
|---|---|
| http://localhost:8080 | приложение |
| http://localhost:8080/admin | админка |
| http://localhost:8080/admin/login | вход |

**Логин админки:** `ADMIN_EMAIL` / `ADMIN_PASSWORD` из `.env.local`.

После правок PHP/Docker:

```bash
docker compose -f docker-compose.yml up -d --build php
docker compose -f docker-compose.yml exec php bin/console cache:clear
```

---

## 3. Подготовка тестовых данных

1. Войти в админку → **Блюда** — создать 2–3 блюда с ценой.
2. **Меню недели** (`/admin/menu-week`) — открыть текущую неделю.
3. **Редактировать день** — добавить блюда, включить **«Опубликовано»**.
4. Seed уже создал точку выдачи «Хануман».

Без опубликованного меню API `/api/menu` вернёт пустой список.

---

## 4. Тестирование API (curl)

Базовый URL: `http://localhost:8080`

### Меню

```bash
curl -s http://localhost:8080/api/menu | jq
```

### Создание заказа

Подставь реальные `menu_day_dish_id` и `pickup_date` из `/api/menu`:

```bash
curl -s -X POST http://localhost:8080/api/orders \
  -H 'Content-Type: application/json' \
  -d '{
    "phone": "+79123456789",
    "name": "Тест",
    "pickup_date": "2026-06-14",
    "personal_data_consent": true,
    "items": [{ "menu_day_dish_id": 1, "quantity": 2 }]
  }' | jq
```

Ожидаем **201**, статус `pending_payment`, блок `payment`, поля `repeat_token` / `repeat_url`.

Без согласия — **422** `consent_required`.

### Статус заказа

```bash
curl -s http://localhost:8080/api/orders/<order_uuid> | jq
```

### Повтор заказа

Подставляются только **телефон, имя и точка выдачи** из исходного заказа. Дату, блюда и комментарий клиент указывает заново.

```bash
# превью (контакты + точка выдачи)
curl -s "http://localhost:8080/api/orders/repeat/<repeat_token>" | jq

# создать новый заказ
curl -s -X POST http://localhost:8080/api/orders/repeat/<repeat_token> \
  -H 'Content-Type: application/json' \
  -d '{
    "pickup_date": "2026-06-15",
    "items": [{ "menu_day_dish_id": 1, "quantity": 2 }]
  }' | jq
```

### Оплата (generic webhook)

```bash
curl -s -X POST http://localhost:8080/api/payment/webhook \
  -H 'Content-Type: application/json' \
  -H "X-Payment-Token: dev_webhook_secret" \
  -d '{
    "order_uuid": "<uuid>",
    "amount": 70000
  }' | jq
```

`amount` — в **копейках**. После успеха статус заказа → `paid`.

Провайдеры Sber / YooKassa: `POST /api/payment/sber/webhook`, `/api/payment/yookassa/webhook` — см. [backend.md](backend.md#оплата).

---

## 5. Тестирование админки и уведомлений

1. Создай заказ через API (канал `web` или `telegram`).
2. Подтверди оплату webhook-ом → статус `paid`.
3. **Кухня** `/admin/kitchen?date=YYYY-MM-DD`:
   - batch «Готов» → статус `ready`
   - для заказов из TG/VK клиент получит push (если настроены токены)

Push работает только если:

- `order.channel` = `telegram` или `vk`
- у клиента заполнен `telegram_id` / `vk_id`
- в `.env.local` заданы `TELEGRAM_BOT_TOKEN` / `VK_GROUP_TOKEN`

---

## 6. Telegram-бот (локально)

### Настройка

```dotenv
TELEGRAM_BOT_TOKEN=123456:ABC...
TELEGRAM_ADMIN_CHAT_ID=ваш_chat_id
PRIVACY_POLICY_URL=https://example.com/privacy
DEFAULT_URI=http://localhost:8080
```

### Webhook

Telegram принимает webhook только по **HTTPS**. Локально — через туннель:

```bash
# пример с ngrok
ngrok http 8080
```

```bash
curl "https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://<ngrok-host>/api/bot/telegram/webhook"
```

### Сценарий проверки

1. `/start` → inline-кнопки «Меню» / «Корзина»
2. «Меню» → выбор дня → блюда (inline) → «Оформить»
3. Подтверждение имени из профиля Telegram («✅ Верно» / «✏️ Другое»)
4. Комментарий текстом или «Без комментария»
5. Кнопка «📱 Отправить телефон» (если номера ещё нет)
6. Сообщение с номером заказа и реквизитами

Команды: `/menu`, `/cart`, `/repeat <token>`.

---

## 7. VK-бот (локально)

```dotenv
VK_GROUP_TOKEN=...
VK_CONFIRMATION_SECRET=строка_из_настроек_callback
```

Callback URL в сообществе VK: `https://<домен>/api/bot/vk/callback`

При `type: confirmation` API вернёт строку `VK_CONFIRMATION_SECRET`.

В разделе **Callback API → Типы событий** включите **`message_event`** (нажатия inline-кнопок).

### Сценарий проверки

1. «начать» или кнопка **«📋 Меню»**  
2. Кнопка с датой → меню блюд (кнопки по блюдам)  
3. **«✅ Оформить»** → отправить телефон текстом `+79123456789` (если ещё не сохранён)  
4. Получить номер заказа и реквизиты оплаты  

Текстовые команды (`меню`, `корзина`, дата `YYYY-MM-DD`, номер блюда) тоже работают.

---

## 8. PHPUnit

```bash
docker compose -f docker-compose.yml exec php vendor/bin/phpunit
```

Тесты: cutoff, нормализация телефона, парсинг webhook Sber.

Добавлять новые тесты в `tests/`.

---

## 9. Полезные команды

```bash
# все API-маршруты
docker compose -f docker-compose.yml exec php bin/console debug:router | grep api

# логи
docker compose -f docker-compose.yml logs -f php

# новая миграция после изменения Entity
docker compose -f docker-compose.yml exec php bin/console doctrine:migrations:diff
docker compose -f docker-compose.yml exec php bin/console doctrine:migrations:migrate --no-interaction

# Twig
docker compose -f docker-compose.yml exec php bin/console lint:twig templates/
```

---

## 10. Переменные окружения (полный список)

| Переменная | Обязательно | Назначение |
|---|---|---|
| `APP_SECRET` | да | Symfony |
| `DATABASE_URL` | да | MySQL (в Docker уже в compose) |
| `DEFAULT_URI` | да | базовый URL для ссылок в уведомлениях |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | да | админка |
| `APP_TIMEZONE` | нет | по умолчанию `Asia/Yekaterinburg` |
| `ORDER_CUTOFF_HOUR` | нет | `18` |
| `ORDER_MENU_HORIZON_DAYS` | нет | `7` |
| `PAYMENT_WEBHOOK_SECRET` | да* | generic webhook |
| `PAYMENT_QR_URL` / `PAYMENT_CARD` | нет | блок оплаты в API |
| `SBER_WEBHOOK_SECRET` | нет | webhook Сбера |
| `YOOKASSA_WEBHOOK_SECRET` | нет | webhook ЮKassa |
| `TELEGRAM_BOT_TOKEN` | нет | бот |
| `TELEGRAM_ADMIN_CHAT_ID` | нет | алерт о новом заказе |
| `VK_GROUP_TOKEN` | нет | бот VK |
| `VK_CONFIRMATION_SECRET` | нет | VK Callback confirmation |
| `PRIVACY_POLICY_URL` | нет | текст согласия ПДн |

\* для тестов оплаты локально — любая строка в `.env.local`, та же в заголовке `X-Payment-Token`.

---

## 11. Production

См. [deploy.md](deploy.md) и скрипт [`deploy.sh`](../deploy.sh).

Кратко:

1. `.env.local` на сервере (не в git)
2. `composer install --no-dev`, `migrations:migrate`, `cache:clear --env=prod`
3. Nginx → `public/`
4. HTTPS webhook для Telegram, VK, оплаты
5. Cron (этап 8): `app:orders:complete-pickup-day`

---

## 12. Частые проблемы

| Симптом | Решение |
|---|---|
| `consent_required` | передать `personal_data_consent: true` (только канал `web`) |
| `cutoff_passed` | заказ на день D только до 18:00 дня D−1 (Екатеринбург) |
| Пустое меню | опубликовать день в админке |
| Webhook 401 | проверить `X-Payment-Token` = `PAYMENT_WEBHOOK_SECRET` |
| TG не отвечает | webhook по HTTPS, токен в `.env.local`, `cache:clear` |
| Push не приходит | канал заказа TG/VK + id клиента + токены бота |

---

## 13. Git и секреты

- **Не коммить:** `.env`, `.env.local`, пароли, токены ботов.
- Шаблон: `.env.example` (можно коммитить).
- Перед PR: `phpunit`, `cache:clear`, проверить миграции.
