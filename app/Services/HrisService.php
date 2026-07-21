<?php

namespace App\Services;

use App\Models\HrisEmployee;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HrisService
{
    private const CACHE_TTL_SECONDS = 60;

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
        return Cache::remember(
            "hris:employee:{$id}",
            self::CACHE_TTL_SECONDS,
            fn () => $this->callApi($id)
        );
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
                ->timeout(15)
                ->retry(2, 200, throw: false)
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
