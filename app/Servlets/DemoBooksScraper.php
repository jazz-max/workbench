<?php

namespace App\Servlets;

use App\Servlets\Concerns\HasHttpClient;

/**
 * Демонстрационный СЕТЕВОЙ сервлет-скрапер.
 *
 * Обходит публичный учебный сайт https://books.toscrape.com — он создан
 * специально для практики скрапинга (без авторизации и ограничений) — постранично
 * собирает книги выбранной категории и пишет CSV: Название;Цена;Наличие;Рейтинг.
 *
 * Демонстрирует публичный API фреймворка:
 *  - run() как точку входа;
 *  - log()/notice()/warn()/error() — живой лог в UI через WebSocket;
 *  - initHttp()/fetchCrawler() из HasHttpClient — HTTP с cookie-jar, retry и DomCrawler;
 *  - clean()/processPrice()/writeCsv()/out() — обработку и запись результата.
 */
class DemoBooksScraper extends BaseServlet
{
    use HasHttpClient;

    private string $base = 'https://books.toscrape.com/';

    public function run(): bool
    {
        $this->initHttp($this->base);

        // URL-путь категории (относительно базового адреса). По умолчанию — все книги.
        $category = $this->params->category ?? 'catalogue/category/books_1/index.html';
        $maxPages = (int) ($this->params->maxPages ?? 3);

        $this->notice("Старт обхода: {$category}");

        $rows = [['Название', 'Цена', 'Наличие', 'Рейтинг']];
        $pageUrl = $category;
        $page = 0;

        while ($pageUrl && $page < $maxPages) {
            $page++;
            $this->log("Страница {$page}: {$pageUrl}");

            $crawler = $this->fetchCrawler($pageUrl);

            $crawler->filter('article.product_pod')->each(function ($node) use (&$rows) {
                $title = $node->filter('h3 a')->attr('title') ?? '';
                $price = $node->filter('.price_color')->text('');
                $avail = $this->clean($node->filter('.availability')->text(''));
                $rating = trim(str_replace('star-rating', '', $node->filter('p.star-rating')->attr('class') ?? ''));

                $rows[] = [$this->clean($title), $this->processPrice($price), $avail, $rating];
            });

            // Пагинация: ссылка «next» относительна текущей страницы каталога.
            $next = $crawler->filter('li.next a');
            $pageUrl = $next->count() > 0
                ? $this->resolveRelative($pageUrl, $next->attr('href'))
                : null;
        }

        $count = count($rows) - 1;

        $this->writeCsv($this->params->outputPath, 'books', array_map(
            fn ($row) => array_map(fn ($cell) => $this->out((string) $cell), $row),
            $rows
        ));

        $this->notice("Готово: собрано книг — {$count}");

        return true;
    }

    /** Разрешить относительную ссылку относительно директории текущего URL. */
    private function resolveRelative(string $base, string $href): string
    {
        if (str_starts_with($href, 'http')) {
            return $href;
        }

        $dir = str_contains($base, '/') ? substr($base, 0, strrpos($base, '/') + 1) : '';

        return $dir.$href;
    }
}
