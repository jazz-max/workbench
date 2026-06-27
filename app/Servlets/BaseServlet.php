<?php

namespace App\Servlets;

use App\Events\ServletLog;

/**
 * Базовый класс сервлета.
 *
 * Сервлет — единица фоновой работы (скрапер, конвертер файлов и т.п.):
 * реализуй run() и используй log()/notice()/warn()/error()/debug() для
 * стриминга прогресса в UI, а $this->params->{inputPath,outputPath,coockiePath}
 * — для работы с файлами. Пример: App\Servlets\DemoBooksScraper.
 */
abstract class BaseServlet
{
    protected \stdClass $params;

    protected mixed $localParams = null;

    private string $localParamsFile = 'local_params.json';

    /** Целевая кодировка выходных файлов (CSV и т.п.). По умолчанию UTF-8. */
    protected string $outputEncoding = 'UTF-8';

    public function __construct(\stdClass $params)
    {
        $this->params = $params;
    }

    abstract public function run(): bool;

    // ─── Logging ────────────────────────────────────────────

    protected function log(string $mes, int $level = 6): void
    {
        $ctx = $GLOBALS['_servlet_context'];
        // Стриминг в UI — best-effort: если WebSocket-сервер (Reverb) не поднят,
        // задача всё равно продолжает работу и пишет work.log.
        try {
            broadcast(new ServletLog($ctx['name'], $ctx['userId'], $mes, $level));
        } catch (\Throwable) {
            // ignore — live log streaming is optional
        }

        if (isset($this->params->coockiePath) && is_dir($this->params->coockiePath)) {
            $prefix = match ($level) {
                3 => '[Error] ',
                4 => '[Warning] ',
                5 => '[Notice] ',
                7 => '[Debug] ',
                default => '',
            };
            file_put_contents(
                $this->params->coockiePath.'work.log',
                date('Y-m-d H:i:s').' :'.$prefix.$mes."\n",
                FILE_APPEND
            );
        }
    }

    protected function notice(string $mes): void
    {
        $this->log($mes, 5);
    }

    protected function warn(string $mes): void
    {
        $this->log($mes, 4);
    }

    protected function error(string $mes): void
    {
        $this->log($mes, 3);
    }

    protected function debug(string $mes): void
    {
        $this->log($mes, 7);
    }

    // ─── File operations ────────────────────────────────────

    protected function clearin(): void
    {
        $in = $this->params->inputPath;
        if (! is_dir($in)) {
            return;
        }

        $this->clearDir($in);
        $this->log('входная папка очищена');

        $ctx = $GLOBALS['_servlet_context'];
        $this->log('reloadSettings '.$ctx['name']);
    }

    protected function clearOutputDir(array $preserve = []): void
    {
        $dir = $this->params->outputPath;
        if (! is_dir($dir)) {
            $this->error('Не найден выходной каталог');

            return;
        }

        $di = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
        $it = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($it as $file) {
            $basename = pathinfo($file, PATHINFO_BASENAME);
            if (in_array($basename, $preserve)) {
                continue;
            }
            if (is_dir($file)) {
                @rmdir($file);
            } else {
                @unlink($file);
            }
        }
    }

    private function clearDir(string $dir): void
    {
        foreach (scandir($dir) as $fil) {
            if ($fil === '..' || $fil === '.') {
                continue;
            }
            $path = $dir.$fil;
            if (is_dir($path)) {
                $di = new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS);
                $it = new \RecursiveIteratorIterator($di, \RecursiveIteratorIterator::CHILD_FIRST);
                foreach ($it as $f) {
                    is_dir($f) ? @rmdir($f) : @unlink($f);
                }
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }

    protected function clearCookies(): void
    {
        $path = $this->params->coockiePath.'cookies.json';
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    // ─── Local params (persistent state) ────────────────────

    protected function loadLocalParams(): void
    {
        $path = $this->params->coockiePath.$this->localParamsFile;
        if (file_exists($path)) {
            $this->localParams = json_decode(file_get_contents($path));
        }
    }

    protected function saveLocalParams(): void
    {
        $json = json_encode($this->localParams, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json !== false) {
            file_put_contents($this->params->coockiePath.$this->localParamsFile, $json);
        } else {
            $this->error('Ошибка при сохранении параметров');
        }
    }

    public function getLocalParams(): mixed
    {
        return $this->localParams;
    }

    protected function getLocalParam(string $name): mixed
    {
        $arr = array_filter($this->localParams ?? [], function ($item) use ($name) {
            return isset($item->name) && $item->name === $name;
        });

        return count($arr) > 0 ? array_shift($arr) : null;
    }

    // ─── Helpers ────────────────────────────────────────────

    protected function out(string $str): string
    {
        return to_legacy_encoding(trim($str), $this->outputEncoding);
    }
}
