<?php

namespace App\Services;

use App\Models\HrisEmployee;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HrisService
{
    private const CACHE_TTL_SECONDS = 300;

    /**
     * Marker stored in the cache for "this employee is not in HRIS".
     *
     * Cache::remember cannot cache a null return — get() returns null, which
     * reads as a miss, so every lookup of a missing employee re-ran the API
     * call and paid the full timeout again.
     */
    private const MISS = '__hris_miss__';

    /**
     * Per-request memo. A single response can serialize the same employee
     * many times, and the cache driver is a database round trip.
     *
     * @var array<string, ?array>
     */
    private array $memo = [];

    /**
     * Find an HRIS employee by their employee ID via the PhilRice api-center.
     */
    public function findByEmployeeId(string $id): ?HrisEmployee
    {
        $data = $this->fetchEmployeeData($id);

        return $data ? new HrisEmployee($data) : null;
    }

    /**
     * Validate that an employee exists in HRIS and is eligible for registration.
     *
     * @return array{valid: bool, employee: ?HrisEmployee, message: string}
     */
    public function validateEmployee(string $id): array
    {
        $employee = $this->findByEmployeeId($id);

        if (!$employee) {
            return [
                'valid' => false,
                'employee' => null,
                'message' => 'Employee not found in HRIS records.',
            ];
        }

        if (strcasecmp($employee->status ?? '', 'Active') !== 0) {
            return [
                'valid' => false,
                'employee' => $employee,
                'message' => 'Employee is not currently active in HRIS.',
            ];
        }

        return [
            'valid' => true,
            'employee' => $employee,
            'message' => 'Employee validated successfully.',
        ];
    }

    private function fetchEmployeeData(string $id): ?array
    {
        if (array_key_exists($id, $this->memo)) {
            return $this->memo[$id];
        }

        $cached = Cache::remember(
            "hris:employee:{$id}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->callApi($id) ?? self::MISS
        );

        $data = $cached === self::MISS ? null : $cached;

        return $this->memo[$id] = $data;
    }

    private function callApi(string $id): ?array
    {
        $baseUrl = rtrim((string) config('services.hris.url'), '/');
        $url = "{$baseUrl}/employees/" . rawurlencode($id);

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . config('services.hris.token'),
                'x-api-key' => config('services.hris.key'),
            ])
                ->withOptions(['verify' => config('services.hris.verify')])
                // Was 15s with 2 retries — a slow HRIS could stall a single
                // request for 45 seconds. Every caller has a local fallback,
                // so failing fast is better than blocking the page.
                ->timeout(5)
                ->connectTimeout(3)
                ->retry(1, 200, throw: false)
                ->get($url);
        } catch (\Throwable $e) {
            Log::error('HRIS API request threw exception', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if ($response->status() === 404) {
            return null;
        }

        if (!$response->successful()) {
            Log::warning('HRIS API request failed', [
                'employee_id' => $id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return null;
        }

        $body = $response->json();
        if (!is_array($body) || ($body['status'] ?? null) !== 'success' || empty($body['data'])) {
            return null;
        }

        return $body['data'];
    }
}
