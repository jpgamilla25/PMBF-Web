<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FmisShareApiClient
{
    /**
     * Fetch one page of share-capital rows from the api-center.
     *
     * @return array{
     *     data: list<array<string, mixed>>,
     *     meta: array<string, mixed>
     * }|null
     */
    public function fetchPage(?CarbonInterface $since, int $page, int $perPage = 200, ?int $year = null): ?array
    {
        $query = ['page' => $page, 'per_page' => $perPage];

        if ($since !== null) {
            $query['since'] = $since->toIso8601String();
        }

        if ($year !== null) {
            $query['year'] = $year;
        }

        $response = $this->call($query);

        if ($response === null || !$response->successful()) {
            return null;
        }

        $body = $response->json();
        if (!is_array($body) || ($body['status'] ?? null) !== 'success') {
            return null;
        }

        return [
            'data' => is_array($body['data'] ?? null) ? $body['data'] : [],
            'meta' => is_array($body['meta'] ?? null) ? $body['meta'] : [],
        ];
    }

    private function call(array $query): ?Response
    {
        $url = rtrim((string) config('services.fmis.url'), '/') . '/shares';

        try {
            return Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . config('services.fmis.token'),
                'x-api-key' => config('services.fmis.key'),
            ])
                ->timeout(60)
                ->retry(2, 500, throw: false)
                ->get($url, $query);
        } catch (\Throwable $e) {
            Log::error('FMIS shares API request threw exception', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
