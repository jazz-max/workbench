# Workbench

**Self-hosted фреймворк для запуска и мониторинга задач-скраперов** на Laravel 13 + Inertia.js + Vue 3.

Workbench даёт готовый веб-интерфейс для написанных вами «сервлетов» (парсеров, конвертеров, любых фоновых задач): запуск в один клик, **живой стрим логов** по WebSocket, управление входными/выходными файлами и формы параметров, описанные декларативно. Вы пишете класс — Workbench даёт ему UI, запуск, логирование и работу с файлами.

> ⚠️ Скрапинг чужих сайтов регулируется их условиями использования и законом. Используйте ответственно и только там, где это разрешено.

## Возможности

- 🧩 **Сервлеты** — единица работы: наследуете `BaseServlet`, реализуете `run()`. Сетевые скраперы и файловые конвертеры — два готовых примера в комплекте.
- 📡 **Живые логи** — `log()/notice()/warn()/error()/debug()` стримятся в браузер в реальном времени (Laravel Reverb / WebSocket).
- 📁 **Файлы** — загрузка входных файлов, скачивание результатов (по одному или ZIP), очистка `in`/`out` по каждому сервлету и пользователю.
- ⚙️ **Параметры** — формы (текст, пароль, чекбоксы и пр.) описываются в `resources/data/servlets.json`, значения сохраняются по пользователю.
- 🔐 **Авторизация** — штатный Laravel Breeze (логин/регистрация/сброс пароля) на том же Inertia + Vue стеке.
- 🌓 **UI** — адаптивный сайдбар, тёмная тема, командная палитра (`⌘/Ctrl + K`), скрытие неактивных сервлетов.
- 🛠 **HTTP-клиент для скраперов** (`HasHttpClient`) — Laravel HTTP + cookie-jar, retry/backoff, парсинг через Symfony DomCrawler, хелперы CSV и кодировок.

## Стек

- **Backend:** PHP 8.4, Laravel 13
- **Frontend:** Inertia.js + Vue 3 + Tailwind CSS 4 (Vite)
- **Realtime:** Laravel Reverb (WebSocket)
- **Auth:** Laravel Breeze (Inertia/Vue)

## Быстрый старт

### Локально (нужны PHP 8.4, Composer, Node 20+)

```bash
git clone <repo-url> workbench && cd workbench
cp .env.example .env

composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed      # создаст демо-пользователя (см. ниже)

npm install
npm run build                   # или `npm run dev` для разработки

# В отдельных терминалах:
php artisan serve               # http://localhost:8000
php artisan reverb:start        # WebSocket-сервер для живых логов
```

Демо-пользователь из сидера: **`test@example.com` / `password`** (либо зарегистрируйте нового через `/register`).

### Docker

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```
Приложение: `http://localhost:8083` (порт настраивается через `APP_PORT`).

## Как написать сервлет

1. Создайте класс в `app/Servlets/`, наследующий `App\Servlets\BaseServlet`, и реализуйте `run(): bool`.
2. Зарегистрируйте его в `resources/data/servlets.json` — **обязательно с FQN-именем класса** (с обратным слэшем), иначе он не появится в интерфейсе.

```php
<?php

namespace App\Servlets;

use App\Servlets\Concerns\HasHttpClient;

class MyScraper extends BaseServlet
{
    use HasHttpClient; // опционально — для сетевых задач

    public function run(): bool
    {
        $this->notice('Старт');                 // → живой лог в UI
        $this->initHttp('https://example.com/');
        $crawler = $this->fetchCrawler('catalog');

        $rows = [];
        $crawler->filter('.product')->each(function ($node) use (&$rows) {
            $rows[] = [$this->clean($node->text())];
        });

        $this->writeCsv($this->params->outputPath, 'result', $rows);
        $this->notice('Готово: ' . count($rows));

        return true;
    }
}
```

```json
{
  "Мои парсеры": {
    "my-scraper": {
      "classname": "App\\Servlets\\MyScraper",
      "title": "Мой парсер",
      "url": "https://example.com",
      "description": "Что он делает",
      "params": [
        { "name": "login", "label": "Логин", "type": "text", "value": "" }
      ]
    }
  }
}
```

Готовые примеры — `app/Servlets/DemoBooksScraper.php` (сетевой скрапер) и `app/Servlets/DemoCsvConverter.php` (файловый конвертер).

### Полезное из `BaseServlet` / `HasHttpClient`

| Метод | Назначение |
|---|---|
| `log/notice/warn/error/debug($msg)` | запись в живой лог UI (и в `work.log`) |
| `$this->params->{inputPath,outputPath,coockiePath}` | пути файлов сервлета |
| `clearOutputDir()` / `clearin()` | очистка результатов / входных файлов |
| `loadLocalParams()/saveLocalParams()` | персистентное состояние (`local_params.json`) |
| `initHttp($base)` / `fetchCrawler($uri)` / `httpGet/httpPost` | HTTP + DomCrawler |
| `writeCsv($dir,$name,$rows)` / `clean()` / `processPrice()` | обработка и запись |

Для источников не в UTF-8 задайте `protected ?string $sourceEncoding = 'windows-1251';`, для выходных файлов — `protected string $outputEncoding = 'windows-1251';` (по умолчанию всё UTF-8).

## Живые логи (Reverb)

Стриминг логов работает через [Laravel Reverb](https://reverb.laravel.com). Заполните в `.env` ключи `REVERB_APP_*` и `VITE_REVERB_*`, запустите `php artisan reverb:start` и убедитесь, что `BROADCAST_CONNECTION=reverb`. Каналы публичные (изоляция по `userId` в имени канала), отдельная channel-авторизация не нужна.

## Опциональная proxy-фича

В комплекте есть выключенный по умолчанию пример reverse-proxy к сайту-источнику (например, чтобы открыть корзину/чекаут источника прямо в UI). Чтобы включить: раскомментируйте `ServletController::proxy()` и роут `servlet/*/proxy/*`, при необходимости — `validateCsrfTokens`/`trustProxies` в `bootstrap/app.php`, и задайте `VITE_ENABLE_PROXY=true`. Скелет обобщённый — допишите под движок целевого сайта.

## Конфигурация

Все настройки — через `.env` (см. `.env.example`). По умолчанию БД — SQLite, очередь/кэш/сессии — в БД, рассылка логов — Reverb.

## Тесты

```bash
php artisan test       # тесты идут на in-memory SQLite
./vendor/bin/pint      # стиль кода (Laravel Pint)
```

## Лицензия

[MIT](LICENSE). PR и issue приветствуются — см. [CONTRIBUTING.md](CONTRIBUTING.md).
