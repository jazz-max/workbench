<?php

namespace App\Console\Commands;

use App\Events\ServletFinished;
use App\Events\ServletLog;
use App\Events\ServletStarted;
use App\Servlets\BaseServlet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class RunServletCommand extends Command
{
    protected $signature = 'servlet:run {name} {userId} {--action= : Run a specific method instead of run()}';

    protected $description = 'Run a servlet in the background';

    public function handle(): int
    {
        $name = $this->argument('name');
        $userId = (int) $this->argument('userId');
        $action = $this->option('action');

        $pidKey = "servlet_pid:{$userId}:{$name}";
        Cache::put($pidKey, getmypid(), 3600);

        try {
            broadcast(new ServletStarted($name, $userId));

            // Контекст для broadcasting из кода сервлета (BaseServlet::log()).
            $GLOBALS['_servlet_context'] = [
                'name' => $name,
                'userId' => $userId,
            ];

            $this->runServlet($name, $userId, $action);

            broadcast(new ServletLog($name, $userId, "Done {$name}", 5));
            broadcast(new ServletFinished($name, $userId));
        } catch (\Throwable $e) {
            broadcast(new ServletLog($name, $userId, 'Ошибка: '.$e->getMessage(), 3, 500));
            broadcast(new ServletFinished($name, $userId));
            $this->error($e->getMessage());

            return self::FAILURE;
        } finally {
            Cache::forget($pidKey);
        }

        return self::SUCCESS;
    }

    private function runServlet(string $name, int $userId, ?string $action): void
    {
        $params = $this->buildParams($name, $userId);
        $className = $this->resolveClassName($name);

        if (! is_subclass_of($className, BaseServlet::class)) {
            throw new \RuntimeException(
                "Класс '{$className}' должен наследовать ".BaseServlet::class
            );
        }

        $servlet = new $className($params);

        if ($action) {
            if (! is_callable([$servlet, $action])) {
                throw new \RuntimeException("Метод '{$action}' не найден в классе '{$className}'");
            }
            broadcast(new ServletLog($name, $userId, "запуск действия {$action}", 7));
            $servlet->$action();
            broadcast(new ServletLog($name, $userId, "действие {$action} выполнено", 7));
        } else {
            $servlet->run();
        }
    }

    private function buildParams(string $name, int $userId): \stdClass
    {
        $filesPath = config('workbench.files_path');
        $servletDir = "{$filesPath}/{$userId}/{$name}";
        $paramsPath = "{$servletDir}/params.json";

        $params = new \stdClass;
        if (file_exists($paramsPath)) {
            $params = json_decode(file_get_contents($paramsPath));
            if (! $params) {
                $params = new \stdClass;
            }
        }

        $params->inputPath = "{$servletDir}/in/";
        $params->outputPath = "{$servletDir}/out/";
        $params->coockiePath = "{$servletDir}/";
        $params->servletName = $name;

        if (! is_dir($params->outputPath)) {
            mkdir($params->outputPath, 0777, true);
        }
        if (! is_dir($params->inputPath)) {
            mkdir($params->inputPath, 0777, true);
        }

        return $params;
    }

    private function resolveClassName(string $name): string
    {
        $servletsJson = json_decode(file_get_contents(resource_path('data/servlets.json')), false);

        foreach ($servletsJson as $category => $items) {
            if (isset($items->$name->classname)) {
                return $items->$name->classname;
            }
        }

        throw new \RuntimeException("Класс сервлета '{$name}' не найден в servlets.json");
    }
}
