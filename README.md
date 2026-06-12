# Ganesha · предзаказное питание · Хануман

Система приёма заказов на регулярное вегетарианское питание с самовывозом в центре йоги «Хануман» (Екатеринбург).

Этот каталог — **git-репозиторий приложения** (Symfony 8, PHP 8.5, EasyAdmin 5, MySQL, Docker).  
Родительская папка `бизнес-проект/` — рабочее пространство целиком (бизнес-план, контекст); там **нет** отдельного git.

---

## Документация

| Раздел | Описание |
|---|---|
| [**План работ**](docs/roadmap.md) | Этапы, чеклисты, что в scope MVP |
| [**Продукт**](docs/product.md) | Бизнес-правила, статусы, оплата без ручных кнопок |
| [**Backend**](docs/backend.md) | Архитектура, модель данных, **API оплаты** |
| [**Админка**](docs/admin.md) | EasyAdmin, меню, кухня, seed |
| [**Deploy**](docs/deploy.md) | Docker, первый запуск, production |
| [**Разработка**](docs/development.md) | **Онбординг: API, боты, тесты, curl** |

---

## Быстрый старт

```bash
cp .env.example .env.local
docker compose -f docker-compose.yml up -d --build
docker compose -f docker-compose.yml exec php composer install
docker compose -f docker-compose.yml exec php bin/console doctrine:migrations:migrate --no-interaction
docker compose -f docker-compose.yml exec php bin/console app:seed
```

- Приложение: http://localhost:8080  
- Админка: http://localhost:8080/admin  

Подробнее: [docs/deploy.md](docs/deploy.md) · [docs/development.md](docs/development.md) (тестирование API и ботов).

---

## Тесты

```bash
docker compose -f docker-compose.yml exec php vendor/bin/phpunit
```

## Ключевые решения

- **Оплата:** автоматически через webhook/API провайдера → `paid`.
- **Cutoff:** 18:00 дня D−1 для заказа на день D (`Asia/Yekaterinburg`).
- **Каналы MVP:** сайт + TG + VK (боты реализованы, см. [development.md](docs/development.md)).

---

## Лицензия

Проект частный. Разработка и продукт — авторство команды проекта.
