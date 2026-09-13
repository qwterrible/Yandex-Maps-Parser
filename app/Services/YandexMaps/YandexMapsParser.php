<?php

namespace App\Services\YandexMaps;

use App\Models\Organization;
use App\Models\ParseRun;
use App\Models\Review;
use App\Services\YandexMaps\Exceptions\ParseException;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class YandexMapsParser
{
    public function __construct(
        private YandexMapsClient $client,
    ) {}

    public function parse(Organization $org): ParseRun
    {
        $org->update(['parse_status' => 'parsing', 'parse_error' => null]);

        $run = ParseRun::create([
            'organization_id' => $org->id,
            'status'          => 'running',
            'started_at'      => now(),
        ]);

        try {
            $orgId = $org->yandex_org_id
                ?: app(YandexUrlParser::class)->extractOrgId($org->yandex_url);

            if (!$orgId) {
                throw new ParseException('Не удалось определить ID организации из ссылки');
            }

            $page = $this->client->fetchOrgPage($orgId);
            $meta = $this->extractMetaFromHtml($page['html']);
            $fingerprint = hash('sha256', json_encode(array_keys($meta)));

            $org->update([
                'yandex_org_id'      => $orgId,
                'name'               => $meta['name'] ?? $org->name,
                'rating'             => $meta['rating'] ?? null,
                'ratings_count'      => $meta['ratings_count'] ?? null,
                'reviews_count'      => $meta['reviews_count'] ?? null,
                'schema_fingerprint' => $fingerprint,
            ]);

            $saved     = 0;
            $pageIndex = 0;
            $perPage   = 50;
            $maxPages  = (int) config('services.yandex.max_pages', 20);

            while ($pageIndex < $maxPages) {
                $data  = $this->client->fetchReviewsPage($orgId, $pageIndex, $perPage, $page['cookies']);
                $items = $this->normalizeReviews($data);

                if (empty($items)) {
                    break;
                }

                $saved += $this->upsertReviews($org, $items);

                $run->update([
                    'pages_fetched'   => $pageIndex + 1,
                    'reviews_fetched' => $saved,
                ]);

                $min = (int) config('services.yandex.throttle_min_ms', 400);
                $max = (int) config('services.yandex.throttle_max_ms', 1200);
                usleep(random_int($min, $max) * 1000);

                if (count($items) < $perPage) {
                    break;
                }

                $pageIndex++;
            }

            if ($saved === 0) {
                throw ParseException::empty();
            }

            $run->update([
                'status'      => 'done',
                'finished_at' => now(),
                'meta'        => [
                    'rating'        => $org->rating,
                    'ratings_count' => $org->ratings_count,
                    'reviews_count' => $org->reviews_count,
                ],
            ]);

            $org->update([
                'parse_status' => 'done',
                'parsed_at'    => now(),
            ]);

            return $run;
        } catch (\Throwable $e) {
            Log::error('Yandex parse failed', [
                'org_id' => $org->id,
                'error'  => $e->getMessage(),
                'code'   => $e->getCode(),
            ]);

            $run->update([
                'status'      => 'failed',
                'error'       => $e->getMessage(),
                'finished_at' => now(),
            ]);

            $org->update([
                'parse_status' => 'failed',
                'parse_error'  => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function extractMetaFromHtml(string $html): array
    {
        $result = [];

        if (preg_match('~"name"\s*:\s*"([^"]+)"~u', $html, $m)) {
            $result['name'] = stripcslashes($m[1]);
        }
        if (preg_match('~"ratingValue"\s*:\s*([\d.]+)~', $html, $m)) {
            $result['rating'] = (float) $m[1];
        }
        if (preg_match('~"ratingCount"\s*:\s*(\d+)~', $html, $m)) {
            $result['ratings_count'] = (int) $m[1];
        }
        if (preg_match('~"reviewCount"\s*:\s*(\d+)~', $html, $m)) {
            $result['reviews_count'] = (int) $m[1];
        }

        if (empty($result)) {
            throw ParseException::layoutChanged('Не удалось извлечь метаданные из HTML');
        }

        return $result;
    }
    
    private function normalizeReviews(array $data): array
    {
        // Разные варианты, где Яндекс может положить отзывы:
        $items = $data['data']['reviews']
            ?? $data['data']['items']
            ?? $data['reviews']
            ?? [];

        $out = [];

        foreach ($items as $r) {
            $externalId = (string) ($r['id'] ?? $r['reviewId'] ?? '');
            if ($externalId === '') {
                continue;
            }

            $out[] = [
                'external_id'  => $externalId,
                'author'       => $r['author']['name'] ?? $r['author'] ?? null,
                'published_at' => isset($r['updatedTime']) || isset($r['createdTime'])
                    ? Carbon::parse($r['updatedTime'] ?? $r['createdTime'])
                    : null,
                'text'         => $r['text'] ?? null,
                'rating'       => isset($r['rating']) ? (int) $r['rating'] : null,
            ];
        }

        return $out;
    }

    private function upsertReviews(Organization $org, array $items): int
    {
        $saved = 0;

        DB::transaction(function () use ($org, $items, &$saved) {
            foreach ($items as $item) {
                $existing = Review::where('organization_id', $org->id)
                    ->where('external_id', $item['external_id'])
                    ->first();

                if ($existing) {
                    $oldSnapshot = [
                        'author'       => $existing->author,
                        'text'         => $existing->text,
                        'rating'       => $existing->rating,
                        'published_at' => optional($existing->published_at)->toDateTimeString(),
                    ];
                    $newSnapshot = [
                        'author'       => $item['author'],
                        'text'         => $item['text'],
                        'rating'       => $item['rating'],
                        'published_at' => optional($item['published_at'])->toDateTimeString(),
                    ];

                    if ($oldSnapshot !== $newSnapshot) {
                        $existing->update([
                            'author'       => $item['author'],
                            'text'         => $item['text'],
                            'rating'       => $item['rating'],
                            'published_at' => $item['published_at'],
                            'previous'     => $oldSnapshot,
                            'changed_at'   => now(),
                        ]);
                    }
                } else {
                    Review::create([
                        'organization_id' => $org->id,
                        'external_id'     => $item['external_id'],
                        'author'          => $item['author'],
                        'published_at'    => $item['published_at'],
                        'text'            => $item['text'],
                        'rating'          => $item['rating'],
                    ]);
                }

                $saved++;
            }
        });

        return $saved;
    }
}