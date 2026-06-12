# Админка

EasyAdmin 5 + кастомные экраны меню и кухни.  
URL: http://localhost:8080/admin (локально).

**Вход:** `/admin/login` — `ADMIN_EMAIL` / `ADMIN_PASSWORD` из `.env.local`.

---

## Меню

| Раздел | Где |
|---|---|
| Главная | `/admin` |
| Меню недели | `/admin/menu-week` |
| Кухня | `/admin/kitchen?date=YYYY-MM-DD` |
| Блюда | EasyAdmin → «Блюда» |
| Дни меню (список) | EasyAdmin → «Дни меню» |
| Редактирование дня | `/admin/menu-day-form/{id}` |
| Заказы (просмотр) | EasyAdmin → «Заказы» |
| Клиенты | EasyAdmin → «Клиенты» |
| Точки выдачи | EasyAdmin → «Точки выдачи» |

---

## Меню недели

1. Открыть `/admin/menu-week` — создаются/показываются 7 дней от выбранной даты.
2. «Редактировать» → форма дня: публикация, блюда, цена дня, порядок.
3. Сначала заполнить **справочник блюд** (EasyAdmin → «Блюда»): цена, состав через форму (не JSON).

---

## Кухня

Экран `/admin/kitchen?date=…`:

- **Сводка порций** — все заказы дня и отдельно только `paid`.
- **Batch:** выбранные → `ready` / `completed` / `cancelled`.
- **Массово:** все `paid` → `ready`; все `ready` → `completed`.

Оплата в `paid` попадает **только через API** ([backend.md](backend.md#оплата)). В админке нет кнопки «Подтвердить оплату».

---

## Сервисы

| Класс | Назначение |
|---|---|
| `MenuDayService` | 7 дней меню от даты |
| `KitchenSummaryService` | заказы и сводка порций |
| `OrderStatusService` | смена статусов (кроме оплаты) |
| `PaymentService` | только webhook/API оплаты |

---

## Seed при первом запуске

```bash
docker compose -f docker-compose.yml exec php bin/console app:seed
```

См. также [deploy.md](deploy.md).
