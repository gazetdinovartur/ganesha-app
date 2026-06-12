# Deploy и локальная разработка

---

## Требования

- Docker + Docker Compose
- Composer 2 (опционально на хосте)

PHP-образ включает расширения: `pdo_mysql`, `zip`, **`intl`** (нужно для EasyAdmin date fields).

---

## Первый запуск

```bash
cp .env.example .env.local
# задай ADMIN_PASSWORD, PAYMENT_WEBHOOK_SECRET и др.

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
4. Cron (опционально): `0 21 * * * php …/bin/console app:orders:complete-pickup-day`

### Переменные окружения

| Переменная | Описание |
|---|---|
| `APP_ENV=prod` | |
| `APP_SECRET` | случайная строка |
| `DATABASE_URL` | MySQL 8 |
| `DEFAULT_URI` | `https://your-domain.ru` |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | admin |
| `PAYMENT_WEBHOOK_SECRET` | секрет webhook оплаты |
| `PAYMENT_QR_URL` / `PAYMENT_CARD` | реквизиты на сайте |
| `APP_TIMEZONE` | `Asia/Yekaterinburg` |
| `ORDER_CUTOFF_HOUR` | `18` |
| `TELEGRAM_BOT_TOKEN` | (этап 6) |
| `VK_GROUP_TOKEN` | (этап 6) |

---

## Webhook оплаты на production

Провайдер должен слать POST на:

```
https://your-domain.ru/api/payment/webhook
X-Payment-Token: <PAYMENT_WEBHOOK_SECRET>
```

См. [backend.md — Оплата](backend.md#оплата).
