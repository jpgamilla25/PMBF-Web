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
     * @var array<string, array{data: ?array, available: bool}>
     */
    private array $memo = [];

    /**
     * Find an HRIS employee by their employee ID via the PhilRice api-center.
     */
    public function findByEmployeeId(string $id): ?HrisEmployee
    {
        $data = $this->lookup($id)['data'];

        return $data ? new HrisEmployee($data) : null;
    }

    /**
     * Validate that an employee exists in HRIS and is eligible for registration.
     *
     * @return array{valid: bool, available: bool, employee: ?HrisEmployee, message: string}
     */
    public function validateEmployee(string $id): array
    {
        $result = $this->lookup($id);

        // "The API did not answer" is not the same as "this employee does not
        // exist". The api-center intermittently returns an Apache 403 block
        // page, and reporting that as "not found" sends the user off hunting
        // for a data problem that isn't there.
        if (!$result['available']) {
            return [
                'valid' => false,
                'available' => false,
                'employee' => null,
                'message' => 'HRIS is temporarily unavailable. Please try again in a moment.',
            ];
        }

        if (!$result['data']) {
            return [
                'valid' => false,
                'available' => true,
                'employee' => null,
                'message' => 'Employee not found in HRIS records.',
            ];
        }

        $employee = new HrisEmployee($result['data']);

        if (strcasecmp($employee->status ?? '', 'Active') !== 0) {
            return [
                'valid' => false,
                'available' => true,
                'employee' => $employee,
                'message' => 'Employee is not currently active in HRIS.',
            ];
        }

        return [
            'valid' => true,
            'available' => true,
            'employee' => $employee,
            'message' => 'Employee validated successfully.',
        ];
    }

    /**
     * Resolve an employee, going through the memo then the cache then the API.
     *
     * `available` is false when the API never gave us a usable answer, so
     * callers can tell "no such employee" apart from "HRIS is down".
     *
     * @return array{data: ?array, available: bool}
     */
    private function lookup(string $id): array
    {
        if (\array_key_exists($id, $this->memo)) {
            return $this->memo[$id];
        }

        $key = "hris:employee:{$id}";
        $cached = Cache::get($key);

        if ($cached !== null) {
            return $this->memo[$id] = [
                'data' => $cached === self::MISS ? null : $cached,
                'available' => true,
            ];
        }

        $result = $this->callApi($id);

        // Only cache answers the API actually gave us. A 403 block page, a
        // 5xx or a timeout is transient — caching it as a miss would report
        // "employee not found" for the whole TTL after a one-second blip.
        if ($result['available']) {
            Cache::put($key, $result['data'] ?? self::MISS, self::CACHE_TTL_SECONDS);
        }

        return $this->memo[$id] = $result;
    }

    /**
     * @return array{data: ?array, available: bool}
     */
    private function callApi(string $id): array
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
            return ['data' => null, 'available' => false];
        }

        // A 404 is the API telling us this employee genuinely isn't there.
        if ($response->status() === 404) {
            return ['data' => null, 'available' => true];
        }

        if (!$response->successful()) {
            Log::warning('HRIS API request failed', [
                'employee_id' => $id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['data' => null, 'available' => false];
        }

        $body = $response->json();

        // 200 but not the JSON envelope we expect — an HTML error or block
        // page proxied through with a 200. Treat it as no answer at all.
        if (!\is_array($body) || !\array_key_exists('status', $body)) {
            Log::warning('HRIS API returned an unexpected body', [
                'employee_id' => $id,
                'body' => $response->body(),
            ]);
            return ['data' => null, 'available' => false];
        }

        if ($body['status'] !== 'success' || empty($body['data'])) {
            return ['data' => null, 'available' => true];
        }

        return ['data' => $body['data'], 'available' => true];
    }
}
