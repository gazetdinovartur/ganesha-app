# Deploy и локальная разработка

Полное руководство для разработчика (API, боты, тесты): **[development.md](development.md)**.

---

## Требования

- Docker + Docker Compose
- Composer 2 (опционально на хосте)

PHP-образ включает расширения: `pdo_mysql`, `zip`, **`intl`** (нужно для EasyAdmin date fields).

---

## Первый запуск

```bash
cp .env.example .env.local
# задай APP_SECRET, ADMIN_PASSWORD, PAYMENT_WEBHOOK_SECRET и др.

docker compose -f docker-compose.yml up -d --build
docker compose -f docker-compose.yml exec php composer install
docker compose -f docker-compose.yml exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f docker-compose.yml exec php bin/console app:seed
```

| URL | Назначение |
|---|---|
| http://localhost:8080 | приложение |
| http://localhost:8080/admin | админка |

После изменения `docker/php/Dockerfile`:

```bash
docker compose -f docker-compose.yml up -d --build php
```

---

## Быстрая проверка после старта

```bash
# маршруты API
docker compose -f docker-compose.yml exec php bin/console debug:router | grep api

# unit-тесты
docker compose -f docker-compose.yml exec php vendor/bin/phpunit

# меню (пусто, пока не опубликованы дни в админке)
curl -s http://localhost:8080/api/menu
```

Примеры curl для заказов, оплаты, повтора — в [development.md §4](development.md#4-тестирование-api-curl).

---

## Полезные команды

```bash
docker compose -f docker-compose.yml exec php bin/console cache:clear
docker compose -f docker-compose.yml exec php bin/console doctrine:migrations:diff
docker compose -f docker-compose.yml exec php bin/console app:seed
docker compose -f docker-compose.yml logs -f php
```

---

## Production

Скрипт: [`deploy.sh`](../deploy.sh)

```bash
./deploy.sh
```

### Перед деплоем

1. PHP 8.5+, Composer, MySQL 8
2. Клон репозитория, `.env.local` с секретами (не коммитить)
3. Nginx → `public/index.php`
4. HTTPS для webhook (оплата, Telegram, VK)
5. Cron (опционально, этап 8): `0 21 * * * php …/bin/console app:orders:complete-pickup-day`

### Переменные окружения

| Переменная | Описание |
|---|---|
| `APP_ENV=prod` | |
| `APP_SECRET` | случайная строка |
| `DATABASE_URL` | MySQL 8 |
| `DEFAULT_URI` | `https://your-domain.ru` |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | admin |
| `PAYMENT_WEBHOOK_SECRET` | секрет generic webhook |
| `PAYMENT_QR_URL` / `PAYMENT_CARD` | реквизиты на сайте |
| `SBER_WEBHOOK_SECRET` | webhook Сбера |
| `APP_TIMEZONE` | `Asia/Yekaterinburg` |
| `ORDER_CUTOFF_HOUR` | `18` |
| `ORDER_MENU_HORIZON_DAYS` | `7` |
| `TELEGRAM_BOT_TOKEN` | бот |
| `TELEGRAM_ADMIN_CHAT_ID` | алерт о новых заказах |
| `VK_GROUP_TOKEN` | бот VK |
| `VK_CONFIRMATION_SECRET` | VK Callback |
| `PRIVACY_POLICY_URL` | ссылка в согласии ПДн |

---

## Webhook на production

| Сервис | URL |
|---|---|
| Оплата (generic) | `POST https://domain/api/payment/webhook` |
| Сбер | `POST https://domain/api/payment/sber/webhook` |
| Telegram | `POST https://domain/api/bot/telegram/webhook` |
| VK | `POST https://domain/api/bot/vk/callback` |

Подробнее: [backend.md](backend.md).
