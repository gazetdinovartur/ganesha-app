# Вегетарианское предзаказное питание · Хануман

Система приёма заказов на регулярное вегетарианское питание с самовывозом в центре йоги «Хануман» (Екатеринбург).

Этот каталог — **git-репозиторий приложения**. Родительская папка `бизнес-проект/` (уровень выше) — рабочее пространство проекта целиком: бизнес-план, контекст, этот код. Там **нет** отдельного git.

---

## Продукт (MVP)

**Один поток:** регулярное предзаказное питание в партнёрском пространстве (Хануман).

**Не в MVP:** событийный кейтеринг (ретриты) — ведётся вручную с организаторами, без экранов в админке.

### Каналы заказа

| Канал | Роль |
|---|---|
| Сайт | Основной UX: календарь меню, корзина, оплата, статус |
| Telegram-бот | Дублирует сайт (webhook → тот же backend) |
| VK-бот | Дублирует сайт (webhook → тот же backend) |

### Бизнес-правила

| Параметр | Значение |
|---|---|
| Горизонт меню | до **7 дней** вперёд |
| Публикация | только дни с `isPublished = true` (может быть меньше 7) |
| Cutoff | заказ на день **D** до **18:00** дня **D−1** (Europe/Yekaterinburg) |
| Самовывоз | точка выдачи `PickupPoint` (MVP: Хануман) |
| Телефон на сайте | **обязателен** |
| Оплата MVP | перевод по QR / СБП → подтверждение |
| Email клиенту | нет |
| Очереди / Redis / RabbitMQ | нет |
| Экспорт в Google Sheets | нет |

### Страница «О нас»

Адрес Ханумана, часы выдачи, cutoff 18:00, краткая философия проекта.

---

## Статусы заказа

| Код | Клиент видит | Когда |
|---|---|---|
| `pending_payment` | Ожидает оплаты | После оформления |
| `paid` | Заказ принят, готовим | Оплата подтверждена |
| `ready` | Можно забирать в Ханумане | Коробка отвезена (массово или выборочно) |
| `completed` | Спасибо | Выдан / день закрыт |
| `cancelled` | Отменён | До cutoff или вручную |

**Поля дат:** `paidAt` — при переходе в `paid`. `readyAt` и `completedAt` **не используем** на MVP (меньше ручных действий).

### Подтверждение оплаты

1. Клиент нажимает **«Я оплатил»** → заказ остаётся `pending_payment`, фиксируется `paymentClaimedAt`.
2. **Автоматически** (фаза 2): webhook банка / API СБП → `paid` + `paidAt`.
3. **MVP:** вы в админке подтверждаете одним кликом → `paid` + `paidAt` (кнопка «Подтвердить оплату»).

### Массовые операции (экран «Кухня»)

- Сводка порций на день: «Суп × 8, Основное × 6».
- Чекбоксы по заказам + кнопки смены статуса.
- **«Все paid → ready»** за день — с уведомлениями всем клиентам (TG/VK + страница статуса).
- Опционально (cron): `app:orders:complete-pickup-day` — все `ready` за вчера → `completed`.

---

## Идентификаторы

| Поле | Назначение |
|---|---|
| `Order.id` | Внутренний PK |
| `Order.humanNumber` | **123, 124…** — для сообщений в TG/VK |
| `Order.uuid` | Публичные ссылки `/order/{uuid}`, статус |
| `Order.repeatToken` | Magic-link повторного заказа (**бессрочно**, с редактированием) |

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

### `Dish` — справочник блюд

- `name`, `shortDescription`, `price` (копейки), `photoPath`, `isActive`, `sortOrder`
- `composition` (JSON) — удобное редактирование в админке (форма, не сырой JSON):

```json
{
  "weight_g": 350,
  "ingredients": ["чечевица", "морковь"],
  "allergens": [],
  "note": "лёгкий, без острого"
}
```

### `MenuDay` — день календаря

- `date` (unique), `isPublished`, `note`
- Связь `MenuDayDish` — **редактируется на одной странице** `/admin/menu-day/{id}` (день + блюда)

### `MenuDayDish`

- `dish`, `priceOverride`, `sortOrder`, `isAvailable`
- `orderedPortions` — счётчик, **+qty при создании заказа**
- `maxPortions` — **не используем** на MVP

### `Customer`

- `phone` (unique, обязателен для web), `name`
- `telegramId`, `vkId` — для ботов и повторных заказов
- `defaultComment`

### `Order`

- `uuid`, `humanNumber`, `customer`, `pickupDate`, `pickupPoint`
- `channel`: `web` | `telegram` | `vk`
- `status`, `totalAmount` (копейки), `comment`, `repeatToken`
- `paidAt`, `paymentClaimedAt`, `createdAt`

### `OrderItem`

- `order`, `quantity`
- `dishSnapshot` (JSON): `{ "dish_id": 1, "name": "Суп", "unit_price": 35000 }`
- **`lineTotal` не храним** — вычисляется: `quantity * dishSnapshot.unit_price`

---

## Архитектура кода

```
src/
  Entity/
  Enum/
  Repository/
  Service/
    OrderService.php
    MenuService.php
    PaymentService.php
    NotificationService.php   # синхронно, без очередей
  Controller/
    Web/
    Admin/                  # кастом: menu-day, kitchen
    Api/Bot/
  Bot/
    Dto/CreateOrderDto.php
    TelegramHandler.php
    VkHandler.php
  Form/                     # composition, menu-day
```

**Стек:** PHP 8.5 · Symfony 8.x · EasyAdmin 5 · MySQL 8 · Twig · Docker (локально).

**Принцип:** контроллеры и боты тонкие → `CreateOrderDto` → `OrderService::create()`.

### Админка

| Задача | Где |
|---|---|
| Блюда (CRUD + composition) | EasyAdmin → «Блюда» |
| Заказы, подтверждение оплаты | EasyAdmin → «Заказы» |
| Клиенты (просмотр) | EasyAdmin → «Клиенты» |
| Календарь меню (7 дней) | `/admin/menu-week` |
| День меню + блюда дня | `/admin/menu-day/{id}/edit` |
| Кухня + batch-статусы | `/admin/kitchen?date=YYYY-MM-DD` |
| Точки выдачи | EasyAdmin → «Точки выдачи» |
| Авторизация | `/admin/login` (Symfony Security, один admin) |

---

## Этап 2: админка ✓

Реализовано: EasyAdmin 5, кастомные экраны меню и кухни, seed-команды.

### Seed-команды

| Команда | Что делает |
|---|---|
| `app:seed:admin` | Admin из `ADMIN_EMAIL` / `ADMIN_PASSWORD` в `.env.local` (создаёт или обновляет пароль) |
| `app:seed:pickup-point` | Точка «Хануман» (Щорса 37А), если ещё нет |
| `app:seed` | Обе команды подряд |

Перед `app:seed:admin` задай в `.env.local` реальные `ADMIN_EMAIL` и `ADMIN_PASSWORD` (не `change_me`).

### Маршруты админки

| URL | Назначение |
|---|---|
| `/admin/login` | Вход |
| `/admin` | Главная (быстрые ссылки) |
| `/admin/menu-week` | Неделя меню: 7 дней, навигация ← → |
| `/admin/menu-day/{id}/edit` | Редактирование дня: публикация, блюда, цены дня |
| `/admin/kitchen` | Кухня: сводка порций, batch-статусы заказов |
| `/admin?crudAction=index&crudControllerFqcn=…` | CRUD через EasyAdmin (блюда, заказы, клиенты…) |

### Рабочий процесс: меню

1. **Блюда** — справочник в EasyAdmin (название, цена, состав через форму, не JSON).
2. **Меню недели** — открыть `/admin/menu-week`; система создаёт пустые `MenuDay` на 7 дней от выбранной даты.
3. **Редактирование дня** — кнопка «Редактировать»: добавить блюда, переопределить цену дня, включить `isPublished`.
4. Клиентский сайт (этап 3) покажет только опубликованные дни.

### Рабочий процесс: кухня

Экран `/admin/kitchen?date=2026-06-13`:

- **Сводка порций** — по всем заказам дня и отдельно по оплаченным (`paid`).
- **Выборочные действия** — чекбоксы + кнопки: подтвердить оплату, → ready, → completed, отменить.
- **Массовые** — «Все paid → ready за день», «Все ready → completed за день».

Подтверждение оплаты также доступно в карточке заказа (EasyAdmin → «Подтвердить оплату»).

### Сервисы этапа 2

| Класс | Назначение |
|---|---|
| `OrderStatusService` | Смена статуса, `paidAt`, batch по id и по дате |
| `MenuDayService` | Создание/получение 7 дней меню от даты |
| `KitchenSummaryService` | Заказы дня и сводка порций |

---

## Локальная разработка

### Требования

- Docker + Docker Compose
- Composer 2 (опционально на хосте)

### Первый запуск

```bash
cp .env.example .env.local
# отредактируй .env.local при необходимости

docker compose -f docker-compose.yml up -d --build
docker compose -f docker-compose.yml exec php composer install
docker compose -f docker-compose.yml exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f docker-compose.yml exec php bin/console app:seed
```

`app:seed` создаёт admin-пользователя и точку выдачи «Хануман» (идемпотентно — можно запускать повторно).

Приложение: http://localhost:8080  
Admin: http://localhost:8080/admin  
Mailpit (если включён): http://localhost:8025

### Полезные команды

```bash
docker compose -f docker-compose.yml exec php bin/console cache:clear
docker compose -f docker-compose.yml exec php bin/console doctrine:migrations:diff
docker compose -f docker-compose.yml exec php bin/console doctrine:migrations:migrate
docker compose -f docker-compose.yml exec php bin/console app:seed              # admin + Хануман
docker compose -f docker-compose.yml exec php bin/console app:seed:admin        # только admin
docker compose -f docker-compose.yml exec php bin/console app:seed:pickup-point # только точка выдачи
docker compose -f docker-compose.yml logs -f php
```

---

## Деплой на production

Скрипт: [`deploy.sh`](deploy.sh) в корне репозитория.

```bash
./deploy.sh
```

Перед первым деплоем на сервере:

1. PHP 8.5+, Composer, MySQL 8
2. Клон репозитория, `.env.local` с секретами (не коммитить)
3. Nginx → `public/index.php`
4. Cron (опционально): `0 21 * * * php .../bin/console app:orders:complete-pickup-day`

### Переменные окружения (production)

| Переменная | Описание |
|---|---|
| `APP_ENV=prod` | |
| `APP_SECRET` | случайная строка |
| `DATABASE_URL` | `mysql://user:pass@127.0.0.1:3306/db?serverVersion=8.0&charset=utf8mb4` |
| `DEFAULT_URI` | `https://your-domain.ru` |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | первый admin |
| `TELEGRAM_BOT_TOKEN` | |
| `TELEGRAM_ADMIN_CHAT_ID` | уведомления вам |
| `VK_GROUP_TOKEN` / `VK_CONFIRMATION` | |
| `PAYMENT_QR_URL` или `PAYMENT_CARD` | QR / реквизиты |
| `ORDER_CUTOFF_HOUR=18` | |
| `APP_TIMEZONE=Asia/Yekaterinburg` | |

---

## Уведомления

| Событие | Сайт | TG/VK |
|---|---|---|
| Заказ создан | страница статуса | — |
| Оплата подтверждена | обновление статуса | push в бот |
| ready (можно забирать) | обновление | push в бот |
| completed | обновление | опционально |

Синхронная отправка в `NotificationService`. Ошибка API бота **не откатывает** заказ.

---

## Повторный заказ

URL: `/order/repeat/{repeatToken}`

- Подставляет прошлый состав, можно изменить.
- Токен бессрочный.
- Новый заказ → новый `uuid` и `humanNumber`.

---

## Что сознательно не делаем в MVP

- Событийный кейтеринг в админке
- OrderType / отдельные flow для event
- Очереди, Google Sheets, email клиенту
- Онлайн-касса / ЮKassa (фаза 2)
- Автослияние Customer по телефону + TG
- `maxPortions`

---

## Дорожная карта

| Этап | Содержание | Статус |
|---|---|---|
| **0** | README, Docker, сущности, миграция, deploy-script | ✓ |
| **1** | Seed: admin + точка выдачи Хануман | ✓ |
| **2** | Admin: EasyAdmin + menu-week + menu-day + kitchen batch | ✓ |
| **3** | Web: календарь, корзина, оплата, статус, «О нас» | |
| **4** | OrderService, payment flow | |
| **5** | TG + VK боты | |
| **6** | Повтор заказа, уведомления | |
| **7** | Автоподтверждение оплаты (банк/webhook), полировка | |

---

## Бизнес-план (зафиксированные дополнения)

- Канал «регулярное питание в партнёрских пространствах» (Хануман — первый якорь).
- Модель подряда с шефом: шеф — самозанятый подрядчик / гражданско-правовой договор, не трудовой; чеки и ответственность — отдельным абзацем в финальной версии плана для комиссии.

---

## Лицензия и авторство

Проект частный. Разработка и продукт — авторство команды проекта.
