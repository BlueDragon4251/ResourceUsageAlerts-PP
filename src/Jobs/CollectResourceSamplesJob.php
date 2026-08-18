<?php

declare(strict_types=1);

namespace PelicanPlugins\ResourceUsageAlerts\Jobs;

use App\Models\Node;
use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use PelicanPlugins\ResourceUsageAlerts\Services\ResourceSampleService;
use PelicanPlugins\ResourceUsageAlerts\Services\RuntimeHealthService;
use PelicanPlugins\ResourceUsageAlerts\Services\TargetBackoffService;
use Throwable;

class CollectResourceSamplesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function handle(ResourceSampleService $service, RuntimeHealthService $health, TargetBackoffService $backoff): void
    {
        $startedAt = microtime(true);
        $chunkSize = (int) config('resourceusagealerts.chunk_size', 100);
        $processed = 0;
        $errors = 0;

        Server::query()->orderBy('id')->chunkById($chunkSize, function ($servers) use ($service, $backoff, &$processed, &$errors): void {
            foreach ($servers as $server) {
                if (! $backoff->canAttempt('server', $server->id)) {
                    continue;
                }
                try {
                    $service->collectServerSamples($server);
                    $backoff->succeeded('server', $server->id);
                    $processed++;
                } catch (Throwable $exception) {
                    $errors++;
                    $seconds = $backoff->failed('server', $server->id);
                    Log::warning('Resource Usage Alerts server collection failed.', [
                        'server_id' => $server->id,
                        'exception' => $exception::class,
                        'backoff_seconds' => $seconds,
                    ]);
                }
            }
        });

        Node::query()->orderBy('id')->chunkById($chunkSize, function ($nodes) use ($service, $backoff, &$processed, &$errors): void {
            foreach ($nodes as $node) {
                if (! $backoff->canAttempt('node', $node->id)) {
                    continue;
                }
                try {
                    $service->collectNodeSamples($node);
                    $backoff->succeeded('node', $node->id);
                    $processed++;
                } catch (Throwable $exception) {
                    $errors++;
                    $seconds = $backoff->failed('node', $node->id);
                    Log::warning('Resource Usage Alerts node collection failed.', [
                        'node_id' => $node->id,
                        'exception' => $exception::class,
                        'backoff_seconds' => $seconds,
                    ]);
                }
            }
        });

        try {
            $service->collectPanelQueueSamples();
            $processed++;
        } catch (Throwable $exception) {
            $errors++;
            Log::warning('Resource Usage Alerts panel queue collection failed.', ['exception' => $exception::class]);
        }

        $health->mark('collection', $processed, $errors, (int) ((microtime(true) - $startedAt) * 1000));

        EvaluateAlertRulesJob::dispatch();
    }

    public function uniqueId(): string
    {
        return 'resource-usage-alerts:collect';
    }
}
