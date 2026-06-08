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
use Throwable;

class CollectResourceSamplesJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $uniqueFor = 300;

    public function handle(ResourceSampleService $service): void
    {
        $chunkSize = (int) config('resourceusagealerts.chunk_size', 100);

        Server::query()->orderBy('id')->chunkById($chunkSize, function ($servers) use ($service): void {
            foreach ($servers as $server) {
                try {
                    $service->collectServerSamples($server);
                } catch (Throwable $exception) {
                    Log::warning('Resource Usage Alerts server collection failed.', [
                        'server_id' => $server->id,
                        'exception' => $exception::class,
                    ]);
                }
            }
        });

        Node::query()->orderBy('id')->chunkById($chunkSize, function ($nodes) use ($service): void {
            foreach ($nodes as $node) {
                try {
                    $service->collectNodeSamples($node);
                } catch (Throwable $exception) {
                    Log::warning('Resource Usage Alerts node collection failed.', [
                        'node_id' => $node->id,
                        'exception' => $exception::class,
                    ]);
                }
            }
        });

        EvaluateAlertRulesJob::dispatch();
    }

    public function uniqueId(): string
    {
        return 'resource-usage-alerts:collect';
    }
}
