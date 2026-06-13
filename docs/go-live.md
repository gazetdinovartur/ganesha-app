# Выход в прод: аудит, чеклист и ручные настройки

Документ фиксирует итог интенсивной разработки (июнь 2026), текущее состояние системы и **пошаговые ручные действия** перед запуском для клиентов.

Связанные документы: [roadmap.md](roadmap.md) · [development.md](development.md) · [deploy.md](deploy.md) · [product.md](product.md)

---

## 1. Итог работы (аудит)

### Что готово и работает

| Область | Состояние | Комментарий |
|---|---|---|
| **Инфраструктура** | ✓ | Docker, Symfony 8, MySQL 8, миграции, `deploy.sh` |
| **Админка** | ✓ | EasyAdmin: блюда, меню недели/дня, кухня, заказы, клиенты |
| **Публичный сайт** | ✓ | Меню на 7 дней, корзина (localStorage), мультидневный заказ, оформление, оплата, статусы |
| **OrderService** | ✓ | Cutoff 18:00, snapshot блюд, consent для web, batch-заказы |
| **API** | ✓ | `/api/menu`, `/api/orders`, repeat, payment webhooks |
| **Боты TG/VK** | ✓ код | Полный flow заказа; нужны токены и HTTPS webhook |
| **Уведомления** | ✓ код | Push клиенту (paid/ready), алерт админу в TG |
| **Тесты** | ✓ частично | 28 PHPUnit-тестов (cutoff, боты, payment adapters, OrderService) |
| **Seed** | ✓ | `app:seed` — admin, точка «Хануман»; `app:seed:menu` — демо-меню на неделю |

### Ключевые коммиты (последние дни)

- `5cc22c4` — публичный сайт: корзина, мультидневный заказ, групповая оплата (`payment_group_uuid`)
- `a128a08` — админка: русские статусы, цветные badge, UX списков, кнопка «Обновить!» на кухне
- Боты TG/VK, повтор заказа, webhook оплаты, OrderService

### Что **ещё не production-ready**

| Риск | Описание | Приоритет |
|---|---|---|
| **Оплата без webhook** | QR/карта на сайте есть, но без callback от банка заказ остаётся `pending_payment` | 🔴 высокий |
| **Пустой `.env.local`** | Нет реальных QR, токенов ботов, политики ПДн | 🔴 высокий |
| **HTTPS на prod** | Webhook TG/VK/Сбер не работают по HTTP | 🔴 высокий |
| **Cron этап 8** | `app:orders:complete-pickup-day` не реализован | 🟡 средний |
| **Групповая оплата + Сбер** | В комментарии/метаданных нужен `payment_group_uuid`, не uuid одного заказа | 🟡 средний |
| **E2E-тесты web** | Нет автоматических browser-тестов | 🟢 низкий |
| **Комплексные обеды / тарифы** | Идея зафиксирована, не в коде | 🟢 позже |

### Архитектурные решения (кратко)

- **Один заказ = один день самовывоза** — кухня и cutoff остаются простыми.
- **Мультидневная корзина** → несколько заказов + общая **группа оплаты** (`payment_group_uuid`).
- **Оплата только через webhook** — без кнопки «Я оплатил» (см. [product.md](product.md)).
- **Dev-режим**: если `PAYMENT_QR_URL` / `PAYMENT_CARD` пусты, на сайте показываются демо-реквизиты.

---

## 2. Чеклист `.env.local` (все переменные)

Скопируйте `.env.example` → `.env.local`. Ниже — что заполнить **обязательно** перед prod и **опционально**.

### Обязательно (любое окружение)

| Переменная | Пример | Как получить |
|---|---|---|
| `APP_SECRET` | `openssl rand -hex 32` | Случайная строка 32+ символов |
| `DATABASE_URL` | см. `.env.example` | MySQL; в Docker уже настроен |
| `DEFAULT_URI` | `https://food.example.ru` | Публичный URL сайта **с https** |
| `ADMIN_EMAIL` | `admin@example.com` | Логин админки |
| `ADMIN_PASSWORD` | надёжный пароль | После смены: `app:seed:admin` или смена в БД |
| `PAYMENT_WEBHOOK_SECRET` | `openssl rand -hex 24` | Секрет для `X-Payment-Token` (тесты и generic webhook) |

### Обязательно для **приёма заказов на сайте**

| Переменная | Назначение |
|---|---|
| `PAYMENT_QR_URL` | URL картинки QR (СБП) или страницы оплаты |
| `PAYMENT_CARD` | Номер карты / телефон СБП для копирования |
| `PRIVACY_POLICY_URL` | Ссылка на политику ПДн (по умолчанию `{DEFAULT_URI}/privacy`, seed проставляет автоматически) |

### Для **автооплаты** (Сбер / агрегатор)

| Переменная | Назначение |
|---|---|
| `SBER_WEBHOOK_SECRET` | Заголовок `X-Sber-Webhook-Secret` на `/api/payment/sber/webhook` |
| `YOOKASSA_WEBHOOK_SECRET` | Если подключите ЮKassa вместо/вместе со Сбером |

### Для **ботов и уведомлений**

| Переменная | Назначение |
|---|---|
| `TELEGRAM_BOT_TOKEN` | Токен от @BotFather |
| `TELEGRAM_ADMIN_CHAT_ID` | Chat ID для алертов о новых заказах |
| `VK_GROUP_TOKEN` | Ключ доступа сообщества VK |
| `VK_CONFIRMATION_SECRET` | Строка подтверждения Callback API |

### Обычно не трогают

| Переменная | Значение по умолчанию |
|---|---|
| `APP_TIMEZONE` | `Asia/Yekaterinburg` |
| `ORDER_CUTOFF_HOUR` | `18` |
| `ORDER_MENU_HORIZON_DAYS` | `7` |

### Проверка после заполнения

```bash
docker compose exec php bin/console lint:container
docker compose exec php bin/console cache:clear
docker compose exec php vendor/bin/phpunit
```

---

## 3. Ручная настройка: Telegram-бот

### 3.1. Создание бота

1. Откройте [@BotFather](https://t.me/BotFather) в Telegram.
2. Команда `/newbot` → имя и username (например `GaneshaFoodBot`).
3. Скопируйте **HTTP API Token** → `TELEGRAM_BOT_TOKEN` в `.env.local`.
4. (Опционально) `/setdescription`, `/setabouttext` — описание для клиентов.

### 3.2. Webhook (только HTTPS)

После деплоя на домен с SSL:

```bash
curl "https://api.telegram.org/bot<TELEGRAM_BOT_TOKEN>/setWebhook?url=https://ВАШ_ДОМЕН/api/bot/telegram/webhook"
```

Проверка:

```bash
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
```

Ожидается `"url": "https://…/api/bot/telegram/webhook"`, без ошибок.

### 3.3. Chat ID администратора

1. Напишите боту любое сообщение.
2. Откройте `https://api.telegram.org/bot<TOKEN>/getUpdates`.
3. Найдите `"chat":{"id":123456789}` → `TELEGRAM_ADMIN_CHAT_ID`.

Либо перешлите сообщение боту [@userinfobot](https://t.me/userinfobot).

### 3.4. Проверка сценария

1. `/start` → «Меню» → день → блюдо → «Оформить».
2. Подтверждение имени, телефон, заказ создан.
3. Новый заказ → сообщение админу в `TELEGRAM_ADMIN_CHAT_ID`.

Подробнее: [development.md §6](development.md#6-telegram-бот-локально).

---

## 4. Ручная настройка: VK-бот

### 4.1. Сообщество

1. Создайте **группу** VK (тип «Бизнес» / «Тематическое сообщество»).
2. **Управление** → **Работа с API** → **Callback API**.
3. **URL сервера:** `https://ВАШ_ДОМЕН/api/bot/vk/callback`
4. **Строка подтверждения** — придумайте и запишите в `VK_CONFIRMATION_SECRET` (то же значение в `.env.local`).
5. Нажмите «Подтвердить» — сервер должен вернуть эту строку (тип события `confirmation`).

### 4.2. Токен и права

1. **Управление** → **Работа с API** → **Ключи доступа**.
2. Создайте ключ с правами: **Сообщения сообщества**, **Управление сообществом** (по необходимости).
3. Токен → `VK_GROUP_TOKEN`.

### 4.3. Типы событий

В Callback API включите минимум:

- `message_new` — входящие сообщения
- `message_event` — нажатия inline-кнопок

### 4.4. Сообщения сообщества

**Управление** → **Сообщения** → включить «Сообщения сообщества», разрешить боту отвечать.

### 4.5. Проверка

1. Написать в сообщество «начать» или «меню».
2. Выбрать день → блюдо → оформить → телефон → номер заказа.

Подробнее: [development.md §7](development.md#7-vk-бот-локально).

---

## 5. Ручная настройка: оплата и QR (Сбер / СБП)

### 5.1. Два режима (важно понять)

| Режим | Как работает | Когда использовать |
|---|---|---|
| **A. Статический QR + карта** | Клиент переводит вручную; заказ **не** станет `paid` сам | Пилот, первые недели, малый поток |
| **B. Эквайринг + webhook** | Сбер шлёт callback → заказ `paid` автоматически | Целевой prod |

Сейчас код поддерживает **оба**, но режим B требует договора с банком и настройки callback.

### 5.2. Режим A — статический QR (быстрый старт)

1. **СберБизнес** (приложение или web) → **СБП** → **QR для оплаты** (статический).
2. Укажите назначение: «Оплата заказа Ganesha».
3. Сохраните QR как изображение → загрузите на сайт/CDN.
4. В `.env.local`:
   ```dotenv
   PAYMENT_QR_URL=https://ваш-домен.ru/static/sbp-qr.png
   PAYMENT_CARD=+7 9XX XXX-XX-XX   # или номер карты для СБП
   ```
5. Клиент указывает в комментарии **UUID заказа** или **код группы** (мультидневный заказ).

**Пока webhook не подключён:** отслеживайте поступления в банке и вручную помечайте оплату:

```bash
# Один заказ
curl -s -X POST https://ВАШ_ДОМЕН/api/payment/webhook \
  -H "Content-Type: application/json" \
  -H "X-Payment-Token: ВАШ_PAYMENT_WEBHOOK_SECRET" \
  -d '{"order_uuid":"UUID-ЗАКАЗА","amount":35000}'

# Группа заказов (сумма = общая по всем pending в группе)
curl -s -X POST https://ВАШ_ДОМЕН/api/payment/webhook \
  -H "Content-Type: application/json" \
  -H "X-Payment-Token: ВАШ_PAYMENT_WEBHOOK_SECRET" \
  -d '{"order_uuid":"UUID-ЛЮБОГО-ЗАКАЗА-ИЗ-ГРУППЫ","amount":210000}'
```

(Сумма в **копейках**. Для группы webhook найдёт остальные заказы по `payment_group_uuid`.)

### 5.3. Режим B — webhook Сбербанка

1. Подключите **интернет-эквайринг / СБП** в СберБизнес (договор, тестовый контур).
2. В настройках callback укажите:
   - URL: `https://ВАШ_ДОМЕН/api/payment/sber/webhook`
   - Заголовок: `X-Sber-Webhook-Secret: <ваш_секрет>`
3. В `.env.local`: `SBER_WEBHOOK_SECRET=<тот_же_секрет>`.
4. При создании платежа в API Сбера в **`orderNumber`** передавайте:
   - один заказ → `order.uuid`
   - группа → `payment_group_uuid` (из страницы оплаты)
5. Адаптер: `SberbankPaymentProviderAdapter` — статусы `2`, `DEPOSITED`, `PAID`.

Документация формата: [backend.md § Оплата](backend.md#оплата).

### 5.4. Проверка блока оплаты на сайте

1. Оформите тестовый заказ на http://localhost:8080 (или prod).
2. Страница `/order/{uuid}/pay` или `/order/group/{uuid}/pay` должна показывать QR и реквизиты.
3. В dev без `.env` — демо QR и карта (только для разработки).

---

## 6. Ручной чеклист перед первым реальным днём

### Инфраструктура

- [ ] Домен + HTTPS (Let's Encrypt)
- [ ] `.env.local` на сервере, **не** в git
- [ ] `APP_ENV=prod`, `composer install --no-dev`
- [ ] `doctrine:migrations:migrate`
- [ ] `app:seed` (или admin + точка выдачи уже есть)
- [ ] `app:seed:menu --force` только на dev; на prod — меню через админку

### Контент

- [ ] Блюда с фото и ценами в админке
- [ ] Меню на ближайшие 7 дней опубликовано (`isPublished`)
- [ ] Страница «О нас» актуальна (адрес, часы)
- [ ] `PRIVACY_POLICY_URL` — проставляется `app:seed:privacy` (текст на `/privacy`)

### Оплата

- [ ] `PAYMENT_QR_URL` и `PAYMENT_CARD` заполнены
- [ ] Тестовый перевод → webhook или ручной curl → статус `paid`
- [ ] Мультидневный заказ: оплата группы одним переводом

### Боты (если используете)

- [ ] Telegram webhook установлен, бот отвечает
- [ ] VK Callback подтверждён, `message_event` включён
- [ ] Алерт о новом заказе приходит в TG админу

### Операционка

- [ ] Кухня `/admin/kitchen` — проверили смену статусов
- [ ] Ответственный знает cutoff **18:00** накануне
- [ ] План на случай «оплата не пришла автоматически» (смотреть выписку + curl webhook)

---

## 7. Дальнейшие шаги разработки

### Ближайшие (1–2 недели)

1. **Production `.env`** — все переменные из §2.
2. **Реальный QR СБП** — режим A или договор Сбера (режим B).
3. **Политика ПДн** — страница + `PRIVACY_POLICY_URL`.
4. **Регистрация TG/VK** — §3–4, smoke-тест на prod.
5. **Меню на prod** — не seed, а реальные блюда через админку.

### Этап 8 (roadmap)

- [ ] Команда `app:orders:complete-pickup-day` + cron 21:00 Екатеринбург
- [ ] Логирование webhook (Monolog channel), алерт при 4xx/5xx оплаты
- [ ] Мониторинг uptime (UptimeRobot / Healthchecks)

### Продукт (идеи, не в scope)

- **Комплексный обед** — фиксированный набор блюд на день со скидкой (`MenuDay` + тип «комплекс»).
- **«На всю неделю»** — уже есть на сайте; позже: пресеты «только супы», абонемент.
- **Единая страница «Мои заказы»** по телефону (SMS-код).
- **Фото блюд** — массовая загрузка в админке.

---

## 8. Быстрые URL (prod)

| Назначение | URL |
|---|---|
| Сайт / меню | `https://domain/` |
| Админка | `https://domain/admin` |
| Кухня | `https://domain/admin/kitchen` |
| Webhook оплаты | `POST …/api/payment/webhook` |
| Webhook Сбер | `POST …/api/payment/sber/webhook` |
| Telegram | `POST …/api/bot/telegram/webhook` |
| VK | `POST …/api/bot/vk/callback` |

---

## 9. Команды на каждый день

```bash
# Утро: проверить меню и опубликовать день
# Админка → Меню недели → день → Опубликовано ✓

# В течение дня: кухня
# /admin/kitchen → дата → «Обновить!» → статусы

# Вечер: после cutoff новые заказы на завтра уже не принимаются автоматически
```

---

*Документ обновлён: июнь 2026. При изменении интеграций дополняйте этот файл и [roadmap.md](roadmap.md).*
