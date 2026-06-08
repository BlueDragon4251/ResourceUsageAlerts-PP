<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PelicanPlugins\ResourceUsageAlerts\Models\ResourceAlertEvent;
use PelicanPlugins\ResourceUsageAlerts\Services\AlertNotificationService;

class SendAlertNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var int[]
     */
    public array $backoff = [30, 120, 300];

    public function __construct(
        public readonly int $eventId,
        public readonly bool $resolved = false,
    ) {}

    public function handle(AlertNotificationService $service): void
    {
        $event = ResourceAlertEvent::query()->with(['rule', 'server', 'node', 'user'])->find($this->eventId);
        if (!$event) {
            return;
        }

        $this->resolved ? $service->sendResolved($event) : $service->sendTriggered($event);
    }
}
