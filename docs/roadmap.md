# План работ

Дорожная карта разработки Ganesha — предзаказное вегетарианское питание (Хануман, Екатеринбург).

| Этап | Содержание | Статус |
|---|---|---|
| **0** | Каркас Symfony, Docker, сущности, миграция, deploy-script | ✓ |
| **1** | Seed: admin + точка выдачи «Хануман» | ✓ |
| **2** | Админка: EasyAdmin + menu-week + menu-day + kitchen | ✓ |
| **3** | Web: календарь, корзина, QR/оплата, статус заказа, «О нас» | |
| **4** | `OrderService`: создание заказа, cutoff, snapshot блюд | ✓ |
| **5** | Интеграция платёжного провайдера → webhook (см. [backend.md](backend.md#оплата)) | частично ✓ |
| **6** | TG + VK боты (дублируют web-flow) | |
| **7** | Повтор заказа (`repeatToken`), уведомления | |
| **8** | Полировка: cron `complete-pickup-day`, мониторинг | |

---

## Принципы

- **Один поток:** регулярное питание в Ханумане, без event-кейтеринга в системе.
- **Оплата только через API:** провайдер (СБП / банк / агрегатор) шлёт webhook → заказ `paid`. Без кнопки «Я оплатил» и без ручного подтверждения в админке.
- **Админка:** меню, кухня, просмотр заказов — не подтверждение оплаты.
- **Без очередей** на MVP: уведомления синхронно.

---

## Этап 3 — публичный сайт

- [ ] Bootstrap + Montserrat, без Turbo/Stimulus
- [ ] Календарь меню (7 дней, только `isPublished`)
- [ ] Корзина, обязательный телефон
- [ ] Страница оплаты: QR / реквизиты + `order_uuid` в комментарии к переводу
- [ ] Страница статуса `/order/{uuid}` (polling или refresh)
- [ ] «О нас»: адрес, часы, cutoff 18:00

## Этап 4 — OrderService

- [x] `OrderService::create()` из DTO
- [x] Проверка cutoff (18:00 D−1, `Asia/Yekaterinburg`)
- [x] `humanNumber`, `uuid`, `repeatToken`
- [x] `dishSnapshot` + increment `orderedPortions`

## Этап 5 — платёж (API)

- [x] `PaymentService` + `POST /api/payment/webhook`
- [x] Адаптеры Sber / YooKassa → `POST /api/payment/{provider}/webhook`
- [x] `NotificationService` при переходе в `paid` (лог; TG/VK — этап 7)
- [ ] Подключить конкретного провайдера в production (ключи, QR, metadata)

## Этап 6 — боты

- [ ] Telegram webhook
- [ ] VK callback API
- [ ] Общий `CreateOrderDto` → `OrderService`

## Этап 7 — повтор и уведомления

- [ ] `/order/repeat/{repeatToken}`
- [ ] Push в TG/VK при `paid`, `ready`

## Этап 8 — эксплуатация

- [ ] `app:orders:complete-pickup-day` (cron)
- [ ] Логирование webhook, алерты при ошибках оплаты
