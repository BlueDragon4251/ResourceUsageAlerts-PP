<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Services;

use Illuminate\Support\Facades\Cache;

class TargetBackoffService
{
    public function canAttempt(string $type, int $id): bool
    {
        return (int) Cache::get($this->key($type, $id, 'until'), 0) <= now()->timestamp;
    }

    public function succeeded(string $type, int $id): void
    {
        Cache::forget($this->key($type, $id, 'failures'));
        Cache::forget($this->key($type, $id, 'until'));
    }

    public function failed(string $type, int $id): int
    {
        $failures = (int) Cache::increment($this->key($type, $id, 'failures'));
        Cache::put($this->key($type, $id, 'failures'), $failures, now()->addDay());
        $seconds = min(3600, 30 * (2 ** min(7, max(0, $failures - 1))));
        Cache::put($this->key($type, $id, 'until'), now()->timestamp + $seconds, now()->addSeconds($seconds));

        return $seconds;
    }

    private function key(string $type, int $id, string $suffix): string
    {
        return "resourceusagealerts.backoff.$type.$id.$suffix";
    }
}
