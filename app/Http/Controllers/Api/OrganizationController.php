<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ParseOrganizationJob;
use App\Models\Organization;
use App\Services\YandexMaps\YandexUrlParser;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function show(Request $request)
    {
        $org = Organization::where('user_id', $request->user()->id)->first();
        return response()->json(['organization' => $org]);
    }

    public function store(Request $request, YandexUrlParser $urlParser)
    {
        $data = $request->validate([
            'yandex_url' => ['required', 'string', 'max:1024', 'url'],
        ]);

        if (!$urlParser->isValidYandexUrl($data['yandex_url'])) {
            return response()->json([
                'message' => 'Ссылка должна вести на Яндекс.Карты (yandex.ru/maps/...)',
                'errors'  => ['yandex_url' => ['Некорректная ссылка на Яндекс.Карты']],
            ], 422);
        }

        $orgId = $urlParser->extractOrgId($data['yandex_url']);
        if (!$orgId) {
            return response()->json([
                'message' => 'Не удалось извлечь ID организации из ссылки',
                'errors'  => ['yandex_url' => ['Ссылка не содержит ID организации']],
            ], 422);
        }

        $org = Organization::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'yandex_url'    => $data['yandex_url'],
                'yandex_org_id' => $orgId,
                'parse_status'  => 'queued',
                'parse_error'   => null,
            ]
        );

        ParseOrganizationJob::dispatch($org->id);

        return response()->json(['organization' => $org->fresh()]);
    }

    public function status(Request $request)
    {
        $org = Organization::where('user_id', $request->user()->id)->first();
        if (!$org) {
            return response()->json(['organization' => null]);
        }

        $lastRun = $org->parseRuns()->latest()->first();

        return response()->json([
            'organization' => $org,
            'last_run'     => $lastRun,
        ]);
    }
}