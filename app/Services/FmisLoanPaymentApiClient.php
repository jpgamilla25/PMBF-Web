<?php

namespace App\Services;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FmisLoanPaymentApiClient
{
    /**
     * Fetch one page of payroll-deduction loan payments from the api-center.
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

    /**
     * Fetch many pages concurrently. Returns a map keyed by page number;
     * null values mean that page failed.
     *
     * @param  list<int>  $pageNumbers
     * @return array<int, array{data: list<array<string, mixed>>, meta: array<string, mixed>}|null>
     */
    public function fetchPagesPool(array $pageNumbers, ?CarbonInterface $since, int $perPage = 200, ?int $year = null): array
    {
        if ($pageNumbers === []) {
            return [];
        }

        $url = $this->endpoint();
        $baseQuery = ['per_page' => $perPage];
        if ($since !== null) {
            $baseQuery['since'] = $since->toIso8601String();
        }
        if ($year !== null) {
            $baseQuery['year'] = $year;
        }
        $headers = $this->headers();

        try {
            $responses = Http::pool(function (Pool $pool) use ($pageNumbers, $headers, $url, $baseQuery) {
                return collect($pageNumbers)
                    ->map(fn ($page) => $pool
                        ->withHeaders($headers)
                        ->timeout(60)
                        ->get($url, array_merge($baseQuery, ['page' => $page]))
                    )
                    ->all();
            });
        } catch (\Throwable $e) {
            Log::error('FMIS loan-payments API pool fetch threw exception', [
                'pages' => $pageNumbers,
                'error' => $e->getMessage(),
            ]);
            return array_fill_keys($pageNumbers, null);
        }

        $result = [];
        foreach ($pageNumbers as $i => $page) {
            $resp = $responses[$i] ?? null;
            $result[$page] = $this->parseResponse($resp, $page);
        }

        return $result;
    }

    private function call(array $query): ?Response
    {
        try {
            return Http::withHeaders($this->headers())
                ->timeout(60)
                ->retry(2, 500, throw: false)
                ->get($this->endpoint(), $query);
        } catch (\Throwable $e) {
            Log::error('FMIS loan-payments API request threw exception', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function parseResponse(mixed $resp, int $page): ?array
    {
        if ($resp instanceof \Throwable) {
            Log::warning('FMIS loan-payments API page failed', ['page' => $page, 'error' => $resp->getMessage()]);
            return null;
        }

        if (!($resp instanceof Response) || !$resp->successful()) {
            return null;
        }

        $body = $resp->json();
        if (!is_array($body) || ($body['status'] ?? null) !== 'success') {
            return null;
        }

        return [
            'data' => is_array($body['data'] ?? null) ? $body['data'] : [],
            'meta' => is_array($body['meta'] ?? null) ? $body['meta'] : [],
        ];
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.fmis.url'), '/') . '/payments';
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . config('services.fmis.token'),
            'x-api-key' => config('services.fmis.key'),
        ];
    }
}
