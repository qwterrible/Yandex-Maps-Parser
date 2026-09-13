# Yandex Maps Parser
Сервис для работы с отзывами и данными организаций в Яндекс.Картах
Проект сделан как тестовое задание  
Основная задача — подключить карточку организации, запустить парсинг в фоне и показать рейтинг, количество оценок, количество отзывов и сами отзывы

## Стек
- Backend — Laravel 9, PHP 8.1
- Frontend — Vue 3, Composition API, Vue Router 4, Pinia
- Сборка — Vite 5
- База данных — MySQL 8
- Авторизация — Laravel Sanctum, SPA / cookie-based
- Очереди — Laravel Queue, database driver
- HTTP — Laravel HTTP Client / Guzzle

## Что есть в проекте
- Авторизация по логину и паролю
- Сид-пользователь для быстрого запуска
- Форма подключения карточки Яндекс.Карт
- Проверка ссылки на организацию
- Фоновый запуск парсинга через очередь
- Три попытки выполнения job с задержкой 30 секунд
- Получение названия организации
- Получение среднего рейтинга
- Получение количества оценок
- Получение количества отзывов
- Пагинация отзывов по 50 записей
- Защита от дублей отзывов
- Сохранение предыдущей версии изменившегося отзыва
- История запусков парсера
- Статусы `queued`, `parsing`, `done`, `failed`
- Отображение ошибки последнего запуска
- Проверка изменения HTML и структуры ответа
- Обработка `403`, `429` и пустых ответов
- Счётчики страниц и найденных отзывов во время парсинга

## Структура проекта
app/
├── Http/Controllers/Api/
│   ├── AuthController.php
│   ├── OrganizationController.php
│   └── ReviewController.php
├── Jobs/
│   └── ParseOrganizationJob.php
├── Models/
│   ├── Organization.php
│   ├── Review.php
│   └── ParseRun.php
└── Services/YandexMaps/
    ├── YandexMapsClient.php
    ├── YandexMapsParser.php
    ├── YandexUrlParser.php
    └── Exceptions/
        └── ParseException.php

database/migrations/
├── ..._create_organizations_table.php
├── ..._create_reviews_table.php
├── ..._create_parse_runs_table.php
└── ..._create_jobs_table.php

resources/js/
├── app.js
├── bootstrap.js
├── router.js
├── App.vue
└── pages/
    ├── Login.vue
    ├── Settings.vue
    └── Reviews.vue

## Локальный запуск
Проект запускался через OSPanel с Apache/Nginx и MySQL
Если используется другой веб-сервер, нужно поменять document root и URL в настройках

### Установка
bash
git clone <repo-url> yandex-maps-parser
cd yandex-maps-parser
composer install
npm install

### Создание базы
sql
CREATE DATABASE yandex_parser
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

### Настройка `.env`
Скопировать `.env.example` в `.env` и заполнить настройки

dotenv
APP_NAME="Yandex Maps Parser"
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost/laraveltz/yandex-maps-parser/public

FRONTEND_URL=http://localhost:5173

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=yandex_parser
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
SESSION_DRIVER=file
CACHE_DRIVER=file

SANCTUM_STATEFUL_DOMAINS=localhost,localhost:5173,127.0.0.1,127.0.0.1:5173
SESSION_DOMAIN=localhost

YANDEX_USER_AGENT="Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
YANDEX_PROXY=
YANDEX_TIMEOUT=20
YANDEX_THROTTLE_MIN_MS=400
YANDEX_THROTTLE_MAX_MS=1200
YANDEX_MAX_PAGES=20

Самое важное здесь
- `APP_URL` — адрес Laravel
- `FRONTEND_URL` — адрес Vite
- `SANCTUM_STATEFUL_DOMAINS` — домены, с которых разрешены запросы через Sanctum
- `SESSION_DOMAIN` — домен для cookie
- `QUEUE_CONNECTION` должен быть `database`, потому что парсинг выполняется в фоне
- `YANDEX_*` вынесены в конфиг, чтобы значения не были захардкожены в коде

### Генерация ключа
bash
php artisan key:generate

### Миграции и сиды
bash
php artisan migrate
php artisan db:seed
После этого создаётся пользователь: admin@example.com
Пароль: admin
Также `ReviewsSeeder` добавляет 120 тестовых отзывов, чтобы можно было сразу проверить пагинацию и список отзывов

### Запуск frontend
bash
npm run dev
После запуска Vite будет доступен по адресу
http://localhost:5173

### Запуск очереди
В отдельном терминале
bash
php artisan queue:work --tries=3
Без worker задачи останутся в статусе `queued`
Laravel при работе через OSPanel отдаётся веб-сервером, поэтому `php artisan serve` в таком варианте не нужен

### Production build
bash
npm run build
После сборки frontend попадёт в
public/build
После этого приложение можно отдавать с одного Laravel-домена

## Как работает парсинг
Официального API Яндекс.Карт для этой задачи нет, поэтому здесь два разных подхода

### Метаданные
`YandexMapsClient::fetchOrgPage()` делает GET-запрос к странице организации
https://yandex.ru/maps/org/{id}/reviews/
Из HTML достаются
- название
- средний рейтинг
- количество оценок
- количество отзывов
Перед разбором проверяется, что страница похожа на ожидаемую
Проверяются маркеры
ratingValue
ratingCount
businessRating
Если ничего из этого нет, выбрасывается `ParseException::layoutChanged`
Это сделано специально, чтобы изменение страницы не превращалось в нули и пустые данные
### Отзывы
Отзывы идут через внутренний endpoint Яндекса
https://yandex.ru/maps/api/business/fetchReviews
Сначала запрос возвращает `csrfToken` и cookies
Потом отправляется следующий запрос уже с токеном
Проблема в том, что для получения самих отзывов Яндекс дополнительно ждёт HMAC-подпись, которая создаётся на стороне JavaScript
Без неё вместо отзывов снова приходит `csrfToken`
В коде это проверяется и превращается в понятную ошибку

### Почему HMAC здесь не реверсился
Я не стал повторять HMAC-логику из обфусцированного JavaScript
Она может меняться, и поддерживать такое решение на backend будет неудобно
Поэтому в тестовой версии сделано разделение
- метаданные реально парсятся с Яндекс.Карт
- отзывы заполняются через `ReviewsSeeder`
- вся остальная логика с отзывами работает уже как с обычными данными
Для production я бы использовал headless-браузер, например Playwright

## Что происходит при ошибках
Есть несколько основных вариантов

| Код | Тип | Что произошло |
|---|---|---|
| `1001` | `layoutChanged` | Изменилась HTML или JSON структура |
| `1002` | `blocked` | `403`, `429` или блокировка |
| `1003` | `empty` | Источник вернул пустой ответ |

При ошибке
- в `parse_runs` сохраняется неудачный запуск
- в `organizations.parse_error` сохраняется причина
- полный trace пишется в `storage/logs/laravel.log`
- job повторяется до трёх раз
- между попытками используется backoff 30 секунд
## Очередь и масштабирование
Парсить всё внутри HTTP-запроса нельзя
Например, если будет 50 карточек по 600 отзывов, это уже около 30000 отзывов
Поэтому парсинг вынесен в
ParseOrganizationJob
Job реализует `ShouldQueue`
Контроллер только ставит задачу в очередь и сразу возвращает ответ
Во время работы в `parse_runs` обновляются
- статус
- количество страниц
- количество отзывов
- ошибка
- дополнительные данные

Frontend раз в три секунды спрашивает
/api/organization/status
## Защита от дублей
Основной ключ для отзыва
organization_id + external_id
На него есть уникальный индекс
Если отзыв уже есть и не поменялся, он просто обновляется без создания копии
Если отзыв изменился, старое значение сохраняется в `reviews.previous`
Новое значение записывается в основную запись, а дата изменения — в `changed_at`
## Что хранится в базе
### `organizations`
Здесь хранится
- организация
- ссылка
- название
- рейтинг
- количество оценок
- количество отзывов
- статус последнего парсинга
- текст ошибки
- fingerprint схемы
### `reviews`
Здесь хранится
- организация
- внешний ID отзыва
- автор
- рейтинг
- текст
- предыдущая версия
- дата изменения
### `parse_runs`
Здесь хранится история запусков
- статус
- количество страниц
- количество отзывов
- ошибка
- метаданные
За счёт этого можно посмотреть, что было во время конкретного запуска
Например
До парсинга
Рейтинг: 4.8
Отзывы: 90
и потом
После парсинга
Рейтинг: 4.7
Отзывы: 96
Эти данные сохраняются в `parse_runs.meta`
## Что уже сделано для защиты от блокировок
Между страницами есть случайная задержка от 400 до 1200 мс
Количество страниц ограничено через
dotenv
YANDEX_MAX_PAGES=20

Также используются обычные браузерные заголовки
Есть поддержка прокси через
dotenv
YANDEX_PROXY=

Ответы `403` и `429` обрабатываются отдельно

## Что бы я добавил для production

- Redis для ограничения количества запросов
- отдельную очередь для парсинга
- разные приоритеты для новых и старых карточек
- ротацию User-Agent
- пул прокси
- экспоненциальный backoff
- обработку `Retry-After`
- остановку парсинга при массовой блокировке
- уведомление администратора при проблемах
- хранение cookies в рамках одной сессии
- headless-браузер для отзывов
- отдельную историю снимков организации
- realtime обновление статуса вместо polling
- тесты для API и парсера
- Sentry и метрики
- Docker Compose
- админку для ручного запуска парсинга

## Почему выбран такой подход
Для метаданных HTTP-парсинг получается быстрым и не требует отдельного сервиса
С отзывами ситуация другая, потому что внутренний endpoint защищён и зависит от JavaScript
Поэтому в текущем варианте я оставил реальный парсинг метаданных и отдельно показал, как должна работать остальная часть системы
Если развивать проект дальше, логичнее вынести получение отзывов в отдельный сервис на Node.js + Playwright
Схема тогда будет примерно такая

Laravel
  ↓
Queue
  ↓
Parser Service
  ↓
Playwright
  ↓
Yandex Maps
Такой вариант проще поддерживать, чем постоянно повторять внутреннюю клиентскую логику Яндекса


<p align="center">
  <img src="https://github.com/user-attachments/assets/4620db7c-c15e-4936-9818-bf941754c5e4" alt="Экран логина" width="800" style="width:100%; max-width:800px; height:auto; display:block; margin:0 auto 16px auto;" />
  <img src="https://github.com/user-attachments/assets/73f66722-4e6e-4c9d-8d29-4c402e4d6379" alt="Страница настроек" width="800" style="width:100%; max-width:800px; height:auto; display:block; margin:0 auto 16px auto;" />
  <img src="https://github.com/user-attachments/assets/44088a7e-f210-4215-bc5c-4421810f30ef" alt="Страница отзывов" width="800" style="width:100%; max-width:800px; height:auto; display:block; margin:0 auto 16px auto;" />
</p>
