<?php

/*
 * Опциональные хелперы кодировки для сервлетов, работающих с не-UTF-8 источниками
 * (например, страницы или CSV в windows-1251). Приложение по умолчанию работает в
 * UTF-8, поэтому эти функции — passthrough, пока явно не указана исходная/целевая
 * кодировка. Удали этот файл (и запись в composer.json → autoload.files), если
 * legacy-кодировки тебе не нужны.
 */

if (! function_exists('to_utf8')) {
    /**
     * Привести значение к UTF-8 из исходной кодировки $from.
     * $from === null → значение возвращается без изменений (passthrough).
     * Рекурсивно обрабатывает массивы и объекты.
     */
    function to_utf8(mixed $value, ?string $from = null): mixed
    {
        if ($from === null) {
            return $value;
        }

        if (is_string($value)) {
            return mb_check_encoding($value, 'UTF-8')
                ? $value
                : mb_convert_encoding($value, 'UTF-8', $from);
        }

        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $newKey = is_string($key) ? to_utf8($key, $from) : $key;
                $out[$newKey] = to_utf8($item, $from);
            }

            return $out;
        }

        if (is_object($value)) {
            foreach (get_object_vars($value) as $name => $item) {
                $value->$name = to_utf8($item, $from);
            }
        }

        return $value;
    }
}

if (! function_exists('to_legacy_encoding')) {
    /**
     * Привести UTF-8-строку к целевой кодировке $to (например, 'windows-1251'
     * для legacy-CSV). $to === 'UTF-8' → passthrough.
     */
    function to_legacy_encoding(mixed $value, string $to = 'UTF-8'): mixed
    {
        if (is_string($value) && strcasecmp($to, 'UTF-8') !== 0) {
            return mb_convert_encoding($value, $to, 'UTF-8');
        }

        return $value;
    }
}
