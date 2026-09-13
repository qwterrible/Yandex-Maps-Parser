<?php

namespace App\Services\YandexMaps;

class YandexUrlParser
{
    public function extractOrgId(string $url): ?string
    {
        // /org/.../1122334455/ или /maps/.../1122334455/
        if (preg_match('~/(?:org/[^/]+/)?(\d{6,})~', $url, $m)) {
            return $m[1];
        }
        if (preg_match('~[?&]oid=(\d+)~', $url, $m)) {
            return $m[1];
        }
        return null;
    }

    public function isValidYandexUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST) ?? '';
        return (bool) preg_match('~(^|\.)yandex\.[a-z.]+$~i', $host)
            && (bool) preg_match('~/maps/~', $url);
    }
}