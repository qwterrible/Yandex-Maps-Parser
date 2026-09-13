<?php

namespace App\Jobs;

use App\Models\Organization;
use App\Services\YandexMaps\YandexMapsParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ParseOrganizationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 30;

    public function __construct(public int $organizationId) {}

    public function handle(YandexMapsParser $parser): void
    {
        $org = Organization::find($this->organizationId);
        if (!$org) {
            return;
        }

        $parser->parse($org);
    }

    public function failed(\Throwable $e): void
    {
        Organization::where('id', $this->organizationId)->update([
            'parse_status' => 'failed',
            'parse_error'  => $e->getMessage(),
        ]);
    }
}