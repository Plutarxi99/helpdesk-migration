
# HelpdeskEddy Data Migration

[![PHP Version](https://img.shields.io/badge/php-8.4-blue.svg)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/laravel-12.x-orange.svg)](https://laravel.com/)
[![Docker](https://img.shields.io/badge/docker-enabled-blue.svg)](https://www.docker.com/)
[![Redis](https://img.shields.io/badge/redis-enabled-red.svg)](https://redis.io/)

## О проекте
Проект для миграции данных между проектами в CRM **HelpdeskEddy**.  
Позволяет переносить данные из одного проекта в другой через **API HelpdeskEddy**.

Документация API: [https://helpdeskeddy.ru/api.html](https://helpdeskeddy.ru/api.html)

## Технологический стек
- PHP 8.4+  
- Laravel 12.x  
- MySQL  
- Docker  
- Redis (для ограничения запросов)

## Ключевые особенности
- Ограничение **300 запросов в минуту** к API (реализовано через макросы Laravel)
- Архитектура **Сервис-Репозиторий**
- Docker-контейнеризация
- Асинхронная обработка через очереди Laravel

## Архитектура проекта
Проект построен по принципу **Service-Repository**:

```
app/
├── Services/       # Логика работы с API и миграцией данных
├── Repositories/   # Работа с базой данных
├── Jobs/           # Очереди для асинхронной обработки
└── ...
```

- **Сервисы (Services)** — отвечают за бизнес-логику и интеграцию с API HelpdeskEddy.
- **Репозитории (Repositories)** — отвечают за работу с локальной базой данных.
- **Очереди (Jobs)** — асинхронная обработка миграций больших объемов данных.
- **Макросы и Middleware** — реализуют ограничение запросов к API.

## Ограничение запросов к API
Для предотвращения превышения лимитов API используется **Redis + макросы Laravel**:

- Максимум **300 запросов в минуту**  
- Если лимит превышен, выполнение запросов ставится в очередь до следующей минуты.

## Установка через Docker

1. Клонируйте репозиторий:
```bash
git clone <REPO_URL> helpdesk-migration
cd helpdesk-migration
```

2. Создайте файл окружения:
```bash
touch .env.helpdesk
```

```bash
touch .env
```

3. Настройте переменные в `.env.helpdesk`:

```
HELP_DESK_KEY=           # API ключ целевого проекта
HELP_DESK_DOMAIN=        # Домен целевого проекта
HELP_DESK_API_KEY_EGOR=  # API ключ проекта мигрирования
HELP_DESK_DOMAIN_EGOR=   # Домен проекта мигрирования
```

4. Запустите контейнеры:
```bash
docker-compose up -d
```

---

> Готовый проект для миграции данных между проектами CRM HelpdeskEddy.
