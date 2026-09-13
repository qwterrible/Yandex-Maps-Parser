<?php

namespace App\Services\YandexMaps\Exceptions;

class ParseException extends \RuntimeException
{
    public static function layoutChanged(string $msg = 'Разметка источника изменилась'): self
    {
        return new self($msg, 1001);
    }

    public static function blocked(): self
    {
        return new self('Похоже, нас заблокировали (капча / 429 / 403)', 1002);
    }

    public static function empty(): self
    {
        return new self('Источник вернул пустой ответ', 1003);
    }
}