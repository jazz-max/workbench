<?php

namespace App\Servlets\Concerns;

use GuzzleHttp\Cookie\FileCookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Symfony\Component\DomCrawler\Crawler;

/**
 * HTTP-клиент для сервлетов-скраперов.
 * Laravel Http + FileCookieJar, retry с backoff.
 */
trait HasHttpClient
{
    protected ?FileCookieJar $cookieJar = null;

    protected string $httpBaseUri = '';

    protected bool $httpFollowRedirects = false;

    protected int $httpRetries = 3;

    protected int $httpRetryDelay = 5000; // ms

    /** Исходная кодировка страниц источника (null = UTF-8, без конвертации). */
    protected ?string $sourceEncoding = null;

    protected function initHttp(string $baseUri, bool $followRedirects = false): void
    {
        $cookieFile = $this->params->coockiePath.'cookies.json';
        $this->cookieJar = new FileCookieJar($cookieFile, true);
        $this->httpBaseUri = rtrim($baseUri, '/').'/';
        $this->httpFollowRedirects = $followRedirects;
    }

    protected function request(): PendingRequest
    {
        $redirects = $this->httpFollowRedirects
            ? ['max' => 10, 'track_redirects' => true]
            : false;

        return Http::withOptions([
            'cookies' => $this->cookieJar,
            'allow_redirects' => $redirects,
        ])
            ->baseUrl($this->httpBaseUri)
            ->withoutVerifying()
            ->timeout(60)
            ->connectTimeout(15)
            ->withUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36')
            ->retry(
                $this->httpRetries,
                fn (int $attempt) => $this->httpRetryDelay * $attempt,
                fn (?\Throwable $e) => $e instanceof RequestException
                    && in_array($e->response->status(), [429, 503]),
            );
    }

    protected function httpGet(string $uri, array $headers = []): string
    {
        $req = $this->request();
        if (! empty($headers)) {
            $req = $req->withHeaders($headers);
        }

        return $req->get($uri)->throw()->body();
    }

    protected function httpPost(string $uri, array $data = [], array $headers = []): string
    {
        $req = $this->request();
        if (! empty($headers)) {
            $req = $req->withHeaders($headers);
        }

        return $req->asForm()->post($uri, $data)->throw()->body();
    }

    protected function httpHead(string $uri): void
    {
        $this->request()->head($uri)->throw();
    }

    protected function fetchCrawler(string $uri): Crawler
    {
        $html = $this->httpGet($uri);

        if ($this->sourceEncoding !== null) {
            $html = to_utf8($html, $this->sourceEncoding);
            // Поправить charset в meta, чтобы DOMDocument не перекодировал повторно
            $html = preg_replace(
                '/charset\s*=\s*["\']?[\w-]+["\']?/i',
                'charset=UTF-8',
                $html
            );
        }

        return new Crawler($html);
    }

    // ─── Goods database (JSON, замена HashMap + serialize) ──────

    protected array $goods = [];

    private string $goodsFile = 'goods.json';

    protected function setGoodsFile(string $filename): void
    {
        $this->goodsFile = $filename;
    }

    protected function loadGoods(): void
    {
        $path = $this->params->coockiePath.$this->goodsFile;
        if (file_exists($path)) {
            $data = json_decode(file_get_contents($path), true);
            if (is_array($data)) {
                $this->goods = $data;
            }
        }
    }

    protected function saveGoods(): void
    {
        $path = $this->params->coockiePath.$this->goodsFile;
        // Резервная копия
        if (file_exists($path)) {
            copy($path, $path.'.bak');
        }
        file_put_contents($path, json_encode($this->goods, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    // ─── CSV helpers ────────────────────────────────────────────

    protected function writeCsv(string $dir, string $name, array $rows): void
    {
        $path = rtrim($dir, '/').'/'.$name.'.csv';
        $this->ensureDir(dirname($path));
        $h = fopen($path, 'a+');
        if (! $h) {
            $this->error("Ошибка записи файла $path");

            return;
        }
        foreach ($rows as $line) {
            fputcsv($h, $line, ';');
        }
        fclose($h);
    }

    protected function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    // ─── String helpers ─────────────────────────────────────────

    protected function clean(string $str): string
    {
        $str = preg_replace(['/[\n\r\t]/', '/\s{2,}/'], ['', ' '], $str);

        return trim($str);
    }

    protected function processPrice(string $price): string
    {
        $search = ['/[^\d,.]/', '/,/'];
        $replace = ['', '.'];

        return trim(preg_replace($search, $replace, $price), '.');
    }
}
