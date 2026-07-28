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
     * Fetch the employment-stint history from
     * /api/v2/hris/employees/{id}/employment. Returns an array of stints
     * normalized to internal types ('share' for permanent, 'premium' for cos)
     * with Carbon-parsed dates. Returns null on transport failure or when the
     * envelope isn't the expected shape. Cached for CACHE_TTL_SECONDS.
     *
     * @return list<array{type:string,start_date:\Illuminate\Support\Carbon,end_date:?\Illuminate\Support\Carbon,is_current:bool}>|null
     */
    public function getEmployment(string $id): ?array
    {
        $memoKey = "stints:{$id}";
        if (\array_key_exists($memoKey, $this->memo)) {
            return $this->memo[$memoKey]['data'];
        }

        $key = "hris:employment:{$id}";
        $cached = Cache::get($key);
        if ($cached !== null) {
            $data = $cached === self::MISS ? null : $this->hydrateStints($cached);
            $this->memo[$memoKey] = ['data' => $data, 'available' => true];
            return $data;
        }

        $result = $this->callEmploymentApi($id);
        if ($result['available']) {
            // Cache primitives only — Carbon can't round-trip through the
            // file cache reliably (unserialize race on class autoload).
            Cache::put(
                $key,
                $result['data'] === null ? self::MISS : $this->dehydrateStints($result['data']),
                self::CACHE_TTL_SECONDS
            );
        }

        $this->memo[$memoKey] = $result;
        return $result['data'];
    }

    /** @param list<array<string,mixed>> $stints */
    private function dehydrateStints(array $stints): array
    {
        return array_map(fn ($s) => [
            'type'       => $s['type'],
            'start_date' => $s['start_date']?->toDateString(),
            'end_date'   => $s['end_date']?->toDateString(),
            'is_current' => $s['is_current'],
        ], $stints);
    }

    /** @param list<array<string,mixed>> $stints */
    private function hydrateStints(array $stints): array
    {
        return array_map(fn ($s) => [
            'type'       => $s['type'],
            'start_date' => !empty($s['start_date']) ? \Illuminate\Support\Carbon::parse($s['start_date']) : null,
            'end_date'   => !empty($s['end_date'])   ? \Illuminate\Support\Carbon::parse($s['end_date'])   : null,
            'is_current' => (bool) ($s['is_current'] ?? false),
        ], $stints);
    }

    /**
     * Which internal type ('share' | 'premium') covers a given year/month?
     * Anchor at the 15th of the month so a stint that ends mid-month is
     * classified by whichever half-month owns the majority. Accepts either an
     * array of arrays (from getEmployment) or a Collection<EmploymentStint>.
     */
    public static function stintTypeAt(iterable $stints, int $year, int $month): ?string
    {
        $anchor = \Illuminate\Support\Carbon::create($year, $month, 15);
        foreach ($stints as $s) {
            $start = \is_array($s) ? $s['start_date'] : $s->start_date;
            $end   = \is_array($s) ? ($s['end_date'] ?? null) : $s->end_date;
            $type  = \is_array($s) ? ($s['type'] ?? null) : $s->type;
            if (!$start || !$type) continue;

            if ($anchor->gte($start) && (!$end || $anchor->lte($end->copy()->endOfDay()))) {
                return $type;
            }
        }
        return null;
    }

    /**
     * HTTP call for /employees/{id}/employment. Follows the same envelope
     * conventions as callApi(): 404 = "no such employee" (available=true),
     * anything else non-2xx = "HRIS unavailable" (available=false).
     *
     * @return array{data: ?list<array<string,mixed>>, available: bool}
     */
    private function callEmploymentApi(string $id): array
    {
        $baseUrl = rtrim((string) config('services.hris.url'), '/');
        $url = "{$baseUrl}/employees/" . rawurlencode($id) . '/employment';

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . config('services.hris.token'),
                'x-api-key' => config('services.hris.key'),
            ])
                ->withOptions(['verify' => config('services.hris.verify')])
                ->timeout(5)
                ->connectTimeout(3)
                ->retry(1, 200, throw: false)
                ->get($url);
        } catch (\Throwable $e) {
            Log::error('HRIS employment API request threw exception', [
                'employee_id' => $id,
                'error' => $e->getMessage(),
            ]);
            return ['data' => null, 'available' => false];
        }

        if ($response->status() === 404) {
            return ['data' => null, 'available' => true];
        }

        if (!$response->successful()) {
            Log::warning('HRIS employment API request failed', [
                'employee_id' => $id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return ['data' => null, 'available' => false];
        }

        $body = $response->json();

        if (!\is_array($body) || !\array_key_exists('status', $body)) {
            Log::warning('HRIS employment API returned an unexpected body', [
                'employee_id' => $id,
                'body' => $response->body(),
            ]);
            return ['data' => null, 'available' => false];
        }

        if ($body['status'] !== 'success') {
            return ['data' => null, 'available' => true];
        }

        $stints = collect($body['data']['stints'] ?? [])
            ->map(fn ($s) => [
                'type' => match (strtolower((string) ($s['type'] ?? ''))) {
                    'permanent' => 'share',
                    'cos'       => 'premium',
                    default     => null,
                },
                'start_date' => !empty($s['start']) ? \Illuminate\Support\Carbon::parse($s['start']) : null,
                'end_date'   => !empty($s['end'])   ? \Illuminate\Support\Carbon::parse($s['end'])   : null,
                'is_current' => (bool) ($s['is_current'] ?? false),
            ])
            ->filter(fn ($s) => $s['type'] !== null && $s['start_date'] !== null)
            ->values()
            ->all();

        return ['data' => $stints, 'available' => true];
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
