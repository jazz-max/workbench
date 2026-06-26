<?php

namespace App\Servlets;

/**
 * Демонстрационный ФАЙЛОВЫЙ сервлет (без сети).
 *
 * Читает каждый .csv из входной папки сервлета (in/), нормализует ячейки
 * (trim) и перезаписывает результат в выходную папку (out/) с разделителем «;».
 *
 * Демонстрирует работу с файлами сервлета без HTTP:
 *  - $this->params->inputPath / outputPath;
 *  - clearOutputDir() — очистку прошлых результатов;
 *  - log()/notice()/warn() — прогресс в UI;
 *  - out() — кодировку выходных данных (по умолчанию UTF-8).
 */
class DemoCsvConverter extends BaseServlet
{
    public function run(): bool
    {
        $this->clearOutputDir();

        $files = glob($this->params->inputPath.'*.csv') ?: [];

        if (! $files) {
            $this->warn('Во входной папке нет .csv файлов. Загрузите файл и запустите снова.');

            return true;
        }

        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $this->log('Обработка: '.basename($file));

            $rows = [];
            if (($h = fopen($file, 'r')) !== false) {
                while (($cols = fgetcsv($h, 0, ',')) !== false) {
                    $rows[] = array_map(fn ($cell) => $this->out(trim((string) $cell)), $cols);
                }
                fclose($h);
            }

            $outPath = $this->params->outputPath.$name.'_converted.csv';
            if (($w = fopen($outPath, 'w')) !== false) {
                foreach ($rows as $row) {
                    fputcsv($w, $row, ';');
                }
                fclose($w);
            }

            $this->notice('Записано: '.basename($outPath).' (строк: '.count($rows).')');
        }

        return true;
    }
}
