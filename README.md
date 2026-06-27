# Workbench

**🇬🇧 English** · [🇷🇺 Русский](README.ru.md)

![License](https://img.shields.io/badge/license-MIT-green)
![PHP](https://img.shields.io/badge/PHP-8.4-777BB4)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20)
![Vue](https://img.shields.io/badge/Vue-3-42b883)
[![CI](https://github.com/jazz-max/workbench/actions/workflows/ci.yml/badge.svg)](https://github.com/jazz-max/workbench/actions/workflows/ci.yml)

**Self-hosted dashboard to run, monitor and parameterize background jobs on Laravel.**

Write a class with a `run()` method — Workbench gives it a web UI: one-click runs, a parameter form, **live logs streamed over WebSocket**, and input/output file handling. Scrapers, importers, converters, report generators — anything you'd otherwise run from cron or the CLI with no UI.

- 🧩 **Write a class → get a UI.** Subclass `BaseServlet`, implement `run()`, register it in JSON. No controllers, no Blade, no per-task admin page.
- 📡 **Live logs over WebSocket.** Each job streams its output into the browser in real time (Laravel Reverb) — no SSH-ing in to `tail` a log.
- 🗂 **File I/O & param forms built in.** Upload inputs, download results (single file or ZIP), forms rendered from a JSON definition.

> ⚠️ If you use Workbench for web scraping, respect each site's terms of service and the law. Scrape responsibly and only where permitted.

## Interface

Servlet list — live search (`⌘/Ctrl + K`), dark mode, sidebar by category:

![Servlet list](docs/screenshots/02-servlets.png)

Servlet page — parameters, one-click run and **live WebSocket log streaming**:

![Live servlet logs](docs/screenshots/04-live-logs.png)

<details>
<summary>More screenshots</summary>

<br>

Servlet page (ready to run):

![Servlet page](docs/screenshots/03-servlet.png)

Login (Laravel Breeze):

![Login](docs/screenshots/01-login.png)

</details>

## Use cases

Workbench fits any **"operator presses a button → task runs → watch the logs → grab the output"** workflow:

- 🕷 **Web scraping / price & feed monitoring** — the headline example; ships with a demo crawler.
- 📥 **Data imports** — CSV/XLSX/feed → your DB or storage, with a form for source and credentials.
- 🔁 **File conversions** — batch-transform uploaded files (demo CSV converter included).
- 📊 **Report / export generation** — produce CSVs and exports on demand (demo report generator included), no one-off admin page.
- 🧰 **Internal tools** — give a non-developer a button + form to run a parameterized task themselves.
- ⏱ **Cron + CLI with a UI** — keep your scheduled scripts, but give them a dashboard, live logs and file handling.

## Features

- 🧩 **Servlets** — the unit of work: subclass `BaseServlet`, implement `run()`. Three demos included (a network scraper, a file converter, a report generator).
- 📡 **Live logs** — `log()/notice()/warn()/error()/debug()` stream to the browser in real time (Laravel Reverb / WebSocket).
- 🗂 **Files** — upload inputs, download results (single or ZIP), clear `in`/`out` per servlet and per user.
- ⚙️ **Parameters** — text/password/checkbox forms declared in `resources/data/servlets.json`; values saved per user.
- 🔐 **Auth** — Laravel Breeze (login / register / password reset) on the same Inertia + Vue stack.
- 🌓 **UI** — responsive sidebar, dark mode, command palette (`⌘/Ctrl + K`), hide inactive servlets.
- 🛠 **HTTP client for scrapers** (`HasHttpClient`, optional) — Laravel HTTP + cookie jar, retry/backoff, Symfony DomCrawler, CSV & encoding helpers.

## Stack

- **Backend:** PHP 8.4, Laravel 13
- **Frontend:** Inertia.js + Vue 3 + Tailwind CSS 4 (Vite)
- **Realtime:** Laravel Reverb (WebSocket)
- **Auth:** Laravel Breeze (Inertia/Vue)

## Quick start

### Local (requires PHP 8.4, Composer, Node 20+)

```bash
git clone https://github.com/jazz-max/workbench.git && cd workbench
cp .env.example .env

composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed      # creates a demo user (below)

npm install
npm run build                   # or `npm run dev` while developing

# in separate terminals:
php artisan serve               # http://localhost:8000
php artisan reverb:start        # WebSocket server for live logs
```

Demo user from the seeder: **`test@example.com` / `password`** (or register a new one at `/register`).

### Docker

```bash
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install && ./vendor/bin/sail npm run build
```
App: `http://localhost:8083` (port configurable via `APP_PORT`).

## Writing a servlet

1. Create a class in `app/Servlets/` extending `App\Servlets\BaseServlet` and implement `run(): bool`.
2. Register it in `resources/data/servlets.json` — **with a fully-qualified class name** (backslash included), otherwise it won't appear in the UI.

```php
<?php

namespace App\Servlets;

class GenerateReport extends BaseServlet
{
    public function run(): bool
    {
        $this->notice('Starting…');           // → live log in the UI

        $rows = (int) ($this->params->rows ?? 100);
        $path = $this->params->outputPath . 'report.csv';

        $h = fopen($path, 'w');
        fputcsv($h, ['id', 'value']);
        for ($i = 1; $i <= $rows; $i++) {
            fputcsv($h, [$i, mt_rand(1, 1000)]);
        }
        fclose($h);

        $this->notice("Done: {$rows} rows written");
        return true;
    }
}
```

```json
{
  "My jobs": {
    "generate-report": {
      "classname": "App\\Servlets\\GenerateReport",
      "title": "Generate report",
      "url": "",
      "description": "What it does",
      "params": [
        { "name": "rows", "label": "Rows", "type": "text", "value": "100" }
      ]
    }
  }
}
```

For **network** jobs, add `use App\Servlets\Concerns\HasHttpClient;` for an HTTP client (cookie jar, retry, DomCrawler) — see `app/Servlets/DemoBooksScraper.php`.

Included demos: `DemoBooksScraper.php` (network scraper), `DemoCsvConverter.php` (file converter), `DemoReportServlet.php` (report generator, runs out of the box).

### Useful `BaseServlet` / `HasHttpClient` API

| Method | Purpose |
|---|---|
| `log/notice/warn/error/debug($msg)` | write to the live UI log (and `work.log`) |
| `$this->params->{inputPath,outputPath,coockiePath}` | servlet file paths |
| `clearOutputDir()` / `clearin()` | clear results / input files |
| `loadLocalParams()/saveLocalParams()` | persistent state (`local_params.json`) |
| `initHttp($base)` / `fetchCrawler($uri)` / `httpGet/httpPost` | HTTP + DomCrawler *(HasHttpClient)* |
| `writeCsv($dir,$name,$rows)` / `clean()` / `processPrice()` | processing & output *(HasHttpClient)* |

For non-UTF-8 sources set `protected ?string $sourceEncoding = 'windows-1251';`, for output files `protected string $outputEncoding = 'windows-1251';` (everything defaults to UTF-8).

## How it compares

**For scraping** — most runners in this space are Python/Go; Workbench is the PHP/Laravel-native option:

| | Workbench | Scrapyd / Gerapy / Scrapydweb | Crawlab |
|---|---|---|---|
| Scraper language | **PHP** (Guzzle, Roach PHP, DomCrawler) | Python (Scrapy) | any (CLI) |
| Stack | Laravel + Vue | Python | Go + MongoDB |
| Live logs | **WebSocket, real-time** | polling / files | yes |
| Extra runtime for a Laravel team | **none** | Python | Go cluster |

**For general task-running** — Workbench is operator-facing, not a queue worker:

| | Workbench | Laravel Horizon | cron + scripts |
|---|---|---|---|
| Trigger | **operator clicks Run** (with a form) | automatic, queued | scheduled |
| Built for | parameterized, on-demand tasks | background queue jobs | unattended scripts |
| UI / live logs / file I/O | **yes** | queue metrics only | none |

Use Horizon for queued background jobs; use Workbench when a human needs to launch a task, fill in parameters, watch it run and download the result.

## Live logs (Reverb)

Log streaming runs over [Laravel Reverb](https://reverb.laravel.com). Fill `REVERB_APP_*` and `VITE_REVERB_*` in `.env`, run `php artisan reverb:start`, and make sure `BROADCAST_CONNECTION=reverb`. Channels are public (isolated by `userId` in the channel name), so no channel authorization is needed.

## Remote access (MCP)

Companion project [**workbench-mcp**](https://github.com/jazz-max/workbench-mcp) — an MCP server that lets a Claude CLI on another machine read/write project files, search code, run allow-listed commands and git, and delegate coding to a Claude on the host. Handy for remote servlet development.

## Optional proxy feature

A reverse-proxy-to-source example ships **disabled by default** (e.g. to open a source site's cart/checkout inside the UI). To enable: uncomment `ServletController::proxy()` and the `servlet/*/proxy/*` route, optionally `validateCsrfTokens`/`trustProxies` in `bootstrap/app.php`, and set `VITE_ENABLE_PROXY=true`. The skeleton is generic — adapt it to the target site.

## Configuration

Everything is configured via `.env` (see `.env.example`). Defaults: SQLite database, DB-backed queue/cache/sessions, Reverb broadcasting.

## Tests

```bash
php artisan test       # runs on in-memory SQLite
./vendor/bin/pint      # code style (Laravel Pint)
```

## License

[MIT](LICENSE). PRs and issues welcome — see [CONTRIBUTING.md](CONTRIBUTING.md).
