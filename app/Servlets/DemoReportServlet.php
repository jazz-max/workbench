<?php

namespace App\Servlets;

/**
 * Демонстрационный сервлет-ГЕНЕРАТОР (без сети и без входных файлов).
 *
 * Генерирует синтетический набор «заказов» и пишет два CSV: детальный
 * (detail.csv) и агрегированную сводку по продуктам (summary.csv).
 *
 * Показывает, что Workbench — раннер для ЛЮБЫХ задач, а не только парсеров
 * (отчёты, импорт, конвертация, разовые батч-задачи). Работает «из коробки»:
 * не нужны ни сеть, ни загрузка файлов — только нажать «Старт».
 *
 * Демонстрирует: параметры, clearOutputDir(), запись нескольких файлов,
 * прогресс в живом логе.
 */
class DemoReportServlet extends BaseServlet
{
    public function run(): bool
    {
        $this->clearOutputDir();

        $rows = max(1, min(5000, (int) ($this->params->rows ?? 50)));
        $products = ['Кофе', 'Чай', 'Какао', 'Сахар', 'Молоко'];

        $this->notice("Генерация отчёта: {$rows} строк…");

        $detail = [['Дата', 'Продукт', 'Количество', 'Сумма']];
        $totals = [];
        $grand = 0.0;

        for ($i = 0; $i < $rows; $i++) {
            $product = $products[mt_rand(0, count($products) - 1)];
            $qty = mt_rand(1, 20);
            $amount = $qty * mt_rand(50, 500);
            $date = date('Y-m-d', strtotime('-'.mt_rand(0, 30).' days'));

            $detail[] = [$date, $product, $qty, number_format($amount, 2, '.', '')];
            $totals[$product] = ($totals[$product] ?? 0) + $amount;
            $grand += $amount;

            if (($i + 1) % 100 === 0) {
                $this->log('обработано строк: '.($i + 1));
            }
        }

        $this->writeCsvFile('detail', $detail);

        arsort($totals);
        $summary = [['Продукт', 'Сумма', 'Доля, %']];
        foreach ($totals as $product => $sum) {
            $share = $grand > 0 ? round($sum / $grand * 100, 1) : 0;
            $summary[] = [$product, number_format($sum, 2, '.', ''), $share];
        }
        $this->writeCsvFile('summary', $summary);

        $this->notice('Готово: detail.csv ('.$rows.' строк) + summary.csv. Итого: '.number_format($grand, 2, '.', ''));

        return true;
    }

    private function writeCsvFile(string $name, array $rows): void
    {
        $path = $this->params->outputPath.$name.'.csv';
        if (($h = fopen($path, 'w')) === false) {
            $this->error("Не удалось записать {$name}.csv");

            return;
        }
        foreach ($rows as $row) {
            fputcsv($h, array_map(fn ($cell) => $this->out((string) $cell), $row), ';');
        }
        fclose($h);
    }
}
