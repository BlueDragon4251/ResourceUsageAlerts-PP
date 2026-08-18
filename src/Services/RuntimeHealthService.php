<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class RuntimeHealthService
{
    public function mark(string $operation, int $processed, int $errors, int $durationMilliseconds): void
    {
        Cache::forever($this->key($operation), [
            'completed_at' => now()->toIso8601String(),
            'processed' => $processed,
            'errors' => $errors,
            'duration_ms' => $durationMilliseconds,
        ]);
    }

    /** @return array{completed_at: ?Carbon, processed: int, errors: int, duration_ms: int} */
    public function status(string $operation): array
    {
        $value = Cache::get($this->key($operation));
        if (! is_array($value)) {
            return ['completed_at' => null, 'processed' => 0, 'errors' => 0, 'duration_ms' => 0];
        }

        return [
            'completed_at' => filled($value['completed_at'] ?? null) ? Carbon::parse($value['completed_at']) : null,
            'processed' => (int) ($value['processed'] ?? 0),
            'errors' => (int) ($value['errors'] ?? 0),
            'duration_ms' => (int) ($value['duration_ms'] ?? 0),
        ];
    }

    private function key(string $operation): string
    {
        return 'resourceusagealerts.runtime.'.$operation;
    }
}
