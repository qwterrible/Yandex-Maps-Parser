<?php

namespace App\Services\YandexMaps;

use App\Services\YandexMaps\Exceptions\ParseException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class YandexMapsClient
{
    private string $userAgent;
    private ?string $proxy;
    private int $timeout;

    public function __construct()
    {
        $this->userAgent = config('services.yandex.user_agent');
        $this->proxy     = config('services.yandex.proxy') ?: null;
        $this->timeout   = config('services.yandex.timeout', 20);
    }

    public function fetchOrgPage(string $orgId): array
    {
        $url = "https://yandex.ru/maps/org/{$orgId}/reviews/";

        $response = Http::withHeaders($this->browserHeaders())
            ->withOptions(['proxy' => $this->proxy])
            ->timeout($this->timeout)
            ->get($url);

        if (in_array($response->status(), [403, 429], true)) {
            throw ParseException::blocked();
        }
        if (!$response->ok()) {
            throw new ParseException("HTTP {$response->status()} при загрузке карточки");
        }

        $html = $response->body();

        if (!str_contains($html, 'ratingValue')
            && !str_contains($html, '"ratingCount"')
            && !str_contains($html, 'businessRating')
        ) {
            Log::warning('Yandex layout fingerprint mismatch', [
                'org'      => $orgId,
                'html_len' => strlen($html),
            ]);
            throw ParseException::layoutChanged();
        }

        return [
            'html'    => $html,
            'cookies' => $this->cookiesFromResponse($response),
        ];
    }

    public function fetchReviewsPage(string $orgId, int $page, int $limit = 50, array $cookies = []): array
    {
        $url = 'https://yandex.ru/maps/api/business/fetchReviews';

        $query = [
            'businessId' => $orgId,
            'page'       => $page,
            'pageSize'   => $limit,
            'ranking'    => 'by_time',
        ];

        // === Шаг 1. Первый запрос — получаем csrfToken и cookies ===
        $first = Http::withHeaders($this->browserHeaders())
            ->withCookies($cookies, 'yandex.ru')
            ->withOptions(['proxy' => $this->proxy])
            ->timeout($this->timeout)
            ->get($url, $query);

        if (in_array($first->status(), [403, 429], true)) {
            throw ParseException::blocked();
        }

        $firstJson  = $first->json();
        $csrfToken  = $firstJson['csrfToken'] ?? null;
        $cookies    = array_merge($cookies, $this->cookiesFromResponse($first));

        Log::info('fetchReviews step1', [
            'status'    => $first->status(),
            'has_token' => (bool) $csrfToken,
            'body'      => substr($first->body(), 0, 500),
        ]);

        // Если Яндекс отдал сразу данные (бывает при наличии правильной сессии)
        if (isset($firstJson['data'])) {
            return $firstJson;
        }

        if (!$csrfToken) {
            throw ParseException::layoutChanged('Яндекс не вернул csrfToken');
        }

        // === Шаг 2. Второй запрос — с токеном и cookies ===
        $second = Http::withHeaders($this->browserHeaders() + [
                'x-csrf-token' => $csrfToken,
            ])
            ->withCookies($cookies, 'yandex.ru')
            ->withOptions(['proxy' => $this->proxy])
            ->timeout($this->timeout)
            ->get($url, $query + ['csrfToken' => $csrfToken]);

        Log::info('fetchReviews step2', [
            'status' => $second->status(),
            'body'   => substr($second->body(), 0, 2000),
        ]);

        if (in_array($second->status(), [403, 429], true)) {
            throw ParseException::blocked();
        }
        if (!$second->ok()) {
            throw new ParseException("HTTP {$second->status()} при загрузке отзывов");
        }

        $data = $second->json();

        if (!is_array($data)) {
            throw ParseException::layoutChanged('Ответ fetchReviews не JSON');
        }

        return $data;
    }

    /**
     * Заголовки, максимально близкие к реальному браузеру Chrome.
     * Яндекс проверяет их при выдаче JSON.
     */
    private function browserHeaders(): array
    {
        return [
            'User-Agent'                => $this->userAgent,
            'Accept'                    => 'application/json, text/plain, */*',
            'Accept-Language'           => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
            'Origin'                    => 'https://yandex.ru',
            'Referer'                   => 'https://yandex.ru/maps/',
            'sec-ch-ua'                 => '"Chromium";v="122", "Not(A:Brand";v="24", "Google Chrome";v="122"',
            'sec-ch-ua-mobile'          => '?0',
            'sec-ch-ua-platform'        => '"Windows"',
            'Sec-Fetch-Dest'            => 'empty',
            'Sec-Fetch-Mode'            => 'cors',
            'Sec-Fetch-Site'            => 'same-origin',
            'X-Requested-With'          => 'XMLHttpRequest',
        ];
    }

    private function cookiesFromResponse($response): array
    {
        $jar = [];
        foreach ($response->headers()['Set-Cookie'] ?? [] as $cookieLine) {
            $pair = explode(';', $cookieLine, 2)[0];
            if (str_contains($pair, '=')) {
                [$k, $v] = explode('=', $pair, 2);
                $jar[trim($k)] = trim($v);
            }
        }
        return $jar;
    }
}