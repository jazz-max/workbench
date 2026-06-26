<?php

namespace App\Http\Controllers;

use App\Events\ServletFinished;
use App\Events\ServletLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ServletController extends Controller
{
    public function index(): Response
    {
        $servlets = $this->loadServlets();

        $list = [];
        foreach ($servlets as $category => $items) {
            foreach ($items as $key => $servlet) {
                // Показываем только native-сервлеты (FQN classname)
                if (! str_contains($servlet['classname'] ?? '', '\\')) {
                    continue;
                }
                $list[] = [
                    'key' => $key,
                    'category' => $category,
                    'title' => $servlet['title'],
                    'url' => $servlet['url'] ?? '',
                ];
            }
        }

        return Inertia::render('Servlets/Index', [
            'servletList' => $list,
        ]);
    }

    public function show(Request $request, string $name): Response
    {
        $servlets = $this->loadServlets();

        foreach ($servlets as $category => $items) {
            if (isset($items[$name])) {
                $userId = $request->user()->getAuthIdentifier();
                $fileController = app(FileController::class);

                $servlet = array_merge($items[$name], [
                    'key' => $name,
                    'category' => $category,
                ]);

                // Merge saved params into servlet params
                $savedParams = $fileController->getSavedParams($userId, $name);
                if ($savedParams && ! empty($servlet['params'])) {
                    foreach ($servlet['params'] as &$param) {
                        // array_key_exists, а не isset — сохранённое снятое состояние
                        // приходит как null, и его тоже нужно применить.
                        if (isset($param['name']) && array_key_exists($param['name'], $savedParams)) {
                            $saved = $savedParams[$param['name']];
                            $param['value'] = $saved;
                            // Для чекбоксов отрисовка идёт по полю checked — синхронизируем.
                            if (in_array($param['type'] ?? '', ['checkbox', 'checkboxCatalog'], true)) {
                                $param['checked'] = ($saved === 'on' || $saved === 'checked' || $saved === true) ? 'checked' : '';
                            }
                        }
                    }
                    unset($param);
                }

                // Append local params (dynamic catalogs from renewcatalogs)
                $localParams = $fileController->getLocalParams($userId, $name);
                if ($localParams) {
                    // Merge saved on/off values (PHP converts dots to underscores in POST keys)
                    if ($savedParams) {
                        foreach ($localParams as &$lp) {
                            if (! isset($lp['name'])) {
                                continue;
                            }
                            $phpKey = str_replace('.', '_', $lp['name']);
                            if (array_key_exists($phpKey, $savedParams)) {
                                $saved = $savedParams[$phpKey];
                                $lp['value'] = $saved;
                                $lp['checked'] = ($saved === 'on' || $saved === 'checked' || $saved === true) ? 'checked' : '';
                            }
                        }
                        unset($lp);
                    }
                    $servlet['params'] = array_merge($servlet['params'] ?? [], $localParams);
                }

                // Check if servlet is currently running
                $isRunning = Cache::has("servlet_pid:{$userId}:{$name}");

                return Inertia::render('Servlets/Show', [
                    'servlet' => $servlet,
                    'inputFiles' => $fileController->getInputFiles($userId, $name),
                    'resultFiles' => $fileController->getResultFiles($userId, $name),
                    'isRunning' => $isRunning,
                    'userId' => $userId,
                ]);
            }
        }

        abort(404, "Сервлет '{$name}' не найден");
    }

    /**
     * Start a servlet run in background.
     */
    public function run(Request $request, string $name): JsonResponse
    {
        $servlet = $this->findServlet($name);
        if (! $servlet) {
            abort(404, "Сервлет '{$name}' не найден");
        }

        $userId = $request->user()->getAuthIdentifier();
        $pidKey = "servlet_pid:{$userId}:{$name}";

        if (Cache::has($pidKey)) {
            return response()->json(['error' => 'Сервлет уже запущен'], 409);
        }

        $artisan = base_path('artisan');
        $logFile = storage_path('logs/servlet.log');

        $cmd = sprintf(
            'php %s servlet:run %s %d >> %s 2>&1 & echo $!',
            escapeshellarg($artisan),
            escapeshellarg($name),
            $userId,
            escapeshellarg($logFile)
        );

        $pid = (int) trim(exec($cmd));

        if ($pid > 0) {
            Cache::put($pidKey, $pid, 3600);
        }

        return response()->json(['success' => true, 'pid' => $pid]);
    }

    /**
     * Stop a running servlet.
     */
    public function stop(Request $request, string $name): JsonResponse
    {
        $userId = $request->user()->getAuthIdentifier();
        $pidKey = "servlet_pid:{$userId}:{$name}";
        $pid = Cache::get($pidKey);

        if (! $pid) {
            return response()->json(['error' => 'Сервлет не запущен'], 404);
        }

        // Kill the process
        exec("kill {$pid} 2>/dev/null");
        Cache::forget($pidKey);

        broadcast(new ServletLog($name, $userId, 'Сервлет остановлен', 4));
        broadcast(new ServletFinished($name, $userId));

        return response()->json(['success' => true]);
    }

    /**
     * Run a servlet action (e.g., clearin, renewcatalogs).
     */
    public function action(Request $request, string $name, string $method): JsonResponse
    {
        $servlet = $this->findServlet($name);
        if (! $servlet) {
            abort(404, "Сервлет '{$name}' не найден");
        }

        $userId = $request->user()->getAuthIdentifier();
        $pidKey = "servlet_pid:{$userId}:{$name}";

        if (Cache::has($pidKey)) {
            return response()->json(['error' => 'Сервлет уже запущен'], 409);
        }

        $artisan = base_path('artisan');
        $logFile = storage_path('logs/servlet.log');

        $cmd = sprintf(
            'php %s servlet:run %s %d --action=%s >> %s 2>&1 & echo $!',
            escapeshellarg($artisan),
            escapeshellarg($name),
            $userId,
            escapeshellarg($method),
            escapeshellarg($logFile)
        );

        $pid = (int) trim(exec($cmd));

        if ($pid > 0) {
            Cache::put($pidKey, $pid, 3600);
        }

        return response()->json(['success' => true, 'pid' => $pid]);
    }

    /*
     * Опциональная proxy-фича (по умолчанию ОТКЛЮЧЕНА).
     *
     * Прозрачный reverse-proxy к сайту-источнику с использованием cookie-jar
     * сервлета — например, чтобы открыть корзину/чекаут источника прямо в UI.
     * Ниже — обобщённый скелет: конкретные правки HTML (абсолютизация ссылок,
     * перехват AJAX, перекодировка) зависят от движка целевого сайта.
     *
     * Чтобы включить:
     *   1) раскомментируй роут servlet/*\/proxy/* в routes/web.php;
     *   2) при необходимости — validateCsrfTokens(except) в bootstrap/app.php;
     *   3) верни блок «корзина» в resources/js/Pages/Servlets/Show.vue;
     *   4) верни импорты FileCookieJar и Http в начале файла.
     *
     * public function proxy(Request $request, string $name, string $path = '')
     * {
     *     if (!$this->findServlet($name)) {
     *         abort(404);
     *     }
     *
     *     $userId = $request->user()->getAuthIdentifier();
     *     $savedParams = app(FileController::class)->getSavedParams($userId, $name);
     *     $baseUrl = $savedParams['base'] ?? null;
     *     if (!$baseUrl) {
     *         abort(400, 'Servlet has no base URL');
     *     }
     *
     *     $cookieFile = config('workbench.files_path') . "/{$userId}/{$name}/cookies.json";
     *     if (!file_exists($cookieFile)) {
     *         abort(400, 'No session cookies yet.');
     *     }
     *     $cookieJar = new FileCookieJar($cookieFile, true);
     *
     *     $targetUrl = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
     *     $pending = Http::withOptions([
     *             'cookies' => $cookieJar,
     *             'allow_redirects' => ['max' => 5, 'track_redirects' => true],
     *         ])->withoutVerifying()->timeout(30);
     *
     *     $resp = $request->isMethod('POST')
     *         ? $pending->asForm()->post($targetUrl, $request->all())
     *         : $pending->get($targetUrl, $request->query());
     *
     *     $contentType = $resp->header('Content-Type') ?? '';
     *     $body = $resp->body();
     *
     *     // Не-HTML (css/js/images) — отдать как есть.
     *     if (!str_contains($contentType, 'text/html')) {
     *         return response($body)->header('Content-Type', $contentType);
     *     }
     *
     *     // HTML: при необходимости перекодируй и абсолютизируй ссылки под
     *     // движок целевого сайта; AJAX-вызовы заверни обратно через proxy.
     *     $origin = rtrim($baseUrl, '/');
     *     $body = preg_replace('#(href|src|action)=(["\'])/(?!/)#i', '$1=$2' . $origin . '/', $body);
     *
     *     return response($body)->header('Content-Type', 'text/html; charset=UTF-8');
     * }
     */

    private function findServlet(string $name): ?array
    {
        $servlets = $this->loadServlets();
        foreach ($servlets as $category => $items) {
            if (isset($items[$name])) {
                return array_merge($items[$name], ['key' => $name, 'category' => $category]);
            }
        }

        return null;
    }

    private function loadServlets(): array
    {
        $path = resource_path('data/servlets.json');

        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?: [];
    }
}
